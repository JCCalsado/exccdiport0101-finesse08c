<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use App\Models\User;
use App\Notifications\OtherChargePaymentConfirmed;
use App\Notifications\OtherChargePaymentRejected;
use App\Notifications\OtherChargePublished;
use App\Services\OtherChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OtherChargeController extends Controller
{
    public function __construct(private readonly OtherChargeService $service) {}

    // ─── Index ────────────────────────────────────────────────────────────────
    //
    // BUG-06 FIX: N+1 queries eliminated for paid_count and total_collected.
    //
    // BEFORE: paid_count and total_collected were computed inside ->map() via
    //   individual ->count() and ->sum() calls — 2 extra queries per charge row.
    //   15 charges = ~31 extra queries just for the index page.
    //
    // AFTER: withCount() and withSum() load both aggregates in the same query
    //   as the charges themselves. matchingStudentCount() still runs per-row
    //   (requires a correlated subquery per charge) but the two cheapest
    //   offenders are now fully batched.

    public function index(): Response
    {
        $this->authorize('viewAny', OtherCharge::class);

        $charges = OtherCharge::with('createdBy')
            ->withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'paid')])
            ->withSum(['payments as total_collected' => fn ($q) => $q->where('status', 'paid')], 'amount_paid')
            ->active()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OtherCharge $charge) => [
                'id'                     => $charge->id,
                'title'                  => $charge->title,
                'description'            => $charge->description,
                'amount'                 => (float) $charge->amount,
                'school_year'            => $charge->school_year,
                'semester'               => $charge->semester,
                'year_level'             => $charge->year_level,
                'course'                 => $charge->course,
                'status_label'           => $charge->status_label,
                'is_published'           => $charge->is_published,
                'published_at'           => $charge->published_at?->format('Y-m-d'),
                'created_by_name'        => $charge->createdBy?->name,
                'matching_student_count' => $charge->matchingStudentCount(),
                'paid_count'             => (int) $charge->paid_count,
                'total_collected'        => (float) ($charge->total_collected ?? 0),
            ]);

        return Inertia::render('Accounting/OtherCharges/Index', [
            'charges' => $charges,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $this->authorize('create', OtherCharge::class);

        return Inertia::render('Accounting/OtherCharges/Create', [
            'schoolYears' => $this->availableSchoolYears(),
            'semesters'   => ['1st Sem', '2nd Sem', 'Summer'],
            'yearLevels'  => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'courses'     => ['BSEET', 'BSEECT'],
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('create', OtherCharge::class);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount'      => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester'    => ['nullable', 'in:1st Sem,2nd Sem,Summer'],
            'year_level'  => ['nullable', 'in:1st Year,2nd Year,3rd Year,4th Year'],
            'course'      => ['nullable', 'string', 'max:100'],
        ]);

        $charge = OtherCharge::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'is_active'  => true,
        ]);

        // No notification here — charge is a DRAFT. Students are notified on publish.

        return redirect()
            ->route('accounting.other-charges.show', $charge)
            ->with('success', "'{$charge->title}' created as a draft.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────
    //
    // BUG-04 FIX: summary counts now correctly account for all statuses.
    //
    // BEFORE:
    //   $unpaidCount = $students->where('status', 'unpaid')->count();
    //   Students with 'pending', 'awaiting_confirmation', 'failed', 'cancelled'
    //   were counted in neither Paid nor Unpaid — they disappeared from the
    //   summary cards entirely.
    //
    // AFTER: three distinct counts, matching what the Vue now renders:
    //   paid        → status = 'paid'
    //   in_progress → online/bank-transfer payment currently in flight
    //   unpaid      → genuinely not paid (unpaid, failed, cancelled)

    public function show(OtherCharge $otherCharge): Response
    {
        $this->authorize('view', $otherCharge);

        $students = $this->service->getStudentsForCharge($otherCharge);

        $paidCount       = $students->where('status', 'paid')->count();
        $inProgressCount = $students->whereIn('status', [
            'pending', 'awaiting_confirmation', 'awaiting_proof', 'awaiting_approval',
        ])->count();
        $unpaidCount     = $students->whereIn('status', ['unpaid', 'failed', 'cancelled'])->count();
        $totalCollected  = $students->where('status', 'paid')->sum('amount_paid');

        return Inertia::render('Accounting/OtherCharges/Show', [
            'charge' => [
                'id'                       => $otherCharge->id,
                'title'                    => $otherCharge->title,
                'description'              => $otherCharge->description,
                'amount'                   => (float) $otherCharge->amount,
                'school_year'              => $otherCharge->school_year,
                'semester'                 => $otherCharge->semester,
                'year_level'               => $otherCharge->year_level,
                'course'                   => $otherCharge->course,
                'is_published'             => $otherCharge->is_published,
                'is_draft'                 => $otherCharge->is_draft,
                'status_label'             => $otherCharge->status_label,
                'published_at'             => $otherCharge->published_at?->format('Y-m-d'),
                'updated_after_publish_at' => $otherCharge->updated_after_publish_at?->format('Y-m-d H:i'),
                'created_at'               => $otherCharge->created_at->format('Y-m-d'),
            ],
            'students'         => $students->values(),
            'summary'          => [
                'total'           => $students->count(),
                'paid'            => $paidCount,
                'in_progress'     => $inProgressCount,
                'unpaid'          => $unpaidCount,
                'total_collected' => (float) $totalCollected,
            ],
            'canEdit'          => request()->user()->can('update', $otherCharge),
            'canRecordPayment' => request()->user()->can('recordPayment', $otherCharge),
            'canApprove'       => request()->user()->can('approvePayment', $otherCharge),
            'canPublish'       => request()->user()->can('publish', $otherCharge),
            'hasPaidStudents'  => $paidCount > 0,
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(OtherCharge $otherCharge): Response
    {
        $this->authorize('update', $otherCharge);

        return Inertia::render('Accounting/OtherCharges/Edit', [
            'charge' => [
                'id'           => $otherCharge->id,
                'title'        => $otherCharge->title,
                'description'  => $otherCharge->description,
                'amount'       => (float) $otherCharge->amount,
                'school_year'  => $otherCharge->school_year,
                'semester'     => $otherCharge->semester,
                'year_level'   => $otherCharge->year_level,
                'course'       => $otherCharge->course,
                'is_published' => $otherCharge->is_published,
            ],
            'hasPaidStudents' => $otherCharge->payments()->where('status', 'paid')->exists(),
            'schoolYears'     => $this->availableSchoolYears(),
            'semesters'       => ['1st Sem', '2nd Sem', 'Summer'],
            'yearLevels'      => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'courses'         => ['BSEET', 'BSEECT'],
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, OtherCharge $otherCharge)
    {
        $this->authorize('update', $otherCharge);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount'      => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester'    => ['nullable', 'in:1st Sem,2nd Sem,Summer'],
            'year_level'  => ['nullable', 'in:1st Year,2nd Year,3rd Year,4th Year'],
            'course'      => ['nullable', 'string', 'max:100'],
        ]);

        $wasPublished = $otherCharge->is_published;
        $updateData   = $validated;

        if ($wasPublished) {
            $updateData['updated_after_publish_at'] = now();
        }

        $otherCharge->update($updateData);

        return redirect()
            ->route('accounting.other-charges.show', $otherCharge)
            ->with('success', "'{$otherCharge->title}' updated successfully.");
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(OtherCharge $otherCharge)
    {
        $this->authorize('delete', $otherCharge);

        $title = $otherCharge->title;
        $otherCharge->update(['is_active' => false]);

        return redirect()
            ->route('accounting.other-charges.index')
            ->with('success', "'{$title}' has been archived.");
    }

    // ─── Publish ──────────────────────────────────────────────────────────────

    public function publish(Request $request, OtherCharge $otherCharge)
    {
        $this->authorize('publish', $otherCharge);

        if ($otherCharge->is_published) {
            return back()->withErrors(['charge' => 'This charge is already published.']);
        }

        $otherCharge->update(['published_at' => now()]);

        Log::info('OtherCharge published', [
            'charge_id'    => $otherCharge->id,
            'title'        => $otherCharge->title,
            'published_by' => $request->user()->id,
        ]);

        // ── Notify all matching students ──────────────────────────────────────
        // Load matching students from the DB. For large cohorts this runs as
        // individual mail dispatches — acceptable for the current Hostinger
        // cron queue setup. If the cohort ever exceeds ~200, move to a queued
        // chunked job.
        $matchingStudents = $otherCharge->buildMatchingStudentsQuery()->get();

        if ($matchingStudents->isNotEmpty()) {
            try {
                Notification::send($matchingStudents, new OtherChargePublished($otherCharge));

                Log::info('OtherChargePublished notification dispatched', [
                    'charge_id'     => $otherCharge->id,
                    'student_count' => $matchingStudents->count(),
                ]);
            } catch (\Throwable $e) {
                // Notification failure must NOT block the publish action.
                Log::error('OtherChargePublished notification failed', [
                    'charge_id' => $otherCharge->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "'{$otherCharge->title}' is now published. {$matchingStudents->count()} student(s) notified.");
    }

    // ─── Record OTC Payment ───────────────────────────────────────────────────

    public function recordPayment(Request $request, OtherCharge $otherCharge)
    {
        $this->authorize('recordPayment', $otherCharge);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'or_number'  => ['required', 'string', 'max:100'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $student = User::findOrFail($validated['student_id']);

        try {
            $payment = $this->service->recordOtcPayment(
                charge:      $otherCharge,
                student:     $student,
                orNumber:    $validated['or_number'],
                notes:       $validated['notes'] ?? null,
                collectedBy: $request->user(),
            );

            // ── Notify student of OTC payment confirmation ────────────────────
            try {
                $student->notify(new OtherChargePaymentConfirmed($otherCharge, $payment));
            } catch (\Throwable $e) {
                // Notification failure must NOT roll back the recorded payment.
                Log::warning('OtherChargePaymentConfirmed notification failed (OTC)', [
                    'charge_id'  => $otherCharge->id,
                    'student_id' => $student->id,
                    'or_number'  => $payment->or_number,
                    'error'      => $e->getMessage(),
                ]);
            }

            return back()->with('success', "Payment recorded for {$student->name}. OR# {$payment->or_number}");

        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  OPTION D — BANK TRANSFER APPROVAL (Disbursing Officer / Admin only)
    // ─────────────────────────────────────────────────────────────────────────

    public function approvePayment(Request $request, OtherChargePayment $otherChargePayment)
    {
        $this->authorize('approvePayment', $otherChargePayment->charge);

        try {
            $this->service->approveBankTransfer($otherChargePayment, $request->user());

            // ── Notify student of bank transfer approval ───────────────────────
            try {
                $otherChargePayment->student->notify(
                    new OtherChargePaymentConfirmed($otherChargePayment->charge, $otherChargePayment)
                );
            } catch (\Throwable $e) {
                Log::warning('OtherChargePaymentConfirmed notification failed (bank transfer)', [
                    'payment_id' => $otherChargePayment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return back()->with('success', "Bank transfer approved for {$otherChargePayment->student->name}.");

        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    public function rejectPayment(Request $request, OtherChargePayment $otherChargePayment)
    {
        $this->authorize('approvePayment', $otherChargePayment->charge);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->service->rejectBankTransfer($otherChargePayment, $request->user(), $validated['reason']);

            // ── Notify student so they know to re-upload ───────────────────────
            try {
                $otherChargePayment->student->notify(
                    new OtherChargePaymentRejected($otherChargePayment->charge, $otherChargePayment)
                );
            } catch (\Throwable $e) {
                Log::warning('OtherChargePaymentRejected notification failed', [
                    'payment_id' => $otherChargePayment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return back()->with('success', "Bank transfer rejected for {$otherChargePayment->student->name}. Student notified.");

        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /**
     * Serve a bank transfer proof file for Accounting review.
     * Route-based serving — no storage symlink required (same reasoning as
     * PaymentController::serveProof, which exists because Hostinger either
     * lacks the symlink or Apache blocks symlink traversal).
     */
    public function serveProof(Request $request, OtherChargePayment $otherChargePayment): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $otherChargePayment->charge);

        $proofPath = $otherChargePayment->proof_path;

        if (! $proofPath) {
            abort(404, 'No proof of payment on record for this payment.');
        }

        if (! Storage::disk('public')->exists($proofPath)) {
            abort(404, 'Proof file not found in storage.');
        }

        $filename  = basename($proofPath);
        $extension = strtolower(pathinfo($proofPath, PATHINFO_EXTENSION));
        $mimeType  = match ($extension) {
            'pdf'         => 'application/pdf',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'application/octet-stream',
        };

        $disposition = $request->query('dl') ? 'attachment' : 'inline';

        return response(
            Storage::disk('public')->get($proofPath),
            200,
            [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
                'Cache-Control'       => 'private, max-age=3600',
            ]
        );
    }

    // ─── Preview Student Count ────────────────────────────────────────────────

    /**
     * AJAX endpoint: returns the count of students that would match the
     * given filter combination. Used by Create.vue for the live preview.
     */
    public function previewCount(Request $request)
    {
        $this->authorize('create', OtherCharge::class);

        $validated = $request->validate([
            'school_year' => ['required', 'string'],
            'semester'    => ['nullable', 'string'],
            'year_level'  => ['nullable', 'string'],
            'course'      => ['nullable', 'string'],
        ]);

        $temp  = new OtherCharge($validated);
        $count = $temp->buildMatchingStudentsQuery()->count();

        return response()->json(['count' => $count]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function availableSchoolYears(): array
    {
        $current = (int) date('Y');
        $years   = [];
        for ($y = $current - 1; $y <= $current + 1; $y++) {
            $years[] = "{$y}-" . ($y + 1);
        }
        return $years;
    }
}
