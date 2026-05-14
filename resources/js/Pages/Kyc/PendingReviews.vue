<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface KycSubmission {
    id: number;
    tenant: {
        id: number;
        name: string;
        email: string;
    };
    requirement: {
        id: number;
        type: string;
        label: string;
    };
    document?: {
        id: number;
        file_name: string;
        file_size_formatted: string;
        is_image: boolean;
        is_pdf: boolean;
    };
    value?: string;
    submitted_at: string;
}

interface Props {
    submissions: {
        data: KycSubmission[];
        links: Record<string, string>;
        meta: Record<string, unknown>;
    };
}

defineProps<Props>();

const reviewingSubmission = ref<KycSubmission | null>(null);
const rejectionReason = ref('');

const form = useForm({
    status: '' as 'approved' | 'rejected',
    rejection_reason: '',
});

function openReviewModal(submission: KycSubmission, status: 'approved' | 'rejected') {
    reviewingSubmission.value = submission;
    form.status = status;
    form.rejection_reason = '';
    rejectionReason.value = '';
}

function submitReview() {
    if (!reviewingSubmission.value) return;

    form.rejection_reason = rejectionReason.value;
    form.post(route('kyc.review', reviewingSubmission.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reviewingSubmission.value = null;
            rejectionReason.value = '';
        },
    });
}

function closeModal() {
    reviewingSubmission.value = null;
    rejectionReason.value = '';
    form.reset();
}
</script>

<template>
    <Head title="KYC Pending Reviews" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                KYC Pending Reviews
            </h1>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="submissions.data.length === 0" class="text-center py-8 text-gray-500">
                            No pending KYC submissions to review.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="submission in submissions.data"
                                :key="submission.id"
                                class="border rounded-lg p-4 hover:bg-gray-50"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            {{ submission.tenant.name }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ submission.tenant.email }}
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <span class="font-medium">{{ submission.requirement.label }}</span>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            Submitted: {{ submission.submitted_at }}
                                        </p>
                                    </div>

                                    <div class="flex space-x-2">
                                        <a
                                            v-if="submission.document"
                                            :href="route('documents.view', submission.document.id)"
                                            target="_blank"
                                            class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 border border-blue-300 rounded"
                                        >
                                            View Document
                                        </a>
                                        <span v-else-if="submission.value" class="text-sm text-gray-600">
                                            Value: {{ submission.value }}
                                        </span>

                                        <button
                                            type="button"
                                            class="px-3 py-1 text-sm text-white bg-green-600 hover:bg-green-700 rounded"
                                            @click="openReviewModal(submission, 'approved')"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="px-3 py-1 text-sm text-white bg-red-600 hover:bg-red-700 rounded"
                                            @click="openReviewModal(submission, 'rejected')"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <div
            v-if="reviewingSubmission"
            class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ form.status === 'approved' ? 'Approve' : 'Reject' }} Submission
                </h3>

                <p class="text-sm text-gray-600 mb-4">
                    {{ reviewingSubmission.requirement.label }} from {{ reviewingSubmission.tenant.name }}
                </p>

                <div v-if="form.status === 'rejected'" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Rejection Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="rejectionReason"
                        rows="3"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Please provide a reason for rejection..."
                    />
                    <p v-if="form.errors.rejection_reason" class="mt-1 text-sm text-red-600">
                        {{ form.errors.rejection_reason }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        @click="closeModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-4 py-2 text-sm text-white rounded',
                            form.status === 'approved'
                                ? 'bg-green-600 hover:bg-green-700'
                                : 'bg-red-600 hover:bg-red-700',
                        ]"
                        :disabled="form.processing"
                        @click="submitReview"
                    >
                        {{ form.processing ? 'Processing...' : (form.status === 'approved' ? 'Approve' : 'Reject') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
