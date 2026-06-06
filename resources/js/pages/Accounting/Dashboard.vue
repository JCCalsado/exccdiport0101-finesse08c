<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const { formatCurrency } = useDataFormatting();

type Stats = {
    total_students: number;
    active_students: number;
    total_charges: number;
    total_payments: number;
    total_pending: number;
    collection_rate: number;
    active_fees: number;
    total_fee_amount: number;
};

type Student = {
    id: number;
    name: string;
    email: string;
    account_id: string;
    course: string;
    year_level: string;
    balance: number;
};

type Payment = {
    id: number;
    reference: string;
    student_name: string;
    amount: number;
    status: string;
    paid_at: string;
    created_at: string;
};

type PaymentTrend = {
    month: string;
    total: number;
    count: number;
};

type PaymentMethod = {
    method: string;
    count: number;
    total: number;
};

type YearLevel = {
    year_level: string;
    count: number;
};

type StudentFeeStats = {
    total_assessments: number;
    total_assessment_amount: number;
    pending_assessments_count: number; // integer count — NOT a currency amount
    recent_assessments: number;
    recent_payments_amount: number;
};

const props = defineProps<{
    stats: Stats;
    studentsWithBalance: Student[];
    recentPayments: Payment[];
    paymentTrends: PaymentTrend[];
    paymentByMethod: PaymentMethod[];
    studentsByYearLevel: YearLevel[];
    currentTerm: {
        year: number;
        semester: string;
    };
    studentFeeStats?: StudentFeeStats;
}>();

const page = usePage();
const accountingType = computed(() => page.props.auth?.user?.accounting_type as string | null);

const isDO = computed(() => accountingType.value === 'disbursing_officer');
const isCashier = computed(() => accountingType.value === 'cashier');
const isBookkeeper = computed(() => accountingType.value === 'bookkeeper');

const breadcrumbs = [{ title: 'Dashboard', href: route('dashboard') }, { title: 'Accounting Dashboard' }];

const activeTab = ref<'overview' | 'payments' | 'balances'>('overview');

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatMonth = (month: string) => {
    const [year, monthNum] = month.split('-');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
};

const getCollectionRateColor = (rate: number) => {
    if (rate >= 80) return 'text-green-600';
    if (rate >= 60) return 'text-yellow-600';
    return 'text-red-600';
};

const getTrendIcon = (index: number) => {
    if (index === 0) return null;
    const current = props.paymentTrends[index]?.total || 0;
    const previous = props.paymentTrends[index - 1]?.total || 0;
    return current > previous ? ArrowUpRight : ArrowDownRight;
};

const getTrendColor = (index: number) => {
    if (index === 0) return '';
    const current = props.paymentTrends[index]?.total || 0;
    const previous = props.paymentTrends[index - 1]?.total || 0;
    return current > previous ? 'text-green-600' : 'text-red-600';
};

// Map snake_case controller data to expected format
const statsFormatted = computed(() => ({
    totalStudents: props.stats.total_students ?? 0,
    activeStudents: props.stats.active_students ?? 0,
    totalCollections: props.stats.total_payments ?? 0,
    pendingPayments: props.stats.total_pending ?? 0,
    collectionRate: props.stats.collection_rate ?? 0,
}));

const feeStats = computed(() => ({
    totalAssessments: props.studentFeeStats?.total_assessments || 0,
    totalAssessmentAmount: props.studentFeeStats?.total_assessment_amount || 0,
    pendingAssessments: props.studentFeeStats?.pending_assessments_count || 0,
    recentPayments: props.studentFeeStats?.recent_payments_amount || 0,
}));

// Format recent payments with computed date field
const recentPaymentsFormatted = computed(() =>
    (props.recentPayments || []).map(p => ({
        ...p,
        date: formatDate(p.paid_at || p.created_at),
    }))
);

// Chart data from payment trends
const chartData = computed(() => {
    const trends = props.paymentTrends || [];
    if (trends.length === 0) return null;

    const maxTotal = Math.max(...trends.map(t => t.total), 1);
    return trends.map(t => ({
        label: formatMonth(t.month),
        amount: t.total,
        percentage: (t.total / maxTotal) * 100,
    }));
});

// Outstanding balances from students with balance
const outstandingBalances = computed(() =>
    (props.studentsWithBalance || []).map(s => ({
        student_id: s.id,
        student_name: s.name,
        account_id: s.account_id,
        year_level: s.year_level,
        balance: s.balance,
    }))
);

const refreshData = () => {
    router.reload({ only: ['stats', 'recentPayments', 'studentsWithBalance', 'studentFeeStats'] });
};

const viewStudent = (studentId: number) => {
    router.visit(route('students.show', studentId));
};
</script>

<template>
    <AppLayout>
        <Head title="Accounting Dashboard" />

        <div class="w-full space-y-5 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Page header -->
            <div class="ccdi-page-header">
                <div>
                    <h1 class="ccdi-section-title">Accounting Dashboard</h1>
                    <p class="ccdi-section-desc">{{ props.currentTerm.year }} — {{ props.currentTerm.semester }} | Financial overview and payment tracking</p>
                </div>
                <button @click="refreshData" class="ccdi-btn-secondary gap-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Refresh
                </button>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="ccdi-stat-card">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total Students</p>
                        <p class="text-xl font-bold text-foreground">{{ statsFormatted.totalStudents }}</p>
                        <p class="text-xs text-muted-foreground">{{ statsFormatted.activeStudents }} active</p>
                    </div>
                </div>
                <div class="ccdi-stat-card">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total Collections</p>
                        <p class="text-xl font-bold text-emerald-600">₱{{ Number(statsFormatted.totalCollections).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">All-time</p>
                    </div>
                </div>
                <div class="ccdi-stat-card">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pending Payments</p>
                        <p class="text-xl font-bold text-red-600">₱{{ Number(statsFormatted.pendingPayments).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">Outstanding</p>
                    </div>
                </div>
                <div class="ccdi-stat-card">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Collection Rate</p>
                        <p class="text-xl font-bold" :class="Number(statsFormatted.collectionRate) >= 50 ? 'text-emerald-600' : 'text-red-600'">{{ Number(statsFormatted.collectionRate).toFixed(2) }}%</p>
                        <p class="text-xs text-muted-foreground">Overall efficiency</p>
                    </div>
                </div>
            </div>

            <!-- Fee Management + Quick actions -->
            <div v-if="isDO || isCashier" class="ccdi-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Student Fee Management</h2>
                        <p class="text-xs text-muted-foreground">Assessment and fee tracking overview</p>
                    </div>
                    <Link :href="route('student-fees.index')" class="ccdi-btn-primary text-xs px-3 py-1.5">Manage Assessments</Link>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Total Assessments</p>
                        <p class="text-2xl font-bold text-foreground">{{ feeStats.totalAssessments }}</p>
                        <p class="text-xs text-blue-600">Active enrollments</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Total Assessment</p>
                        <p class="text-2xl font-bold text-blue-600">₱{{ Number(feeStats.totalAssessmentAmount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">Current term</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Pending Assessments</p>
                        <p class="text-2xl font-bold" :class="feeStats.pendingAssessments > 0 ? 'text-amber-600' : 'text-foreground'">{{ feeStats.pendingAssessments }}</p>
                        <p class="text-xs text-muted-foreground">Outstanding</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Recent Payments</p>
                        <p class="text-2xl font-bold text-emerald-600">₱{{ Number(feeStats.recentPayments).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">Last 30 days</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Link v-if="isDO" :href="route('student-fees.create')" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-all hover:border-blue-300 hover:bg-blue-50">
                        <div><p class="text-sm font-medium text-foreground">Create Assessment</p><p class="text-xs text-muted-foreground">New student fee</p></div>
                    </Link>
                    <Link :href="route('student-fees.index')" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-all hover:border-blue-300 hover:bg-blue-50">
                        <div><p class="text-sm font-medium text-foreground">View All Students</p><p class="text-xs text-muted-foreground">Manage fees</p></div>
                    </Link>
                    <Link v-if="isDO" :href="route('approvals.index')" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-all hover:border-red-200 hover:bg-red-50">
                        <div><p class="text-sm font-medium text-foreground">Payment Approvals</p><p class="text-xs text-muted-foreground">View pending</p></div>
                    </Link>
                </div>
            </div>

            <!-- Tabs: Overview / Recent Payments / Outstanding -->
            <div v-if="isDO || isCashier" class="ccdi-card overflow-hidden">
                <div class="flex border-b border-border bg-muted/20">
                    <button @click="activeTab = 'overview'" class="px-5 py-3 text-sm font-medium transition-all" :class="activeTab === 'overview' ? 'border-b-2 border-blue-600 text-blue-700 bg-card' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'">
                        Overview
                    </button>
                    <button @click="activeTab = 'payments'" class="px-5 py-3 text-sm font-medium transition-all" :class="activeTab === 'payments' ? 'border-b-2 border-blue-600 text-blue-700 bg-card' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'">
                        Recent Payments
                    </button>
                    <button v-if="isDO || isCashier" @click="activeTab = 'balances'" class="px-5 py-3 text-sm font-medium transition-all" :class="activeTab === 'balances' ? 'border-b-2 border-blue-600 text-blue-700 bg-card' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'">
                        Outstanding Balances
                    </button>
                </div>
                <div class="p-5">
                    <!-- Overview: Payment Trends Chart placeholder -->
                    <div v-if="activeTab === 'overview'">
                        <h3 class="mb-4 text-sm font-semibold text-foreground">Payment Trends (Last 6 Months)</h3>
                        <div v-if="chartData && chartData.length > 0" class="space-y-2.5">
                            <div v-for="(month, i) in chartData" :key="i" class="flex items-center gap-3">
                                <span class="w-12 text-xs text-muted-foreground text-right flex-shrink-0">{{ month.label }}</span>
                                <div class="flex-1 rounded-full bg-muted h-5 overflow-hidden">
                                    <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: month.percentage + '%' }"></div>
                                </div>
                                <span class="w-24 text-xs font-medium text-foreground text-right flex-shrink-0">₱{{ Number(month.amount).toLocaleString('en-PH', { minimumFractionDigits: 0 }) }}</span>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
                            <p class="text-sm">No payment data available yet</p>
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <div v-if="activeTab === 'payments' && (isDO || isCashier)">
                        <div v-if="recentPaymentsFormatted && recentPaymentsFormatted.length > 0" class="divide-y divide-border">
                            <div v-for="payment in recentPaymentsFormatted" :key="payment.id" class="flex items-center justify-between py-3">
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ payment.student_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ payment.reference }} · {{ payment.date }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-emerald-600">+₱{{ Number(payment.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                                    <span class="ccdi-badge-green text-xs">{{ payment.status }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-10 text-center text-sm text-muted-foreground">No recent payments recorded</div>
                    </div>

                    <!-- Outstanding -->
                    <div v-if="activeTab === 'balances' && (isDO || isCashier)">
                        <div v-if="outstandingBalances && outstandingBalances.length > 0" class="divide-y divide-border">
                            <div v-for="balance in outstandingBalances" :key="balance.student_id" class="flex items-center justify-between py-3">
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ balance.student_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ balance.account_id }} · {{ balance.year_level }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-red-600">₱{{ Number(balance.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                                    <span class="ccdi-badge-red">Outstanding</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                            <p class="text-sm font-medium text-foreground">All clear!</p>
                            <p class="text-xs text-muted-foreground mt-1">No outstanding balances found</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookkeeper Section -->
            <div v-if="isBookkeeper" class="ccdi-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Financial Reports</h2>
                        <p class="text-xs text-muted-foreground">View collection summaries and financial analytics</p>
                    </div>
                    <Link :href="route('accounting.transactions.index')" class="ccdi-btn-primary text-xs px-3 py-1.5">View Reports</Link>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Total Collections</p>
                        <p class="text-2xl font-bold text-emerald-600">₱{{ Number(statsFormatted.totalCollections).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">All-time</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Collection Rate</p>
                        <p class="text-2xl font-bold" :class="Number(statsFormatted.collectionRate) >= 50 ? 'text-emerald-600' : 'text-red-600'">{{ Number(statsFormatted.collectionRate).toFixed(2) }}%</p>
                        <p class="text-xs text-muted-foreground">Efficiency</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Active Assessments</p>
                        <p class="text-2xl font-bold text-foreground">{{ feeStats.totalAssessments }}</p>
                        <p class="text-xs text-muted-foreground">Current term</p>
                    </div>
                    <div class="rounded-xl border border-border bg-muted/30 p-4">
                        <p class="text-xs text-muted-foreground">Pending Payments</p>
                        <p class="text-2xl font-bold text-red-600">₱{{ Number(statsFormatted.pendingPayments).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
                        <p class="text-xs text-muted-foreground">Outstanding</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link :href="route('accounting.transactions.index')" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-all hover:border-blue-300 hover:bg-blue-50">
                        <div><p class="text-sm font-medium text-foreground">Transaction History</p><p class="text-xs text-muted-foreground">All payments and charges</p></div>
                    </Link>
                    <Link :href="route('accounting.dashboard')" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-all hover:border-blue-300 hover:bg-blue-50">
                        <div><p class="text-sm font-medium text-foreground">Export Summary</p><p class="text-xs text-muted-foreground">Download financial reports</p></div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
