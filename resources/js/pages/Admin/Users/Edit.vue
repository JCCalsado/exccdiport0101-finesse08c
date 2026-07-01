<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import AdminForm from './Form.vue';

interface Props {
    admin: any;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    {
        title: `Edit: ${props.admin.name ?? `${props.admin.last_name}, ${props.admin.first_name}`}`,
        href: route('users.edit', props.admin.id),
    },
];
</script>

<template>
    <Head title="Edit Accounting Staff" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-6">
            <div class="max-w-2xl">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-md">
                    <h1 class="mb-6 text-2xl font-bold text-gray-900">Edit Accounting Staff</h1>

                    <div class="mb-4 rounded-lg bg-gray-50 p-4">
                        <div class="text-sm text-gray-600">
                            <p><strong>Staff ID:</strong> {{ admin.id }}</p>
                            <p><strong>Created:</strong> {{ new Date(admin.created_at).toLocaleDateString() }}</p>
                            <p v-if="admin.updated_by">
                                <strong>Last Updated:</strong> {{ new Date(admin.updated_at).toLocaleDateString() }}
                            </p>
                        </div>
                    </div>

                    <AdminForm :admin="admin" :is-editing="true" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>