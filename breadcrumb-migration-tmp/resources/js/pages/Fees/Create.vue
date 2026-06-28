<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    yearLevels: string[];
    semesters: string[];
    categories: string[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Fee Management', href: route('fees.index') },
    { title: 'Create Fee' },
];

const form = useForm({
    name: '',
    category: '',
    amount: '',
    year_level: '',
    semester: '',
    school_year: '',
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(route('fees.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Create Fee" />

        <div class="w-full p-6">
            <div class="mx-auto max-w-3xl">
                <h1 class="mb-6 text-3xl font-bold">Create New Fee</h1>

                <form @submit.prevent="submit" class="space-y-6 rounded-lg bg-white p-6 shadow-md">
                    <!-- Name -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Fee Name *</label>
                        <input v-model="form.name" type="text" class="w-full rounded border px-4 py-2" placeholder="e.g., Tuition Fee" required />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-500">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Category *</label>
                        <select v-model="form.category" class="w-full rounded border px-4 py-2" required>
                            <option value="">Select Category</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                        <div v-if="form.errors.category" class="mt-1 text-sm text-red-500">
                            {{ form.errors.category }}
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Amount *</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full rounded border px-4 py-2"
                            placeholder="0.00"
                            required
                        />
                        <div v-if="form.errors.amount" class="mt-1 text-sm text-red-500">
                            {{ form.errors.amount }}
                        </div>
                    </div>

                    <!-- Year Level -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Year Level *</label>
                        <select v-model="form.year_level" class="w-full rounded border px-4 py-2" required>
                            <option value="">Select Year Level</option>
                            <option v-for="level in yearLevels" :key="level" :value="level">
                                {{ level }}
                            </option>
                        </select>
                        <div v-if="form.errors.year_level" class="mt-1 text-sm text-red-500">
                            {{ form.errors.year_level }}
                        </div>
                    </div>

                    <!-- Semester -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Semester *</label>
                        <select v-model="form.semester" class="w-full rounded border px-4 py-2" required>
                            <option value="">Select Semester</option>
                            <option v-for="sem in semesters" :key="sem" :value="sem">
                                {{ sem }}
                            </option>
                        </select>
                        <div v-if="form.errors.semester" class="mt-1 text-sm text-red-500">
                            {{ form.errors.semester }}
                        </div>
                    </div>

                    <!-- School Year -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">School Year *</label>
                        <input
                            v-model="form.school_year"
                            type="text"
                            class="w-full rounded border px-4 py-2"
                            placeholder="e.g., 2025-2026"
                            required
                        />
                        <div v-if="form.errors.school_year" class="mt-1 text-sm text-red-500">
                            {{ form.errors.school_year }}
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Description</label>
                        <textarea
                            v-model="form.description"
                            class="w-full rounded border px-4 py-2"
                            rows="3"
                            placeholder="Optional description..."
                        ></textarea>
                        <div v-if="form.errors.description" class="mt-1 text-sm text-red-500">
                            {{ form.errors.description }}
                        </div>
                    </div>

                    <!-- Is Active -->
                    <div class="flex items-center">
                        <input v-model="form.is_active" type="checkbox" id="is_active" class="mr-2" />
                        <label for="is_active" class="text-sm font-medium">Active</label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between border-t pt-4">
                        <Link :href="route('fees.index')" class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"> Cancel </Link>
                        <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : 'Create Fee' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
