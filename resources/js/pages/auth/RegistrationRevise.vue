<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertTriangle, ChevronRight, LoaderCircle, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Registration {
    id: number;
    tracking_token: string;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    gender: string | null;
    birthdate: string | null;
    civil_status: string | null;
    contact_number: string;
    email: string;
    address_house: string | null;
    address_street: string | null;
    address_barangay: string;
    address_city: string;
    address_province: string;
    address_zip: string | null;
    existing_student_id: string | null;
    course: string;
    year_level: string;
    semester: string;
    school_year: string;
    student_type: string;
    guardian_name: string | null;
    guardian_contact: string | null;
    emergency_contact: string | null;
    revision_notes: string | null;
    registrar_revision_notes: string | null;
    revision_stage: 'registrar' | 'finance' | null;
    has_valid_id: boolean;
    has_proof: boolean;
}

const props = defineProps<{
    registration: Registration;
    token: string;
}>();

// Which notes to display depends on which stage requested the revision
const revisionNotes = computed(() =>
    props.registration.revision_stage === 'finance'
        ? props.registration.revision_notes
        : props.registration.registrar_revision_notes
);

const reviewingOffice = computed(() =>
    props.registration.revision_stage === 'finance'
        ? 'Accounting Department (Finance)'
        : "Registrar's Office"
);

// File upload state
const validIdName  = ref(props.registration.has_valid_id ? '(existing file on record)' : '');
const proofName    = ref(props.registration.has_proof    ? '(existing file on record)' : '');

const form = useForm({
    last_name:            props.registration.last_name,
    first_name:           props.registration.first_name,
    middle_name:          props.registration.middle_name ?? '',
    suffix:               props.registration.suffix ?? '',
    gender:               props.registration.gender ?? '',
    birthdate:            props.registration.birthdate ?? '',
    civil_status:         props.registration.civil_status ?? '',
    contact_number:       props.registration.contact_number,
    email:                props.registration.email,
    address_house:        props.registration.address_house ?? '',
    address_street:       props.registration.address_street ?? '',
    address_barangay:     props.registration.address_barangay,
    address_city:         props.registration.address_city,
    address_province:     props.registration.address_province,
    address_zip:          props.registration.address_zip ?? '',
    existing_student_id:  props.registration.existing_student_id ?? '',
    course:               props.registration.course,
    year_level:           props.registration.year_level,
    semester:             props.registration.semester,
    school_year:          props.registration.school_year,
    student_type:         props.registration.student_type,
    guardian_name:        props.registration.guardian_name ?? '',
    guardian_contact:     props.registration.guardian_contact ?? '',
    emergency_contact:    props.registration.emergency_contact ?? '',
    valid_id:             null as File | null,
    proof_of_enrollment:  null as File | null,
});

const handleValidId = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.valid_id = file;
    validIdName.value = file?.name ?? '';
};

const handleProof = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.proof_of_enrollment = file;
    proofName.value = file?.name ?? '';
};

const submit = () => {
    form.post(route('registration.update', { token: props.token }), {
        forceFormData: true,
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Update Your Registration" />

        <div class="w-full max-w-2xl mx-auto space-y-6 py-8 px-4">

            <!-- Header -->
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-bold text-gray-900">Update Your Registration</h1>
                <p class="text-sm text-gray-500">Tracking ID: <span class="font-mono font-medium">{{ registration.tracking_token }}</span></p>
            </div>

            <!-- Revision notes banner -->
            <div v-if="revisionNotes" class="rounded-lg border border-amber-300 bg-amber-50 p-4 space-y-2">
                <div class="flex items-center gap-2 text-amber-800 font-semibold">
                    <AlertTriangle class="h-4 w-4 shrink-0" />
                    <span>Corrections Requested by {{ reviewingOffice }}</span>
                </div>
                <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ revisionNotes }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-8" enctype="multipart/form-data">

                <!-- ── Personal Information ──────────────────────────────────── -->
                <section class="space-y-4">
                    <h2 class="text-base font-semibold text-gray-800 border-b pb-1">Personal Information</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="last_name">Last Name <span class="text-red-500">*</span></Label>
                            <Input id="last_name" v-model="form.last_name" required />
                            <InputError :message="form.errors.last_name" />
                        </div>
                        <div class="space-y-1">
                            <Label for="first_name">First Name <span class="text-red-500">*</span></Label>
                            <Input id="first_name" v-model="form.first_name" required />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="space-y-1">
                            <Label for="middle_name">Middle Name</Label>
                            <Input id="middle_name" v-model="form.middle_name" />
                            <InputError :message="form.errors.middle_name" />
                        </div>
                        <div class="space-y-1">
                            <Label for="suffix">Suffix</Label>
                            <Input id="suffix" v-model="form.suffix" placeholder="Jr., Sr., III…" />
                            <InputError :message="form.errors.suffix" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="gender">Gender</Label>
                            <select id="gender" v-model="form.gender" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">Select…</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>
                        <div class="space-y-1">
                            <Label for="civil_status">Civil Status</Label>
                            <select id="civil_status" v-model="form.civil_status" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">Select…</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                            <InputError :message="form.errors.civil_status" />
                        </div>
                        <div class="space-y-1">
                            <Label for="birthdate">Birthdate</Label>
                            <Input id="birthdate" v-model="form.birthdate" type="date" />
                            <InputError :message="form.errors.birthdate" />
                        </div>
                        <div class="space-y-1">
                            <Label for="contact_number">Contact Number <span class="text-red-500">*</span></Label>
                            <Input id="contact_number" v-model="form.contact_number" required />
                            <InputError :message="form.errors.contact_number" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="email">Email Address <span class="text-red-500">*</span></Label>
                        <Input id="email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                </section>

                <!-- ── Address ──────────────────────────────────────────────── -->
                <section class="space-y-4">
                    <h2 class="text-base font-semibold text-gray-800 border-b pb-1">Address</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="address_house">House / Lot / Unit</Label>
                            <Input id="address_house" v-model="form.address_house" />
                            <InputError :message="form.errors.address_house" />
                        </div>
                        <div class="space-y-1">
                            <Label for="address_street">Street</Label>
                            <Input id="address_street" v-model="form.address_street" />
                            <InputError :message="form.errors.address_street" />
                        </div>
                        <div class="space-y-1">
                            <Label for="address_barangay">Barangay <span class="text-red-500">*</span></Label>
                            <Input id="address_barangay" v-model="form.address_barangay" required />
                            <InputError :message="form.errors.address_barangay" />
                        </div>
                        <div class="space-y-1">
                            <Label for="address_city">City / Municipality <span class="text-red-500">*</span></Label>
                            <Input id="address_city" v-model="form.address_city" required />
                            <InputError :message="form.errors.address_city" />
                        </div>
                        <div class="space-y-1">
                            <Label for="address_province">Province <span class="text-red-500">*</span></Label>
                            <Input id="address_province" v-model="form.address_province" required />
                            <InputError :message="form.errors.address_province" />
                        </div>
                        <div class="space-y-1">
                            <Label for="address_zip">ZIP Code</Label>
                            <Input id="address_zip" v-model="form.address_zip" />
                            <InputError :message="form.errors.address_zip" />
                        </div>
                    </div>
                </section>

                <!-- ── Academic Information ──────────────────────────────────── -->
                <section class="space-y-4">
                    <h2 class="text-base font-semibold text-gray-800 border-b pb-1">Academic Information</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="course">Course <span class="text-red-500">*</span></Label>
                            <Input id="course" v-model="form.course" required />
                            <InputError :message="form.errors.course" />
                        </div>
                        <div class="space-y-1">
                            <Label for="year_level">Year Level <span class="text-red-500">*</span></Label>
                            <select id="year_level" v-model="form.year_level" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="5th Year">5th Year</option>
                            </select>
                            <InputError :message="form.errors.year_level" />
                        </div>
                        <div class="space-y-1">
                            <Label for="semester">Semester <span class="text-red-500">*</span></Label>
                            <select id="semester" v-model="form.semester" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                            <InputError :message="form.errors.semester" />
                        </div>
                        <div class="space-y-1">
                            <Label for="school_year">School Year <span class="text-red-500">*</span></Label>
                            <Input id="school_year" v-model="form.school_year" placeholder="e.g. 2024-2025" required />
                            <InputError :message="form.errors.school_year" />
                        </div>
                        <div class="space-y-1">
                            <Label for="student_type">Student Type <span class="text-red-500">*</span></Label>
                            <select id="student_type" v-model="form.student_type" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="new">New Student</option>
                                <option value="old">Old Student</option>
                                <option value="returnee">Returnee</option>
                                <option value="transferee">Transferee</option>
                            </select>
                            <InputError :message="form.errors.student_type" />
                        </div>
                        <div class="space-y-1">
                            <Label for="existing_student_id">Existing Student ID</Label>
                            <Input id="existing_student_id" v-model="form.existing_student_id" placeholder="If returning student" />
                            <InputError :message="form.errors.existing_student_id" />
                        </div>
                    </div>
                </section>

                <!-- ── Guardian & Emergency ──────────────────────────────────── -->
                <section class="space-y-4">
                    <h2 class="text-base font-semibold text-gray-800 border-b pb-1">Guardian & Emergency Contact</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="guardian_name">Guardian Name</Label>
                            <Input id="guardian_name" v-model="form.guardian_name" />
                            <InputError :message="form.errors.guardian_name" />
                        </div>
                        <div class="space-y-1">
                            <Label for="guardian_contact">Guardian Contact</Label>
                            <Input id="guardian_contact" v-model="form.guardian_contact" />
                            <InputError :message="form.errors.guardian_contact" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <Label for="emergency_contact">Emergency Contact</Label>
                            <Input id="emergency_contact" v-model="form.emergency_contact" />
                            <InputError :message="form.errors.emergency_contact" />
                        </div>
                    </div>
                </section>

                <!-- ── Documents ─────────────────────────────────────────────── -->
                <section class="space-y-4">
                    <h2 class="text-base font-semibold text-gray-800 border-b pb-1">Documents</h2>
                    <p class="text-sm text-gray-500">Only re-upload if the document was flagged. Existing files are kept if left blank.</p>

                    <div class="space-y-3">
                        <!-- Valid ID -->
                        <div class="space-y-1">
                            <Label>Valid ID</Label>
                            <label class="flex items-center gap-3 cursor-pointer rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors">
                                <Upload class="h-4 w-4 text-gray-400 shrink-0" />
                                <span class="text-sm text-gray-600 truncate">
                                    {{ validIdName || 'Click to upload Valid ID (JPG, PNG, PDF — max 5MB)' }}
                                </span>
                                <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf" @change="handleValidId" />
                            </label>
                            <InputError :message="form.errors.valid_id" />
                        </div>

                        <!-- Proof of Enrollment -->
                        <div class="space-y-1">
                            <Label>Proof of Enrollment</Label>
                            <label class="flex items-center gap-3 cursor-pointer rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors">
                                <Upload class="h-4 w-4 text-gray-400 shrink-0" />
                                <span class="text-sm text-gray-600 truncate">
                                    {{ proofName || 'Click to upload Proof of Enrollment (JPG, PNG, PDF — max 5MB)' }}
                                </span>
                                <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf" @change="handleProof" />
                            </label>
                            <InputError :message="form.errors.proof_of_enrollment" />
                        </div>
                    </div>
                </section>

                <!-- Global error -->
                <InputError v-if="form.errors.error" :message="form.errors.error" class="text-sm" />

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <ChevronRight v-else class="h-4 w-4" />
                        {{ form.processing ? 'Resubmitting…' : 'Resubmit Registration' }}
                    </Button>
                </div>

            </form>
        </div>
    </AuthLayout>
</template>