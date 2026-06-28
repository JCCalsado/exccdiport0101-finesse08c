<script setup lang="ts">
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
const { formatCurrency } = useDataFormatting();

interface Workflow {
    id: number;
    workflow_type: string;
    description: string;
    status: string; // pending | in_progress | completed | rejected
    current_step: string;
    approver_name: string | null;
    comment: string | null;
    course: string | null;
    created_at: string;
    updated_at: string;
    completed_at: string | null;
}

interface Assessment {
    id: number;
    course: string | null;
    year_level: string;
    semester: string;
    school_year: string;
    total_assessment: number;
    tuition_fee: number;
    other_fees: number;
    status: string;
    created_at: string;
}

interface Props {
    account_id: string;
    workflows: Workflow[];
    assessments: Assessment[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Archives', href: route('students.archive') },
    { title: `${props.account_id} — Workflow History` },
];

// ── Helpers ──────────────────────────────────────────────────────────────────

const formatDate = (date: string | null): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateShort = (date: string | null): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isFinished = (status: string): boolean => status === 'completed' || status === 'rejected';

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending: 'Pending',
        in_progress: 'In Progress',
        completed: 'Completed',
        rejected: 'Rejected',
    };
    return labels[status] ?? status;
};

const dotClass = (status: string): string => {
    const map: Record<string, string> = {
        completed: 'bg-green-500',
        rejected: 'bg-red-500',
        in_progress: 'bg-yellow-500',
        pending: 'bg-gray-400',
    };
    return map[status] ?? 'bg-gray-400';
};

const badgeClass = (status: string): string => {
    const map: Record<string, string> = {
        completed: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        in_progress: 'bg-yellow-100 text-yellow-800',
        pending: 'bg-gray-100 text-gray-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-800';
};

const assessmentStatusBadge = (status: string): string => {
    return status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <Head title="Workflow History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full space-y-8 p-6">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold">Workflow History</h1>
                <p class="text-gray-500">Approval workflow and assessment history for this student</p>
            </div>

            <!-- ── Approval Workflow Timeline ─────────────────────────────── -->
            <div>
                <h2 class="mb-4 text-lg font-semibold text-gray-800">Approval Workflows</h2>

                <div class="space-y-4">
                    <div v-if="workflows && workflows.length > 0">
                        <div v-for="(workflow, index) in workflows" :key="workflow.id" class="relative">
                            <!-- Connector line between items -->
                            <div v-if="index < workflows.length - 1" class="absolute top-12 left-6 h-12 w-0.5 bg-gray-300"></div>

                            <div class="flex gap-4">
                                <!-- Status dot -->
                                <div
                                    class="z-10 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full font-bold text-white"
                                    :class="dotClass(workflow.status)"
                                >
                                    {{ index + 1 }}
                                </div>

                                <!-- Card -->
                                <div class="flex-1 rounded-lg border bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold">{{ workflow.workflow_type }}</h3>
                                            <p class="mt-1 text-sm text-gray-600">{{ workflow.description }}</p>
                                            <p class="mt-1 text-xs text-gray-400">
                                                Current step: <span class="font-medium">{{ workflow.current_step }}</span>
                                            </p>
                                        </div>
                                        <span class="rounded-full px-3 py-1 text-sm font-semibold capitalize" :class="badgeClass(workflow.status)">
                                            {{ statusLabel(workflow.status) }}
                                        </span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-500">Started</p>
                                            <p class="font-semibold">{{ formatDate(workflow.created_at) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">
                                                {{ isFinished(workflow.status) ? 'Completed' : 'Last updated' }}
                                            </p>
                                            <p class="font-semibold">
                                                {{ formatDate(workflow.completed_at ?? workflow.updated_at) }}
                                            </p>
                                        </div>
                                        <div v-if="workflow.course">
                                            <p class="text-gray-500">Course</p>
                                            <p class="font-semibold">{{ workflow.course }}</p>
                                        </div>
                                    </div>

                                    <div v-if="workflow.approver_name" class="mt-4 border-t pt-4 text-sm">
                                        <p class="text-gray-500">Handled by</p>
                                        <p class="font-semibold">{{ workflow.approver_name }}</p>
                                    </div>

                                    <div v-if="workflow.comment" class="mt-4 text-sm">
                                        <p class="text-gray-500">Comments</p>
                                        <p class="mt-1 rounded bg-gray-50 p-3">{{ workflow.comment }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="rounded-lg border bg-gray-50 py-10 text-center">
                        <p class="text-gray-500">No approval workflows on record.</p>
                        <p class="mt-1 text-sm text-gray-400">Payment approvals will appear here once the student submits a payment.</p>
                    </div>
                </div>
            </div>

            <!-- ── Assessment History ─────────────────────────────────────── -->
            <div>
                <h2 class="mb-4 text-lg font-semibold text-gray-800">Assessment History</h2>
                <p class="mb-4 text-sm text-gray-500">
                    Each row is a fee assessment created by the registrar. This is the source of each charge on the student's account.
                </p>

                <div v-if="assessments && assessments.length > 0" class="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold text-gray-600">School Year</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-600">Semester</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-600">Year Level</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-600">Course</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-600">Tuition Fee</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-600">Other Fees</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-600">Total Assessed</th>
                                <th class="px-5 py-3 text-center font-semibold text-gray-600">Status</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-600">Date Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="assessment in assessments" :key="assessment.id" class="transition-colors hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-800">{{ assessment.school_year }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ assessment.semester }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ assessment.year_level }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ assessment.course ?? '—' }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">
                                    {{ formatCurrency(assessment.tuition_fee) }}
                                </td>
                                <td class="px-5 py-3 text-right text-gray-700">
                                    {{ formatCurrency(assessment.other_fees) }}
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                    {{ formatCurrency(assessment.total_assessment) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                        :class="assessmentStatusBadge(assessment.status)"
                                    >
                                        {{ assessment.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">{{ formatDateShort(assessment.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty state -->
                <div v-else class="rounded-lg border bg-gray-50 py-10 text-center">
                    <p class="text-gray-500">No assessments on record.</p>
                    <p class="mt-1 text-sm text-gray-400">Fee assessments will appear here once the registrar creates one for this student.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
