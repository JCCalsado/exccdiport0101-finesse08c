<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\OtherChargePayment;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\Workflow;
use App\Services\OtherChargeService;
use App\Services\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymongoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;
    public int $timeout = 60;

    public function __construct(
        private readonly array  $payload,
        private readonly string $eventType,
    ) {}

    public function handle(): void
    {
        match ($this->eventType) {
            'checkout_session.payment.paid',
            'payment.paid'   => $this->handlePaymentPaid(),
            'payment.failed' => $this->handlePaymentFailed(),
            default          => Log::info('ProcessPaymongoWebhook: ignoring event type', [
                'type' => $this->eventType,
            ]),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handlePaymentPaid(): void
    {
        $checkoutSessionNode = data_get($this->payload, 'data.attributes.data');

        if (! $checkoutSessionNode) {
            Log::error('ProcessPaymongoWebhook: no checkout_session data found in payload', [
                'event_id'     => data_get($this->payload, 'data.id'),
                'event_type'   => $this->eventType,
                'payload_keys' => array_keys($this->payload['data']['attributes'] ?? []),
            ]);
            return;
        }

        $sessionId    = data_get($checkoutSessionNode, 'id');
        $sessionAttrs = data_get($checkoutSessionNode, 'attributes', []);

        // ── EXTRACT payment_intent_id ─────────────────────────────────────────
        $paymentIntentId = data_get($sessionAttrs, 'payment_intent.id')
            ?? data_get($sessionAttrs, 'payments.0.attributes.payment_intent_id');

        if (! $paymentIntentId) {
            Log::error('ProcessPaymongoWebhook: missing payment_intent_id', [
                'event_id'   => data_get($this->payload, 'data.id'),
                'session_id' => $sessionId,
            ]);
            return;
        }

        // ── EXTRACT payment status ────────────────────────────────────────────
        $intentStatus  = data_get($sessionAttrs, 'payment_intent.attributes.status');
        $paymentStatus = data_get($sessionAttrs, 'payments.0.attributes.status');
        $sessionStatus = data_get($sessionAttrs, 'status');

        $isSuccessful = $paymentStatus === 'paid'
            || $intentStatus === 'succeeded'
            || $sessionStatus === 'paid';

        if (! $isSuccessful) {
            Log::warning('ProcessPaymongoWebhook: payment not in successful state, skipping', [
                'session_id'     => $sessionId,
                'intent_status'  => $intentStatus,
                'payment_status' => $paymentStatus,
                'session_status' => $sessionStatus,
            ]);
            return;
        }

        // ── BRANCH: Check if this is an Other Charge payment ─────────────────
        // Metadata set by OtherChargeService::initiateOnlinePayment()
        $metadata = data_get($sessionAttrs, 'metadata', []);
        if (data_get($metadata, 'type') === 'other_charge') {
            $this->handleOtherChargePaymentPaid($sessionId, $paymentIntentId);
            return;
        }

        // ── EXISTING ASSESSMENT PAYMENT FLOW (unchanged) ──────────────────────

        // ── FIND LOCAL PAYMENT ROW ────────────────────────────────────────────
        $payment = Payment::where('paymongo_source_id', $sessionId)
            ->orWhere(function ($q) use ($paymentIntentId) {
                $q->whereJsonContains('meta->paymongo_intent_id', $paymentIntentId);
            })
            ->first();

        // ── IDEMPOTENCY GUARD ─────────────────────────────────────────────────
        // IMPORTANT: Even if the Transaction exists (created by the redirect handler),
        // we still need to ensure the Payment row is marked "completed".
        // A stuck "pending" Payment row blocks future payments for the same term.
        $reference   = "PAY-{$paymentIntentId}";
        $existingTxn = Transaction::where('reference', $reference)->first();

        if ($existingTxn) {
            Log::info('ProcessPaymongoWebhook: transaction already exists (idempotency check)', [
                'payment_intent_id' => $paymentIntentId,
                'reference'         => $reference,
            ]);

            // FIX: Update the Payment row regardless — it may still be "pending"
            // if the redirect created the Transaction but the webhook arrives after.
            if ($payment && $payment->status !== 'completed') {
                $payment->update([
                    'status'             => 'completed',
                    'paymongo_intent_id' => $paymentIntentId,
                    'description'        => 'PayMongo payment — confirmed via webhook (idempotency update)',
                ]);
                Log::info('ProcessPaymongoWebhook: fixed stale pending Payment row', [
                    'payment_id'        => $payment->id,
                    'payment_intent_id' => $paymentIntentId,
                ]);
            }

            return;
        }

        if (! $payment) {
            Log::warning('ProcessPaymongoWebhook: no Payment row found for session', [
                'payment_intent_id' => $paymentIntentId,
                'session_id'        => $sessionId,
                'event_id'          => data_get($this->payload, 'data.id'),
            ]);
            return;
        }

        $user = \App\Models\User::find($payment->user_id);

        if (! $user) {
            Log::error('ProcessPaymongoWebhook: user not found', [
                'user_id'           => $payment->user_id,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $termId   = $payment->meta['selected_term_id'] ?? null;
        $termInfo = $termId ? StudentPaymentTerm::find($termId) : null;
        $grossAmount   = (float) $payment->amount;

        // ✅ FIX (Bug #2): Extract actual amount received after fees
        // PayMongo API provides both gross amount and fees, or we can calculate net_amount
        // Amount in checkout session is in centavos, convert to pesos
        $sessionAmount = data_get($sessionAttrs, 'amount') ? (float) data_get($sessionAttrs, 'amount') / 100 : $grossAmount;

        // Extract fees charged by PayMongo from payment intent attributes
        $transactionFee = 0;
        $netAmount = $grossAmount;

        // PayMongo fees structure in payment intent
        $paymentIntent = data_get($sessionAttrs, 'payment_intent.attributes', []);
        if (isset($paymentIntent['fees']) && is_array($paymentIntent['fees'])) {
            $transactionFee = (float) collect($paymentIntent['fees'])->sum('amount') / 100;
            $netAmount = $grossAmount - $transactionFee;
        } elseif (isset($paymentIntent['fee_amount'])) {
            $transactionFee = (float) $paymentIntent['fee_amount'] / 100;
            $netAmount = $grossAmount - $transactionFee;
        }

        // Fallback: if we have a net_amount field directly
        if ($netAmount === $grossAmount && isset($paymentIntent['net_amount'])) {
            $netAmount = (float) $paymentIntent['net_amount'] / 100;
            $transactionFee = $grossAmount - $netAmount;
        }

        Log::info('ProcessPaymongoWebhook: amount breakdown', [
            'payment_intent_id' => $paymentIntentId,
            'gross_amount'      => $grossAmount,
            'transaction_fee'   => $transactionFee,
            'net_amount'        => $netAmount,
        ]);

        DB::transaction(function () use (
            $payment, $user, $paymentIntentId, $sessionId, $termId, $termInfo, $grossAmount, $transactionFee, $netAmount
        ) {
            $payment->update([
                'status'             => 'completed',
                'paymongo_intent_id' => $paymentIntentId,
                'description'        => 'PayMongo payment — awaiting accounting review (via webhook)',
            ]);

            // ✅ FIX (Bug #2): Store net_amount in meta so finalizeApprovedPayment uses correct amount
            $transactionMeta = [
                'description'         => $payment->meta['term_name'] ?? 'PayMongo Payment',
                'paymongo_session_id' => $sessionId,
                'paymongo_intent_id'  => $paymentIntentId,
                'term_name'           => $payment->meta['term_name'] ?? 'Payment',
                'selected_term_id'    => $termId,
                'payment_method'      => 'paymongo',
                'assessment_id'       => $termInfo?->student_assessment_id ?? null,
                'requires_approval'   => true,
                'source'              => 'webhook',
                'gross_amount'        => $grossAmount,
                'transaction_fee'     => $transactionFee,
                'net_amount'          => $netAmount,
            ];

            $transaction = Transaction::create([
                'user_id'         => $user->id,
                'kind'            => 'payment',
                'status'          => PaymentStatus::AWAITING_APPROVAL->value,
                'payment_channel' => 'paymongo',
                'amount'          => $grossAmount,
                'reference'       => "PAY-{$paymentIntentId}",
                'type'            => 'Payment',
                'paid_at'         => now(),
                'year'            => now()->year,
                'semester'        => $termInfo?->assessment?->semester ?? null,
                'meta'            => $transactionMeta,
            ]);

            $this->startPaymentApprovalWorkflow($transaction->id, $user->id);

            Log::info('ProcessPaymongoWebhook: payment submitted for accounting review', [
                'user_id'         => $user->id,
                'transaction_id'  => $transaction->id,
                'gross_amount'    => $grossAmount,
                'transaction_fee' => $transactionFee,
                'net_amount'      => $netAmount,
                'term_id'         => $termId,
                'payment_intent'  => $paymentIntentId,
            ]);
        });
    }

    // ─── Other Charge Branch ──────────────────────────────────────────────────

    /**
     * Handle a confirmed payment for an Other Charge.
     * Delegates entirely to OtherChargeService — no assessment logic involved.
     */
    private function handleOtherChargePaymentPaid(string $sessionId, string $paymentIntentId): void
    {
        Log::info('ProcessPaymongoWebhook: routing to OtherChargeService', [
            'session_id'        => $sessionId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        app(OtherChargeService::class)->handleWebhookPaid($sessionId, $paymentIntentId);
    }

    private function handlePaymentFailed(): void
    {
        $checkoutSessionNode = data_get($this->payload, 'data.attributes.data');

        if (! $checkoutSessionNode) {
            Log::warning('ProcessPaymongoWebhook payment.failed: no checkout_session data');
            return;
        }

        $sessionId    = data_get($checkoutSessionNode, 'id');
        $sessionAttrs = data_get($checkoutSessionNode, 'attributes', []);

        $paymentIntentId = data_get($sessionAttrs, 'payment_intent.id')
            ?? data_get($sessionAttrs, 'payments.0.attributes.payment_intent_id');

        if (! $paymentIntentId) {
            Log::warning('ProcessPaymongoWebhook payment.failed: missing payment_intent_id');
            return;
        }

        // ── BRANCH: Other Charge failure ─────────────────────────────────────
        $metadata = data_get($sessionAttrs, 'metadata', []);
        if (data_get($metadata, 'type') === 'other_charge') {
            app(OtherChargeService::class)->handleWebhookFailed($sessionId, $paymentIntentId);
            return;
        }

        // ── EXISTING: Assessment payment failure (unchanged) ──────────────────
        $cancelled = Payment::where('paymongo_source_id', $sessionId)
            ->orWhere('paymongo_intent_id', $paymentIntentId)
            ->update(['status' => 'cancelled']);

        Transaction::where('reference', "PAY-{$paymentIntentId}")
            ->where('status', PaymentStatus::AWAITING_APPROVAL->value)
            ->update(['status' => PaymentStatus::FAILED->value]);

        Log::info('ProcessPaymongoWebhook: payment marked as failed', [
            'payment_intent_id' => $paymentIntentId,
            'rows_cancelled'    => $cancelled,
        ]);
    }

    private function startPaymentApprovalWorkflow(int $transactionId, int $userId): void
    {
        $workflow = Workflow::active()
            ->where('type', 'payment_approval')
            ->first();

        if (! $workflow) {
            Log::warning('ProcessPaymongoWebhook: no active payment_approval workflow found', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        app(WorkflowService::class)->startWorkflow(
            $workflow,
            Transaction::find($transactionId),
            $userId,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessPaymongoWebhook job FAILED after all retries', [
            'event_type' => $this->eventType,
            'error'      => $e->getMessage(),
            'trace'      => $e->getTraceAsString(),
        ]);
    }
}
