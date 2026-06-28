<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useDashboardRoute } from '@/composables/useDashboardRoute';

const page = usePage();
const { dashboardHref } = useDashboardRoute();
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="relative min-h-screen overflow-hidden text-blue-900" style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background-image: url('/images/welcome.png'); background-size: cover; background-position: center; background-attachment: fixed;">


            <!-- Header -->
            <header class="flex w-full items-center justify-between px-8 py-5">
                <div class="[&_span]:!text-white [&_span]:!text-lg [&_span]:font-bold">
                    <AppLogo />
                </div>
                <nav class="flex items-center gap-3">
                    <Link v-if="page.props.auth.user" :href="dashboardHref" class="rounded-lg px-4 py-2 text-sm font-medium text-blue-700 transition-all hover:bg-blue-100" style="border: 1px solid rgba(30,58,138,0.25);">Dashboard</Link>
                    <template v-else>
                        <Link :href="route('login')" class="rounded-lg px-4 py-2 text-sm font-medium text-blue-700 transition-all hover:bg-blue-100 hover:text-blue-900">Log in</Link>
                        <Link :href="route('register')" class="rounded-lg px-4 py-2 text-sm font-semibold text-blue-700 transition-all hover:opacity-90" style="background: rgba(30,58,138,0.15); border: 1px solid rgba(30,58,138,0.3);">Register</Link>
                    </template>
                </nav>
            </header>

            <!-- Hero -->
            <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-medium text-blue-700" style="background: rgba(30,58,138,0.08); border: 1px solid rgba(30,58,138,0.15);">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-400"></span>
                    AY 2026-2027 · CCDI Account Portal
                </div>

                <h1 class="mb-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight drop-shadow-lg md:text-5xl lg:text-6xl text-blue-900">
                    Computer Communication<br />Development Institute
                </h1>

                <p class="mb-10 max-w-xl text-base leading-relaxed text-blue-800 md:text-lg">
                    CCDI envisions providing a service of leadership through excellent instructions that will produce empowered and world-class I.T. graduates.
                </p>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Link
                        :href="page.props.auth.user ? dashboardHref : route('login')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-7 py-3.5 text-sm font-semibold text-white shadow-lg transition-all hover:scale-105 hover:opacity-90 bg-blue-600 hover:bg-blue-700"
                    >
                        Get Started
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m0 0l-7-7m7 7l-7 7" /></svg>
                    </Link>
                    <a href="https://www.ccdisorsogon.edu.ph/" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl px-7 py-3.5 text-sm font-medium text-blue-700 transition-all hover:bg-blue-100" style="border: 1px solid rgba(30,58,138,0.3);">
                        Learn more
                    </a>
                </div>
                
            </main>
    </div>
</template>