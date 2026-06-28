<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import AdminForm from './Form.vue';

const breadcrumbs = [
    { title: 'Admin', href: route('admin.dashboard') },
    { title: 'Admin Users', href: route('users.index') },
    { title: 'Add Accounting Staff', href: route('users.create') },
];
</script>

<template>
    <Head title="Add Accounting Staff" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-6">
            <div class="max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Add Accounting Staff</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a new Accounting department user with financial permissions.
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-md">
                    <AdminForm :is-editing="false" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>