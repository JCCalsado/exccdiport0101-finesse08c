<?php

use App\Http\Controllers\Accounting\CurriculumPresetController;
use App\Http\Controllers\Accounting\FinancialReportsController;
use App\Http\Controllers\Accounting\FeeSettingsController;
use App\Http\Controllers\Accounting\OtherChargeController;
use App\Http\Controllers\Accounting\PresetSubjectController;
use App\Http\Controllers\Accounting\SubjectController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\AccountingTransactionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReminderController;
use App\Http\Controllers\PaymentTermsController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\Student\OtherChargeController as StudentOtherChargeController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WorkflowApprovalController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Accounting\RegistrationApprovalController;

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::post('/webhook/paymongo', [PaymongoWebhookController::class, 'handle']);

Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/cancel',  [PaymentController::class, 'cancel'])->name('payment.cancel');

// ============================================
// AUTHENTICATED ROUTES
// ============================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/payments/bank-details', [PaymentController::class, 'getBankDetails'])->name('payment.bank-details');
});

// ============================================
// STUDENT-SPECIFIC ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/account', [StudentAccountController::class, 'index'])->name('student.account');
    Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payment/checkout', [PaymentController::class, 'createCheckout'])->name('payment.checkout');
    Route::post('/payment/bank-transfer', [PaymentController::class, 'submitBankTransfer'])->name('payment.bank-transfer');
    Route::post('reminders/{reminder}/read', [PaymentReminderController::class, 'markRead'])->name('reminders.read');
    Route::post('reminders/{reminder}/dismiss', [PaymentReminderController::class, 'dismiss'])->name('reminders.dismiss');
    Route::post('/account/pay-now', [TransactionController::class, 'payNow'])->name('account.pay-now');
    Route::get('/payment/{transaction}/proof', [PaymentController::class, 'showProofForm'])->name('payment.proof.show');
    Route::post('/payment/{transaction}/proof', [PaymentController::class, 'uploadProof'])->name('payment.proof.upload');
    Route::delete('/payment/{transaction}/proof/cancel', [PaymentController::class, 'cancelAbandonedProof'])->name('payment.proof.cancel');
    Route::get('/notifications', [NotificationController::class, 'studentIndex'])->name('student.notifications');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('student.notifications.mark-all-read');
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');

    // ── Other Charges — Student portal ───────────────────────────────────────
    Route::get('/other-charges', [StudentOtherChargeController::class, 'index'])->name('student.other-charges.index');
    Route::post('/other-charges/{otherCharge}/pay', [StudentOtherChargeController::class, 'initiatePayment'])->name('student.other-charges.pay');
    Route::post('/other-charges/{otherCharge}/bank-transfer', [StudentOtherChargeController::class, 'initiateBankTransfer'])->name('student.other-charges.bank-transfer');
    Route::get('/other-charges/payments/{otherChargePayment}/proof', [StudentOtherChargeController::class, 'showProofForm'])->name('student.other-charges.proof.show');
    Route::post('/other-charges/payments/{otherChargePayment}/proof', [StudentOtherChargeController::class, 'uploadProof'])->name('student.other-charges.proof.upload');
    Route::delete('/other-charges/payments/{otherChargePayment}/proof', [StudentOtherChargeController::class, 'cancelAwaitingProof'])->name('student.other-charges.proof.cancel');
});

// ============================================
// STUDENT FEE MANAGEMENT ROUTES
// ============================================

// ── Shared read: Admin + Accounting ──────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:admin,accounting'])
    ->prefix('student-fees')
    ->name('student-fees.')
    ->group(function () {
        Route::get('/', [StudentFeeController::class, 'index'])->name('index');
        Route::get('/search', [StudentFeeController::class, 'search'])->name('search');
        Route::get('/latest-assessment', [StudentFeeController::class, 'getLatestAssessmentData'])->name('latest-assessment');
        Route::get('/{userId}/export-pdf', [StudentFeeController::class, 'exportPdf'])->whereNumber('userId')->name('export-pdf');
        Route::get('/{userId}', [StudentFeeController::class, 'show'])->whereNumber('userId')->name('show');
    });

// ── Write / create — Accounting only (Policy restricts to Disbursing Officer) ──
// Cashier: recordPayment only. Disbursing Officer: all write actions.
// Note: route middleware allows all accounting sub-types; Policy enforces sub-role.
// TODO: Add $this->authorize() to StudentFeeController (see session handoff).
Route::middleware(['auth', 'verified', 'role:accounting'])
    ->prefix('student-fees')
    ->name('student-fees.')
    ->group(function () {
        Route::get('/curriculum-units', [StudentFeeController::class, 'getCurriculumUnits'])->name('curriculum-units');
        Route::get('/subjects/search', [StudentFeeController::class, 'subjectSearch'])->name('subject-search');
        Route::get('/create', [StudentFeeController::class, 'create'])->name('create');
        Route::post('/', [StudentFeeController::class, 'store'])->name('store');
        Route::get('/create-student', [StudentFeeController::class, 'createStudent'])->name('create-student');
        Route::post('/store-student', [StudentFeeController::class, 'storeStudent'])->name('store-student');
        Route::post('/{userId}/payments', [StudentFeeController::class, 'storePayment'])->whereNumber('userId')->name('payments.store');
        Route::post('/{user}/drop', [StudentFeeController::class, 'drop'])->whereNumber('user')->name('drop');
        Route::get('/{userId}/edit', [StudentFeeController::class, 'edit'])->whereNumber('userId')->name('edit');
        Route::put('/{userId}', [StudentFeeController::class, 'update'])->whereNumber('userId')->name('update');
        Route::get('/{student}/edit-student', [StudentFeeController::class, 'editStudent'])->name('edit-student');
        Route::put('/{student}/update-student', [StudentFeeController::class, 'updateStudent'])->name('update-student');
    });

// ============================================
// TRANSACTION ROUTES
// ============================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/download', [TransactionController::class, 'download'])->name('transactions.download');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
});

Route::middleware(['auth', 'verified', 'role:accounting'])->group(function () {
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
});

// ============================================
// ADMIN ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('users', [AdminController::class, 'index'])->name('users.index');
    Route::get('users/create', [AdminController::class, 'create'])->name('users.create');
    Route::post('users', [AdminController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [AdminController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [AdminController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [AdminController::class, 'update'])->name('users.update');
    Route::post('users/{user}/deactivate', [AdminController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/reactivate', [AdminController::class, 'reactivate'])->name('users.reactivate');

    Route::get('notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('admin.notifications.show');
    Route::post('notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('admin.notifications.dismiss');

    Route::get('/payment-terms', [PaymentTermsController::class, 'index'])->name('admin.payment-terms.index');
});

// ============================================
// STUDENT ARCHIVE ROUTES (Admin view-only)
// ============================================
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('students-archive', [StudentController::class, 'archive'])->name('students.archive');
    Route::post('students/{student}/reinstate', [StudentController::class, 'reinstate'])->name('students.reinstate');
    Route::get('students/{student}/workflow-history', [StudentController::class, 'workflowHistory'])->name('students.workflow-history');
});

// ============================================
// ACCOUNTING ROUTES
// Role: accounting + admin — sub-role restrictions enforced by Policies.
// TODO (next session): Add $this->authorize() to FeeSettingsController,
//       FinancialReportsController, and WorkflowApprovalController.
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->prefix('accounting')->group(function () {
    Route::get('/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('accounting.transactions.index');

    // Financial Reports — Policy: bookkeeper + disbursing_officer + admin
    Route::get('/financial-reports', [FinancialReportsController::class, 'index'])->name('accounting.financial-reports');
    Route::get('/financial-reports/export', [FinancialReportsController::class, 'export'])->name('accounting.financial-reports.export');
    Route::get('/financial-reports/export-assessments', [FinancialReportsController::class, 'exportAssessments'])->name('accounting.financial-reports.export-assessments');
    Route::get('/financial-reports/export-receipts', [FinancialReportsController::class, 'exportReceipts'])->name('accounting.financial-reports.export-receipts');
    Route::get('/financial-reports/export-yearly', [FinancialReportsController::class, 'exportYearly'])->name('accounting.financial-reports.export-yearly');
    Route::get('/financial-reports/student-history', [FinancialReportsController::class, 'studentTransactionHistory'])->name('accounting.financial-reports.student-history');
    Route::get('/financial-reports/student-receipt', [FinancialReportsController::class, 'downloadStudentReceipt'])->name('accounting.financial-reports.student-receipt');

    // Fee Settings — Policy: disbursing_officer + admin
    Route::get('/fee-settings', [FeeSettingsController::class, 'index'])->name('accounting.fee-settings.index');
    Route::patch('/fee-settings/{feeSetting}', [FeeSettingsController::class, 'update'])->name('accounting.fee-settings.update');
    Route::post('/fee-settings/bulk', [FeeSettingsController::class, 'bulkUpdate'])->name('accounting.fee-settings.bulk');
    Route::post('/fee-settings', [FeeSettingsController::class, 'store'])->name('accounting.fee-settings.store');
    Route::delete('/fee-settings/{feeSetting}', [FeeSettingsController::class, 'destroy'])->name('accounting.fee-settings.destroy');

    // Preset Subject sub-resource (legacy deep links from FeeSettings)
    Route::prefix('fee-settings/presets/{preset}/subjects')
        ->name('accounting.fee-settings.preset-subjects.')
        ->group(function () {
            Route::get('/',                   [PresetSubjectController::class, 'index'])  ->name('index');
            Route::post('/',                  [PresetSubjectController::class, 'store'])  ->name('store');
            Route::delete('/{presetSubject}', [PresetSubjectController::class, 'destroy'])->name('destroy');
            Route::post('/sync',              [PresetSubjectController::class, 'sync'])   ->name('sync');
        });

    Route::post('/payment-terms/{paymentTerm}/due-date', [PaymentTermsController::class, 'updateDueDate'])->name('admin.payment-terms.update-due-date');
    Route::post('/payment-terms/bulk-due-date', [PaymentTermsController::class, 'bulkUpdateDueDate'])->name('admin.payment-terms.bulk-due-date');

    // ── Other Charges — Accounting management ─────────────────────────────────
    // Policy (OtherChargePolicy) enforces sub-role access:
    //   viewAny/view  → disbursing_officer + cashier + bookkeeper + admin
    //   create/update → disbursing_officer + admin
    //   recordPayment → disbursing_officer + cashier + admin
    Route::prefix('other-charges')
        ->name('accounting.other-charges.')
        ->group(function () {
            Route::get('/',                          [OtherChargeController::class, 'index'])         ->name('index');
            Route::get('/create',                    [OtherChargeController::class, 'create'])        ->name('create');
            Route::post('/',                         [OtherChargeController::class, 'store'])         ->name('store');
            Route::get('/preview-count',             [OtherChargeController::class, 'previewCount'])  ->name('preview-count');
            Route::get('/{otherCharge}',             [OtherChargeController::class, 'show'])          ->name('show');
            Route::get('/{otherCharge}/edit',        [OtherChargeController::class, 'edit'])          ->name('edit');
            Route::put('/{otherCharge}',             [OtherChargeController::class, 'update'])        ->name('update');
            Route::delete('/{otherCharge}',          [OtherChargeController::class, 'destroy'])       ->name('destroy');
            Route::post('/{otherCharge}/publish',    [OtherChargeController::class, 'publish'])       ->name('publish');
            Route::post('/{otherCharge}/payments',   [OtherChargeController::class, 'recordPayment']) ->name('payments.store');
            Route::post('/payments/{otherChargePayment}/approve', [OtherChargeController::class, 'approvePayment'])->name('payments.approve');
            Route::post('/payments/{otherChargePayment}/reject', [OtherChargeController::class, 'rejectPayment'])->name('payments.reject');
            Route::get('/payments/{otherChargePayment}/proof/serve', [OtherChargeController::class, 'serveProof'])->name('payments.proof.serve');
        });
});

// ============================================
// CURRICULUM PRESETS + SUBJECTS
// Role: accounting + admin + registrar
// Policy:
//   - Read  → disbursing_officer + registrar + admin
//   - Write → registrar + admin
// These stay at /accounting/* URLs to avoid breaking existing links.
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin,registrar'])
    ->prefix('accounting')
    ->group(function () {
        // Curriculum Presets
        Route::prefix('curriculum-presets')
            ->name('accounting.curriculum-presets.')
            ->group(function () {
                Route::get('/',            [CurriculumPresetController::class, 'index'])  ->name('index');
                Route::post('/',           [CurriculumPresetController::class, 'store'])  ->name('store');
                Route::patch('/{preset}',  [CurriculumPresetController::class, 'update']) ->name('update');
                Route::delete('/{preset}', [CurriculumPresetController::class, 'destroy'])->name('destroy');

                Route::get('/{preset}/subjects',                    [PresetSubjectController::class, 'curriculumIndex'])  ->name('subjects.index');
                Route::post('/{preset}/subjects',                   [PresetSubjectController::class, 'store'])            ->name('subjects.store');
                Route::delete('/{preset}/subjects/{presetSubject}', [PresetSubjectController::class, 'destroy'])          ->name('subjects.destroy');
                Route::post('/{preset}/subjects/sync',              [PresetSubjectController::class, 'sync'])             ->name('subjects.sync');
            });

        // Subjects
        Route::prefix('subjects')
            ->name('accounting.subjects.')
            ->group(function () {
                Route::get('/',                    [SubjectController::class, 'index'])        ->name('index');
                Route::get('/create',              [SubjectController::class, 'create'])       ->name('create');
                Route::post('/',                   [SubjectController::class, 'store'])        ->name('store');
                Route::get('/{subject}/edit',      [SubjectController::class, 'edit'])         ->name('edit');
                Route::put('/{subject}',           [SubjectController::class, 'update'])       ->name('update');
                Route::delete('/{subject}',        [SubjectController::class, 'destroy'])      ->name('destroy');
                Route::patch('/{subject}/inline',  [SubjectController::class, 'inlineUpdate']) ->name('inline-update');
            });
    });

// ============================================
// NOTIFICATIONS MANAGEMENT
// Role: registrar + admin (Registrar owns creation and management)
// These stay at /accounting/* URLs to avoid breaking existing links.
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin,registrar'])
    ->prefix('accounting')
    ->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('accounting.notifications.index');
        Route::get('notifications/create', [NotificationController::class, 'create'])->name('accounting.notifications.create');
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('accounting.notifications.show');
        Route::get('notifications/{notification}/edit', [NotificationController::class, 'edit'])->name('accounting.notifications.edit');
        Route::post('notifications', [NotificationController::class, 'store'])->name('accounting.notifications.store');
        Route::put('notifications/{notification}', [NotificationController::class, 'update'])->name('accounting.notifications.update');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('accounting.notifications.destroy');
    });

// ============================================
// ACCOUNTING TRANSACTION WORKFLOW ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting'])->prefix('accounting-workflows')->group(function () {
    Route::get('/', [AccountingTransactionController::class, 'index'])->name('accounting-workflows.index');
    Route::get('/create', [AccountingTransactionController::class, 'create'])->name('accounting-workflows.create');
    Route::post('/', [AccountingTransactionController::class, 'store'])->name('accounting-workflows.store');
    Route::get('/{transaction}', [AccountingTransactionController::class, 'show'])->name('accounting-workflows.show');
    Route::put('/{transaction}', [AccountingTransactionController::class, 'update'])->name('accounting-workflows.update');
    Route::delete('/{transaction}', [AccountingTransactionController::class, 'destroy'])->name('accounting-workflows.destroy');
    Route::post('/{transaction}/submit', [AccountingTransactionController::class, 'submitForApproval'])->name('accounting-workflows.submit');
});

// ============================================
// WORKFLOW MANAGEMENT ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting'])->group(function () {
    Route::resource('workflows', WorkflowController::class);
});

// ============================================
// PAYMENT APPROVAL ROUTES
// Role: accounting (route-level). Policy: disbursing_officer + admin.
// TODO (next session): Add $this->authorize() to WorkflowApprovalController.
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->group(function () {
    Route::get('/approvals', [WorkflowApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [WorkflowApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{approval}/approve', [WorkflowApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [WorkflowApprovalController::class, 'reject'])->name('approvals.reject');
});

// ============================================
// PROOF OF PAYMENT FILE SERVING
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->group(function () {
    Route::get('/payment/{transaction}/proof/serve', [PaymentController::class, 'serveProof'])->name('payment.proof.serve');
});

// ============================================
// REGISTRATION APPROVAL ROUTES — FINANCE STAGE
// Role: accounting + admin. Policy: disbursing_officer + admin.
// Shows: registrar_cleared queue only (records the Registrar has cleared).
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin'])
    ->prefix('accounting/registrations')
    ->name('accounting.registrations.')
    ->group(function () {
        Route::get('/',                                 [RegistrationApprovalController::class, 'index'])          ->name('index');
        Route::get('/{registration}',                   [RegistrationApprovalController::class, 'show'])           ->name('show');
        Route::post('/{registration}/approve',          [RegistrationApprovalController::class, 'approve'])        ->name('approve');
        Route::post('/{registration}/reject',           [RegistrationApprovalController::class, 'reject'])         ->name('reject');
        Route::post('/{registration}/request-revision', [RegistrationApprovalController::class, 'requestRevision'])->name('request-revision');
        Route::get('/{registration}/documents/{type}',  [RegistrationApprovalController::class, 'serveDocument'])  ->name('document');
    });

// ============================================
// REGISTRAR ROUTES
// Role: registrar + admin.
// Shows: pending queue (academic-review stage).
// ============================================
Route::middleware(['auth', 'verified', 'role:registrar,admin'])
    ->prefix('registrar')
    ->name('registrar.')
    ->group(function () {
        Route::get('/dashboard', [RegistrarController::class, 'dashboard'])->name('dashboard');

        Route::prefix('registrations')
            ->name('registrations.')
            ->group(function () {
                Route::get('/',                                 [RegistrarController::class, 'registrationIndex'])          ->name('index');
                Route::get('/{registration}',                   [RegistrarController::class, 'registrationShow'])           ->name('show');
                Route::post('/{registration}/approve',          [RegistrarController::class, 'registrationApprove'])        ->name('approve');
                Route::post('/{registration}/reject',           [RegistrarController::class, 'registrationReject'])         ->name('reject');
                Route::post('/{registration}/request-revision', [RegistrarController::class, 'registrationRequestRevision'])->name('request-revision');
                Route::get('/{registration}/documents/{type}',  [RegistrarController::class, 'serveDocument'])              ->name('document');
            });
    });

// ============================================
// DEBUG / LOCAL ONLY
// ============================================
if (app()->environment(['local', 'staging'])) {
    Route::get('/test-resend', function () {
        \Illuminate\Support\Facades\Notification::route('mail', 'ryuzakikamisama@gmail.com')
            ->notify(new \App\Notifications\TestNotification());
        return response()->json([
            'status'   => 'sent',
            'mailer'   => config('mail.default'),
            'from'     => config('mail.from.address'),
            'to'       => 'ryuzakikamisama@gmail.com',
            'env'      => app()->environment(),
        ]);
    })->name('test.resend');
}

if (app()->environment('local')) {
    Route::middleware('auth')->get('/debug/csrf-token', [\App\Http\Controllers\Debug\DebugController::class, 'csrfToken']);
}

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
