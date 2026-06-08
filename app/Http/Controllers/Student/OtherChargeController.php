<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\OtherCharge;
use App\Services\OtherChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OtherChargeController extends Controller
{
    public function __construct(private readonly OtherChargeService $service) {}

    /**
     * Student's own Other Charges portal page.
     * Lists all matching charges with payment status.
     */
    public function index(Request $request): Response
    {
        $student = $request->user();
        $charges = $this->service->getChargesForStudent($student);

        return Inertia::render('Student/OtherCharges/Index', [
            'charges'         => $charges->values(),
            'paymentFeedback' => $request->query('payment'), // 'success' | 'cancelled'
        ]);
    }

    /**
     * Initiate a PayMongo online payment for an Other Charge.
     * Returns JSON with checkout_url — Vue redirects the browser.
     */
    public function initiatePayment(Request $request, OtherCharge $otherCharge)
    {
        $student = $request->user();

        // Guard: charge must be published and active
        if (! $otherCharge->is_published || ! $otherCharge->is_active) {
            return response()->json(['error' => 'This charge is not available for payment.'], 422);
        }

        // Guard: student must match charge filters
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
