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
}
