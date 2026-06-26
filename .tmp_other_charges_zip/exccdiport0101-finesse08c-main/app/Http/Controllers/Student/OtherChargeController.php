<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use App\Services\OtherChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OtherChargeController extends Controller
{
    public function __construct(private readonly OtherChargeService $service) {}

    /**
     * Student's Other Charges portal page.
     *
     * On ?payment=success, we optimistically move the student's pending row to
     * 'awaiting_confirmation' so the UI can show a meaningful intermediate state
     * instead of leaving them stuck on 'In Progress' until the webhook fires.
     *
     * The webhook (handleWebhookPaid) remains the AUTHORITATIVE confirmation.
     * awaiting_confirmation is a UX hint only — it never replaces the webhook.
     */
    public function index(Request $request): Response
    {
        $student  = $request->user();
        $feedback = $request->query('payment'); // 'success' | 'cancelled' | null

        // ── Optimistic status advancement on PayMongo success redirect ─────────
        if ($feedback === 'success') {
            OtherChargePayment::where('user_id', $student->id)
                ->where('status', 'pending')
                ->whereNotNull('paymongo_session_id')
                ->update(['status' => 'awaiting_confirmation']);
        }

        $charges = $this->service->getChargesForStudent($student);

        return Inertia::render('Student/OtherCharges/Index', [
            'charges'         => $charges->values(),
            'paymentFeedback' => $feedback,
        ]);
    }

    /**
     * Initiate a PayMongo online payment for an Other Charge.
     * Returns JSON { checkout_url, session_id } — Vue redirects the browser.
     */
    public function initiatePayment(Request $request, OtherCharge $otherCharge)
    {
        $student = $request->user();

        if (! $otherCharge->is_published || ! $otherCharge->is_active) {
            return response()->json(['error' => 'This charge is not available for payment.'], 422);
        }

        if (! $otherCharge->matchesStudent($student)) {
            return response()->json(['error' => 'You are not eligible for this charge.'], 403);
        }

        try {
            $result = $this->service->initiateOnlinePayment($otherCharge, $student);

            return response()->json([
                'checkout_url' => $result['checkout_url'],
                'session_id'   => $result['session_id'],
            ]);

        } catch (\RuntimeException $e) {
            Log::warning('Student other charge payment initiation failed', [
                'charge_id'  => $otherCharge->id,
                'student_id' => $student->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  OPTION D — BANK TRANSFER + PROOF UPLOAD
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Submit bank transfer details for an Other Charge.
     * Creates/reuses an other_charge_payments row in 'awaiting_proof' status.
     * Returns JSON { payment_id } — Vue redirects to the proof upload page.
     */
    public function initiateBankTransfer(Request $request, OtherCharge $otherCharge)
    {
        $student = $request->user();

        if (! $otherCharge->is_published || ! $otherCharge->is_active) {
            return response()->json(['error' => 'This charge is not available for payment.'], 422);
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payment = $this->service->initiateBankTransfer(
                $otherCharge,
                $student,
                $validated['reference_number'],
            );

            return response()->json(['payment_id' => $payment->id]);

        } catch (\RuntimeException $e) {
            Log::warning('Student other charge bank transfer initiation failed', [
                'charge_id'  => $otherCharge->id,
                'student_id' => $student->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function showProofForm(Request $request, OtherChargePayment $otherChargePayment): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if ($otherChargePayment->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this payment.');
        }

        if ($otherChargePayment->status !== 'awaiting_proof') {
            return redirect()->route('student.other-charges.index')
                ->with('flash.info', 'This payment is not waiting for proof of payment.');
        }

        $otherChargePayment->loadMissing('charge');

        return Inertia::render('Student/OtherCharges/ProofUpload', [
            'payment' => [
                'id'                => $otherChargePayment->id,
                'amount'            => (float) $otherChargePayment->amount_paid,
                'reference_number'  => $otherChargePayment->reference,
                'charge_title'      => $otherChargePayment->charge->title,
                'rejection_reason'  => $otherChargePayment->rejection_reason,
                'created_at'        => $otherChargePayment->created_at,
            ],
        ]);
    }

    public function uploadProof(Request $request, OtherChargePayment $otherChargePayment)
    {
        $user = $request->user();

        if ($otherChargePayment->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this payment.');
        }

        if ($otherChargePayment->status !== 'awaiting_proof') {
            return redirect()->route('student.other-charges.index')
                ->with('flash.info', 'This payment has already been submitted for review.');
        }

        $validated = $request->validate([
            'proof_of_payment' => 'required|file|mimes:pdf,jpg,jpeg,jfif,png,webp|max:5120',
        ]);

        $file = $validated['proof_of_payment'];

        $rawExtension = strtolower($file->getClientOriginalExtension());
        $extension    = match ($rawExtension) {
            'jfif', 'jpe', 'jif', 'jfi' => 'jpg',
            default                       => $rawExtension,
        };

        $filename = 'oc_proof_' . $otherChargePayment->id . '_' . time() . '.' . $extension;
        $filepath = $file->storeAs('other_charge_proofs', $filename, 'public');

        try {
            $this->service->submitProof($otherChargePayment, $filepath);
        } catch (\RuntimeException $e) {
            return redirect()->route('student.other-charges.index')
                ->with('flash.error', $e->getMessage());
        }

        return redirect()->route('student.other-charges.index')
            ->with('flash.success', 'Proof of payment uploaded. Awaiting accounting verification.');
    }

    /**
     * Cancel a bank transfer that is stuck in 'awaiting_proof' — the student
     * self-rescue path, mirroring PaymentController::cancelAbandonedProof().
     */
    public function cancelAwaitingProof(Request $request, OtherChargePayment $otherChargePayment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if ($otherChargePayment->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this payment.');
        }

        try {
            $this->service->cancelAwaitingProof($otherChargePayment);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment cancelled. You can now submit a new payment for this charge.',
        ]);
    }
}
