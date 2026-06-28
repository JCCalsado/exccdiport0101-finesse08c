<script setup lang="ts">
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
const { formatCurrency } = useDataFormatting();

const props = defineProps<{
    students: any;
    filters: { search?: string; status?: string };
    auth: { user: { role: string } };
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

// Debounced search
let timeout: number;
watch([search, statusFilter], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('students.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
    }, 300);
});

const breadcrumbs = [{ title: 'Dashboard', href: route('admin.dashboard') }, { title: 'Students' }];

const formatDate = (date: string | null) => {
    return date ? new Date(date).toLocaleDateString() : '-';
};
</script>

<template>
    <Head title="Students Archive" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-6 py-4">
            <!-- Header -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-semibold text-gray-800">Students</h1>
                <Link
                    :href="route('students.create')"
                    class="mt-4 inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 sm:mt-0"
                >
                    ➕ Add Student
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search students..."
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="graduated">Graduated</option>
                </select>
            </div>

            <!-- Students Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-md">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Student Number</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Enrollment Date</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-700">{{ student.student_number }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ student.user?.first_name }} {{ student.user?.last_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ student.user?.email }}</td>
                            <td class="px-6 py-4 text-sm font-semibold capitalize">
                                <span :class="['status-badge', `status-${student.enrollment_status?.toLowerCase()}`]">
                                    {{ student.enrollment_status || '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(student.enrollment_date) }}</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900">
                                {{ formatCurrency(Math.abs(student.user?.account?.balance ?? 0)) }}
                            </td>
                            <td class="flex gap-3 px-6 py-4 text-sm">
                                <!-- View -->
                                <Link :href="`/students/${student.id}`" class="text-blue-600 hover:text-blue-800"> View </Link>

                                <!-- Edit (only for admins/super_admins) -->
                                <Link
                                    v-if="['admin', 'super_admin'].includes(props.auth.user.role)"
                                    :href="`/students/${student.id}/edit`"
                                    class="text-green-600 hover:text-green-800"
                                >
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="students.data.length === 0">
                            <td colspan="7" class="px-6 py-6 text-center text-gray-500">No students found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex justify-center space-x-2">
                <Link
                    v-for="link in students.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg border px-4 py-2 text-sm transition-colors"
                    :class="{
                        'border-blue-600 bg-blue-600 text-white': link.active,
                        'text-gray-600 hover:bg-gray-100': !link.active,
                        'cursor-not-allowed opacity-50': !link.url,
                    }"
                >
                    {{ link.label }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-block;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-suspended {
    background: #fee2e2;
    color: #991b1b;
}

.status-graduated {
    background: #dbeafe;
    color: #1e40af;
}
</style>
