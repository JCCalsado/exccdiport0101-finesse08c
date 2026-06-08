<?php

namespace App\Services;

use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use App\Models\StudentAssessment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtherChargeService
{
    // ─── Student-facing ───────────────────────────────────────────────────────

    /**
     * Get all published, active Other Charges that apply to a given student,
     * annotated with payment status for that student.
     *
     * Matching is dynamic — late enrollees automatically qualify if their
     * active assessment matches the charge filters.
     *
     * @return Collection<array>
     */
    public function getChargesForStudent(User $student): Collection
    {
        $assessment = StudentAssessment::where('user_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $assessment) {
            return collect();
        }

        // Build base query matching this student's active assessment dimensions
        $charges = OtherCharge::published()
            ->active()
            ->where('school_year', $assessment->school_year)
            ->where(function ($q) use ($assessment) {
                $q->whereNull('semester')
                  ->orWhere('semester', $assessment->semester);
            })
            ->where(function ($q) use ($assessment) {
                $q->whereNull('year_level')
                  ->orWhere('year_level', $assessment->year_level);
            })
            ->where(function ($q) use ($assessment) {
                $q->whereNull('course')
                  ->orWhere('course', $assessment->course);
            })
            ->with(['payments' => fn ($q) => $q->where('user_id', $student->id)])
            ->orderBy('created_at', 'desc')
            ->get();

        return $charges->map(function (OtherCharge $charge) use ($student) {
            $payment = $charge->payments->first();

            $status     = 'unpaid';
            $amountPaid = 0.0;
            $paidAt     = null;
            $orNumber   = null;

            if ($payment) {
                $status     = $payment->status;
                $amountPaid = (float) $payment->amount_paid;
                $paidAt     = $payment->paid_at?->format('Y-m-d H:i');
                $orNumber   = $payment->or_number;
            }

            return [
                'id'                        => $charge->id,
                'title'                     => $charge->title,
                'description'               => $charge->description,
                'amount'                    => (float) $charge->amount,
                'school_year'               => $charge->school_year,
                'semester'                  => $charge->semester,
                'year_level'                => $charge->year_level,
                'course'                    => $charge->course,
                'published_at'              => $charge->published_at?->format('Y-m-d'),
                'updated_after_publish_at'  => $charge->updated_after_publish_at?->format('Y-m-d H:i'),
                'status'                    => $status,     // 'unpaid' | 'pending' | 'awaiting_approval' | 'paid'
                'amount_paid'               => $amountPaid,
                'paid_at'                   => $paidAt,
                'or_number'                 => $orNumber,
                'payment_id'                => $payment?->id,
            ];
        });
    }

    // ─── Accounting-facing ────────────────────────────────────────────────────

    /**
     * Get all students who match a charge's filters, with their payment status.
     * Used by the accounting Show page.
     *
     * @return Collection<array>
     */
    public function getStudentsForCharge(OtherCharge $charge): Collection
    {
        $students = $charge->buildMatchingStudentsQuery()
            ->with([
                'studentAssessments' => fn ($q) => $q->where('status', 'active')->latest()->limit(1),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Load all payments for this charge in one query
        $payments = OtherChargePayment::where('other_charge_id', $charge->id)
            ->get()
            ->keyBy('user_id');

        return $students->map(function (User $student) use ($charge, $payments) {
            $payment    = $payments->get($student->id);
            $assessment = $student->studentAssessments->first();

            $status     = 'unpaid';
            $amountPaid = 0.0;
            $paidAt     = null;
            $orNumber   = null;
            $collectedByName = null;

            if ($payment) {
                $status          = $payment->status;
                $amountPaid      = (float) $payment->amount_paid;
                $paidAt          = $payment->paid_at?->format('Y-m-d H:i');
                $orNumber        = $payment->or_number;
                $collectedByName = $payment->collectedBy?->name;
            }

            return [
                'user_id'          => $student->id,
                'name'             => $student->name,
                'account_id'       => $student->account_id,
                'course'           => $assessment?->course ?? $student->course,
                'year_level'       => $assessment?->year_level ?? $student->year_level,
                'semester'         => $assessment?->semester,
                'status'           => $status,
                'amount_paid'      => $amountPaid,
                'balance'          => $status === 'paid' ? 0.0 : (float) $charge->amount,
                'paid_at'          => $paidAt,
                'or_number'        => $orNumber,
                'collected_by'     => $collectedByName,
                'payment_id'       => $payment?->id,
                'payment_method'   => $payment?->payment_method,
            ];
        });
    }

    /**
     * Record an OTC (over-the-counter) payment by accounting staff.
     *
     * Guards:
     *   - No existing paid record for this student+charge
     *   - Amount must equal charge amount (full payment only)
     *
     * @throws \RuntimeException
     */
    public function recordOtcPayment(
        OtherCharge $charge,
        User        $student,
        string      $orNumber,
        ?string     $notes,
        User        $collectedBy,
    ): OtherChargePayment {
        // Guard: already paid
        $existing = OtherChargePayment::where('other_charge_id', $charge->id)
            ->where('user_id', $student->id)
            ->where('status', 'paid')
            ->first();

        if ($existing) {
            throw new \RuntimeException(
                "Student {$student->name} has already paid this charge (OR# {$existing->or_number})."
            );
        }

        // Guard: student must match charge filters
        if (! $charge->matchesStudent($student)) {
            throw new \RuntimeException(
                "Student {$student->name} does not match the target group for this charge."
            );
        }

        return DB::transaction(function () use ($charge, $student, $orNumber, $notes, $collectedBy) {
            // Cancel any pending online attempts for this student+charge
            OtherChargePayment::where('other_charge_id', $charge->id)
                ->where('user_id', $student->id)
                ->whereIn('status', ['pending', 'awaiting_proof', 'awaiting_approval'])
                ->update(['status' => 'cancelled']);

            $payment = OtherChargePayment::create([
                'other_charge_id' => $charge->id,
                'user_id'         => $student->id,
                'amount_paid'     => $charge->amount,
                'payment_method'  => 'otc',
                'or_number'       => $orNumber,
                'status'          => 'paid',
                'collected_by'    => $collectedBy->id,
                'paid_at'         => now(),
                'notes'           => $notes,
            ]);

            Log::info('OtherChargeService: OTC payment recorded', [
                'charge_id'    => $charge->id,
                'charge_title' => $charge->title,
                'student_id'   => $student->id,
                'or_number'    => $orNumber,
                'collected_by' => $collectedBy->id,
                'amount'       => $charge->amount,
            ]);

            return $payment;
        });
    }

    // ─── PayMongo Online Payment ──────────────────────────────────────────────

    /**
     * Initiate a PayMongo checkout session for an Other Charge online payment.
     *
     * Creates the OtherChargePayment row with status=pending BEFORE calling
     * PayMongo — ensures the webhook always has a row to find.
     *
     * @return array ['checkout_url' => string, 'session_id' => string]
     * @throws \RuntimeException
     */
    public function initiateOnlinePayment(OtherCharge $charge, User $student): array
    {
        // Guard: already paid
        if (! $charge->isOwedByStudent($student)) {
            throw new \RuntimeException('This charge has already been paid.');
        }

        // Guard: no duplicate pending
        $existingPending = OtherChargePayment::where('other_charge_id', $charge->id)
            ->where('user_id', $student->id)
            ->whereIn('status', ['pending', 'awaiting_approval'])
            ->first();

        if ($existingPending) {
            throw new \RuntimeException(
                'A payment for this charge is already in progress. Please wait or contact accounting.'
            );
        }

        $amountCents = (int) round((float) $charge->amount * 100);

        // Create pending row first — webhook safety net
        $payment = OtherChargePayment::create([
            'other_charge_id' => $charge->id,
            'user_id'         => $student->id,
            'amount_paid'     => $charge->amount,
            'payment_method'  => 'online',
            'status'          => 'pending',
        ]);

        try {
            $secretKey = config('services.paymongo.secret_key');

            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'amount'          => $amountCents,
                            'currency'        => 'PHP',
                            'description'     => $charge->title,
                            'payment_method_types' => ['gcash', 'card', 'paymaya'],
                            'success_url'     => route('student.other-charges.index') . '?payment=success',
                            'cancel_url'      => route('student.other-charges.index') . '?payment=cancelled',
                            'metadata'        => [
                                'type'                  => 'other_charge',
                                'other_charge_id'       => $charge->id,
                                'other_charge_payment_id' => $payment->id,
                                'student_id'            => $student->id,
                            ],
                            'line_items' => [[
                                'currency' => 'PHP',
                                'amount'   => $amountCents,
                                'name'     => $charge->title,
                                'quantity' => 1,
                            ]],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('PayMongo checkout session creation failed: ' . $response->body());
            }

            $session    = $response->json('data');
            $sessionId  = data_get($session, 'id');
            $checkoutUrl = data_get($session, 'attributes.checkout_url');

            // Store session ID on the payment row
            $payment->update([
                'paymongo_session_id' => $sessionId,
                'reference'           => "OC-{$sessionId}",
            ]);

            Log::info('OtherChargeService: checkout session created', [
                'charge_id'  => $charge->id,
                'student_id' => $student->id,
                'session_id' => $sessionId,
                'amount'     => $charge->amount,
            ]);

            return [
                'checkout_url' => $checkoutUrl,
                'session_id'   => $sessionId,
            ];

        } catch (\Throwable $e) {
            // Payment row was pre-created — mark it failed so it doesn't block future attempts
            $payment->update(['status' => 'cancelled']);

            Log::error('OtherChargeService: checkout session failed', [
                'charge_id'  => $charge->id,
                'student_id' => $student->id,
                'error'      => $e->getMessage(),
            ]);

            throw new \RuntimeException('Could not initiate payment: ' . $e->getMessage());
        }
    }

    /**
     * Handle a confirmed PayMongo payment for an Other Charge.
     * Called by ProcessPaymongoWebhook job when it detects an other_charge payment.
     */
    public function handleWebhookPaid(string $sessionId, string $paymentIntentId): void
    {
        $payment = OtherChargePayment::where('paymongo_session_id', $sessionId)
            ->orWhere('payment_intent_id', $paymentIntentId)
            ->first();

        if (! $payment) {
            Log::warning('OtherChargeService::handleWebhookPaid: no payment row found', [
                'session_id'        => $sessionId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        // Idempotency: already paid
        if ($payment->status === 'paid') {
            Log::info('OtherChargeService::handleWebhookPaid: already paid, skipping', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $payment->update([
            'status'            => 'paid',
            'payment_intent_id' => $paymentIntentId,
            'reference'         => "OC-{$paymentIntentId}",
            'paid_at'           => now(),
        ]);

        Log::info('OtherChargeService::handleWebhookPaid: payment confirmed', [
            'payment_id'        => $payment->id,
            'other_charge_id'   => $payment->other_charge_id,
            'student_id'        => $payment->user_id,
            'payment_intent_id' => $paymentIntentId,
            'amount'            => $payment->amount_paid,
        ]);
    }

    /**
     * Handle a failed PayMongo payment for an Other Charge.
     */
    public function handleWebhookFailed(string $sessionId, string $paymentIntentId): void
    {
        OtherChargePayment::where('paymongo_session_id', $sessionId)
            ->orWhere('payment_intent_id', $paymentIntentId)
            ->whereIn('status', ['pending', 'awaiting_approval'])
            ->update(['status' => 'failed']);

        Log::info('OtherChargeService::handleWebhookFailed: payment failed', [
            'session_id'        => $sessionId,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }
}
