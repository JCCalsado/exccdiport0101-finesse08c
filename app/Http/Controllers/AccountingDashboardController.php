<?php

namespace App\Http\Controllers;

use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkflowApproval;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDashboardController extends Controller
{
    /**
     * Accounting dashboard — rendered for all accounting sub-roles and admin.
     *
     * Sub-role query scoping (Item 7 fix):
     *
     *   Disbursing Officer → full dataset (they manage the complete workflow)
     *   Cashier            → student balances + recent payments (they process collections)
     *   Bookkeeper         → financial summaries + trends + method breakdown (they analyse)
     *   Admin              → full dataset (super-user access)
     *
     * The Vue (Accounting/Dashboard.vue) already uses `isDO`, `isCashier`, `isBookkeeper`
     * computed from auth.user.accounting_type to conditionally render sections.
     * This controller aligns the server-side data with those display decisions so
     * Cashiers and Bookkeepers don't receive data blobs they'll never render.
     *
     * IMPORTANT: This is not a security boundary — the route middleware
     * (role:accounting,admin) is. This is a data-hygiene and performance concern.
     * Inertia serialises all props to JSON in the page response; a Cashier could
     * inspect source and see pending approval counts if we don't scope them out.
     *
     * ── SEMESTER INVARIANT ────────────────────────────────────────────────────
     * student_assessments.semester stores '1st Sem' / '2nd Sem' / 'Summer'.
     * AssessmentService::normalizeSemester() maps '1st' → '1st Sem' before
     * every DB write. currentSemester here must match that canonical format
     * exactly — never '1st' or '2nd' without the ' Sem' suffix.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function index(): Response
    {
        $user        = auth()->user();
        $currentYear = now()->year;
        $month       = now()->month;

        // ── CORRECT: matches student_assessments.semester canonical format ────
        $currentSemester = match (true) {
            $month >= 6 && $month <= 10 => '1st Sem',
            $month >= 11 || $month <= 3 => '2nd Sem',
            default                     => 'Summer',
        };

        // Determine which data buckets this sub-role needs.
        $isAdmin = $user->isAdmin();
        $isDO    = $isAdmin || $user->isDisbursingOfficer();
        $isCash  = $isAdmin || $user->isCashier();
        $isBook  = $isAdmin || $user->isBookkeeper();

        // ── Core financial aggregates (all sub-roles) ─────────────────────────
        $totalCharges  = (float) StudentAssessment::where('status', 'active')->sum('total_assessment');
        $totalPayments = (float) Transaction::where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $collectionRate = $totalCharges > 0
            ? round(($totalPayments / $totalCharges) * 100, 2)
            : 0;

        // ── Student balance data (DO + Cashier only) ──────────────────────────
        $studentsWithBalance = [];
        $totalPending        = 0.0;

        if ($isDO || $isCash) {
            $studentsWithBalance = User::students()
                ->join('accounts', 'accounts.user_id', '=', 'users.id')
                ->where('accounts.balance', '>', 0)
                ->orderByDesc('accounts.balance')
                ->limit(10)
                ->get(['users.id', 'users.last_name', 'users.first_name', 'users.middle_initial',
                       'users.email', 'users.account_id', 'users.course', 'users.year_level',
                       'accounts.balance'])
                ->map(fn ($u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'email'      => $u->email,
                    'account_id' => $u->account_id,
                    'course'     => $u->course,
                    'year_level' => $u->year_level,
                    'balance'    => abs((float) $u->balance),
                ]);

            $totalPending = (float) DB::table('accounts')
                ->join('users', 'users.id', '=', 'accounts.user_id')
                ->where('users.role', 'student')
                ->where('accounts.balance', '>', 0)
                ->sum('accounts.balance');
        }

        // ── Assessment stats (all sub-roles) ──────────────────────────────────
        $assessmentStats = StudentAssessment::select(
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_assessment) as total_amount')
            )
            ->whereIn('status', ['active', 'pending'])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $activeAssessmentCount  = (int)   ($assessmentStats['active']?->count       ?? 0);
        $activeAssessmentAmount = (float)  ($assessmentStats['active']?->total_amount ?? 0);
        $pendingAssessmentCount = (int)   ($assessmentStats['pending']?->count       ?? 0);
        $recentAssessmentsCount = StudentAssessment::where('created_at', '>=', now()->subDays(30))->count();

        // ── Pending approvals (Disbursing Officer + Admin only) ───────────────
        $pendingApprovals = 0;
        if ($isDO) {
            $pendingApprovals = WorkflowApproval::where('status', 'pending')
                ->whereHas('workflowInstance.workflow', fn ($q) => $q->where('type', 'payment_approval'))
                ->count();
        }

        // ── Recent payments (DO + Cashier) ────────────────────────────────────
        $recentPayments = [];
        if ($isDO || $isCash) {
            $recentPayments = Transaction::where('kind', 'payment')
                ->where('status', 'paid')
                ->with('user:id,last_name,first_name,middle_initial')
                ->orderByDesc('paid_at')
                ->limit(10)
                ->get()
                ->map(fn ($t) => [
                    'id'           => $t->id,
                    'reference'    => $t->reference,
                    'student_name' => $t->user?->name ?? 'N/A',
                    'amount'       => (float) $t->amount,
                    'status'       => $t->status,
                    'paid_at'      => $t->paid_at,
                    'created_at'   => $t->created_at,
                ]);
        }

        // ── Payment trends — last 6 months (all sub-roles) ───────────────────
        $paymentTrends = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ── Payment by channel (all sub-roles) ───────────────────────────────
        $paymentByMethod = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->whereNotNull('payment_channel')
            ->select(
                'payment_channel as method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('payment_channel')
            ->orderByDesc('total')
            ->get();

        // ── Students by year level (all sub-roles) ───────────────────────────
        $studentsByYearLevel = User::students()
            ->where('status', User::STATUS_ACTIVE)
            ->select('year_level', DB::raw('COUNT(*) as count'))
            ->groupBy('year_level')
            ->orderBy('year_level')
            ->get();

        // ── Recent payment amount — last 30 days (all sub-roles) ─────────────
        $recentPaymentsAmount = (float) Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        return Inertia::render('Accounting/Dashboard', [
            'stats' => [
                'total_students'    => User::students()->count(),
                'active_students'   => User::students()->where('status', User::STATUS_ACTIVE)->count(),
                'total_charges'     => $totalCharges,
                'total_payments'    => $totalPayments,
                'total_pending'     => $totalPending,
                'collection_rate'   => $collectionRate,
                'active_fees'       => $activeAssessmentCount,
                'total_fee_amount'  => $activeAssessmentAmount,
                'pending_approvals' => $pendingApprovals,
            ],

            'studentsWithBalance' => $studentsWithBalance,
            'recentPayments'      => $recentPayments,
            'paymentTrends'       => $paymentTrends,
            'paymentByMethod'     => $paymentByMethod,
            'studentsByYearLevel' => $studentsByYearLevel,

            'currentTerm' => [
                'year'     => $currentYear,
                // '1st Sem' | '2nd Sem' | 'Summer' — matches student_assessments.semester exactly.
                // AssessmentService::normalizeSemester() is the canonical writer for that column.
                'semester' => $currentSemester,
            ],

            'studentFeeStats' => [
                'total_assessments'         => $activeAssessmentCount,
                'total_assessment_amount'   => $activeAssessmentAmount,
                'pending_assessments_count' => $pendingAssessmentCount,
                'recent_assessments'        => $recentAssessmentsCount,
                'recent_payments_amount'    => $recentPaymentsAmount,
            ],
        ]);
    }
}
