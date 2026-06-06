<script setup lang="ts">
import type { StudentUser } from '@/types/user';
import type { Page } from '@inertiajs/core';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}
defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Profile settings', href: route('profile.edit') },
];

type AppPageProps = Page['props'] & {
    auth: { user: StudentUser };
    latestAssessmentInfo?: { year_level: string; semester: string; school_year: string } | null;
};

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);

const displayYearLevel = computed(() => {
    const assessment = (page.props as any).latestAssessmentInfo;
    if (assessment?.year_level) return assessment.year_level;
    return user.value.year_level ?? '';
});

const userRole = computed(() => {
    const role = (user.value as any).role;
    if (!role) return 'student';
    if (typeof role === 'string') return role;
    return role.value ?? role.name ?? 'student';
});

const accountingType = computed(() => (user.value as any).accounting_type as string | null);

const isStudent    = computed(() => userRole.value === 'student');
const isAdmin      = computed(() => userRole.value === 'admin');
const isRegistrar  = computed(() => userRole.value === 'registrar');
// Staff section: accounting, admin, and registrar all have a department/position to display
const isStaff      = computed(() => ['accounting', 'admin', 'registrar'].includes(userRole.value));

const accountingTypeLabel = computed(() => {
    if (!accountingType.value) return null;
    const map: Record<string, string> = {
        cashier:             'Cashier',
        bookkeeper:          'Bookkeeper',
        disbursing_officer:  'Disbursing Officer',
    };
    return map[accountingType.value] ?? accountingType.value;
});

const initialStatus = computed(() => {
    const s = (user.value as any).status;
    if (!s) return 'active';
    if (typeof s === 'string') return s;
    return s.value ?? s.name ?? 'active';
});

const formatDate = (val: any): string => {
    if (!val) return '';
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    try { return new Date(val).toISOString().split('T')[0]; } catch { return ''; }
};

const form = useForm({
    // ── Personal ──────────────────────────────────────────────────
    last_name:    user.value.last_name  ?? '',
    first_name:   user.value.first_name ?? '',
    middle_name:  user.value.middle_name ?? '',
    suffix:       (user.value as any).suffix ?? '',
    gender:       (user.value as any).gender ?? '',
    civil_status: (user.value as any).civil_status ?? '',
    birthday:     formatDate(user.value.birthday),
    email:        user.value.email ?? '',
    phone:        user.value.phone ?? '',

    // ── Address ───────────────────────────────────────────────────
    address_house_lot_unit:    (user.value as any).address_house_lot_unit    ?? '',
    address_street_name:       (user.value as any).address_street_name       ?? '',
    address_barangay:          (user.value as any).address_barangay          ?? '',
    address_municipality_city: (user.value as any).address_municipality_city ?? '',
    address_province:          (user.value as any).address_province          ?? 'Sorsogon',
    address_zip:               (user.value as any).address_zip               ?? '',

    // ── Guardian / Emergency ──────────────────────────────────────
    guardian_name:     (user.value as any).guardian_name     ?? '',
    guardian_contact:  (user.value as any).guardian_contact  ?? '',
    emergency_contact: (user.value as any).emergency_contact ?? '',

    // ── Academic / Staff ──────────────────────────────────────────
    account_id: user.value.account_id  ?? '',
    course:     user.value.course      ?? '',
    year_level: user.value.year_level  ?? '',
    faculty:    (user.value as any).faculty     ?? '',
    status:     initialStatus.value,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['auth'] }),
        onError:   (errors: any) => console.error('Profile update errors:', errors),
    });
};

// ── PROFILE PICTURE ───────────────────────────────────────────────────────────

const profilePicturePreview  = ref<string | null>(user.value.avatar ?? null);
const profilePictureError    = ref<string | undefined>();
const profilePictureInput    = ref<HTMLInputElement | null>(null);

const profilePictureForm = useForm<{ profile_picture: File | null }>({ profile_picture: null });

const selectProfilePicture = () => profilePictureInput.value?.click();

const updateProfilePicturePreview = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    profilePictureForm.profile_picture = file;

    const reader = new FileReader();
    reader.onload = (e) => { profilePicturePreview.value = e.target?.result as string; };
    reader.readAsDataURL(file);

    profilePictureForm.post(route('profile.update-picture'), {
        forceFormData: true,
        onError: (errors) => {
            profilePictureError.value = (errors as any).profile_picture ?? undefined;
            profilePicturePreview.value = user.value.avatar ?? null;
        },
        onSuccess: () => {
            profilePictureError.value = undefined;
            router.reload({ only: ['auth'] });
        },
    });
};

const removeProfilePicture = () => {
    router.delete(route('profile.remove-picture'), {
        onSuccess: () => { profilePicturePreview.value = null; },
    });
};

const hasProfilePicture = computed(() => !!profilePicturePreview.value);

const profileInitial = computed(() => {
    if (form.first_name) return form.first_name.charAt(0).toUpperCase();
    return user.value.name?.charAt(0)?.toUpperCase() ?? '?';
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="space-y-8">

                <!-- ═══════════════════════════════════════════════════ -->
                <!-- PROFILE PICTURE                                      -->
                <!-- ═══════════════════════════════════════════════════ -->
                <div class="flex items-center gap-6">
                    <div class="relative">
                        <div
                            v-if="hasProfilePicture"
                            class="h-20 w-20 overflow-hidden rounded-full border-2 border-gray-200"
                        >
                            <img
                                :src="profilePicturePreview!"
                                alt="Profile"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div
                            v-else
                            class="flex h-20 w-20 items-center justify-center rounded-full border-2 border-dashed border-gray-300 bg-gray-50 text-2xl font-bold text-gray-500"
                        >
                            {{ profileInitial }}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-medium text-gray-700">Profile Picture</p>
                        <div class="flex gap-2">
                            <input
                                ref="profilePictureInput"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                class="hidden"
                                @change="updateProfilePicturePreview"
                            />
                            <Button type="button" variant="outline" size="sm" @click="selectProfilePicture">
                                Change
                            </Button>
                            <Button
                                v-if="hasProfilePicture"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-red-600 hover:text-red-700"
                                @click="removeProfilePicture"
                            >
                                Remove
                            </Button>
                        </div>
                        <p v-if="profilePictureError" class="text-xs text-red-600">{{ profilePictureError }}</p>
                        <p class="text-xs text-gray-400">JPG, PNG, GIF or WebP. Max 2MB.</p>
                    </div>
                </div>

                <Separator />

                <form class="space-y-8" @submit.prevent="submit">

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SECTION 1 — PERSONAL INFORMATION                -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <section class="space-y-4">
                        <HeadingSmall
                            title="Personal Information"
                            description="Your legal name and personal details"
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="last_name">Last Name</Label>
                                <Input id="last_name" v-model="form.last_name" autocomplete="family-name" />
                                <InputError :message="form.errors.last_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="first_name">First Name</Label>
                                <Input id="first_name" v-model="form.first_name" autocomplete="given-name" />
                                <InputError :message="form.errors.first_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="middle_name">Middle Name</Label>
                                <Input id="middle_name" v-model="form.middle_name" autocomplete="additional-name" />
                                <InputError :message="form.errors.middle_name" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="suffix">Suffix</Label>
                                <Input id="suffix" v-model="form.suffix" placeholder="Jr., Sr., III, etc." />
                                <InputError :message="form.errors.suffix" />
                            </div>
                            <div class="space-y-2">
                                <Label for="gender">Gender</Label>
                                <select
                                    id="gender"
                                    v-model="form.gender"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">— Select —</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                                <InputError :message="form.errors.gender" />
                            </div>
                            <div class="space-y-2">
                                <Label for="civil_status">Civil Status</Label>
                                <select
                                    id="civil_status"
                                    v-model="form.civil_status"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">— Select —</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                </select>
                                <InputError :message="form.errors.civil_status" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="birthday">Date of Birth</Label>
                                <Input id="birthday" type="date" v-model="form.birthday" />
                                <InputError :message="form.errors.birthday" />
                            </div>
                            <div class="space-y-2">
                                <Label for="email">Email Address</Label>
                                <Input id="email" type="email" v-model="form.email" autocomplete="email" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="space-y-2">
                                <Label for="phone">Phone Number</Label>
                                <Input id="phone" v-model="form.phone" autocomplete="tel" placeholder="09XXXXXXXXX" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                    </section>

                    <Separator />

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SECTION 2 — ADDRESS                              -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <section class="space-y-4">
                        <HeadingSmall
                            title="Address"
                            description="Your current residential address"
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="address_house_lot_unit">Unit / Lot No.</Label>
                                <Input id="address_house_lot_unit" v-model="form.address_house_lot_unit" placeholder="Unit/Lot No." />
                                <InputError :message="form.errors.address_house_lot_unit" />
                            </div>
                            <div class="space-y-2">
                                <Label for="address_street_name">Street Name</Label>
                                <Input id="address_street_name" v-model="form.address_street_name" placeholder="Street Name" />
                                <InputError :message="form.errors.address_street_name" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label>Barangay</Label>
                                <Input v-model="form.address_barangay" placeholder="Barangay" />
                                <InputError :message="form.errors.address_barangay" />
                            </div>
                            <div class="space-y-2">
                                <Label>City / Municipality</Label>
                                <Input v-model="form.address_municipality_city" placeholder="City/Municipality" />
                                <InputError :message="form.errors.address_municipality_city" />
                            </div>
                            <div class="space-y-2">
                                <Label>Province</Label>
                                <Input v-model="form.address_province" placeholder="Province" />
                                <InputError :message="form.errors.address_province" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="address_zip">ZIP Code</Label>
                                <Input id="address_zip" v-model="form.address_zip" placeholder="4700" maxlength="10" />
                                <InputError :message="form.errors.address_zip" />
                            </div>
                        </div>
                    </section>

                    <Separator />

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SECTION 3 — ACADEMIC (Students only)            -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <section v-if="isStudent" class="space-y-4">
                        <HeadingSmall
                            title="Academic Information"
                            description="Your enrollment details — contact accounting to change course or year level"
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label>Account ID</Label>
                                <div class="flex items-center rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    {{ form.account_id || 'Not assigned' }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Course</Label>
                                <div class="flex items-center rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    {{ form.course || 'Not assigned' }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Year Level</Label>
                                <div class="flex items-center rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    {{ displayYearLevel || 'Not assigned' }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="status">Status</Label>
                                <select
                                    v-if="isAdmin"
                                    id="status"
                                    v-model="form.status"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="active">Active</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                                <div v-else class="flex items-center gap-2 rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground capitalize">
                                    {{ form.status }}
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                                            user.is_irregular ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700',
                                        ]"
                                    >{{ user.is_irregular ? 'Irregular' : 'Regular' }}</span>
                                </div>
                                <InputError :message="form.errors.status" />
                            </div>
                        </div>
                    </section>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SECTION 3b — STAFF INFO (Accounting/Admin/Registrar) -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <section v-if="isStaff" class="space-y-4">
                        <HeadingSmall
                            title="Staff Information"
                            description="Your department and position details"
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Department — read-only, set by Admin -->
                            <div class="space-y-2">
                                <Label>Department</Label>
                                <div class="flex items-center rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    {{ (user as any).department ?? '—' }}
                                </div>
                                <p class="text-xs text-muted-foreground">Assigned by Admin — contact Admin to change.</p>
                            </div>

                            <!-- Position / Sub-role — read-only, only visible for Accounting staff -->
                            <div v-if="accountingType" class="space-y-2">
                                <Label>Position</Label>
                                <div class="flex items-center rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    {{ accountingTypeLabel ?? '—' }}
                                </div>
                                <p class="text-xs text-muted-foreground">Assigned by Admin — contact Admin to change.</p>
                            </div>
                        </div>

                        <!-- Faculty / Department (editable free-text) -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="faculty">Faculty / Room Assignment <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                <Input id="faculty" v-model="form.faculty" autocomplete="organization" placeholder="e.g., Room 201, Admin Building" />
                                <InputError :message="form.errors.faculty" />
                            </div>
                        </div>
                    </section>

                    <Separator />

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SECTION 4 — GUARDIAN & EMERGENCY CONTACT        -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <section class="space-y-4">
                        <HeadingSmall
                            title="Guardian & Emergency Contact"
                            description="Contact information in case of emergency"
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="guardian_name">Guardian Name</Label>
                                <Input id="guardian_name" v-model="form.guardian_name" placeholder="Full name of parent or guardian" />
                                <InputError :message="form.errors.guardian_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="guardian_contact">Guardian Contact Number</Label>
                                <Input id="guardian_contact" v-model="form.guardian_contact" placeholder="09171234567" />
                                <InputError :message="form.errors.guardian_contact" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="emergency_contact">Emergency Contact</Label>
                                <Input id="emergency_contact" v-model="form.emergency_contact" placeholder="Name and/or number" />
                                <InputError :message="form.errors.emergency_contact" />
                            </div>
                        </div>
                    </section>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- SAVE BUTTON                                      -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <div class="flex items-center gap-4 pt-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save Changes' }}
                        </Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>

                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>