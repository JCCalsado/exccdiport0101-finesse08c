<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatusEnum;
use App\Http\Requests\Accounting\RejectRegistrationRequest;
use App\Http\Requests\Accounting\RequestRevisionRequest;
use App\Models\CurriculumFeePreset;
use App\Models\StudentRegistration;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RegistrarController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:web', 'role:registrar,admin']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════════════════════════════════

    public function dashboard(): Response
    {
        $queueCount      = StudentRegistration::registrarQueue()->count();
        $clearedToday    = StudentRegistration::where('status', 'registrar_cleared')
                            ->whereDate('registrar_reviewed_at', today())
                            ->count();
        $rejectedCount   = StudentRegistration::where('status', 'rejected_by_registrar')->count();
        $presetCount     = CurriculumFeePreset::count();
        $subjectCount    = Subject::count();

        return Inertia::render('Registrar/Dashboard', [
            'stats' => [
                'queue_count'    => $queueCount,
                'cleared_today'  => $clearedToday,
                'rejected_count' => $rejectedCount,
                'preset_count'   => $presetCount,
                'subject_count'  => $subjectCount,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REGISTRAR-STAGE REGISTRATION QUEUE
    // ══════════════════════════════════════════════════════════════════════════

    public function registrationIndex(Request $request): Response
    {
        $this->authorize('viewRegistrarQueue', StudentRegistration::class);

        $status = $request->get('status', 'pending');
        $search = $request->get('search');

        $query = StudentRegistration::query()
            ->when($status === 'pending', fn($q) =>
                $q->where('status', 'pending')
            )
            ->when($status === 'needs_revision', fn($q) =>
                $q->where('status', 'needs_revision')->where('revision_stage', 'registrar')
            )
            ->when($status === 'registrar_cleared', fn($q) =>
                $q->where('status', 'registrar_cleared')
            )
            ->when($status === 'rejected_by_registrar', fn($q) =>
                $q->where('status', 'rejected_by_registrar')
            )
            ->when($status === 'all', fn($q) =>
                $q->where(function ($inner) {
                    $inner->where('status', 'pending')
                          ->orWhere(function ($r) {
                              $r->where('status', 'needs_revision')
                                ->where('revision_stage', 'registrar');
                          })
                          ->orWhereIn('status', ['registrar_cleared', 'rejected_by_registrar']);
                })
            )
            ->when($search, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('last_name', 'like', "%{$s}%")
                          ->orWhere('first_name', 'like', "%{$s}%")
                          ->orWhere('email', 'like', "%{$s}%")
                          ->orWhere('tracking_token', 'like', "%{$s}%")
                          ->orWhere('contact_number', 'like', "%{$s}%");
                });
            })
            ->with('registrarReviewer:id,first_name,last_name')
            ->orderBy('submitted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'               => StudentRegistration::where('status', 'pending')->count(),
            'needs_revision'        => StudentRegistration::where('status', 'needs_revision')
                                        ->where('revision_stage', 'registrar')->count(),
            'registrar_cleared'     => StudentRegistration::where('status', 'registrar_cleared')->count(),
            'rejected_by_registrar' => StudentRegistration::where('status', 'rejected_by_registrar')->count(),
        ];

        return Inertia::render('Registrar/Registrations/Index', [
            'registrations' => $query->through(fn($r) => $this->serializeForList($r)),
            'counts'        => $counts,
            'filters'       => ['status' => $status, 'search' => $search],
        ]);
    }

    public function registrationShow(StudentRegistration $registration): Response
    {
        $this->authorize('viewRegistrarQueue', StudentRegistration::class);

        $registration->load('registrarReviewer:id,first_name,last_name');

        $duplicates = $registration->detectDuplicates();

        return Inertia::render('Registrar/Registrations/Show', [
            'registration' => $this->serializeForDetail($registration),
            'duplicates'   => $duplicates->map(fn($d) => [
                'id'           => $d->id,
                'full_name'    => $d->last_name . ', ' . $d->first_name,
                'email'        => $d->email,
                'status'       => $d->status->value,
                'submitted_at' => $d->submitted_at?->format('M d, Y'),
            ]),
            'documentUrls' => [
                'valid_id' => $registration->valid_id_path
                    ? route('registrar.registrations.document', [$registration, 'valid_id'])
                    : null,
                'proof'    => $registration->proof_of_enrollment_path
                    ? route('registrar.registrations.document', [$registration, 'proof'])
                    : null,
            ],
        ]);
    }

    /**
     * Clear the registration academically (Registrar stage approval).
     * Sets status → registrar_cleared. Forwards to the Finance queue.
     *
     * FIX: Was using $registration->notify(...) which fails silently because
     * StudentRegistration does not have the Notifiable trait. Switched to the
     * Notification::route() facade pattern (same as RegistrationApprovalController).
     */
    public function registrationApprove(StudentRegistration $registration): RedirectResponse
    {
        $this->authorize('actAsRegistrar', $registration);
        $this->ensureRegistrarActionable($registration);

        $registration->update([
            'status'                => RegistrationStatusEnum::REGISTRAR_CLEARED->value,
            'registrar_reviewed_by' => auth()->id(),
            'registrar_reviewed_at' => now(),
            'revision_stage'        => null,
        ]);

        // Notify applicant — academic clearance granted, awaiting Finance.
        try {
            Notification::route('mail', $registration->email)
                ->notify(new \App\Notifications\RegistrationClearedByRegistrar($registration));
        } catch (\Exception $e) {
            Log::warning('Failed to send RegistrationClearedByRegistrar email', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        // Notify Disbursing Officer(s) — new item in Finance queue.
        try {
            $disbursers = \App\Models\User::where('role', 'accounting')
                ->where('is_active', true)
                ->get()
                ->filter(fn ($u) => $u->isDisbursingOfficer());

            if ($disbursers->isNotEmpty()) {
                Notification::send(
                    $disbursers,
                    new \App\Notifications\NewRegistrationSubmitted($registration)
                );
            }
        } catch (\Exception $e) {
            Log::warning('Failed to notify Disbursing Officers of new Finance queue item', [
                'registration_id' => $registration->id,
            ]);
        }

        return redirect()
            ->route('registrar.registrations.index')
            ->with('flash.success', 'Registration cleared academically. Forwarded to the Finance queue.');
    }

    /**
     * Reject at the Registrar stage.
     * Sets status → rejected_by_registrar. Terminal state.
     *
     * FIX: Same Notifiable-trait issue as approve(). Switched to facade.
     */
    public function registrationReject(
        RejectRegistrationRequest $request,
        StudentRegistration $registration
    ): RedirectResponse {
        $this->authorize('actAsRegistrar', $registration);
        $this->ensureRegistrarActionable($registration);

        $registration->update([
            'status'                      => RegistrationStatusEnum::REJECTED_BY_REGISTRAR->value,
            'registrar_rejection_reason'  => $request->rejection_reason,
            'registrar_reviewed_by'       => auth()->id(),
            'registrar_reviewed_at'       => now(),
        ]);

        // Notify applicant — rejected with reason, must visit Registrar's office.
        try {
            Notification::route('mail', $registration->email)
                ->notify(new \App\Notifications\RegistrationRejectedByRegistrar($registration));
        } catch (\Exception $e) {
            Log::warning('Failed to send RegistrationRejectedByRegistrar email', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('registrar.registrations.index')
            ->with('flash.success', 'Registration rejected. The applicant has been notified.');
    }

    /**
     * Request academic revision from the applicant.
     */
    public function registrationRequestRevision(
        RequestRevisionRequest $request,
        StudentRegistration $registration
    ): RedirectResponse {
        $this->authorize('actAsRegistrar', $registration);
        $this->ensureRegistrarActionable($registration);

        $registration->update([
            'status'                   => RegistrationStatusEnum::NEEDS_REVISION->value,
            'registrar_revision_notes' => $request->revision_notes,
            'registrar_reviewed_by'    => auth()->id(),
            'registrar_reviewed_at'    => now(),
            'revision_stage'           => 'registrar',
        ]);

        // Notify applicant — academic documents need revision.
        // NOTE: RegistrationNeedsRevision reads $registration->revision_notes (Finance)
        //       not registrar_revision_notes. The student-facing RegistrationRevise.vue
        //       must display registrar_revision_notes when revision_stage = 'registrar'.
        try {
            Notification::route('mail', $registration->email)
                ->notify(new \App\Notifications\RegistrationNeedsRevision($registration));
        } catch (\Exception $e) {
            Log::warning('Failed to send RegistrationNeedsRevision email (registrar stage)', [
                'registration_id' => $registration->id,
            ]);
        }

        return redirect()
            ->route('registrar.registrations.index')
            ->with('flash.success', "Revision request sent to {$registration->email}.");
    }

    /**
     * Serve a registration document securely (Registrar-accessible copy).
     */
    public function serveDocument(StudentRegistration $registration, string $type): mixed
    {
        $this->authorize('viewRegistrarQueue', StudentRegistration::class);

        $path = match ($type) {
            'valid_id' => $registration->valid_id_path,
            'proof'    => $registration->proof_of_enrollment_path,
            default    => null,
        };

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path);
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function ensureRegistrarActionable(StudentRegistration $registration): void
    {
        if ($registration->isRegistrarCleared() || $registration->isApproved()) {
            abort(422, 'This registration has already passed the academic review stage.');
        }

        if ($registration->isRejectedByRegistrar()) {
            abort(422, 'This registration has already been rejected by the Registrar.');
        }

        if ($registration->isRejected()) {
            abort(422, 'This registration has been rejected at the Finance stage and cannot be modified here.');
        }

        if ($registration->needsRevision() && $registration->revision_stage !== 'registrar') {
            abort(422, 'This revision request belongs to the Finance stage queue, not the Registrar queue.');
        }
    }

    // ── Serializers ────────────────────────────────────────────────────────

    private function serializeForList(StudentRegistration $r): array
    {
        return [
            'id'                       => $r->id,
            'tracking_token'           => $r->tracking_token,
            'full_name'                => $r->full_name,
            'email'                    => $r->email,
            'contact_number'           => $r->contact_number,
            'course'                   => $r->course,
            'year_level'               => $r->year_level,
            'student_type'             => $r->student_type,
            'status'                   => $r->status->value,
            'status_label'             => $r->status->label(),
            'status_color'             => $r->status->color(),
            'submitted_at'             => $r->submitted_at?->format('M d, Y g:i A'),
            'registrar_reviewer_name'  => $r->registrarReviewer
                ? $r->registrarReviewer->first_name . ' ' . $r->registrarReviewer->last_name
                : null,
        ];
    }

    private function serializeForDetail(StudentRegistration $r): array
    {
        return [
            'id'                          => $r->id,
            'tracking_token'              => $r->tracking_token,
            'full_name'                   => $r->full_name,
            'full_address'                => $r->full_address,
            'last_name'                   => $r->last_name,
            'first_name'                  => $r->first_name,
            'middle_name'                 => $r->middle_name,
            'suffix'                      => $r->suffix,
            'gender'                      => $r->gender,
            'birthdate'                   => $r->birthdate?->format('F d, Y'),
            'civil_status'                => $r->civil_status,
            'contact_number'              => $r->contact_number,
            'email'                       => $r->email,
            'address_house'               => $r->address_house,
            'address_street'              => $r->address_street,
            'address_barangay'            => $r->address_barangay,
            'address_city'                => $r->address_city,
            'address_province'            => $r->address_province,
            'address_zip'                 => $r->address_zip,
            'existing_student_id'         => $r->existing_student_id,
            'course'                      => $r->course,
            'year_level'                  => $r->year_level,
            'semester'                    => $r->semester,
            'school_year'                 => $r->school_year,
            'student_type'                => $r->student_type,
            'guardian_name'               => $r->guardian_name,
            'guardian_contact'            => $r->guardian_contact,
            'emergency_contact'           => $r->emergency_contact,
            'has_valid_id'                => ! empty($r->valid_id_path),
            'has_proof'                   => ! empty($r->proof_of_enrollment_path),
            'status'                      => $r->status->value,
            'status_label'                => $r->status->label(),
            'status_color'                => $r->status->color(),
            'revision_stage'              => $r->revision_stage,
            'registrar_rejection_reason'  => $r->registrar_rejection_reason,
            'registrar_revision_notes'    => $r->registrar_revision_notes,
            'registrar_reviewed_at'       => $r->registrar_reviewed_at?->format('F d, Y g:i A'),
            'registrar_reviewer_name'     => $r->registrarReviewer
                ? $r->registrarReviewer->first_name . ' ' . $r->registrarReviewer->last_name
                : null,
            'submitted_at'                => $r->submitted_at?->format('F d, Y g:i A'),
            'is_registrar_actionable'     => $r->isRegistrarActionable(),
        ];
    }
}