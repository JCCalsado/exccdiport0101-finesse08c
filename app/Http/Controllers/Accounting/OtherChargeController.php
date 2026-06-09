<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use App\Models\User;
use App\Notifications\OtherChargePaymentConfirmed;
use App\Notifications\OtherChargePublished;
use App\Services\OtherChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class OtherChargeController extends Controller
{
    public function __construct(private readonly OtherChargeService $service) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $this->authorize('viewAny', OtherCharge::class);

        $charges = OtherCharge::with('createdBy')
            ->active()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OtherCharge $charge) => [
                'id'                       => $charge->id,
                'title'                    => $charge->title,
                'description'              => $charge->description,
                'amount'                   => (float) $charge->amount,
                'school_year'              => $charge->school_year,
                'semester'                 => $charge->semester,
                'year_level'               => $charge->year_level,
                'course'                   => $charge->course,
                'status_label'             => $charge->status_label,
                'is_published'             => $charge->is_published,
                'published_at'             => $charge->published_at?->format('Y-m-d'),
                'created_by_name'          => $charge->createdBy?->name,
                'matching_student_count'   => $charge->matchingStudentCount(),
                'paid_count'               => $charge->payments()->where('status', 'paid')->count(),
                'total_collected'          => (float) $charge->payments()->where('status', 'paid')->sum('amount_paid'),
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

    public function show(OtherCharge $otherCharge): Response
    {
        $this->authorize('view', $otherCharge);

        $students = $this->service->getStudentsForCharge($otherCharge);

        $paidCount      = $students->where('status', 'paid')->count();
        $unpaidCount    = $students->where('status', 'unpaid')->count();
        $totalCollected = $students->where('status', 'paid')->sum('amount_paid');

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
                'unpaid'          => $unpaidCount,
                'total_collected' => (float) $totalCollected,
            ],
            'canEdit'          => request()->user()->can('update', $otherCharge),
            'canRecordPayment' => request()->user()->can('recordPayment', $otherCharge),
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
                    'charge_id'      => $otherCharge->id,
                    'student_count'  => $matchingStudents->count(),
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
