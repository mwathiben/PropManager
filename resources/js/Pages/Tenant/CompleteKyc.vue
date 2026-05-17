<script setup lang="ts">
import { ref, computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { CompleteKycPageProps, KycSubmission } from '@/types';
import {
    UserCircleIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    DocumentIcon,
    ArrowUpTrayIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { CheckBadgeIcon } from '@heroicons/vue/24/solid';

const props = defineProps<CompleteKycPageProps>();

interface SubmissionEntry {
    requirement_id: number;
    file: File | null;
    value: string;
}

interface SubmissionStatus {
    status: 'not_submitted' | 'pending' | 'approved' | 'rejected';
    label: string;
    color: 'gray' | 'yellow' | 'green' | 'red';
    rejectionReason?: string;
    document?: KycSubmission['document'];
    submittedAt?: string;
}

// Build form dynamically based on requirements
const buildSubmissions = (): Record<number, SubmissionEntry> => {
    const entries: Record<number, SubmissionEntry> = {};
    props.requirements.forEach((req) => {
        entries[req.id] = {
            requirement_id: req.id,
            file: null,
            value: '',
        };
    });
    return entries;
};

const form = useForm({
    submissions: buildSubmissions(),
});

// Helper functions for dynamic error key handling (type assertions for Inertia form)
const setFileError = (requirementId: number, message: string) => {
    (form.setError as (key: string, message: string) => void)(
        `submissions.${requirementId}.file`,
        message
    );
};

const clearFileError = (requirementId: number) => {
    (form.clearErrors as (key: string) => void)(`submissions.${requirementId}.file`);
};

const getFileError = (requirementId: number): string | undefined => {
    return (form.errors as Record<string, string>)[`submissions.${requirementId}.file`];
};

// Track file previews per requirement
const filePreviews = ref<Record<number, string>>({});

// Track selected file names for display
const fileNames = reactive<Record<number, string>>({});

// Get submission status for a requirement
const getSubmissionStatus = (requirementId: number): SubmissionStatus => {
    const submission = props.submissions.find((s) => s.requirement_id === requirementId);

    if (!submission) {
        return {
            status: 'not_submitted',
            label: 'Not submitted',
            color: 'gray',
        };
    }

    const colorMap: Record<string, 'gray' | 'yellow' | 'green' | 'red'> = {
        approved: 'green',
        rejected: 'red',
        pending: 'yellow',
    };

    return {
        status: submission.status,
        label: submission.status_label,
        color: colorMap[submission.status] || 'gray',
        rejectionReason: submission.rejection_reason,
        document: submission.document,
        submittedAt: submission.submitted_at,
    };
};

// Dynamic completion status
const completionStatus = computed(() => {
    const requiredReqs = props.requirements.filter((r) => r.is_required);

    const completedReqs = requiredReqs.filter((req) => {
        const status = getSubmissionStatus(req.id);
        // Count as complete if approved, pending, or has a new file ready to upload
        return (
            status.status === 'approved' ||
            status.status === 'pending' ||
            form.submissions[req.id]?.file !== null
        );
    });

    const fields = props.requirements.map((req) => {
        const status = getSubmissionStatus(req.id);
        const hasNewFile = form.submissions[req.id]?.file !== null;
        return {
            name: req.label,
            required: req.is_required,
            complete:
                status.status === 'approved' ||
                status.status === 'pending' ||
                hasNewFile,
        };
    });

    return {
        fields,
        completed: completedReqs.length,
        total: requiredReqs.length,
        percentage:
            requiredReqs.length > 0
                ? Math.round((completedReqs.length / requiredReqs.length) * 100)
                : 100,
    };
});

// Can submit form?
const canSubmit = computed(() => {
    return props.requirements.filter((req) => req.is_required).every((req) => {
        const status = getSubmissionStatus(req.id);
        // Already approved or pending - no action needed
        if (status.status === 'approved' || status.status === 'pending') {
            return true;
        }
        // Rejected or not submitted - needs new file
        return form.submissions[req.id]?.file !== null;
    });
});

// Handle file selection for a requirement
const handleFileSelect = (requirementId: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    // Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        setFileError(requirementId, 'File must not exceed 10MB');
        input.value = ''; // Reset input so same file can be selected again
        return;
    }

    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        setFileError(requirementId, 'File must be PDF, JPG, PNG, or GIF');
        input.value = ''; // Reset input so same file can be selected again
        return;
    }

    // Clear any previous errors
    clearFileError(requirementId);

    // Set file in form
    form.submissions[requirementId].file = file;
    fileNames[requirementId] = file.name;

    // Generate preview for images
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            filePreviews.value[requirementId] = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        // Clear preview for non-images (PDF)
        filePreviews.value[requirementId] = '';
    }

    // Reset input value so selecting the same file again will trigger change event
    input.value = '';
};

// Handle file drop from drag-and-drop
const handleFileDrop = (requirementId: number, event: DragEvent) => {
    event.preventDefault();
    dragOver.value[requirementId] = false;

    const file = event.dataTransfer?.files?.[0];
    if (!file) return;

    // Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        setFileError(requirementId, 'File must not exceed 10MB');
        return;
    }

    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        setFileError(requirementId, 'File must be PDF, JPG, PNG, or GIF');
        return;
    }

    // Clear any previous errors
    clearFileError(requirementId);

    // Set file in form
    form.submissions[requirementId].file = file;
    fileNames[requirementId] = file.name;

    // Generate preview for images
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            filePreviews.value[requirementId] = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        // Clear preview for non-images (PDF)
        filePreviews.value[requirementId] = '';
    }
};

// Handle drag over
const handleDragOver = (requirementId: number, event: DragEvent) => {
    event.preventDefault();
    dragOver.value[requirementId] = true;
};

// Handle drag leave
const handleDragLeave = (requirementId: number) => {
    dragOver.value[requirementId] = false;
};

// Track drag state for visual feedback
const dragOver = ref<Record<number, boolean>>({});

// Clear selected file
const clearFile = (requirementId: number) => {
    form.submissions[requirementId].file = null;
    delete fileNames[requirementId];
    delete filePreviews.value[requirementId];
    clearFileError(requirementId);
};

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

// Check if requirement needs action (not submitted or rejected)
const needsAction = (requirementId: number): boolean => {
    const status = getSubmissionStatus(requirementId);
    return status.status === 'not_submitted' || status.status === 'rejected';
};

// Submit the form
const submit = () => {
    // Filter to only submit requirements that have files
    const submissionsToSend = Object.entries(form.submissions).filter(
        ([, sub]) => sub.file !== null
    );

    if (submissionsToSend.length === 0) {
        return;
    }

    // Track submitted IDs for cleanup
    const submittedIds = submissionsToSend.map(([id]) => Number(id));

    form.post(route('tenant.kyc.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            // Clear local state on success
            Object.keys(fileNames).forEach((key) => delete fileNames[Number(key)]);
            Object.keys(filePreviews.value).forEach(
                (key) => delete filePreviews.value[Number(key)]
            );
            // Clear File objects from form.submissions for submitted entries
            submittedIds.forEach((id) => {
                if (form.submissions[id]) {
                    form.submissions[id].file = null;
                }
            });
        },
    });
};

// Status badge classes
const getStatusBadgeClasses = (color: string): string => {
    const classes: Record<string, string> = {
        gray: 'bg-gray-100 text-gray-700',
        yellow: 'bg-yellow-100 text-yellow-700',
        green: 'bg-green-100 text-green-700',
        red: 'bg-red-100 text-red-700',
    };
    return classes[color] || classes.gray;
};
</script>

<template>
    <Head title="Complete Your KYC" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <UserCircleIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">
                        Complete Your KYC
                    </h1>
                    <p class="text-sm text-gray-500">
                        Please upload the required documents to verify your identity
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Progress Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-medium text-gray-700">
                            KYC Completion
                        </h2>
                        <span class="text-sm font-semibold text-indigo-600">
                            {{ completionStatus.percentage }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                        <div
                            class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: completionStatus.percentage + '%' }"
                        ></div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div
                            v-for="field in completionStatus.fields"
                            :key="field.name"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircleIcon
                                v-if="field.complete"
                                class="w-5 h-5 text-green-500 shrink-0"
                            />
                            <XCircleIcon
                                v-else-if="field.required"
                                class="w-5 h-5 text-red-300 shrink-0"
                            />
                            <ClockIcon
                                v-else
                                class="w-5 h-5 text-gray-300 shrink-0"
                            />
                            <span
                                :class="
                                    field.complete
                                        ? 'text-gray-700'
                                        : 'text-gray-400'
                                "
                            >
                                {{ field.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Requirements List -->
                <form @submit.prevent="submit" class="space-y-4">
                    <div
                        v-for="requirement in props.requirements"
                        :key="requirement.id"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 p-6"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-medium text-gray-900">
                                        {{ requirement.label }}
                                    </h3>
                                    <span
                                        v-if="requirement.is_required"
                                        class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full"
                                    >
                                        Required
                                    </span>
                                </div>
                                <p
                                    v-if="requirement.description"
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{ requirement.description }}
                                </p>
                            </div>

                            <!-- Status Badge -->
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                                    getStatusBadgeClasses(
                                        getSubmissionStatus(requirement.id).color
                                    ),
                                ]"
                            >
                                <CheckBadgeIcon
                                    v-if="
                                        getSubmissionStatus(requirement.id).status ===
                                        'approved'
                                    "
                                    class="w-3.5 h-3.5"
                                />
                                <ClockIcon
                                    v-else-if="
                                        getSubmissionStatus(requirement.id).status ===
                                        'pending'
                                    "
                                    class="w-3.5 h-3.5"
                                />
                                <XCircleIcon
                                    v-else-if="
                                        getSubmissionStatus(requirement.id).status ===
                                        'rejected'
                                    "
                                    class="w-3.5 h-3.5"
                                />
                                {{ getSubmissionStatus(requirement.id).label }}
                            </span>
                        </div>

                        <!-- Existing Document Info (if submitted) -->
                        <div
                            v-if="getSubmissionStatus(requirement.id).document"
                            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg mb-3"
                        >
                            <DocumentIcon class="w-8 h-8 text-gray-400" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{
                                        getSubmissionStatus(requirement.id).document
                                            ?.file_name
                                    }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{
                                        getSubmissionStatus(requirement.id).document
                                            ?.file_size_formatted
                                    }}
                                    <span
                                        v-if="
                                            getSubmissionStatus(requirement.id)
                                                .submittedAt
                                        "
                                    >
                                        &middot; Submitted
                                        {{
                                            getSubmissionStatus(requirement.id)
                                                .submittedAt
                                        }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Rejection Reason -->
                        <div
                            v-if="
                                getSubmissionStatus(requirement.id).status ===
                                    'rejected' &&
                                getSubmissionStatus(requirement.id).rejectionReason
                            "
                            class="flex items-start gap-2 p-3 bg-red-50 border border-red-100 rounded-lg mb-3"
                        >
                            <ExclamationTriangleIcon
                                class="w-5 h-5 text-red-500 shrink-0 mt-0.5"
                            />
                            <div>
                                <p class="text-sm font-medium text-red-800">
                                    Document Rejected
                                </p>
                                <p class="text-sm text-red-700 mt-0.5">
                                    {{
                                        getSubmissionStatus(requirement.id)
                                            .rejectionReason
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- File Upload (if needs action) -->
                        <div
                            v-if="needsAction(requirement.id)"
                            class="mt-3"
                        >
                            <InputLabel :value="getSubmissionStatus(requirement.id).status === 'rejected' ? 'Upload New Document' : 'Upload Document'" />

                            <!-- File Input Area -->
                            <div class="mt-2">
                                <!-- No file selected yet -->
                                <label
                                    v-if="!form.submissions[requirement.id]?.file"
                                    :class="[
                                        'flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer transition-colors',
                                        dragOver[requirement.id]
                                            ? 'border-indigo-500 bg-indigo-100'
                                            : 'border-gray-300 hover:border-indigo-400 hover:bg-indigo-50/50'
                                    ]"
                                    @dragover="handleDragOver(requirement.id, $event)"
                                    @dragleave="handleDragLeave(requirement.id)"
                                    @drop="handleFileDrop(requirement.id, $event)"
                                >
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <ArrowUpTrayIcon
                                            class="w-8 h-8 text-gray-400 mb-2"
                                        />
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium text-indigo-600">
                                                Click to upload
                                            </span>
                                            or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            PDF, JPG, PNG or GIF (Max 10MB)
                                        </p>
                                    </div>
                                    <input
                                        type="file"
                                        class="hidden"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif"
                                        @change="handleFileSelect(requirement.id, $event)"
                                    />
                                </label>

                                <!-- File selected -->
                                <div
                                    v-else
                                    class="flex items-center gap-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg"
                                >
                                    <!-- Image Preview -->
                                    <img
                                        v-if="filePreviews[requirement.id]"
                                        :src="filePreviews[requirement.id]"
                                        alt="Preview"
                                        class="w-12 h-12 object-cover rounded"
                                    />
                                    <DocumentIcon
                                        v-else
                                        class="w-10 h-10 text-indigo-400"
                                    />

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ fileNames[requirement.id] }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{
                                                formatFileSize(
                                                    form.submissions[requirement.id]
                                                        ?.file?.size || 0
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="clearFile(requirement.id)"
                                        class="text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        <XCircleIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>

                            <InputError
                                :message="getFileError(requirement.id)"
                                class="mt-2"
                            />
                        </div>

                        <!-- Approved - No action needed -->
                        <div
                            v-else-if="
                                getSubmissionStatus(requirement.id).status === 'approved'
                            "
                            class="flex items-center gap-2 mt-3 text-sm text-green-600"
                        >
                            <CheckCircleIcon class="w-5 h-5" />
                            <span>Document verified and approved</span>
                        </div>

                        <!-- Pending - Awaiting review -->
                        <div
                            v-else-if="
                                getSubmissionStatus(requirement.id).status === 'pending'
                            "
                            class="flex items-center gap-2 mt-3 text-sm text-yellow-600"
                        >
                            <ClockIcon class="w-5 h-5" />
                            <span>Awaiting review by landlord</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div
                        class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    {{ completionStatus.completed }} of
                                    {{ completionStatus.total }} required documents
                                    submitted
                                </p>
                                <p
                                    v-if="!canSubmit"
                                    class="text-xs text-gray-500 mt-1"
                                >
                                    Upload all required documents to continue
                                </p>
                            </div>
                            <PrimaryButton
                                :disabled="form.processing || !canSubmit"
                                :class="{
                                    'opacity-50 cursor-not-allowed': !canSubmit,
                                }"
                            >
                                <span v-if="form.processing">Uploading...</span>
                                <span v-else>Submit Documents</span>
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <!-- Info Card -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg
                                class="h-5 w-5 text-blue-400"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div class="ms-3">
                            <h3 class="text-sm font-medium text-blue-800">
                                About KYC Verification
                            </h3>
                            <p class="mt-1 text-sm text-blue-700">
                                Your documents will be reviewed by your landlord. Once
                                approved, you'll have full access to your tenant portal.
                                Make sure documents are clear and legible.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
