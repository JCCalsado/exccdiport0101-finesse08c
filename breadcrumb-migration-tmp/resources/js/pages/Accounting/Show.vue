<template>
    <Head title="Workflow Details" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Accounting Workflow</h1>
                    <p class="text-gray-500">Review and process this transaction workflow</p>
                </div>
            </div>

            <!-- Workflow Details -->
            <div v-if="workflow" class="grid grid-cols-3 gap-6">
                <!-- Reference & Status -->
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">Reference</p>
                    <p class="mt-2 font-mono text-lg font-bold">{{ workflow.reference }}</p>
                    <div class="mt-4 border-t pt-4">
                        <p class="text-sm text-gray-600">Status</p>
                        <span
                            :class="{
                                'bg-yellow-100 text-yellow-800': workflow.status === 'pending',
                                'bg-blue-100 text-blue-800': workflow.status === 'in_progress',
                                'bg-green-100 text-green-800': workflow.status === 'approved',
                                'bg-red-100 text-red-800': workflow.status === 'rejected',
                            }"
                            class="mt-2 inline-block rounded-full px-3 py-1 text-sm font-semibold"
                        >
                            {{ workflow.status }}
                        </span>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">Student</p>
                    <p class="mt-2 text-lg font-bold">{{ workflow.student_name }}</p>
                    <p class="mt-1 text-sm text-gray-600">ID: {{ workflow.account_id }}</p>
                    <div class="mt-4 border-t pt-4">
                        <p class="text-sm text-gray-600">Year Level</p>
                        <p class="font-semibold">{{ workflow.student_year_level }}</p>
                    </div>
                </div>

                <!-- Amount Info -->
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">Amount</p>
                    <p class="mt-2 text-3xl font-bold text-green-600">₱{{ formatCurrency(workflow.amount) }}</p>
                    <div class="mt-4 border-t pt-4">
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="font-semibold">{{ workflow.type }}</p>
                    </div>
                </div>
            </div>

            <!-- Transaction Details -->
            <div v-if="workflow" class="space-y-4 rounded-xl border bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">Transaction Details</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-semibold">{{ formatPaymentMethod(workflow.payment_method) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Created At</p>
                        <p class="font-semibold">{{ formatDate(workflow.created_at) }}</p>
                    </div>
                    <div v-if="workflow.reference_number">
                        <p class="text-sm text-gray-600">Reference Number</p>
                        <p class="font-semibold">{{ workflow.reference_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Last Updated</p>
                        <p class="font-semibold">{{ formatDate(workflow.updated_at) }}</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div v-if="workflow && workflow.status === 'pending'" class="flex gap-3">
                <button
                    @click="approveWorkflow"
                    :disabled="processing"
                    class="flex-1 rounded-lg bg-green-600 px-6 py-3 font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                >
                    {{ processing ? 'Processing...' : 'Approve & Process' }}
                </button>
                <button
                    @click="rejectWorkflow"
                    :disabled="processing"
                    class="flex-1 rounded-lg bg-red-600 px-6 py-3 font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                >
                    {{ processing ? 'Processing...' : 'Declined' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const { formatCurrency } = useDataFormatting();

interface Workflow {
    id: number;
    reference: string;
    type: string;
    status: string;
    student_name: string;
    account_id: string;
    student_year_level: string;
    amount: number;
    payment_method: string;
    reference_number: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    workflow: Workflow;
}

const props = defineProps<Props>();
const processing = ref(false);

const breadcrumbs = [
    { title: 'Dashboard', href: route('accounting.dashboard') },
    { title: 'Workflows', href: route('accounting-workflows.index') },
    { title: 'Details' },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const approveWorkflow = async () => {
    processing.value = true;
    router.post(
        route('accounting-workflows.submit', props.workflow.id),
        {},
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const rejectWorkflow = async () => {
    const reason = prompt('Please provide a reason for decline:');
    if (!reason) return;

    processing.value = true;
    router.delete(route('accounting-workflows.destroy', props.workflow.id), {
        onFinish: () => {
            processing.value = false;
        },
    });
};

const formatPaymentMethod = (m: string): string => {
    const labels: Record<string, string> = {
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        credit_card: 'Credit Card',
        debit_card: 'Debit Card',
        paymaya: 'Maya',
        maya: 'Maya',
        paymongo: 'PayMongo',
    };
    return labels[m?.toLowerCase()] ?? m ?? '—';
};

</script>
