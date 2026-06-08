<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\RegistrationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\RejectRegistrationRequest;
use App\Http\Requests\Accounting\RequestRevisionRequest;
use App\Models\Account;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationNeedsRevision;
use App\Notifications\RegistrationRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationApprovalController extends Controller
{
    /**
     * List registrations in the Finance queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewFinanceQueue', StudentRegistration::class);

        $status = $request->get('status', 'registrar_cleared');
        $search = $request->get('search');

        $query = StudentRegistration::query()
            ->when($status === 'registrar_cleared', fn($q) =>
                $q->where('status', 'registrar_cleared')
            )
            ->when($status === 'needs_revision', fn($q) =>
                $q->where('status', 'needs_revision')->where('revision_stage', 'finance')
            )
            ->when($status === 'approved', fn($q) =>
                $q->where('status', 'approved')
            )
            ->when($status === 'rejected', fn($q) =>
                $q->where('status', 'rejected')
            )
            ->when($status === 'all', fn($q) =>
                $q->whereIn('status', [
                    'registrar_cleared', 'approved', 'rejected', 'needs_revision',
                ])
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
            ->with([
                'reviewer:id,first_name,last_name',
                'registrarReviewer:id,first_name,last_name',
            ])
            ->orderBy('submitted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'registrar_cleared' => StudentRegistration::where('status', 'registrar_cleared')->count(),
            'needs_revision'    => StudentRegistration::where('status', 'needs_revision')
                                    ->where('revision_stage', 'finance')->count(),
            'approved'          => StudentRegistration::where('status', 'approved')->count(),
            'rejected'          => StudentRegistration::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Accounting/RegistrationApprovals/Index', [
            'registrations' => $query->through(fn($r) => $this->serializeForList($r)),
            'counts'        => $counts,
            'filters'       => ['status' => $status, 'search' => $search],
        ]);
    }

    public function show(StudentRegistration $registration): Response
    {
        $this->authorize('viewFinanceQueue', StudentRegistration::class);

        $registration->load([
            'reviewer:id,first_name,last_name',
            'registrarReviewer:id,first_name,last_name',
        ]);

        $duplicates    = $registration->detectDuplicates();
        $matchResult   = $registration->findMatchingUser();

        // Serialize existingUser for the frontend — null, or a typed object
        // with is_same_person so the UI can render the correct message.
        $existingUser = null;
        if ($matchResult) {
            $u = $matchResult['user'];
            $existingUser = [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'account_id'     => $u->account_id,
                'is_active'      => $u->is_active,
                'is_same_person' => $matchResult['is_same_person'],
            ];
        }

        return Inertia::render('Accounting/RegistrationApprovals/Show', [
            'registration' => $this->serializeForDetail($registration),
            'duplicates'   => $duplicates->map(fn($d) => [
                'id'             => $d->id,
                'full_name'      => $d->last_name . ', ' . $d->first_name,
                'email'          => $d->email,
                'contact_number' => $d->contact_number,
                'status'         => $d->status->value,
                'submitted_at'   => $d->submitted_at?->format('M d, Y'),
            ]),
            'existingUser' => $existingUser,
            'documentUrls' => [
                'valid_id' => $registration->valid_id_path
                    ? route('accounting.registrations.document', [$registration, 'valid_id'])
                    : null,
                'proof'    => $registration->proof_of_enrollment_path
                    ? route('accounting.registrations.document', [$registration, 'proof'])
                    : null,
            ],
        ]);
    }

    /**
     * Finance-stage approval.
     *
     * Two paths:
     *
     * A) No existing User with this email → normal path.
     *    Creates: User → Student → Account.
     *
     * B) Existing User with same name (returning student / transferee).
     *    Does NOT create a new User. Updates the existing user's enrollment
     *    data (course, year_level) and reactivates the account if needed.
     *    The Student and Account rows already exist — no duplication.
     *
     * C) Existing User with different name (genuine email collision).
     *    Hard block — cannot approve. DO must reject and contact applicant.
     */
    public function approve(StudentRegistration $registration): RedirectResponse
    {
        $this->authorize('actAsFinance', $registration);
        $this->ensureFinanceActionable($registration);

        $matchResult = $registration->findMatchingUser();

        // Path C: genuine email collision — different person, hard block.
        if ($matchResult && ! $matchResult['is_same_person']) {
            return back()->with(
                'flash.error',
                'A different person with the email ' . $registration->email . ' already exists (ID: ' . $matchResult['user']->id . '). Cannot approve — this would overwrite another user\'s account. Reject this registration and ask the applicant to use a different email.'
            );
        }

        DB::beginTransaction();
        try {
            // ── PATH B: returning student — link to existing User ─────────────
            if ($matchResult && $matchResult['is_same_person']) {
                $user = $matchResult['user'];

                // Update enrollment-relevant fields on the existing user.
                // Do NOT overwrite password, account_id, or role.
                $user->update([
                    'course'                    => $registration->course,
                    'year_level'                => $registration->year_level,
                    'phone'                     => $registration->contact_number,
                    'gender'                    => $registration->gender,
                    'civil_status'              => $registration->civil_status,
                    'birthday'                  => $registration->birthdate,
                    'address_house_lot_unit'    => $registration->address_house,
                    'address_street_name'       => $registration->address_street,
                    'address_barangay'          => $registration->address_barangay,
                    'address_municipality_city' => $registration->address_city,
                    'address_province'          => $registration->address_province,
                    'address_zip'               => $registration->address_zip,
                    'guardian_name'             => $registration->guardian_name,
                    'guardian_contact'          => $registration->guardian_contact,
                    'emergency_contact'         => $registration->emergency_contact,
                    'is_irregular'              => in_array($registration->student_type, ['irregular'], true),
                    'status'                    => User::STATUS_ACTIVE,
                    'is_active'                 => true,
                ]);

                // Ensure the Student row exists (may have been soft-dropped).
                Student::firstOrCreate(
                    ['user_id' => $user->id],
                    ['student_id' => $user->account_id, 'enrollment_status' => 'active']
                );
                Student::where('user_id', $user->id)
                    ->update(['enrollment_status' => 'active']);

                // Ensure an Account row exists.
                Account::firstOrCreate(
                    ['user_id' => $user->id],
                    ['account_number' => Account::generateAccountNumber(), 'balance' => 0]
                );

                $registration->update([
                    'status'         => RegistrationStatusEnum::APPROVED->value,
                    'reviewed_by'    => auth()->id(),
                    'reviewed_at'    => now(),
                    'revision_stage' => null,
                    'user_id'        => $user->id,
                    'password_hash'  => null,
                ]);

                DB::commit();

                try {
                    Notification::route('mail', $registration->email)
                        ->notify(new RegistrationApproved($registration, $user));
                } catch (\Exception $e) {
                    Log::warning('Failed to send approval notification (returning student)', [
                        'registration_id' => $registration->id,
                        'user_id'         => $user->id,
                        'error'           => $e->getMessage(),
                    ]);
                }

                Log::info('Finance approval: returning student linked to existing account', [
                    'registration_id' => $registration->id,
                    'user_id'         => $user->id,
                    'account_id'      => $user->account_id,
                ]);

                return redirect()
                    ->route('accounting.registrations.index')
                    ->with('flash.success', "Registration approved. Existing account {$user->account_id} updated for {$user->first_name} {$user->last_name}.");
            }

            // ── PATH A: new student — create User + Student + Account ─────────
            $accountId    = $this->generateUniqueAccountId();
            $passwordHash = $registration->password_hash ?? Hash::make(str()->random(32));
            $usedFallback = ! $registration->password_hash;

            $user = User::create([
                'last_name'                 => $registration->last_name,
                'first_name'                => $registration->first_name,
                'middle_name'               => $registration->middle_name,
                'middle_initial'            => $registration->middle_name
                                                ? mb_strtoupper(mb_substr($registration->middle_name, 0, 1))
                                                : null,
                'suffix'                    => $registration->suffix,
                'gender'                    => $registration->gender,
                'civil_status'              => $registration->civil_status,
                'email'                     => $registration->email,
                'password'                  => $passwordHash,
                'email_verified_at'         => now(),
                'phone'                     => $registration->contact_number,
                'birthday'                  => $registration->birthdate,
                'address_house_lot_unit'    => $registration->address_house,
                'address_street_name'       => $registration->address_street,
                'address_barangay'          => $registration->address_barangay,
                'address_municipality_city' => $registration->address_city,
                'address_province'          => $registration->address_province,
                'address_zip'               => $registration->address_zip,
                'guardian_name'             => $registration->guardian_name,
                'guardian_contact'          => $registration->guardian_contact,
                'emergency_contact'         => $registration->emergency_contact,
                'course'                    => $registration->course,
                'year_level'                => $registration->year_level,
                'account_id'                => $accountId,
                'is_irregular'              => in_array($registration->student_type, ['irregular'], true),
                'status'                    => User::STATUS_ACTIVE,
                'role'                      => UserRoleEnum::STUDENT->value,
                'is_active'                 => true,
                'created_by'                => auth()->id(),
            ]);

            Student::create([
                'user_id'           => $user->id,
                'student_id'        => $accountId,
                'enrollment_status' => 'active',
            ]);

            Account::create([
                'user_id'        => $user->id,
                'account_number' => Account::generateAccountNumber(),
                'balance'        => 0,
            ]);

            $registration->update([
                'status'         => RegistrationStatusEnum::APPROVED->value,
                'reviewed_by'    => auth()->id(),
                'reviewed_at'    => now(),
                'revision_stage' => null,
                'user_id'        => $user->id,
                'password_hash'  => null,
            ]);

            DB::commit();

            try {
                Notification::route('mail', $registration->email)
                    ->notify(new RegistrationApproved($registration, $user));
            } catch (\Exception $e) {
                Log::warning('Failed to send approval notification', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
            }

            if ($usedFallback) {
                Log::warning('Finance approval used fallback random password — student must reset', [
                    'registration_id' => $registration->id,
                    'user_id'         => $user->id,
                ]);
            }

            return redirect()
                ->route('accounting.registrations.index')
                ->with('flash.success', "Registration approved. Account {$accountId} created for {$user->first_name} {$user->last_name}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Finance registration approval failed', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);

            return back()->with('flash.error', 'Approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Finance-stage rejection.
     */
    public function reject(
        RejectRegistrationRequest $request,
        StudentRegistration $registration
    ): RedirectResponse {
        $this->authorize('actAsFinance', $registration);
        $this->ensureFinanceActionable($registration);

        $registration->update([
            'status'       => RegistrationStatusEnum::REJECTED->value,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
        ]);

        try {
            Notification::route('mail', $registration->email)
                ->notify(new RegistrationRejected($registration));
        } catch (\Exception $e) {
            Log::warning('Failed to send rejection notification', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('accounting.registrations.index')
            ->with('flash.success', 'Registration rejected. The applicant has been notified.');
    }

    /**
     * Finance-stage revision request.
     */
    public function requestRevision(
        RequestRevisionRequest $request,
        StudentRegistration $registration
    ): RedirectResponse {
        $this->authorize('actAsFinance', $registration);
        $this->ensureFinanceActionable($registration);

        $registration->update([
            'status'         => RegistrationStatusEnum::NEEDS_REVISION->value,
            'revision_notes' => $request->revision_notes,
            'reviewed_by'    => auth()->id(),
            'reviewed_at'    => now(),
            'revision_stage' => 'finance',
        ]);

        try {
            Notification::route('mail', $registration->email)
                ->notify(new RegistrationNeedsRevision($registration));
        } catch (\Exception $e) {
            Log::warning('Failed to send revision notification', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('accounting.registrations.index')
            ->with('flash.success', "Revision request sent to {$registration->email}.");
    }

    /**
     * Serve a registration document securely.
     */
    public function serveDocument(StudentRegistration $registration, string $type): mixed
    {
        $this->authorize('viewFinanceQueue', StudentRegistration::class);

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

    private function ensureFinanceActionable(StudentRegistration $registration): void
    {
        if ($registration->isApproved()) {
            abort(422, 'This registration has already been approved and enrolled.');
        }

        if ($registration->isRejected()) {
            abort(422, 'This registration has already been rejected at the Finance stage.');
        }

        if ($registration->isRejectedByRegistrar()) {
            abort(422, 'This registration was rejected by the Registrar and cannot be processed by Finance.');
        }

        if ($registration->isPending()) {
            abort(422, 'This registration has not yet been reviewed by the Registrar. It cannot be processed at Finance.');
        }

        if ($registration->needsRevision() && $registration->revision_stage !== 'finance') {
            abort(422, 'This revision request belongs to the Registrar stage queue, not Finance.');
        }
    }

    private function generateUniqueAccountId(): string
    {
        $year = now()->year;

        $last = User::where('account_id', 'like', "{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(account_id, 6) AS UNSIGNED) DESC')
            ->first();

        $lastNumber = $last ? intval(substr($last->account_id, -4)) : 0;
        $newNumber  = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        $accountId  = "{$year}-{$newNumber}";

        $attempts = 0;
        while (User::where('account_id', $accountId)->exists() && $attempts < 20) {
            $lastNumber++;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $accountId = "{$year}-{$newNumber}";
            $attempts++;
        }

        if ($attempts >= 20) {
            throw new \RuntimeException('Unable to generate a unique account ID.');
        }

        return $accountId;
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
            'reviewer_name'            => $r->reviewer
                ? $r->reviewer->first_name . ' ' . $r->reviewer->last_name
                : null,
            'registrar_reviewer_name'  => $r->registrarReviewer
                ? $r->registrarReviewer->first_name . ' ' . $r->registrarReviewer->last_name
                : null,
            'registrar_reviewed_at'    => $r->registrar_reviewed_at?->format('M d, Y'),
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
            'rejection_reason'            => $r->rejection_reason,
            'revision_notes'              => $r->revision_notes,
            'reviewed_at'                 => $r->reviewed_at?->format('F d, Y g:i A'),
            'reviewer_name'               => $r->reviewer
                ? $r->reviewer->first_name . ' ' . $r->reviewer->last_name
                : null,
            'registrar_reviewed_at'       => $r->registrar_reviewed_at?->format('F d, Y g:i A'),
            'registrar_reviewer_name'     => $r->registrarReviewer
                ? $r->registrarReviewer->first_name . ' ' . $r->registrarReviewer->last_name
                : null,
            'registrar_rejection_reason'  => $r->registrar_rejection_reason,
            'registrar_revision_notes'    => $r->registrar_revision_notes,
            'submitted_at'                => $r->submitted_at?->format('F d, Y g:i A'),
            'is_finance_actionable'       => $r->isFinanceActionable(),
        ];
    }
}