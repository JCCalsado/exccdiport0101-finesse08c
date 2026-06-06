<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, CheckCircle2, XCircle, AlertTriangle,
    User, MapPin, GraduationCap, Shield, FileText,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Registration {
    id: number;
    tracking_token: string;
    full_name: string;
    full_address: string;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    gender: string;
    birthdate: string | null;
    civil_status: string;
    contact_number: string;
    email: string;
    course: string;
    year_level: string;
    semester: string;
    school_year: string;
    student_type: string;
    existing_student_id: string | null;
    guardian_name: string | null;
    guardian_contact: string | null;
    emergency_contact: string | null;
    has_valid_id: boolean;
    has_proof: boolean;
    status: string;
    status_label: string;
    status_color: string;
    revision_stage: string | null;
    registrar_rejection_reason: string | null;
    registrar_revision_notes: string | null;
    registrar_reviewed_at: string | null;
    registrar_reviewer_name: string | null;
    submitted_at: string;
    is_registrar_actionable: boolean;
}

interface Duplicate {
    id: number;
    full_name: string;
    email: string;
    status: string;
    submitted_at: string;
}

const props = defineProps<{
    registration: Registration;
    duplicates: Duplicate[];
    documentUrls: { valid_id: string | null; proof: string | null };
}>();

// ── Modal state ────────────────────────────────────────────────────────────
const showRejectModal   = ref(false);
const showRevisionModal = ref(false);

// ── Forms ──────────────────────────────────────────────────────────────────
const approveForm = useForm({});

const rejectForm = useForm({
    rejection_reason: '',
});

const revisionForm = useForm({
    revision_notes: '',
});

// ── Actions ────────────────────────────────────────────────────────────────
const approve = () => {
    approveForm.post(route('registrar.registrations.approve', props.registration.id));
};

const reject = () => {
    rejectForm.post(route('registrar.registrations.reject', props.registration.id), {
        onSuccess: () => { showRejectModal.value = false; rejectForm.reset(); },
    });
};

const requestRevision = () => {
    revisionForm.post(route('registrar.registrations.request-revision', props.registration.id), {
        onSuccess: () => { showRevisionModal.value = false; revisionForm.reset(); },
    });
};

// ── Status config ──────────────────────────────────────────────────────────
const statusBadgeClass: Record<string, string> = {
    pending:               'bg-yellow-100 text-yellow-800 border-yellow-200',
    needs_revision:        'bg-orange-100 text-orange-800 border-orange-200',
    registrar_cleared:     'bg-blue-100  text-blue-800  border-blue-200',
    rejected_by_registrar: 'bg-red-100   text-red-800   border-red-200',
    approved:              'bg-green-100 text-green-800 border-green-200',
    rejected:              'bg-red-100   text-red-800   border-red-200',
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Registrar',           href: route('registrar.dashboard') },
        { title: 'Registration Queue',  href: route('registrar.registrations.index') },
        { title: registration.full_name },
    ]">
        <Head :title="`Review — ${registration.full_name}`" />

        <div class="space-y-6 p-4 md:p-6 max-w-4xl">

            <!-- Back + Header -->
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('registrar.registrations.index')"
                        class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Queue
                    </Link>
                </div>
                <span :class="['rounded-full border px-3 py-1 text-xs font-semibold', statusBadgeClass[registration.status] ?? 'bg-gray-100 text-gray-700']">
                    {{ registration.status_label }}
                </span>
            </div>

            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ registration.full_name }}</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Token: <span class="font-mono font-medium">{{ registration.tracking_token }}</span>
                    · Submitted {{ registration.submitted_at }}
                </p>
            </div>

            <!-- Duplicate warning -->
            <div v-if="duplicates.length > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-800 mb-2">⚠ Possible Duplicate Submissions</p>
                <ul class="space-y-1">
                    <li v-for="dup in duplicates" :key="dup.id" class="text-xs text-amber-700">
                        {{ dup.full_name }} — {{ dup.email }} ({{ dup.status }}, {{ dup.submitted_at }})
                    </li>
                </ul>
            </div>

            <!-- Registrar stage review result (if already reviewed) -->
            <div v-if="registration.registrar_reviewed_at" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Registrar Review</p>
                <p class="text-sm text-blue-800">
                    Reviewed by <strong>{{ registration.registrar_reviewer_name }}</strong>
                    on {{ registration.registrar_reviewed_at }}
                </p>
                <p v-if="registration.registrar_rejection_reason" class="mt-2 text-sm text-red-700">
                    <strong>Rejection reason:</strong> {{ registration.registrar_rejection_reason }}
                </p>
                <p v-if="registration.registrar_revision_notes" class="mt-2 text-sm text-orange-700">
                    <strong>Revision notes:</strong> {{ registration.registrar_revision_notes }}
                </p>
            </div>

            <!-- Action Buttons (only when actionable) -->
            <div v-if="registration.is_registrar_actionable" class="flex flex-wrap gap-3">
                <button
                    @click="approve"
                    :disabled="approveForm.processing"
                    class="flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60 transition-colors"
                >
                    <CheckCircle2 class="h-4 w-4" />
                    {{ approveForm.processing ? 'Processing…' : 'Clear — Forward to Finance' }}
                </button>

                <button
                    @click="showRevisionModal = true"
                    class="flex items-center gap-2 rounded-lg border border-orange-300 bg-orange-50 px-5 py-2.5 text-sm font-semibold text-orange-700 hover:bg-orange-100 transition-colors"
                >
                    <AlertTriangle class="h-4 w-4" />
                    Request Revision
                </button>

                <button
                    @click="showRejectModal = true"
                    class="flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition-colors"
                >
                    <XCircle class="h-4 w-4" />
                    Reject
                </button>
            </div>

            <!-- Info sections grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                <!-- Personal Info -->
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <User class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-gray-900 text-sm">Personal Information</h2>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted-foreground">Full Name</dt><dd class="font-medium text-gray-900">{{ registration.full_name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Gender</dt><dd class="font-medium text-gray-900">{{ registration.gender }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Birthdate</dt><dd class="font-medium text-gray-900">{{ registration.birthdate ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Civil Status</dt><dd class="font-medium text-gray-900">{{ registration.civil_status }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Contact</dt><dd class="font-medium text-gray-900">{{ registration.contact_number }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Email</dt><dd class="font-medium text-gray-900 break-all">{{ registration.email }}</dd></div>
                    </dl>
                </div>

                <!-- Academic Info -->
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <GraduationCap class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-gray-900 text-sm">Academic Information</h2>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted-foreground">Course</dt><dd class="font-medium text-gray-900">{{ registration.course }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Year Level</dt><dd class="font-medium text-gray-900">{{ registration.year_level }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Semester</dt><dd class="font-medium text-gray-900">{{ registration.semester }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">School Year</dt><dd class="font-medium text-gray-900">{{ registration.school_year }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Student Type</dt><dd class="font-medium text-gray-900">{{ registration.student_type }}</dd></div>
                        <div v-if="registration.existing_student_id" class="flex justify-between">
                            <dt class="text-muted-foreground">Previous ID</dt>
                            <dd class="font-medium text-gray-900">{{ registration.existing_student_id }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Address -->
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <MapPin class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-gray-900 text-sm">Address</h2>
                    </div>
                    <p class="text-sm text-gray-700">{{ registration.full_address || '—' }}</p>
                </div>

                <!-- Guardian / Emergency -->
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <Shield class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-gray-900 text-sm">Guardian / Emergency Contact</h2>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted-foreground">Guardian Name</dt><dd class="font-medium text-gray-900">{{ registration.guardian_name ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Guardian Contact</dt><dd class="font-medium text-gray-900">{{ registration.guardian_contact ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">Emergency Contact</dt><dd class="font-medium text-gray-900">{{ registration.emergency_contact ?? '—' }}</dd></div>
                    </dl>
                </div>

            </div>

            <!-- Submitted Documents -->
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex items-center gap-2 mb-4">
                    <FileText class="h-4 w-4 text-muted-foreground" />
                    <h2 class="font-semibold text-gray-900 text-sm">Submitted Documents</h2>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a
                        v-if="documentUrls.valid_id"
                        :href="documentUrls.valid_id"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <FileText class="h-4 w-4" />
                        View Valid ID
                    </a>
                    <span v-else class="text-sm text-muted-foreground">No valid ID uploaded.</span>

                    <a
                        v-if="documentUrls.proof"
                        :href="documentUrls.proof"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <FileText class="h-4 w-4" />
                        View Proof of Enrollment
                    </a>
                    <span v-else class="text-sm text-muted-foreground">No proof uploaded.</span>
                </div>
            </div>

        </div>

        <!-- ── REJECT MODAL ─────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-xl p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">Reject Registration</h2>
                    <p class="text-sm text-muted-foreground mb-4">
                        The applicant will be notified with your reason. This action is terminal — the student must re-apply.
                    </p>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection *</label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="4"
                        placeholder="e.g. Incomplete academic requirements. Please visit the Registrar's office with your TOR and NSO documents."
                        class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="mt-1 text-xs text-red-600">
                        {{ rejectForm.errors.rejection_reason }}
                    </p>
                    <div class="flex gap-3 mt-5">
                        <button
                            @click="reject"
                            :disabled="rejectForm.processing || !rejectForm.rejection_reason.trim()"
                            class="flex-1 rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60 transition-colors"
                        >
                            {{ rejectForm.processing ? 'Rejecting…' : 'Confirm Rejection' }}
                        </button>
                        <button
                            @click="showRejectModal = false; rejectForm.reset()"
                            class="flex-1 rounded-lg border border-input py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── REVISION MODAL ───────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showRevisionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-xl p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">Request Revision</h2>
                    <p class="text-sm text-muted-foreground mb-4">
                        The applicant will receive a link to correct their submission and resubmit to the Registrar queue.
                    </p>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Revision Instructions *</label>
                    <textarea
                        v-model="revisionForm.revision_notes"
                        rows="4"
                        placeholder="e.g. Please upload a clearer copy of your valid ID. The submitted image is unreadable."
                        class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p v-if="revisionForm.errors.revision_notes" class="mt-1 text-xs text-red-600">
                        {{ revisionForm.errors.revision_notes }}
                    </p>
                    <div class="flex gap-3 mt-5">
                        <button
                            @click="requestRevision"
                            :disabled="revisionForm.processing || !revisionForm.revision_notes.trim()"
                            class="flex-1 rounded-lg bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60 transition-colors"
                        >
                            {{ revisionForm.processing ? 'Sending…' : 'Send Revision Request' }}
                        </button>
                        <button
                            @click="showRevisionModal = false; revisionForm.reset()"
                            class="flex-1 rounded-lg border border-input py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>