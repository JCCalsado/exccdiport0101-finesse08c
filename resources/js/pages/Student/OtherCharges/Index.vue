<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { formatCurrency } from '@/composables/useMoney';
import {
    BadgeDollarSign, CheckCircle, Clock, AlertTriangle,
    CreditCard, RefreshCw,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface OtherCharge {
    id: number;
    title: string;
    description: string | null;
    amount: number;
    school_year: string;
    semester: string | null;
    year_level: string | null;
    course: string | null;
    published_at: string | null;
    updated_after_publish_at: string | null;
    status: string;   // 'unpaid' | 'pending' | 'awaiting_approval' | 'paid' | 'failed'
    amount_paid: number;
    paid_at: string | null;
    or_number: string | null;
    payment_id: number | null;
}

const props = defineProps<{
    charges: OtherCharge[];
    paymentFeedback: string | null; // 'success' | 'cancelled' | null
}>();

const breadcrumbs = [
    { title: 'My Account', href: route('student.account') },
    { title: 'Other Charges' },
];

// ── Filter tabs ───────────────────────────────────────────────────────────────
const activeTab = ref<'pending' | 'paid' | 'all'>('pending');

const pendingCharges = computed(() =>
    props.charges.filter((c) => c.status !== 'paid'),
);
const paidCharges = computed(() =>
    props.charges.filter((c) => c.status === 'paid'),
);
const displayedCharges = computed(() => {
    if (activeTab.value === 'pending') return pendingCharges.value;
    if (activeTab.value === 'paid')    return paidCharges.value;
    return props.charges;
});

// ── Online Payment ────────────────────────────────────────────────────────────
const payingChargeId = ref<number | null>(null);
const payError       = ref<string | null>(null);

const payOnline = async (charge: OtherCharge) => {
    if (charge.status === 'pending') {
        alert('A payment for this charge is already in progress. Please wait or contact accounting.');
        return;
    }

    payingChargeId.value = charge.id;
    payError.value = null;

    try {
        const page      = usePage();
        const csrfToken = (page.props.csrf_token as string) ?? '';

        const response = await fetch(route('student.other-charges.pay', charge.id), {
            method:      'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || `Error: ${response.status}`);
        }

        if (!data.checkout_url) {
            throw new Error('No checkout URL returned. Please try again.');
        }

        window.location.href = data.checkout_url;

    } catch (err) {
        payError.value = err instanceof Error ? err.message : 'Payment could not be initiated. Please try again.';
    } finally {
        payingChargeId.value = null;
    }
};

// ── Status helpers ────────────────────────────────────────────────────────────
const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        paid:               'bg-green-100 text-green-800',
        unpaid:             'bg-gray-100 text-gray-700',
        pending:            'bg-yellow-100 text-yellow-800',
        awaiting_approval:  'bg-blue-100 text-blue-800',
        failed:             'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-700';
};

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        paid:               'Paid',
        unpaid:             'Unpaid',
        pending:            'In Progress',
        awaiting_approval:  'Awaiting Verification',
        failed:             'Payment Failed',
    };
    return map[status] ?? status;
};
</script>

<template>
    <AppLayout>
        <Head title="Other Charges" />

        <div class="w-full p-6 space-y-6">
            <div :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <BadgeDollarSign class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Other Charges</h1>
                    <p class="text-sm text-muted-foreground">View and pay event fees outside your regular assessment.</p>
                </div>
            </div>

            <!-- Payment feedback banners -->
            <div
                v-if="paymentFeedback === 'success'"
                class="flex items-center gap-3 rounded-xl border border-green-300 bg-green-50 p-4"
            >
                <CheckCircle class="h-5 w-5 text-green-600 flex-shrink-0" />
                <div>
                    <p class="font-semibold text-green-900">Payment submitted!</p>
                    <p class="text-sm text-green-700">Your payment is being processed. This page will update shortly.</p>
                </div>
            </div>

            <div
                v-if="paymentFeedback === 'cancelled'"
                class="flex items-center gap-3 rounded-xl border border-gray-300 bg-gray-50 p-4"
            >
                <AlertTriangle class="h-5 w-5 text-gray-500 flex-shrink-0" />
                <p class="text-sm text-gray-700">Payment was cancelled. You can try again anytime.</p>
            </div>

            <!-- Pay error -->
            <div
                v-if="payError"
                class="flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 p-4"
            >
                <AlertTriangle class="h-5 w-5 text-red-600 flex-shrink-0" />
                <p class="text-sm text-red-800">{{ payError }}</p>
            </div>

            <!-- Empty state -->
            <div
                v-if="charges.length === 0"
                class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
            >
                <BadgeDollarSign class="mx-auto h-10 w-10 text-gray-400 mb-3" />
                <p class="font-medium text-gray-700">No other charges for your account</p>
                <p class="text-sm text-muted-foreground mt-1">
                    Charges for events like Christmas Fee or Intramurals will appear here when published by accounting.
                </p>
            </div>

            <!-- Tabs + Charge Cards -->
            <div v-else class="space-y-4">

                <!-- Tabs -->
                <div class="flex gap-1 border-b border-gray-200">
                    <button
                        v-for="tab in [
                            { key: 'pending', label: 'Pending', count: pendingCharges.length },
                            { key: 'paid',    label: 'Paid',    count: paidCharges.length    },
                            { key: 'all',     label: 'All',     count: charges.length        },
                        ]"
                        :key="tab.key"
                        @click="activeTab = tab.key as any"
                        :class="[
                            'flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                            activeTab === tab.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tab.count > 0"
                            :class="[
                                'rounded-full px-1.5 py-0.5 text-xs font-bold',
                                activeTab === tab.key ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600',
                            ]"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </div>

                <!-- Charge cards -->
                <div v-if="displayedCharges.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No charges in this category.
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="charge in displayedCharges"
                        :key="charge.id"
                        class="rounded-xl border bg-white shadow-sm overflow-hidden"
                        :class="charge.status === 'paid' ? 'border-green-200' : 'border-gray-200'"
                    >
                        <!-- Card header -->
                        <div class="p-4 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-gray-900 leading-tight">{{ charge.title }}</p>
                                <span
                                    :class="['flex-shrink-0 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(charge.status)]"
                                >
                                    {{ statusLabel(charge.status) }}
                                </span>
                            </div>

                            <p v-if="charge.description" class="text-xs text-muted-foreground">
                                {{ charge.description }}
                            </p>

                            <!-- Updated notice -->
                            <div
                                v-if="charge.updated_after_publish_at && charge.status !== 'paid'"
                                class="flex items-center gap-1.5 rounded-lg bg-yellow-50 border border-yellow-200 px-2.5 py-1.5 text-xs text-yellow-800"
                            >
                                <AlertTriangle class="h-3 w-3 flex-shrink-0" />
                                This charge was recently updated by the Accounting Office.
                            </div>

                            <!-- Amount -->
                            <div class="pt-1">
                                <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(charge.amount) }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ charge.school_year }}
                                    <span v-if="charge.semester"> · {{ charge.semester }}</span>
                                    <span v-if="charge.year_level"> · {{ charge.year_level }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Card footer -->
                        <div class="border-t border-gray-100 bg-gray-50 px-4 py-3">
                            <!-- Paid state -->
                            <div v-if="charge.status === 'paid'" class="flex items-center gap-2 text-sm text-green-700">
                                <CheckCircle class="h-4 w-4" />
                                <span>
                                    Paid {{ charge.paid_at ? 'on ' + charge.paid_at : '' }}
                                    <span v-if="charge.or_number"> · OR# {{ charge.or_number }}</span>
                                </span>
                            </div>

                            <!-- In-progress state -->
                            <div v-else-if="charge.status === 'pending' || charge.status === 'awaiting_approval'" class="flex items-center gap-2 text-sm text-blue-700">
                                <Clock class="h-4 w-4" />
                                <span>{{ statusLabel(charge.status) }} — please wait.</span>
                            </div>

                            <!-- Failed state — allow retry -->
                            <div v-else-if="charge.status === 'failed'" class="space-y-2">
                                <p class="text-xs text-red-600">Previous payment failed. You may try again.</p>
                                <button
                                    @click="payOnline(charge)"
                                    :disabled="payingChargeId === charge.id"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
                                >
                                    <RefreshCw v-if="payingChargeId === charge.id" class="h-4 w-4 animate-spin" />
                                    <CreditCard v-else class="h-4 w-4" />
                                    {{ payingChargeId === charge.id ? 'Redirecting…' : 'Retry Payment' }}
                                </button>
                            </div>

                            <!-- Unpaid — primary CTA -->
                            <div v-else class="space-y-1.5">
                                <button
                                    @click="payOnline(charge)"
                                    :disabled="payingChargeId !== null"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
                                >
                                    <RefreshCw v-if="payingChargeId === charge.id" class="h-4 w-4 animate-spin" />
                                    <CreditCard v-else class="h-4 w-4" />
                                    {{ payingChargeId === charge.id ? 'Redirecting…' : 'Pay Online' }}
                                </button>
                                <p class="text-center text-xs text-muted-foreground">
                                    Or pay at the Accounting Office (OTC)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
