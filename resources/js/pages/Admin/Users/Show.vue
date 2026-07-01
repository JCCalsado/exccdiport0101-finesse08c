<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
    admin: any;
    // canManage is true for Accounting and Registrar department users (not Administrator)
    canManage: boolean;
}

const props = defineProps<Props>();
const showDeactivateWarning = ref(false);

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    { title: `${props.admin.name ?? ''}`, href: route('users.show', props.admin.id) },
];

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const accountingTypeLabel = computed(() => {
    const t = props.admin.accounting_type as string | null;
    if (!t) return null;
    const map: Record<string, string> = {
        cashier:            'Cashier',
        bookkeeper:         'Bookkeeper',
        disbursing_officer: 'Disbursing Officer',
    };
    return map[t] ?? t;
});

const departmentBadgeClass = computed(() => {
    const dept = props.admin.department as string | null;
    if (dept === 'Accounting') return 'bg-blue-100 text-blue-800';
    if (dept === 'Registrar')  return 'bg-violet-100 text-violet-800';
    return 'bg-purple-100 text-purple-800'; // Administrator
});

const confirmDeactivate = () => {
    showDeactivateWarning.value = false;
    const form = useForm({});
    form.post(route('users.deactivate', props.admin.id));
};

const reactivate = () => {
    const form = useForm({});
    form.post(route('users.reactivate', props.admin.id));
};
</script>

<template>
    <Head :title="`Admin: ${admin.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-6">
            <div class="mb-6 flex items-center justify-between">
                <div v-if="canManage" class="flex shrink-0 gap-2">
                    <Link :href="route('users.edit', admin.id)">
                        <Button>Edit</Button>
                    </Link>
                    <Button v-if="admin.is_active" variant="destructive" @click="showDeactivateWarning = true">
                        Deactivate
                    </Button>
                    <Button v-else variant="outline" @click="reactivate">Reactivate</Button>
                </div>

                <div v-else>
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700">
                        Administrator — View only
                    </span>
                </div>
            </div>

            <!-- Deactivate Confirmation Modal -->
            <div v-if="showDeactivateWarning" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="max-w-md rounded-lg bg-white p-6 shadow-lg">
                    <h2 class="text-lg font-bold text-gray-900">Deactivate Staff Member?</h2>
                    <p class="mt-3 text-gray-600">
                        This will deactivate the account for
                        <strong>{{ admin.name }}</strong>.
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        They will no longer be able to access the system. You can reactivate this account at any time.
                    </p>
                    <div class="mt-5 flex justify-end gap-3">
                        <Button variant="outline" @click="showDeactivateWarning = false">Cancel</Button>
                        <Button variant="destructive" @click="confirmDeactivate">Confirm Deactivate</Button>
                    </div>
                </div>
            </div>

            <div class="max-w-4xl space-y-5">
                <!-- Header card -->
                <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ admin.name }}
                    </h1>
                    <p class="mt-1 text-gray-500">{{ admin.email }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', departmentBadgeClass]">
                            {{ admin.department ?? 'Administrator' }}
                        </span>
                        <!-- Position badge for Accounting sub-roles -->
                        <span
                            v-if="accountingTypeLabel"
                            class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800"
                        >
                            {{ accountingTypeLabel }}
                        </span>
                        <span
                            :class="[
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                admin.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700',
                            ]"
                        >
                            {{ admin.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Staff info -->
                    <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 font-semibold text-gray-800">Staff information</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Department</dt>
                                <dd class="text-gray-900">{{ admin.department ?? 'Administrator' }}</dd>
                            </div>
                            <!-- Position — only shown for Accounting staff with a sub-role -->
                            <div v-if="accountingTypeLabel" class="flex justify-between">
                                <dt class="text-gray-500">Position</dt>
                                <dd class="text-gray-900">{{ accountingTypeLabel }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Status</dt>
                                <dd>
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-xs font-medium',
                                            admin.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700',
                                        ]"
                                    >
                                        {{ admin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Terms accepted</dt>
                                <dd>
                                    <span v-if="admin.terms_accepted_at" class="text-xs font-medium text-green-600">
                                        ✓ {{ formatDate(admin.terms_accepted_at) }}
                                    </span>
                                    <span v-else class="text-xs text-red-500">✗ Not accepted</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Account details -->
                    <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 font-semibold text-gray-800">Account details</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">User ID</dt>
                                <dd class="font-mono text-gray-700">{{ admin.id }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Created</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.created_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Last updated</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.updated_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Last login</dt>
                                <dd class="text-gray-900">{{ formatDate(admin.last_login_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Audit trail -->
                <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-800">Audit trail</h2>
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500">Created by</dt>
                            <dd class="mt-1 text-gray-900">
                                <span v-if="admin.createdByUser">
                                    {{ admin.createdByUser.name }}
                                </span>
                                <span v-else class="text-gray-400">System</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Last updated by</dt>
                            <dd class="mt-1 text-gray-900">
                                <span v-if="admin.updatedByUser">
                                    {{ admin.updatedByUser.name }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex">
                    <Link :href="route('users.index')">
                        <Button variant="outline">← Back to Admin Users</Button>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>