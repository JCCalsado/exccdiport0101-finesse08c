<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { formatCurrency } from '@/composables/useMoney';
import { BadgeDollarSign, Users, Info } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    schoolYears: string[];
    semesters: string[];
    yearLevels: string[];
    courses: string[];
}>();

const form = useForm({
    title:       '',
    description: '',
    amount:      '' as string | number,
    // schoolYears is now sorted descending from the controller (most recent active AY first),
    // so [0] is the year with enrolled students rather than a future calendar year.
    school_year: props.schoolYears[0] ?? '',
    semester:    '' as string,
    year_level:  '' as string,
    course:      '' as string,
});

// ── Live student count preview ────────────────────────────────────────────────

const previewCount = ref<number | null>(null);
const previewLoading = ref(false);
let previewDebounce: ReturnType<typeof setTimeout>;

const fetchPreview = () => {
    clearTimeout(previewDebounce);
    previewDebounce = setTimeout(async () => {
        if (!form.school_year) {
            previewCount.value = null;
            return;
        }
        previewLoading.value = true;
        try {
            const params = new URLSearchParams({
                school_year: form.school_year,
                ...(form.semester   ? { semester: form.semester }     : {}),
                ...(form.year_level ? { year_level: form.year_level } : {}),
                ...(form.course     ? { course: form.course }         : {}),
            });
            const res = await fetch(
                route('accounting.other-charges.preview-count') + '?' + params.toString(),
                { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
            );
            if (res.ok) {
                const data = await res.json();
                previewCount.value = data.count;
            }
        } catch {
            previewCount.value = null;
        } finally {
            previewLoading.value = false;
        }
    }, 500);
};

watch([() => form.school_year, () => form.semester, () => form.year_level, () => form.course], fetchPreview, { immediate: true });

// ── Submit ────────────────────────────────────────────────────────────────────

const submit = () => {
    form.post(route('accounting.other-charges.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Accounting', href: route('accounting.dashboard') },
        { title: 'Other Charges', href: route('accounting.other-charges.index') },
        { title: 'Create' },
    ]">
        <Head title="Create Other Charge" />

        <div class="p-4 md:p-6 max-w-2xl space-y-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <BadgeDollarSign class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">New Other Charge</h1>
                    <p class="text-sm text-muted-foreground">Define a charge for events like Christmas Fee, Intramurals, etc.</p>
                </div>
            </div>

            <!-- Flash error -->
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
                        placeholder="e.g. Christmas Fee 2025-2026"
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
                        placeholder="Optional details or instructions for students"
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
                        placeholder="0.00"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        :class="{ 'border-red-400': form.errors.amount }"
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Full payment only — students must pay the exact amount.</p>
                </div>

                <!-- Targeting Filters -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-muted-foreground" />
                        <p class="text-sm font-semibold text-gray-800">Target Group</p>
                    </div>
                    <p class="text-xs text-muted-foreground -mt-2">
                        Leave filters blank to target ALL enrolled students. Filters are applied cumulatively.
                    </p>

                    <!-- School Year — required -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            School Year <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.school_year"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        >
                            <option v-for="sy in schoolYears" :key="sy" :value="sy">{{ sy }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Semester -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Semester</label>
                            <select
                                v-model="form.semester"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                            >
                                <option value="">All Semesters</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>

                        <!-- Year Level -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
                            <select
                                v-model="form.year_level"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                            >
                                <option value="">All Year Levels</option>
                                <option v-for="yl in yearLevels" :key="yl" :value="yl">{{ yl }}</option>
                            </select>
                        </div>

                        <!-- Course -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Course</label>
                            <select
                                v-model="form.course"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                            >
                                <option value="">All Courses</option>
                                <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live preview -->
                    <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                        <Info class="h-4 w-4 text-blue-600 flex-shrink-0" />
                        <p class="text-xs text-blue-800">
                            <span v-if="previewLoading">Calculating matching students…</span>
                            <span v-else-if="previewCount !== null">
                                This charge will apply to
                                <strong>{{ previewCount }} student{{ previewCount !== 1 ? 's' : '' }}</strong>
                                currently enrolled with an active assessment.
                                Late enrollees will automatically qualify.
                            </span>
                            <span v-else>Select a school year to preview the matching student count.</span>
                        </p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {{ form.processing ? 'Saving…' : 'Save as Draft' }}
                    </button>
                    <a
                        :href="route('accounting.other-charges.index')"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
