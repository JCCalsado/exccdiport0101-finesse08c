<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    UploadCloud, File, AlertCircle, ShieldCheck, ShieldX, Loader2, XCircle, AlertTriangle,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const { formatCurrency } = useDataFormatting();

const props = defineProps<{
    payment: {
        id: number;
        amount: number;
        reference_number: string | null;
        charge_title: string;
        rejection_reason: string | null;
        created_at: string;
    };
}>();

const breadcrumbs = [
    { title: 'Other Charges', href: route('student.other-charges.index') },
    { title: 'Upload Proof of Payment' },
];

const form = useForm({
    proof_of_payment: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref<string | null>(null);
const fileSize = ref<number | null>(null);

// ─── AI Validation State (reuses the same /ai/verify-proof proxy as the
// regular tuition-payment proof upload — see Payment/ProofUpload.vue) ──────
const aiValidating = ref(false);
const aiResult = ref<'valid' | 'invalid' | 'uncertain' | null>(null);
const aiMessage = ref<string | null>(null);

// ─── Cancel State ──────────────────────────────────────────────────────────
const showCancelConfirm = ref(false);
const cancelling = ref(false);
const cancelError = ref<string | null>(null);

const cancelPayment = async () => {
    cancelling.value = true;
    cancelError.value = null;

    try {
        const page      = usePage();
        const csrfToken = (page.props.csrf_token as string) ?? '';

        const response = await fetch(route('student.other-charges.proof.cancel', props.payment.id), {
            method:      'DELETE',
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
            throw new Error(data.error || `Server error: ${response.status}`);
        }

        window.location.href = route('student.other-charges.index');

    } catch (err) {
        cancelError.value = err instanceof Error
            ? err.message
            : 'Failed to cancel. Please try again.';
        showCancelConfirm.value = false;
    } finally {
        cancelling.value = false;
    }
};

const fileToBase64 = (file: File): Promise<string> =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const result = reader.result as string;
            resolve(result.split(',')[1]);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });

const validateWithAI = async (file: File): Promise<void> => {
    aiValidating.value = true;
    aiResult.value = null;
    aiMessage.value = null;

    if (file.type === 'application/pdf') {
        await new Promise((r) => setTimeout(r, 400));
        aiResult.value = 'uncertain';
        aiMessage.value =
            'PDF detected. Please make sure this is a valid bank transfer receipt. Our team will verify it manually.';
        aiValidating.value = false;
        return;
    }

    try {
        const base64 = await fileToBase64(file);
        const mediaType = file.type as 'image/jpeg' | 'image/png' | 'image/webp';
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        const response = await fetch('/ai/verify-proof', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                image:      base64,
                media_type: mediaType,
            }),
        });

        const parsed: { result: string; reason: string } | null = response.ok
            ? await response.json().catch(() => null)
            : null;

        if (parsed?.result === 'valid') {
            aiResult.value = 'valid';
            aiMessage.value = parsed.reason ?? 'Image looks like a valid payment receipt.';
        } else if (parsed?.result === 'invalid') {
            aiResult.value = 'invalid';
            aiMessage.value =
                parsed.reason ??
                'This image does not appear to be a payment receipt. Please upload the correct file.';
            form.errors.proof_of_payment = aiMessage.value as any;
        } else {
            aiResult.value = 'uncertain';
            aiMessage.value =
                parsed?.reason ?? 'Could not fully verify this image. Make sure it clearly shows payment details.';
        }
    } catch {
        aiResult.value = 'uncertain';
        aiMessage.value = 'Automatic verification unavailable. Our team will review your submission manually.';
    } finally {
        aiValidating.value = false;
    }
};

const handleFileSelect = async (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        form.proof_of_payment = file;
        fileName.value = file.name;
        fileSize.value = file.size;
        form.errors.proof_of_payment = '';
        aiResult.value = null;
        aiMessage.value = null;
        await validateWithAI(file);
    }
};

const handleDrop = async (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();

    const file = e.dataTransfer?.files?.[0];
    if (file) {
        const input = fileInput.value;
        if (input) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
            await handleFileSelect({ target: input } as any);
        }
    }
};

const removeFile = () => {
    fileName.value = null;
    fileSize.value = null;
    form.proof_of_payment = null;
    aiResult.value = null;
    aiMessage.value = null;
};

const submit = () => {
    if (!form.proof_of_payment) {
        form.errors.proof_of_payment = ['Please select a file to upload.'];
        return;
    }
    if (aiResult.value === 'invalid') {
        form.errors.proof_of_payment = 'Please upload a valid proof of payment, not a random image.';
        return;
    }

    form.post(route('student.other-charges.proof.upload', props.payment.id), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const isValidFile = computed(() => {
    if (!form.proof_of_payment) return false;
    const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    const maxSize = 5120 * 1024;
    return validTypes.includes(form.proof_of_payment.type) && form.proof_of_payment.size <= maxSize;
});

const canSubmit = computed(() =>
    isValidFile.value &&
    !form.processing &&
    !aiValidating.value &&
    aiResult.value !== 'invalid',
);
</script>

<template>
    <AppLayout>
        <Head title="Upload Proof of Payment" />

        <div class="w-full max-w-2xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Upload Proof of Payment</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Upload your bank transfer receipt to complete this submission.
                </p>
            </div>

            <!-- Rejection notice — only present if this is a re-upload after a DO rejection -->
            <div
                v-if="payment.rejection_reason"
                class="mb-6 flex items-start gap-3 rounded-xl border border-red-300 bg-red-50 p-4"
            >
                <AlertTriangle class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-red-800">
                    <p class="font-semibold">Your previous proof was rejected</p>
                    <p class="mt-1">{{ payment.rejection_reason }}</p>
                    <p class="mt-1">Please upload a corrected proof of payment below.</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Payment Summary Card -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-semibold text-gray-900 border-b pb-3">
                        Payment Summary
                    </h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Charge</span>
                            <span class="font-semibold">{{ payment.charge_title }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount</span>
                            <span class="font-semibold">{{ formatCurrency(payment.amount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method</span>
                            <span class="font-semibold">Bank Transfer</span>
                        </div>
                        <div v-if="payment.reference_number" class="flex justify-between">
                            <span class="text-gray-600">Your Reference Number</span>
                            <span class="font-mono font-semibold">{{ payment.reference_number }}</span>
                        </div>
                    </div>
                </div>

                <!-- File Upload Section -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900 border-b pb-3">
                        Upload Receipt
                    </h2>

                    <!-- Drag and Drop Area -->
                    <div
                        @drop="handleDrop"
                        @dragover.prevent
                        class="rounded-xl border-2 border-dashed border-gray-300 hover:border-indigo-400 p-8 text-center transition-colors cursor-pointer"
                        :class="{ 'border-indigo-400 bg-indigo-50': fileName }"
                        @click="fileInput?.click()"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            @change="handleFileSelect"
                            class="hidden"
                        />

                        <div class="space-y-3">
                            <div class="flex justify-center">
                                <div
                                    class="p-3 rounded-full"
                                    :class="fileName ? 'bg-green-100' : 'bg-gray-100'"
                                >
                                    <UploadCloud
                                        :size="32"
                                        :class="fileName ? 'text-green-600' : 'text-gray-400'"
                                    />
                                </div>
                            </div>

                            <div>
                                <p class="font-semibold text-gray-900">
                                    {{ fileName ? 'File Selected' : 'Drag and drop your receipt here' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    or <span class="text-indigo-600 font-medium">browse your files</span>
                                </p>
                            </div>

                            <p class="text-xs text-gray-400">
                                PDF, JPG, PNG, or WebP • Max 5 MB
                            </p>
                        </div>
                    </div>

                    <!-- Selected File Details -->
                    <div v-if="fileName" class="flex items-center gap-3 p-4 bg-green-50 rounded-lg border border-green-200">
                        <File :size="20" class="text-green-600 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-green-900 truncate">{{ fileName }}</p>
                            <p class="text-xs text-green-700">
                                {{ fileSize ? (fileSize / 1024).toFixed(1) : 0 }} KB
                            </p>
                        </div>
                        <button type="button" @click="removeFile" class="text-green-600 hover:text-green-700 font-medium text-sm">
                            Remove
                        </button>
                    </div>

                    <!-- AI Validation: Loading -->
                    <div v-if="aiValidating" class="flex items-center gap-3 p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                        <Loader2 :size="18" class="text-indigo-600 flex-shrink-0 animate-spin" />
                        <p class="text-sm text-indigo-700 font-medium">Verifying your file, please wait…</p>
                    </div>

                    <!-- AI Validation: Valid -->
                    <div v-else-if="aiResult === 'valid'" class="flex items-start gap-3 p-4 bg-green-50 rounded-lg border border-green-200">
                        <ShieldCheck :size="18" class="text-green-600 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-green-800">
                            <strong>Verification passed.</strong> {{ aiMessage }}
                        </p>
                    </div>

                    <!-- AI Validation: Invalid -->
                    <div v-else-if="aiResult === 'invalid'" class="flex items-start gap-3 p-4 bg-red-50 rounded-lg border border-red-200">
                        <ShieldX :size="18" class="text-red-600 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-red-800">
                            <p class="font-semibold">Invalid file detected.</p>
                            <p class="mt-1">{{ aiMessage }}</p>
                        </div>
                    </div>

                    <!-- AI Validation: Uncertain -->
                    <div v-else-if="aiResult === 'uncertain'" class="flex items-start gap-3 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <AlertCircle :size="18" class="text-yellow-600 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-yellow-800">
                            <strong>Note:</strong> {{ aiMessage }}
                        </p>
                    </div>

                    <!-- Validation Error -->
                    <div v-if="form.errors.proof_of_payment" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {{ form.errors.proof_of_payment }}
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="button"
                        @click="submit"
                        :disabled="!canSubmit"
                        class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white shadow transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="form.processing">Uploading…</span>
                        <span v-else-if="aiValidating">Checking file…</span>
                        <span v-else-if="aiResult === 'invalid'">Invalid File — Please Re-upload</span>
                        <span v-else>Submit for Verification</span>
                    </button>

                    <!-- Cancel Section -->
                    <div class="border-t pt-4">
                        <p class="text-xs text-center text-gray-400 mb-3">
                            Changed your mind? You can cancel this submission and start over.
                        </p>

                        <div v-if="showCancelConfirm" class="rounded-lg border border-red-200 bg-red-50 p-4 space-y-3">
                            <div class="flex items-start gap-2">
                                <XCircle :size="18" class="text-red-500 flex-shrink-0 mt-0.5" />
                                <div class="text-sm text-red-800">
                                    <p class="font-semibold">Cancel this payment?</p>
                                    <p class="mt-1">
                                        This will void this submission and allow you to submit a new payment for this charge.
                                    </p>
                                </div>
                            </div>
                            <p v-if="cancelError" class="text-xs text-red-600">{{ cancelError }}</p>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="cancelPayment"
                                    :disabled="cancelling"
                                    class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                                >
                                    {{ cancelling ? 'Cancelling…' : 'Yes, Cancel Payment' }}
                                </button>
                                <button
                                    type="button"
                                    @click="showCancelConfirm = false"
                                    class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    Never Mind
                                </button>
                            </div>
                        </div>
                        <button
                            v-else
                            type="button"
                            @click="showCancelConfirm = true"
                            class="w-full text-center text-xs text-red-500 hover:text-red-700 hover:underline"
                        >
                            Cancel this payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
