<script setup lang="ts">
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
const { formatCurrency } = useDataFormatting();

interface Student {
    id: number;
    student_id: string;
    student_number: string | null;
    enrollment_status: string;
    updated_at: string;
    user?: {
        id: number;
        first_name: string;
        last_name: string;
        middle_initial: string | null;
        email: string;
        course: string;
        year_level: string;
    };
    account?: { balance: number } | null;
}

interface Props {
    students: { data: Student[]; links: any[] };
    filters: { search?: string; status?: string };
    counts: { graduated: number; dropped: number; inactive: number };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

let timeout: ReturnType<typeof setTimeout>;
watch([search, statusFilter], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('students.archive'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
    }, 300);
});

const breadcrumbs = [{ title: 'Dashboard', href: route('admin.dashboard') }, { title: 'Archives' }];

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—');

const statusConfig: Record<string, { label: string; classes: string }> = {
    graduated: { label: 'Graduated', classes: 'bg-blue-100 text-blue-800' },
    dropped: { label: 'Dropped', classes: 'bg-red-100 text-red-800' },
    inactive: { label: 'Inactive', classes: 'bg-gray-100 text-gray-700' },
};

const totalArchived = props.counts.graduated + props.counts.dropped + props.counts.inactive;

// ── Reinstate modal ────────────────────────────────────────────────────────
const reinstateModal = ref(false);
const selectedStudent = ref<Student | null>(null);

const reinstateForm = useForm({ reason: '' });

const openReinstate = (student: Student) => {
    selectedStudent.value = student;
    reinstateForm.reset();
    reinstateModal.value = true;
};

const closeReinstate = () => {
    reinstateModal.value = false;
    selectedStudent.value = null;
};

const submitReinstate = () => {
    if (!selectedStudent.value) return;
    reinstateForm.post(route('students.reinstate', selectedStudent.value.id), {
        onSuccess: () => {
            closeReinstate();
            router.reload();
        },
    });
};

const canReinstate = (student: Student) => ['dropped', 'inactive'].includes(student.enrollment_status);
</script>

<template>
    <Head title="Student Archives" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Student Archives</h1>
                <p class="mt-1 text-sm text-gray-500">Graduated, dropped, and inactive students</p>
            </div>

            <!-- Summary cards -->
            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Total Archived</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800">{{ totalArchived }}</p>
                </div>
                <div
                    class="cursor-pointer rounded-lg border p-5 shadow-sm transition-colors"
                    :class="statusFilter === 'graduated' ? 'border-blue-600 bg-blue-600' : 'border-gray-100 bg-white hover:border-blue-300'"
                    @click="statusFilter = statusFilter === 'graduated' ? '' : 'graduated'"
                >
                    <p :class="['text-xs font-medium tracking-wide uppercase', statusFilter === 'graduated' ? 'text-blue-100' : 'text-gray-500']">
                        Graduated
                    </p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'graduated' ? 'text-white' : 'text-blue-700']">{{ counts.graduated }}</p>
                </div>
                <div
                    class="cursor-pointer rounded-lg border p-5 shadow-sm transition-colors"
                    :class="statusFilter === 'dropped' ? 'border-red-600 bg-red-600' : 'border-gray-100 bg-white hover:border-red-300'"
                    @click="statusFilter = statusFilter === 'dropped' ? '' : 'dropped'"
                >
                    <p :class="['text-xs font-medium tracking-wide uppercase', statusFilter === 'dropped' ? 'text-red-100' : 'text-gray-500']">
                        Dropped
                    </p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'dropped' ? 'text-white' : 'text-red-700']">{{ counts.dropped }}</p>
                </div>
                <div
                    class="cursor-pointer rounded-lg border p-5 shadow-sm transition-colors"
                    :class="statusFilter === 'inactive' ? 'border-gray-700 bg-gray-700' : 'border-gray-100 bg-white hover:border-gray-400'"
                    @click="statusFilter = statusFilter === 'inactive' ? '' : 'inactive'"
                >
                    <p :class="['text-xs font-medium tracking-wide uppercase', statusFilter === 'inactive' ? 'text-gray-200' : 'text-gray-500']">
                        Inactive
                    </p>
                    <p :class="['mt-1 text-3xl font-bold', statusFilter === 'inactive' ? 'text-white' : 'text-gray-700']">{{ counts.inactive }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, ID, email…"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="">All archived statuses</option>
                    <option value="graduated">Graduated</option>
                    <option value="dropped">Dropped</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button
                    v-if="search || statusFilter"
                    @click="
                        search = '';
                        statusFilter = '';
                    "
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                >
                    Clear
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm whitespace-nowrap">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Student ID</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Name</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Email</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Course</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Year Level</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Status</th>
                            <th class="px-5 py-3 text-right font-medium text-gray-600">Balance</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Last Updated</th>
                            <th class="px-5 py-3 text-left font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="student in students.data" :key="student.id" class="transition-colors hover:bg-gray-50">
                            <td class="px-5 py-4 font-mono text-xs text-gray-700">{{ student.student_id }}</td>
                            <td class="px-5 py-4 font-medium text-gray-900">
                                {{ student.user?.last_name }}, {{ student.user?.first_name }}
                                <span v-if="student.user?.middle_initial" class="text-gray-400"> {{ student.user.middle_initial }}. </span>
                            </td>
                            <td class="px-5 py-4 text-gray-600">{{ student.user?.email }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ student.user?.course }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ student.user?.year_level }}</td>
                            <td class="px-5 py-4">
                                <span
                                    v-if="statusConfig[student.enrollment_status]"
                                    :class="['rounded-full px-2.5 py-1 text-xs font-medium', statusConfig[student.enrollment_status].classes]"
                                >
                                    {{ statusConfig[student.enrollment_status].label }}
                                </span>
                                <span v-else class="text-xs text-gray-400">{{ student.enrollment_status }}</span>
                            </td>
                            <td class="px-5 py-4 text-right text-gray-700">
                                {{ formatCurrency(Math.abs(student.account?.balance ?? 0)) }}
                            </td>
                            <td class="px-5 py-4 text-gray-500">{{ formatDate(student.updated_at) }}</td>

                            <td class="px-5 py-4">
                                <div class="flex flex-col items-start gap-1">
                                    <Link
                                        :href="route('student-fees.show', student.user?.id ?? student.id) + '?from=archive'"
                                        class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200 transition-colors ring-inset hover:bg-indigo-100"
                                    >
                                        Fee Details
                                    </Link>
                                    <Link
                                        :href="route('students.workflow-history', student.id)"
                                        class="inline-flex items-center rounded-md bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200 transition-colors ring-inset hover:bg-gray-100"
                                    >
                                        Workflow
                                    </Link>
                                    <!-- Reinstate — only for dropped or inactive -->
                                    <button
                                        v-if="canReinstate(student)"
                                        @click="openReinstate(student)"
                                        class="inline-flex items-center rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200 transition-colors ring-inset hover:bg-green-100"
                                    >
                                        Reinstate
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="students.data.length === 0">
                            <td colspan="9" class="px-5 py-12 text-center text-gray-400">
                                <p class="font-medium">No archived students found.</p>
                                <p class="mt-1 text-xs">Try adjusting your search or filter.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <!-- Pagination -->
                <div v-if="students.links?.length > 3" class="flex justify-center gap-1 border-t bg-gray-50 px-5 py-3">
                    <Link
                        v-for="link in students.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded px-3 py-1.5 text-xs font-medium transition-colors',
                            link.active ? 'bg-blue-600 text-white' : 'border bg-white text-gray-600 hover:bg-gray-100',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- ── Reinstate Confirmation Modal ──────────────────────────────── -->
        <Teleport to="body">
            <div v-if="reinstateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" @click.self="closeReinstate">
                <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-900">Reinstate Student</h2>
                        <button @click="closeReinstate" class="text-xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
                    </div>

                    <!-- Body -->
                    <div class="space-y-4 px-6 py-5">
                        <p class="text-sm text-gray-600">
                            You are reinstating
                            <span class="font-semibold text-gray-900">
                                {{ selectedStudent?.user?.last_name }}, {{ selectedStudent?.user?.first_name }}
                            </span>
                            from
                            <span class="font-medium text-red-600 capitalize">{{ selectedStudent?.enrollment_status }}</span>
                            back to
                            <span class="font-medium text-green-600">Active</span>.
                        </p>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700"> Reason <span class="text-gray-400">(optional)</span> </label>
                            <textarea
                                v-model="reinstateForm.reason"
                                rows="3"
                                placeholder="e.g. Student resolved financial obligations and returned to school."
                                class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:outline-none"
                            />
                            <p v-if="reinstateForm.errors.reason" class="mt-1 text-xs text-red-500">
                                {{ reinstateForm.errors.reason }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 border-t px-6 py-4">
                        <button @click="closeReinstate" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button
                            @click="submitReinstate"
                            :disabled="reinstateForm.processing"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                        >
                            {{ reinstateForm.processing ? 'Reinstating…' : 'Confirm Reinstate' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
