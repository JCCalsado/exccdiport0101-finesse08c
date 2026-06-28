<template>
    <Head title="Accounting Workflows" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Accounting Workflows</h1>
                    <p class="text-gray-500">Manage transaction and payment workflows</p>
                </div>
                <button
                    @click="$router.visit(route('accounting-workflows.create'))"
                    class="rounded-lg bg-blue-600 px-6 py-2 font-medium text-white transition-colors hover:bg-blue-700"
                >
                    + New Workflow
                </button>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex gap-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by reference, student, or type..."
                    class="flex-1 rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500"
                />
                <select v-model="statusFilter" class="rounded-lg border p-3 outline-none focus:border-transparent focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Workflows Table -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="w-full">
                    <thead class="border-b bg-gray-100">
                        <tr class="text-left text-sm font-semibold text-gray-700">
                            <th class="p-4">Reference</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Student</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Created</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="workflow in filteredWorkflows" :key="workflow.id" class="border-b text-sm transition-colors hover:bg-gray-50">
                            <td class="p-4 font-mono">{{ workflow.reference }}</td>
                            <td class="p-4">{{ workflow.type }}</td>
                            <td class="p-4">
                                <div>
                                    <p class="font-medium">{{ workflow.student_name }}</p>
                                    <p class="text-xs text-gray-500">{{ workflow.account_id }}</p>
                                </div>
                            </td>
                            <td class="p-4 font-semibold">₱{{ formatCurrency(workflow.amount) }}</td>
                            <td class="p-4">
                                <span
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': workflow.status === 'pending',
                                        'bg-blue-100 text-blue-800': workflow.status === 'in_progress',
                                        'bg-green-100 text-green-800': workflow.status === 'approved',
                                        'bg-red-100 text-red-800': workflow.status === 'rejected',
                                    }"
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                >
                                    {{ workflow.status }}
                                </span>
                            </td>
                            <td class="p-4">{{ formatDate(workflow.created_at) }}</td>
                            <td class="p-4">
                                <button
                                    @click="$router.visit(route('accounting-workflows.show', workflow.id))"
                                    class="rounded bg-blue-600 px-3 py-1 text-xs text-white transition-colors hover:bg-blue-700"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-if="filteredWorkflows.length === 0" class="p-12 text-center">
                    <p class="text-lg text-gray-500">No workflows found</p>
                    <p class="mt-2 text-sm text-gray-400">Try adjusting your filters or search criteria</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const { formatCurrency } = useDataFormatting();

interface Workflow {
    id: number;
    reference: string;
    type: string;
    student_name: string;
    account_id: string;
    amount: number;
    status: string;
    created_at: string;
}

interface Props {
    workflows: Workflow[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const statusFilter = ref('');

const breadcrumbs = [{ title: 'Dashboard', href: route('accounting.dashboard') }, { title: 'Accounting Workflows' }];

const filteredWorkflows = computed(() => {
    let filtered = props.workflows;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(
            (w) =>
                w.reference.toLowerCase().includes(query) ||
                w.student_name.toLowerCase().includes(query) ||
                w.account_id.toLowerCase().includes(query) ||
                w.type.toLowerCase().includes(query),
        );
    }

    if (statusFilter.value) {
        filtered = filtered.filter((w) => w.status === statusFilter.value);
    }

    return filtered;
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>
