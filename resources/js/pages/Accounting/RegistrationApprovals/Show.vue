<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    AlertTriangle, CheckCircle2, ChevronLeft, FileText, User, XCircle, Info
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Registration {
    id: number;
    tracking_token: string;
    full_name: string;
    full_address: string;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    gender: string | null;
    birthdate: string;
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
    has_valid_id: boolean;
    has_proof: boolean;
    status: string;
    status_label: string;
    status_color: string;
    rejection_reason: string | null;
    revision_notes: string | null;
    submitted_at: string;
    reviewed_at: string | null;
    reviewer_name: string | null;
    is_finance_actionable: boolean;
    revision_stage: string | null;
    registrar_reviewed_at: string | null;
    registrar_reviewer_name: string | null;
    registrar_rejection_reason: string | null;
    registrar_revision_notes: string | null;
}

interface ExistingUser {
    id: number;
    name: string;
    email: string;
    account_id: string;
    is_active: boolean;
    is_same_person: boolean;
}

const props = defineProps<{
    registration: Registration;
    duplicates: any[];
    existingUser: ExistingUser | null;
    documentUrls: { valid_id: string | null; proof: string | null };
}>();

// ── Modals ─────────────────────────────────────────────────────────────────
const showRejectModal   = ref(false);
const showRevisionModal = ref(false);

// ── Reject form ────────────────────────────────────────────────────────────
const rejectForm = useForm({ rejection_reason: '' });

const submitReject = () => {
    rejectForm.post(route('accounting.registrations.reject', props.registration.id), {
        onSuccess: () => { showRejectModal.value = false; },
    });
};

// ── Revision request form ──────────────────────────────────────────────────
const revisionForm = useForm({ revision_notes: '' });

const submitRevision = () => {
    revisionForm.post(route('accounting.registrations.request-revision', props.registration.id), {
        onSuccess: () => { showRevisionModal.value = false; },
    });
};

// ── Approve ────────────────────────────────────────────────────────────────
const approving = ref(false);

// Hard block only when there's an existing user who is a DIFFERENT person.
// Same-person match (returning student) is allowed through.
const isHardBlocked = computed(() =>
    props.existingUser !== null && !props.existingUser.is_same_person
);

const approveLabel = computed(() => {
    if (approving.value) return 'Approving…';
    if (props.existingUser?.is_same_person) return 'Approve (Returning Student)';
    return 'Approve';
});

const approve = () => {
    const confirmMsg = props.existingUser?.is_same_person
        ? `Approve the registration for ${props.registration.full_name}?\n\nThis person already has an account (${props.existingUser.account_id}). Approving will update their enrollment data — no new account will be created.`
        : `Approve the registration for ${props.registration.full_name}?\n\nThis will create their account immediately.`;

    if (!confirm(confirmMsg)) return;
    approving.value = true;
    router.post(
        route('accounting.registrations.approve', props.registration.id),
        {},
        { onFinish: () => { approving.value = false; } }
    );
};

const statusBadgeClass: Record<string, string> = {
    pending:                'bg-yellow-100 text-yellow-800',
    registrar_cleared:      'bg-blue-100 text-blue-800',
    approved:               'bg-green-100 text-green-800',
    rejected:               'bg-red-100 text-red-800',
    rejected_by_registrar:  'bg-red-100 text-red-800',
    needs_revision:         'bg-orange-100 text-orange-800',
};

const canApprove = computed(() => props.registration.is_finance_actionable);
const canReject  = computed(() => props.registration.is_finance_actionable);
const canRevise  = computed(() => props.registration.is_finance_actionable && props.registration.revision_stage !== 'registrar');

const studentTypeLabel: Record<string, string> = {
    new:        'New Student',
    old:        'Old Student',
    transferee: 'Transferee',
    returnee:   'Returnee',
    irregular:  'Irregular',
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: route('accounting.dashboard') },
        { title: 'Registration Approvals', href: route('accounting.registrations.index') },
        { title: registration.full_name },
    ]">
        <Head :title="`Review: ${registration.full_name}`" />

        <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-5">

            <!-- Back + header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <Link
                        :href="route('accounting.registrations.index')"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground mb-2"
                    >
                        <ChevronLeft class="h-4 w-4" /> Back to list
                    </Link>
                    <h1 class="text-xl font-bold text-gray-900">{{ registration.full_name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Token: <code class="font-mono font-bold">{{ registration.tracking_token }}</code>
                        · Submitted {{ registration.submitted_at }}
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <span :class="['rounded-full px-3 py-1 text-sm font-semibold', statusBadgeClass[registration.status]]">
                        {{ registration.status_label }}
                    </span>

                    <template v-if="registration.status !== 'approved' && registration.status !== 'rejected'">
                        <button
                            v-if="canRevise"
                            @click="showRevisionModal = true"
                            class="inline-flex items-center gap-1.5 rounded-md border border-orange-300 bg-orange-50 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-100 transition-colors"
                        >
                            <AlertTriangle class="h-4 w-4" />
                            Request Revision
                        </button>

                        <button
                            v-if="canReject"
                            @click="showRejectModal = true"
                            class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100 transition-colors"
                        >
                            <XCircle class="h-4 w-4" />
                            Reject
                        </button>

                        <button
                            v-if="canApprove"
                            @click="approve"
                            :disabled="approving || isHardBlocked"
                            class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            {{ approveLabel }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Registrar Clearance Context -->
            <div
                v-if="registration.registrar_reviewed_at"
                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"
            >
                <strong>✓ Registrar clearance:</strong>
                Academically cleared by {{ registration.registrar_reviewer_name ?? 'Registrar' }}
                on {{ registration.registrar_reviewed_at }}.
            </div>

            <!-- Returning student notice (same person, existing account) -->
            <div
                v-if="existingUser && existingUser.is_same_person"
                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"
            >
                <div class="flex items-start gap-2">
                    <Info class="h-4 w-4 mt-0.5 flex-shrink-0" />
                    <div>
                        <strong>Returning student detected.</strong>
                        An account already exists for <strong>{{ existingUser.name }}</strong>
                        (Account ID: <code class="font-mono">{{ existingUser.account_id }}</code>,
                        User ID: {{ existingUser.id }}).
                        Approving will update their enrollment information — no duplicate account will be created.
                    </div>
                </div>
            </div>

            <!-- Hard conflict (different person, same email) -->
            <div
                v-if="existingUser && !existingUser.is_same_person"
                class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <div class="flex items-start gap-2">
                    <XCircle class="h-4 w-4 mt-0.5 flex-shrink-0" />
                    <div>
                        <strong>⚠ Email conflict — approval blocked.</strong>
                        A <em>different person</em> (<strong>{{ existingUser.name }}</strong>, ID: {{ existingUser.id }})
                        already has the email <strong>{{ registration.email }}</strong> in the system.
                        Approving would overwrite their account.
                        Reject this registration and ask the applicant to resubmit with a different email address.
                    </div>
                </div>
            </div>

            <!-- Duplicate registrations -->
            <div
                v-if="duplicates.length > 0"
                class="rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 space-y-2"
            >
                <strong>⚠ Potential duplicates detected:</strong>
                <ul class="mt-1 space-y-1 text-xs">
                    <li v-for="dup in duplicates" :key="dup.id">
                        <strong>{{ dup.full_name }}</strong> — {{ dup.email }} — {{ dup.status }}
                        (submitted {{ dup.submitted_at }})
                    </li>
                </ul>
            </div>

            <!-- Rejection / Revision info -->
            <div
                v-if="registration.rejection_reason"
                class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800"
            >
                <strong>Rejection reason:</strong> {{ registration.rejection_reason }}
                <span v-if="registration.reviewer_name" class="ml-1 text-xs text-red-600">
                    — by {{ registration.reviewer_name }}, {{ registration.reviewed_at }}
                </span>
            </div>

            <div
                v-if="registration.revision_notes"
                class="rounded-md border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800"
            >
                <strong>Revision requested:</strong> {{ registration.revision_notes }}
                <span v-if="registration.reviewer_name" class="ml-1 text-xs text-orange-600">
                    — by {{ registration.reviewer_name }}, {{ registration.reviewed_at }}
                </span>
            </div>

            <!-- Two-column detail grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Personal Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                        <User class="h-4 w-4" /> Personal Information
                    </h2>
                    <dl class="space-y-2 text-sm">
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Full Name</dt>
                            <dd class="font-medium">{{ registration.full_name }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Gender</dt>
                            <dd>{{ registration.gender ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Birthdate</dt>
                            <dd>{{ registration.birthdate }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Civil Status</dt>
                            <dd>{{ registration.civil_status ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Contact No.</dt>
                            <dd>{{ registration.contact_number }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Email</dt>
                            <dd class="break-all">{{ registration.email }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Address</dt>
                            <dd class="text-xs">{{ registration.full_address }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Academic Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Academic Information</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Course</dt>
                            <dd class="font-medium text-xs">{{ registration.course }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Year Level</dt>
                            <dd>{{ registration.year_level }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Semester</dt>
                            <dd>{{ registration.semester }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">School Year</dt>
                            <dd>{{ registration.school_year }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Student Type</dt>
                            <dd>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium">
                                    {{ studentTypeLabel[registration.student_type] ?? registration.student_type }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="registration.existing_student_id" class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Existing ID</dt>
                            <dd class="font-mono">{{ registration.existing_student_id }}</dd>
                        </div>
                        <hr class="my-2 border-gray-100" />
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Guardian</dt>
                            <dd>{{ registration.guardian_name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Guardian Contact</dt>
                            <dd>{{ registration.guardian_contact ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-1">
                            <dt class="text-muted-foreground">Emergency Contact</dt>
                            <dd class="text-xs">{{ registration.emergency_contact ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Documents -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                    <FileText class="h-4 w-4" /> Uploaded Documents
                </h2>
                <div class="flex gap-3 flex-wrap">
                    <a
                        v-if="documentUrls.valid_id"
                        :href="documentUrls.valid_id"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-md border border-input bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 transition-colors"
                    >
                        <FileText class="h-4 w-4 text-blue-500" />
                        View Valid ID
                    </a>
                    <span v-else class="text-sm text-muted-foreground italic">No Valid ID uploaded</span>

                    <a
                        v-if="documentUrls.proof"
                        :href="documentUrls.proof"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-md border border-input bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 transition-colors"
                    >
                        <FileText class="h-4 w-4 text-green-500" />
                        View Proof of Enrollment
                    </a>
                    <span v-else class="text-sm text-muted-foreground italic">No proof uploaded</span>
                </div>
            </div>
        </div>

        <!-- ── Reject Modal ─────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showRejectModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showRejectModal = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
                    <div class="flex items-center gap-2">
                        <XCircle class="h-5 w-5 text-red-600" />
                        <h2 class="text-base font-semibold text-gray-900">Reject Registration</h2>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Provide a reason so the applicant understands why their registration was not approved.
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason <span class="text-destructive">*</span></label>
                        <textarea
                            v-model="rejectForm.rejection_reason"
                            rows="4"
                            placeholder="e.g. Incomplete personal information — missing valid contact number and address."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        />
                        <p v-if="rejectForm.errors.rejection_reason" class="mt-1 text-xs text-destructive">
                            {{ rejectForm.errors.rejection_reason }}
                        </p>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button
                            type="button"
                            @click="showRejectModal = false"
                            class="rounded-md border border-input bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitReject"
                            :disabled="rejectForm.processing || !rejectForm.rejection_reason"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            Confirm Rejection
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Revision Modal ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showRevisionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showRevisionModal = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                        <h2 class="text-base font-semibold text-gray-900">Request Revision</h2>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        The student will receive an email with a link to correct their registration.
                        Be specific about what needs to be fixed.
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">What needs to be corrected? <span class="text-destructive">*</span></label>
                        <textarea
                            v-model="revisionForm.revision_notes"
                            rows="4"
                            placeholder="e.g. Please correct your barangay — 'Balagtas' should be 'Balagtas Poblacion'. Also, upload a clearer copy of your valid ID."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        />
                        <p v-if="revisionForm.errors.revision_notes" class="mt-1 text-xs text-destructive">
                            {{ revisionForm.errors.revision_notes }}
                        </p>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button
                            type="button"
                            @click="showRevisionModal = false"
                            class="rounded-md border border-input bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitRevision"
                            :disabled="revisionForm.processing || !revisionForm.revision_notes"
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            Send Revision Request
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>