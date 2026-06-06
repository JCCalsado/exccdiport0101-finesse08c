<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    admin?: any;
    isEditing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isEditing: false,
});

const form = useForm({
    last_name:             props.admin?.last_name             ?? '',
    first_name:            props.admin?.first_name            ?? '',
    middle_initial:        props.admin?.middle_initial        ?? '',
    email:                 props.admin?.email                 ?? '',
    password:              '',
    password_confirmation: '',
    department:            props.admin?.department            ?? 'Accounting',
    accounting_type:       props.admin?.accounting_type       ?? '',
    is_active:             props.admin?.is_active             ?? true,
});

// Show accounting_type selector only when department = Accounting
const isAccountingDept = computed(() => form.department === 'Accounting');

// Clear accounting_type when switching away from Accounting
const onDepartmentChange = () => {
    if (form.department !== 'Accounting') {
        form.accounting_type = '';
    }
};

const submitLabel = computed(() => {
    if (form.processing) return 'Saving…';
    if (props.isEditing) return 'Update Staff Member';
    return form.department === 'Registrar'
        ? 'Create Registrar Staff'
        : 'Create Accounting Staff';
});

const submit = () => {
    if (props.isEditing) {
        form.put(route('users.update', props.admin.id));
    } else {
        form.post(route('users.store'));
    }
};

const goBack = () => { history.back(); };
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">

        <!-- Name fields -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <Label for="last_name">Last Name *</Label>
                <Input id="last_name" v-model="form.last_name" type="text" required />
                <InputError :message="form.errors.last_name" />
            </div>
            <div>
                <Label for="first_name">First Name *</Label>
                <Input id="first_name" v-model="form.first_name" type="text" required />
                <InputError :message="form.errors.first_name" />
            </div>
            <div>
                <Label for="middle_initial">Middle Initial</Label>
                <Input id="middle_initial" v-model="form.middle_initial" type="text" maxlength="1" class="uppercase" />
                <InputError :message="form.errors.middle_initial" />
            </div>
        </div>

        <!-- Email -->
        <div>
            <Label for="email">Email Address *</Label>
            <Input id="email" v-model="form.email" type="email" required />
            <InputError :message="form.errors.email" />
        </div>

        <!-- Password -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <Label for="password">
                    {{ isEditing ? 'New Password (leave blank to keep)' : 'Password *' }}
                </Label>
                <Input id="password" v-model="form.password" type="password" :required="!isEditing" autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div>
                <Label for="password_confirmation">
                    Confirm Password{{ isEditing ? '' : ' *' }}
                </Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" :required="!isEditing" autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>
        </div>

        <!-- Department -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <Label for="department">Department *</Label>
                <select
                    id="department"
                    v-model="form.department"
                    @change="onDepartmentChange"
                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                >
                    <option value="Accounting">Accounting</option>
                    <option value="Registrar">Registrar</option>
                </select>
                <InputError :message="form.errors.department" />
                <p class="mt-1 text-xs text-muted-foreground">
                    Administrator accounts cannot be created via this form.
                </p>
            </div>

            <!-- Accounting sub-role — only shown when department = Accounting -->
            <div v-if="isAccountingDept">
                <Label for="accounting_type">Accounting Role *</Label>
                <select
                    id="accounting_type"
                    v-model="form.accounting_type"
                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                >
                    <option value="" disabled>Select a role…</option>
                    <option value="cashier">Cashier — records over-the-counter payments</option>
                    <option value="bookkeeper">Bookkeeper — read-only financial reports</option>
                    <option value="disbursing_officer">Disbursing Officer — full accounting access</option>
                </select>
                <InputError :message="form.errors.accounting_type" />
            </div>

            <!-- Registrar info badge -->
            <div v-else class="flex items-end">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <p class="font-semibold">Registrar</p>
                    <p class="text-xs mt-0.5 text-blue-600">Manages academic clearance, curriculum presets, subjects, and student notifications.</p>
                </div>
            </div>
        </div>

        <!-- Active Status -->
        <div class="w-48">
            <Label for="is_active">Active Status</Label>
            <select
                id="is_active"
                v-model="form.is_active"
                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option :value="true">Active</option>
                <option :value="false">Inactive</option>
            </select>
            <InputError :message="form.errors.is_active" />
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" @click="goBack">Cancel</Button>
        </div>
    </form>
</template>