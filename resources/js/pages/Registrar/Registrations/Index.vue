<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Search, Eye } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Registration {
    id: number;
    tracking_token: string;
    full_name: string;
    email: string;
    contact_number: string;
    course: string;
    year_level: string;
    student_type: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string;
    registrar_reviewer_name: string | null;
}

const props = defineProps<{
    registrations: {
        data: Registration[];
        links: any[];
        meta: any;
    };
    counts: Record<string, number>;
    filters: { status: string; search: string | null };
}>();

const search       = ref(props.filters.search ?? '');
const activeStatus = ref(props.filters.status ?? 'pending');

const tabs = [
    { key: 'pending',               label: 'Pending'            },
    { key: 'needs_revision',        label: 'Needs Revision'     },
    { key: 'registrar_cleared',     label: 'Cleared to Finance' },
    { key: 'rejected_by_registrar', label: 'Rejected'           },
    { key: 'all',                   label: 'All'                },
];

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('registrar.registrations.index'), {
            status: activeStatus.value,
            search: val || undefined,
        }, { preserveState: true, replace: true });
    }, 400);
});

const switchTab = (key: string) => {
    activeStatus.value = key;
    router.get(route('registrar.registrations.index'), {
        status: key,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};

const statusBadgeClass: Record<string, string> = {
    pending:               'bg-yellow-100 text-yellow-800',
    needs_revision:        'bg-orange-100 text-orange-800',
    registrar_cleared:     'bg-blue-100 text-blue-800',
    rejected_by_registrar: 'bg-red-100 text-red-800',
};

const studentTypeLabel: Record<string, string> = {
    new:        'New',
    old:        'Old',
    transferee: 'Transferee',
    returnee:   'Returnee',
    irregular:  'Irregular',
};

// Tabs that indicate actionable items (show badge)
const actionableTabs = new Set(['pending', 'needs_revision']);
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: route('registrar.dashboard') },
        { title: 'Registration Queue' }
    ]">
        <Head title="Registration Queue" />

        <div class="space-y-5 p-4 md:p-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <ClipboardList class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Registration Queue</h1>
                    <p class="text-sm text-muted-foreground">Academic review — Stage 1 of 2</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 border-b border-gray-200 overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    :class="[
                        'flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                        activeStatus === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                    <span
                        v-if="counts[tab.key] > 0"
                        :class="[
                            'rounded-full px-1.5 py-0.5 text-xs font-bold',
                            activeStatus === tab.key ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600',
                        ]"
                    >
                        {{ counts[tab.key] }}
                    </span>
                </button>
            </div>

            <!-- Search -->
            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, email, or token…"
                    class="w-full rounded-md border border-input bg-background pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
            </div>

            <!-- Stage context banner -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <span class="font-semibold">Registrar Stage:</span>
                Review academic eligibility. Approve to forward to Finance for billing clearance.
                Reject to end the application. Request Revision for missing documents.
            </div>

            <!-- Table -->
            <div class="rounded-lg border border-gray-200 overflow-hidden bg-white shadow-sm">
                <div v-if="registrations.data.length === 0" class="p-12 text-center text-sm text-muted-foreground">
                    No registrations found for this filter.
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Applicant</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Course / Year</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Submitted</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Reviewed By</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="reg in registrations.data"
                            :key="reg.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ reg.full_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ reg.email }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ reg.tracking_token }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 truncate max-w-[200px]">{{ reg.course }}</p>
                                <p class="text-xs text-muted-foreground">{{ reg.year_level }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                    {{ studentTypeLabel[reg.student_type] ?? reg.student_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                {{ reg.submitted_at }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        statusBadgeClass[reg.status] ?? 'bg-gray-100 text-gray-700',
                                    ]"
                                >
                                    {{ reg.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ reg.registrar_reviewer_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('registrar.registrations.show', reg.id)"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-input bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    Review
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="registrations.meta?.last_page > 1" class="flex items-center gap-2 flex-wrap">
                <Link
                    v-for="link in registrations.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-md border transition-colors',
                        link.active
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-white text-gray-700 border-input hover:bg-gray-50',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                    v-html="link.label"
                />
            </div>

        </div>
    </AppLayout>
</template>