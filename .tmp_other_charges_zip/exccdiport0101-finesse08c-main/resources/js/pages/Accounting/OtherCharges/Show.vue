<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { formatCurrency } from '@/composables/useMoney';
import {
    BadgeDollarSign, CheckCircle, Clock, AlertTriangle,
    Send, Pencil, CreditCard, Search, X, Landmark, FileSearch, ShieldCheck, ShieldX,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ChargeStudent {
    user_id: number;
    name: string;
    account_id: string;
    course: string;
    year_level: string;
    semester: string | null;
    status: string;
    amount_paid: number;
    balance: number;
    paid_at: string | null;
    or_number: string | null;
    collected_by: string | null;
    payment_id: number | null;
    payment_method: string | null;
}

interface Charge {
    id: number;
    title: string;
    description: string | null;
    amount: number;
    school_year: string;
    semester: string | null;
    year_level: string | null;
    course: string | null;
    is_published: boolean;
    is_draft: boolean;
    status_label: string;
    published_at: string | null;
    updated_after_publish_at: string | null;
    created_at: string;
}

const props = defineProps<{
    charge: Charge;
    students: ChargeStudent[];
    summary: { total: number; paid: number; in_progress: number; unpaid: number; total_collected: number };
    canEdit: boolean;
    canRecordPayment: boolean;
    canApprove: boolean;
    canPublish: boolean;
    hasPaidStudents: boolean;
}>();

// ── Search ────────────────────────────────────────────────────────────────────
const search = ref('');
const filteredStudents = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.students;
    return props.students.filter(
        (s) => s.name.toLowerCase().includes(q) || s.account_id.toLowerCase().includes(q),
    );
});

// ── Publish ───────────────────────────────────────────────────────────────────
const publishing = ref(false);

const publishCharge = () => {
    if (!confirm(`Publish "${props.charge.title}"?\n\nStudents will immediately see this charge on their portal.`)) return;
    publishing.value = true;
    router.post(
        route('accounting.other-charges.publish', props.charge.id),
        {},
        { onFinish: () => { publishing.value = false; } },
    );
};

// ── OTC Payment Modal ─────────────────────────────────────────────────────────
const showPaymentModal = ref(false);
const selectedStudent = ref<ChargeStudent | null>(null);

const paymentForm = useForm({
    student_id: 0,
    or_number:  '',
    notes:      '',
});

const openPaymentModal = (student: ChargeStudent) => {
    selectedStudent.value = student;
    paymentForm.student_id = student.user_id;
    paymentForm.or_number  = '';
    paymentForm.notes      = '';
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedStudent.value  = null;
    paymentForm.reset();
};

const submitPayment = () => {
    paymentForm.post(
        route('accounting.other-charges.payments.store', props.charge.id),
        {
            onSuccess: () => closePaymentModal(),
            preserveScroll: true,
        },
    );
};

// ── OPTION D: Bank Transfer Approval ──────────────────────────────────────────
const approving = ref<number | null>(null);

const approvePayment = (student: ChargeStudent) => {
    if (!student.payment_id) return;
    if (!confirm(`Approve this bank transfer for ${student.name}?\n\nThis will mark the charge as paid.`)) return;

    approving.value = student.payment_id;
    router.post(
        route('accounting.other-charges.payments.approve', student.payment_id),
        {},
        { preserveScroll: true, onFinish: () => { approving.value = null; } },
    );
};

const viewProof = (student: ChargeStudent) => {
    if (!student.payment_id) return;
    window.open(route('accounting.other-charges.payments.proof.serve', student.payment_id), '_blank');
};

// ── Reject Modal ───────────────────────────────────────────────────────────────
const showRejectModal = ref(false);
const rejectingStudent = ref<ChargeStudent | null>(null);

const rejectForm = useForm({ reason: '' });

const openRejectModal = (student: ChargeStudent) => {
    rejectingStudent.value = student;
    rejectForm.reset();
    rejectForm.clearErrors();
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value  = false;
    rejectingStudent.value = null;
};

const submitReject = () => {
    if (!rejectingStudent.value?.payment_id) return;

    rejectForm.post(
        route('accounting.other-charges.payments.reject', rejectingStudent.value.payment_id),
        {
            preserveScroll: true,
            onSuccess: () => closeRejectModal(),
        },
    );
};

// ── Status badge ──────────────────────────────────────────────────────────────
const studentStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        paid:                   'bg-green-100 text-green-800',
        unpaid:                 'bg-gray-100 text-gray-700',
        pending:                'bg-yellow-100 text-yellow-800',
        awaiting_confirmation:  'bg-blue-100 text-blue-800',
        awaiting_proof:         'bg-blue-100 text-blue-800',
        awaiting_approval:      'bg-indigo-100 text-indigo-800',
        failed:                 'bg-red-100 text-red-800',
        cancelled:              'bg-gray-100 text-gray-500',
    };
    return map[status] ?? 'bg-gray-100 text-gray-700';
};

const studentStatusLabel = (status: string) => {
    const map: Record<string, string> = {
        paid:                   'Paid',
        unpaid:                 'Unpaid',
        pending:                'In Progress (Online)',
        awaiting_confirmation:  'Confirming Payment',
        awaiting_proof:         'Awaiting Proof Upload',
        awaiting_approval:      'Awaiting Verification',
        failed:                 'Failed',
        cancelled:              'Cancelled',
    };
    return map[status] ?? status;
};

// Whether a student row represents an in-progress online payment
// (accounting should NOT record OTC for these)
const isOnlineInProgress = (student: ChargeStudent) =>
    student.payment_method === 'online' &&
    ['pending', 'awaiting_confirmation'].includes(student.status);

// Whether a student row is a bank-transfer proof awaiting DO review
const isBankTransferAwaitingApproval = (student: ChargeStudent) =>
    student.payment_method === 'bank_transfer' &&
    student.status === 'awaiting_approval';

// Whether the "Record OTC" button should be hidden — bank transfers in flight
// must go through Approve/Reject, not be overwritten by an OTC record.
const isBankTransferInProgress = (student: ChargeStudent) =>
    student.payment_method === 'bank_transfer' &&
    ['awaiting_proof', 'awaiting_approval'].includes(student.status);
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Accounting', href: route('accounting.dashboard') },
        { title: 'Other Charges', href: route('accounting.other-charges.index') },
        { title: charge.title },
    ]">
        <Head :title="charge.title" />

        <div class="space-y-5 p-4 md:p-6">

            <!-- Charge Header Card -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary flex-shrink-0">
                            <BadgeDollarSign class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-lg font-bold text-gray-900">{{ charge.title }}</h1>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        charge.is_published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800',
                                    ]"
                                >
                                    <CheckCircle v-if="charge.is_published" class="h-3 w-3" />
                                    <Clock v-else class="h-3 w-3" />
                                    {{ charge.status_label }}
                                </span>
                            </div>
                            <p v-if="charge.description" class="text-sm text-muted-foreground mt-1">
                                {{ charge.description }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>{{ charge.school_year }}</span>
                                <span v-if="charge.semester">{{ charge.semester }}</span>
                                <span v-if="charge.year_level">{{ charge.year_level }}</span>
                                <span v-if="charge.course">{{ charge.course }}</span>
                                <span v-if="!charge.year_level && !charge.course && !charge.semester">All Students</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(charge.amount) }}</p>
                        <div class="flex items-center gap-2">
                            <!-- Publish button (draft only) -->
                            <button
                                v-if="charge.is_draft && canPublish"
                                @click="publishCharge"
                                :disabled="publishing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                            >
                                <Send class="h-3.5 w-3.5" />
                                {{ publishing ? 'Publishing…' : 'Publish' }}
                            </button>
                            <!-- Edit button -->
                            <Link
                                v-if="canEdit"
                                :href="route('accounting.other-charges.edit', charge.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Edit
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards
                 BUG-04 FIX: added "In Progress" card. Previously students with
                 pending/awaiting_confirmation/failed/cancelled were counted in neither
                 Paid nor Unpaid — they vanished from the dashboard entirely.
                 Now: Paid = 'paid'; In Progress = online/bank payment in flight;
                 Unpaid = genuinely not paid (unpaid, failed, cancelled).
                 The In Progress card is conditionally rendered so the grid stays
                 clean when no in-progress payments exist.
            -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-gray-900">{{ summary.total }}</p>
                    <p class="text-xs text-muted-foreground mt-1">Total Students</p>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-green-700">{{ summary.paid }}</p>
                    <p class="text-xs text-muted-foreground mt-1">Paid</p>
                </div>
                <div
                    v-if="summary.in_progress > 0"
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-center shadow-sm"
                >
                    <p class="text-2xl font-bold text-blue-700">{{ summary.in_progress }}</p>
                    <p class="text-xs text-muted-foreground mt-1">In Progress</p>
                </div>
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-yellow-700">{{ summary.unpaid }}</p>
                    <p class="text-xs text-muted-foreground mt-1">Unpaid</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-indigo-700">{{ formatCurrency(summary.total_collected) }}</p>
                    <p class="text-xs text-muted-foreground mt-1">Total Collected</p>
                </div>
            </div>

            <!-- Draft warning -->
            <div
                v-if="charge.is_draft"
                class="flex items-start gap-3 rounded-xl border border-yellow-300 bg-yellow-50 p-4"
            >
                <AlertTriangle class="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-yellow-800">
                    <p class="font-semibold">This charge is a draft</p>
                    <p class="mt-0.5">Students cannot see it yet. Publish it to make it visible on the student portal and enable payment collection.</p>
                </div>
            </div>

            <!-- Student List -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Student Payment Status</h2>
                    <!-- Search -->
                    <div class="relative w-64">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or ID…"
                            class="w-full rounded-lg border border-gray-200 pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50"
                        />
                    </div>
                </div>

                <div v-if="students.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                    No students match this charge's target group.
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Student</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Year / Course</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">OR / Reference</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Paid At</th>
                            <th v-if="canRecordPayment || canApprove" class="px-4 py-3 text-center font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="student in filteredStudents"
                            :key="student.user_id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ student.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ student.account_id }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ student.year_level }} · {{ student.course }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', studentStatusBadge(student.status)]"
                                >
                                    {{ studentStatusLabel(student.status) }}
                                </span>
                                <span v-if="student.payment_method === 'bank_transfer'" class="block mt-1 text-[10px] text-indigo-600 font-medium">
                                    <Landmark class="inline h-2.5 w-2.5 mb-0.5" /> Bank Transfer
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span v-if="student.or_number" class="font-mono text-gray-700">{{ student.or_number }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                                <span v-if="student.payment_method === 'online'" class="ml-1 text-blue-600">(Online)</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ student.paid_at ?? '—' }}
                            </td>
                            <td v-if="canRecordPayment || canApprove" class="px-4 py-3 text-center">

                                <!-- Bank transfer awaiting DO approval -->
                                <div v-if="isBankTransferAwaitingApproval(student) && canApprove" class="flex items-center justify-center gap-1.5">
                                    <button
                                        @click="viewProof(student)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                        title="View uploaded proof"
                                    >
                                        <FileSearch class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="approvePayment(student)"
                                        :disabled="approving === student.payment_id"
                                        class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                                        title="Approve"
                                    >
                                        <ShieldCheck class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="openRejectModal(student)"
                                        class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700 transition-colors"
                                        title="Reject"
                                    >
                                        <ShieldX class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Bank transfer in progress, not yet at approval stage -->
                                <span
                                    v-else-if="isBankTransferInProgress(student)"
                                    class="text-xs text-indigo-600 font-medium"
                                >
                                    ⏳ Awaiting proof upload
                                </span>

                                <!-- Standard OTC recording -->
                                <button
                                    v-else-if="student.status !== 'paid' && !isOnlineInProgress(student) && canRecordPayment"
                                    @click="openPaymentModal(student)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary/90 transition-colors"
                                >
                                    <CreditCard class="h-3.5 w-3.5" />
                                    Record OTC
                                </button>
                                <span
                                    v-else-if="isOnlineInProgress(student)"
                                    class="text-xs text-blue-600 font-medium"
                                >
                                    ⏳ Online pending
                                </span>
                                <span v-else-if="student.status === 'paid'" class="text-xs text-green-600 font-medium">✓ Paid</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- OTC Payment Modal -->
        <Teleport to="body">
            <div
                v-if="showPaymentModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closePaymentModal"
            >
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 p-5">
                        <h3 class="text-base font-bold text-gray-900">Record OTC Payment</h3>
                        <button @click="closePaymentModal" class="rounded-lg p-1.5 hover:bg-gray-100 transition-colors">
                            <X class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>

                    <div v-if="selectedStudent" class="p-5 space-y-4">
                        <!-- Charge + Student info -->
                        <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Charge</span>
                                <span class="font-medium text-gray-900">{{ charge.title }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Student</span>
                                <span class="font-medium text-gray-900">{{ selectedStudent.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <span class="font-bold text-gray-900">{{ formatCurrency(charge.amount) }}</span>
                            </div>
                        </div>

                        <!-- OR Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Official Receipt Number <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="paymentForm.or_number"
                                type="text"
                                placeholder="e.g. OR-2025-00123"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                :class="{ 'border-red-400': paymentForm.errors.or_number }"
                            />
                            <p v-if="paymentForm.errors.or_number" class="mt-1 text-xs text-red-600">{{ paymentForm.errors.or_number }}</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <textarea
                                v-model="paymentForm.notes"
                                rows="2"
                                placeholder="Any remarks for this payment"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                            />
                        </div>

                        <!-- Server error -->
                        <div v-if="paymentForm.errors.payment" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                            {{ paymentForm.errors.payment }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-gray-200 p-5">
                        <button
                            @click="submitPayment"
                            :disabled="paymentForm.processing"
                            class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
                        >
                            {{ paymentForm.processing ? 'Recording…' : 'Confirm Payment' }}
                        </button>
                        <button
                            @click="closePaymentModal"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Reject Bank Transfer Modal -->
        <Teleport to="body">
            <div
                v-if="showRejectModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeRejectModal"
            >
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 p-5">
                        <h3 class="text-base font-bold text-gray-900">Reject Bank Transfer Proof</h3>
                        <button @click="closeRejectModal" class="rounded-lg p-1.5 hover:bg-gray-100 transition-colors">
                            <X class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>

                    <div v-if="rejectingStudent" class="p-5 space-y-4">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Student</span>
                                <span class="font-medium text-gray-900">{{ rejectingStudent.name }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            The student will be notified by email and sent back to the upload page
                            with this reason shown to them.
                        </p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="rejectForm.reason"
                                rows="3"
                                placeholder="e.g. The receipt amount does not match the charge amount."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                :class="{ 'border-red-400': rejectForm.errors.reason }"
                            />
                            <p v-if="rejectForm.errors.reason" class="mt-1 text-xs text-red-600">{{ rejectForm.errors.reason }}</p>
                        </div>

                        <div v-if="rejectForm.errors.payment" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                            {{ rejectForm.errors.payment }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-gray-200 p-5">
                        <button
                            @click="submitReject"
                            :disabled="rejectForm.processing"
                            class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 transition-colors"
                        >
                            {{ rejectForm.processing ? 'Rejecting…' : 'Confirm Rejection' }}
                        </button>
                        <button
                            @click="closeRejectModal"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
