<?php

namespace App\Providers;

use App\Models\CurriculumPreset;
use App\Models\FeeSetting;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\StudentRegistration;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Policies\CurriculumPresetPolicy;
use App\Policies\FeeManagementPolicy;
use App\Policies\FinancialReportPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\StudentFeePolicy;
use App\Policies\StudentPaymentTermPolicy;
use App\Policies\StudentRegistrationPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkflowApprovalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class                => UserPolicy::class,
        Notification::class        => NotificationPolicy::class,
        WorkflowApproval::class    => WorkflowApprovalPolicy::class,
        StudentPaymentTerm::class  => StudentPaymentTermPolicy::class,
        Payment::class             => PaymentPolicy::class,
        StudentRegistration::class => StudentRegistrationPolicy::class,
        CurriculumPreset::class    => CurriculumPresetPolicy::class,
        Subject::class             => SubjectPolicy::class,

        // Bound so `$this->authorize('manageSystemFees', FeeSetting::class)` works.
        // FeeManagementPolicy methods take only User — no model instance required.
        FeeSetting::class          => FeeManagementPolicy::class,

        // Bound so `$this->authorize('create', StudentAssessment::class)` works.
        // StudentFeePolicy methods take only User — the model class is just a resolver key.
        StudentAssessment::class   => StudentFeePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // FinancialReportPolicy has no natural model (reports are aggregations).
        // Register as named Gate abilities so controllers call `$this->authorize('viewFinancialReports')`.
        Gate::define('viewFinancialReports',  [FinancialReportPolicy::class, 'viewAny']);
        Gate::define('exportFinancialReports', [FinancialReportPolicy::class, 'export']);

        Route::bind('student', function ($value) {
            return Student::withTrashed()->findOrFail($value);
        });
    }
}