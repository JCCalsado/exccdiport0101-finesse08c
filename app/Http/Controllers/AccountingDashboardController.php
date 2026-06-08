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
    public function index(): Response
    {
        $currentYear = now()->year;
        $month       = now()->month;

        $currentSemester = match (true) {
            $month >= 6 && $month <= 10 => '1st',
            $month >= 11 || $month <= 3 => '2nd',
            default                     => 'Summer',
        };

        // ── Financial aggregates ──────────────────────────────────────────────
        // Total assessed = sum of all active assessment totals.
        // No longer derived from kind='charge' Transaction rows — those are gone.
        $totalCharges  = (float) StudentAssessment::where('status', 'active')->sum('total_assessment');
        $totalPayments = (float) Transaction::where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $collectionRate = $totalCharges > 0
            ? round(($totalPayments / $totalCharges) * 100, 2)
            : 0;

        // ── Students with outstanding balance ──────────────────────────────────
        // Single query with a DB join aggregate — replaces two separate
        // collection-in-PHP queries (old Q5 and Q8 both ran the same filter).
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

        // Total outstanding: DB-level sum — no PHP collection needed.
        $totalPending = (float) DB::table('accounts')
            ->join('users', 'users.id', '=', 'accounts.user_id')
            ->where('users.role', 'student')
            ->where('accounts.balance', '>', 0)
            ->sum('accounts.balance');

        // ── Assessment stats ───────────────────────────────────────────────────
        $assessmentStats = StudentAssessment::select(
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_assessment) as total_amount')
            )
            ->whereIn('status', ['active', 'pending'])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $activeAssessmentCount  = (int) ($assessmentStats['active']?->count  ?? 0);
        $activeAssessmentAmount = (float) ($assessmentStats['active']?->total_amount ?? 0);
        $pendingAssessmentCount = (int) ($assessmentStats['pending']?->count ?? 0);

        $recentAssessmentsCount = StudentAssessment::where('created_at', '>=', now()->subDays(30))->count();

        // ── Pending approvals ──────────────────────────────────────────────────
        $pendingApprovals = WorkflowApproval::where('status', 'pending')
            ->whereHas('workflowInstance.workflow', fn ($q) => $q->where('type', 'payment_approval'))
            ->count();

        // ── Recent payments ────────────────────────────────────────────────────
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

        // ── Payment trends — last 6 months ────────────────────────────────────
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

        // ── Payment by channel ────────────────────────────────────────────────
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

        // ── Students by year level ─────────────────────────────────────────────
        $studentsByYearLevel = User::students()
            ->where('status', User::STATUS_ACTIVE)
            ->select('year_level', DB::raw('COUNT(*) as count'))
            ->groupBy('year_level')
            ->orderBy('year_level')
            ->get();

        // ── Recent payment amount (last 30 days) ──────────────────────────────
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
                'semester' => $currentSemester,
            ],

            // pending_assessments_count is an INTEGER COUNT — not a currency amount.
            // The Vue template previously passed this through formatCurrency() by mistake.
            'studentFeeStats' => [
                'total_assessments'        => $activeAssessmentCount,
                'total_assessment_amount'  => $activeAssessmentAmount,
                'pending_assessments_count' => $pendingAssessmentCount,   // renamed key — was pending_assessments
                'recent_assessments'       => $recentAssessmentsCount,
                'recent_payments_amount'   => $recentPaymentsAmount,
            ],
        ]);
    }
}