<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { formatCurrency } from '@/composables/useMoney';
import { BadgeDollarSign, AlertTriangle } from 'lucide-vue-next';

interface Charge {
    id: number;
    title: string;
    description: string | null;
    amount: number;
    school_year: string;
    semester: string | null;
    year_level: string | null;
    course: string | null;
    is_published: boolean;
}

const props = defineProps<{
    charge: Charge;
    hasPaidStudents: boolean;
    schoolYears: string[];
    semesters: string[];
    yearLevels: string[];
    courses: string[];
}>();

const form = useForm({
    title:       props.charge.title,
    description: props.charge.description ?? '',
    amount:      props.charge.amount,
    school_year: props.charge.school_year,
    semester:    props.charge.semester ?? '',
    year_level:  props.charge.year_level ?? '',
    course:      props.charge.course ?? '',
});

const submit = () => {
    form.put(route('accounting.other-charges.update', props.charge.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: route('accounting.dashboard') },
        { title: 'Other Charges', href: route('accounting.other-charges.index') },
        { title: charge.title, href: route('accounting.other-charges.show', charge.id) },
        { title: 'Edit' },
    ]">
        <Head :title="`Edit – ${charge.title}`" />

        <div class="p-4 md:p-6 max-w-2xl space-y-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <BadgeDollarSign class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Edit Charge</h1>
                    <p class="text-sm text-muted-foreground">{{ charge.title }}</p>
                </div>
            </div>

            <!-- Warning: has paid students -->
            <div
                v-if="hasPaidStudents && charge.is_published"
                class="flex items-start gap-3 rounded-xl border border-yellow-300 bg-yellow-50 p-4"
            >
                <AlertTriangle class="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-yellow-800">
                    <p class="font-semibold">Some students have already paid this charge</p>
                    <p class="mt-0.5">
                        Editing the amount will <strong>not</strong> retroactively adjust paid records.
                        Students who have already paid will see a notice that the charge was updated.
                        Proceed only if you intend this for future payers.
                    </p>
                </div>
            </div>

            <!-- Errors -->
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <ul class="list-disc list-inside space-y-1">
                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                </ul>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Charge Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        :class="{ 'border-red-400': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                    />
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Amount (₱) <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        :class="{ 'border-red-400': form.errors.amount }"
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                </div>

                <!-- Targeting Filters -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-4">
                    <p class="text-sm font-semibold text-gray-800">Target Group</p>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">School Year <span class="text-red-500">*</span></label>
                        <select v-model="form.school_year" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                            <option v-for="sy in schoolYears" :key="sy" :value="sy">{{ sy }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Semester</label>
                            <select v-model="form.semester" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <option value="">All Semesters</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
                            <select v-model="form.year_level" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <option value="">All Year Levels</option>
                                <option v-for="yl in yearLevels" :key="yl" :value="yl">{{ yl }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Course</label>
                            <select v-model="form.course" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                                <option value="">All Courses</option>
                                <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                    <a
                        :href="route('accounting.other-charges.show', charge.id)"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
