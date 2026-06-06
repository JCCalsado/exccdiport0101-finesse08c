<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, Bell, LayoutTemplate, CheckCircle2, XCircle } from 'lucide-vue-next';

const props = defineProps<{
    stats: {
        queue_count:    number;
        cleared_today:  number;
        rejected_count: number;
        preset_count:   number;
        subject_count:  number;
    };
}>();
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Registrar Dashboard' }]">
        <Head title="Registrar Dashboard" />

        <div class="space-y-6 p-4 md:p-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Registrar Dashboard</h1>
                <p class="text-sm text-muted-foreground mt-1">Academic clearance and registry management</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-yellow-700">Pending Review</p>
                        <ClipboardList class="h-5 w-5 text-yellow-500" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-yellow-900">{{ stats.queue_count }}</p>
                    <p class="text-xs text-yellow-600 mt-1">registrations awaiting action</p>
                </div>

                <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-green-700">Cleared Today</p>
                        <CheckCircle2 class="h-5 w-5 text-green-500" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-green-900">{{ stats.cleared_today }}</p>
                    <p class="text-xs text-green-600 mt-1">forwarded to Finance</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-600">Curriculum Presets</p>
                        <LayoutTemplate class="h-5 w-5 text-gray-400" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.preset_count }}</p>
                    <p class="text-xs text-muted-foreground mt-1">active presets</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-600">Subjects</p>
                        <BookOpen class="h-5 w-5 text-gray-400" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.subject_count }}</p>
                    <p class="text-xs text-muted-foreground mt-1">in the registry</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">Quick Actions</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">

                    <Link
                        :href="route('registrar.registrations.index')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-primary/40 hover:shadow-md transition-all"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                            <ClipboardList class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-gray-900">Registration Queue</p>
                            <p class="text-xs text-muted-foreground">
                                <span v-if="stats.queue_count > 0" class="text-yellow-600 font-medium">{{ stats.queue_count }} pending</span>
                                <span v-else>No pending items</span>
                            </p>
                        </div>
                    </Link>

                    <Link
                        :href="route('accounting.curriculum-presets.index')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-primary/40 hover:shadow-md transition-all"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <LayoutTemplate class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-gray-900">Curriculum Presets</p>
                            <p class="text-xs text-muted-foreground">{{ stats.preset_count }} presets</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('accounting.subjects.index')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-primary/40 hover:shadow-md transition-all"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                            <BookOpen class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-gray-900">Subject Registry</p>
                            <p class="text-xs text-muted-foreground">{{ stats.subject_count }} subjects</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('accounting.notifications.index')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-primary/40 hover:shadow-md transition-all"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition-colors">
                            <Bell class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-gray-900">Notifications</p>
                            <p class="text-xs text-muted-foreground">Manage student alerts</p>
                        </div>
                    </Link>

                </div>
            </div>

        </div>
    </AppLayout>
</template>