<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use App\Models\StudentAssessment;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name'                      => config('app.name'),
            'unreadNotificationsCount'  => $this->resolveUnreadNotificationsCount($request),
            // pendingRegistrationsCount removed — now inside auth.user.registration_counts
            'quote'                     => ['message' => trim($message), 'author' => trim($author)],
            'auth'                      => ['user' => $this->resolveAuthUser($request)],
            'latestAssessmentInfo'      => $this->resolveLatestAssessmentInfo($request),
            'sidebarOpen'               => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token'                => csrf_token(),
            'flash'                     => [
                'error'   => $request->session()->pull('flash.error'),
                'warning' => $request->session()->pull('flash.warning'),
                'success' => $request->session()->pull('flash.success'),
                'info'    => $request->session()->pull('flash.info'),
            ],
        ];
    }

    private function resolveUnreadNotificationsCount(Request $request): int
    {
        $user = $request->user();
        if (! $user) return 0;
        if ($user->isAdmin()) return 0;

        $cacheKey = "unread_notifications_count:{$user->id}";
        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($user) {
            return \App\Models\Notification::active()
                ->forUser($user->id)
                ->withinDateRange()
                ->unread()
                ->count();
        });
    }

    private function resolveAuthUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) return null;

        $role = $user->role instanceof UserRoleEnum
            ? $user->role->value
            : (string) $user->role;

        // accounting_type is null for admin, student, registrar users.
        $accountingType = null;
        if ($user->accounting_type !== null) {
            $accountingType = $user->accounting_type instanceof \App\Enums\AccountingTypeEnum
                ? $user->accounting_type->value
                : (string) $user->accounting_type;
        }

        $avatar = $user->profile_picture
            ? asset('storage/' . $user->profile_picture)
            : null;

        return [
            // ── Identity ──────────────────────────────────────────────────────
            'id'              => $user->id,
            'name'            => $user->name,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'middle_name'     => $user->middle_name,
            'middle_initial'  => $user->middle_initial,
            'suffix'          => $user->suffix,
            'gender'          => $user->gender,
            'civil_status'    => $user->civil_status,

            // ── Auth & Role ───────────────────────────────────────────────────
            'email'              => $user->email,
            'role'               => $role,
            'accounting_type'    => $accountingType,   // ← NEW
            'email_verified_at'  => $user->email_verified_at,
            'is_active'          => $user->is_active,

            // ── Avatar ────────────────────────────────────────────────────────
            'avatar'          => $avatar,
            'profile_picture' => $user->profile_picture,

            // ── Student academic ──────────────────────────────────────────────
            'account_id'  => $user->account_id,
            'course'      => $user->course,
            'year_level'  => $user->year_level,
            'is_irregular'=> $user->is_irregular,
            'status'      => $user->status,

            // ── Contact ───────────────────────────────────────────────────────
            'birthday' => $user->birthday?->format('Y-m-d'),
            'phone'    => $user->phone,

            // ── Address ───────────────────────────────────────────────────────
            'address_house_lot_unit'    => $user->address_house_lot_unit,
            'address_street_name'       => $user->address_street_name,
            'address_barangay'          => $user->address_barangay,
            'address_municipality_city' => $user->address_municipality_city,
            'address_province'          => $user->address_province,
            'address_zip'               => $user->address_zip,

            // ── Guardian / Emergency ──────────────────────────────────────────
            'guardian_name'     => $user->guardian_name,
            'guardian_contact'  => $user->guardian_contact,
            'emergency_contact' => $user->emergency_contact,

            // ── Staff-only ────────────────────────────────────────────────────
            'faculty'    => $user->faculty,
            'department' => $user->department,

            // ── Registration queue badge counts (structured) ──────────────────
            // registrar_queue  → pending + needs_revision/registrar  (for Registrar badge)
            // finance_queue    → registrar_cleared + needs_revision/finance  (for DO badge)
            // Both are 0 for roles that do not own those queues.
            'registration_counts' => $this->resolveRegistrationCounts($user), // ← NEW
        ];
    }

    /**
     * Resolve per-role registration queue badge counts.
     * Replaces the old flat `pendingRegistrationsCount` prop.
     */
    private function resolveRegistrationCounts(User $user): array
    {
        $zero = ['registrar_queue' => 0, 'finance_queue' => 0];

        if (! $user->is_active) {
            return $zero;
        }

        if ($user->isAdmin()) {
            return [
                'registrar_queue' => $this->countRegistrarQueue(),
                'finance_queue'   => $this->countFinanceQueue(),
            ];
        }

        if ($user->isRegistrar()) {
            return [
                'registrar_queue' => $this->countRegistrarQueue(),
                'finance_queue'   => 0,
            ];
        }

        if ($user->isDisbursingOfficer()) {
            return [
                'registrar_queue' => 0,
                'finance_queue'   => $this->countFinanceQueue(),
            ];
        }

        return $zero;
    }

    private function countRegistrarQueue(): int
    {
        if (! Schema::hasTable('student_registrations')) {
            return 0;
        }

        return Cache::remember('registrar_queue_count', now()->addMinutes(5), function () {
            return StudentRegistration::query()
                ->where(function ($q) {
                    $q->where('status', 'pending')
                      ->orWhere(function ($inner) {
                          $inner->where('status', 'needs_revision')
                                ->where('revision_stage', 'registrar');
                      });
                })
                ->count();
        });
    }

    private function countFinanceQueue(): int
    {
        if (! Schema::hasTable('student_registrations')) {
            return 0;
        }

        return Cache::remember('finance_queue_count', now()->addMinutes(5), function () {
            return StudentRegistration::query()
                ->where(function ($q) {
                    $q->where('status', 'registrar_cleared')
                      ->orWhere(function ($inner) {
                          $inner->where('status', 'needs_revision')
                                ->where('revision_stage', 'finance');
                      });
                })
                ->count();
        });
    }

    private function resolveLatestAssessmentInfo(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || $user->role !== UserRoleEnum::STUDENT) return null;

        $cacheKey = "student_assessment_info:{$user->id}";
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $assessment = StudentAssessment::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first(['year_level', 'semester', 'school_year']);

            if (! $assessment) return null;

            return [
                'year_level'  => $assessment->year_level,
                'semester'    => $assessment->semester,
                'school_year' => $assessment->school_year,
            ];
        });
    }
}