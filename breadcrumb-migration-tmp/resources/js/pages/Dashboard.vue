<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
];

// const notifications = computed(() => usePage().props.notifications ?? [])
// const page = usePage()
// const notifications = computed(() => page.props.notifications ?? [])
// ✅ Define only the new data you need to *merge* with global PageProps
type Notification = {
    id: number;
    title: string;
    message: string;
    start_date: string | null;
    end_date: string | null;
    target_role: string;
};

// ✅ Safely extend the global PageProps type with an intersection
type ExtendedProps = import('@inertiajs/core').PageProps & {
    notifications?: Notification[];
};

// ✅ Use your extended type
const page = usePage<ExtendedProps>();

// ✅ Extract notifications safely (with fallback)
const notifications = computed(() => page.props.notifications ?? []);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full p-6">
            <div class="mt-6 space-y-6">
                <h1 class="text-2xl font-bold">Welcome back!</h1>

                <!-- <div v-if="notifications.length" class="bg-white shadow rounded-lg p-4">
                <h2 class="font-semibold mb-2 text-blue-700">Upcoming Payables</h2>
                <ul class="list-disc ml-5 text-sm text-gray-700">
                <li
                    v-for="n in notifications"
                    :key="n.id"
                    class="whitespace-pre-line"
                >
                    <strong>{{ n.title }}:</strong> {{ n.message }}
                </li>
                </ul>
            </div> -->
                <div v-if="notifications.length" class="mt-6 rounded-lg bg-white p-4 shadow">
                    <h2 class="mb-2 text-lg font-semibold text-blue-700">📅 Upcoming Payables & Schedules</h2>
                    <ul class="ml-5 list-disc text-sm text-gray-700">
                        <li v-for="n in notifications" :key="n.id" class="mb-1 whitespace-pre-line">
                            <strong>{{ n.title }}:</strong> {{ n.message }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
