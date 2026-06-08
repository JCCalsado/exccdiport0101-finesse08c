<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatCurrency } from '@/composables/useMoney';
import { BadgeDollarSign, Plus, Eye, CheckCircle, Clock } from 'lucide-vue-next';

interface OtherCharge {
    id: number;
    title: string;
    description: string | null;
    amount: number;
    school_year: string;
    semester: string | null;
    year_level: string | null;
    course: string | null;
    status_label: string;
    is_published: boolean;
    published_at: string | null;
    created_by_name: string | null;
    matching_student_count: number;
    paid_count: number;
    total_collected: number;
}

const props = defineProps<{
    charges: OtherCharge[];
}>();

const statusBadge = (charge: OtherCharge) => {
    if (charge.status_label === 'Published') return 'bg-green-100 text-green-800';
    if (charge.status_label === 'Archived')  return 'bg-gray-100 text-gray-500';
    return 'bg-yellow-100 text-yellow-800'; // Draft
};

const targetLabel = (charge: OtherCharge): string => {
    const parts: string[] = [];
    if (charge.year_level) parts.push(charge.year_level);
    if (charge.course)     parts.push(charge.course);
    if (charge.semester)   parts.push(charge.semester);
    parts.push(charge.school_year);
    return parts.join(' · ');
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Accounting', href: route('accounting.dashboard') },
        { title: 'Other Charges' },
    ]">
        <Head title="Other Charges" />

        <div class="space-y-5 p-4 md:p-6">

            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <BadgeDollarSign class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Other Charges</h1>
                        <p class="text-sm text-muted-foreground">
                            Manage event and activity fees outside of student assessments
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('accounting.other-charges.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                >
                    <Plus class="h-4 w-4" />
                    New Charge
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="charges.length === 0"
                class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
            >
                <BadgeDollarSign class="mx-auto h-10 w-10 text-gray-400 mb-3" />
                <p class="text-sm font-medium text-gray-700">No other charges yet</p>
                <p class="text-sm text-muted-foreground mt-1 mb-4">
                    Create a charge for events like Christmas Fee, Intramurals, or Recollection.
                </p>
                <Link
                    :href="route('accounting.other-charges.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
                >
                    <Plus class="h-4 w-4" />
                    Create First Charge
                </Link>
            </div>

            <!-- Charges table -->
            <div v-else class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Charge</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Target Group</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Students</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Collected</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="charge in charges"
                            :key="charge.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ charge.title }}</p>
                                <p v-if="charge.description" class="text-xs text-muted-foreground mt-0.5 truncate max-w-xs">
                                    {{ charge.description }}
                                </p>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ formatCurrency(charge.amount) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground text-xs">
                                {{ targetLabel(charge) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(charge)]"
                                >
                                    <CheckCircle v-if="charge.is_published" class="h-3 w-3" />
                                    <Clock v-else class="h-3 w-3" />
                                    {{ charge.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-medium text-gray-900">{{ charge.paid_count }}</span>
                                <span class="text-muted-foreground"> / {{ charge.matching_student_count }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-green-700">
                                {{ formatCurrency(charge.total_collected) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Link
                                    :href="route('accounting.other-charges.show', charge.id)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
