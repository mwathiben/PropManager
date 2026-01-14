<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';

const props = defineProps({
    deletionStatus: Object,
    canDelete: Object,
    gracePeriodDays: Number,
});

const showDeleteModal = ref(false);
const showExportModal = ref(false);
const confirmText = ref('');

const deleteForm = useForm({
    reason: '',
    confirm: false,
});

const exportInProgress = ref(false);

const requestExport = () => {
    exportInProgress.value = true;
    useForm({}).post(route('gdpr.request-export'), {
        onFinish: () => {
            exportInProgress.value = false;
            showExportModal.value = false;
        },
    });
};

const immediateExport = () => {
    window.location.href = route('gdpr.immediate-export');
};

const requestDeletion = () => {
    deleteForm.confirm = true;
    deleteForm.post(route('gdpr.request-deletion'), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteForm.reset();
        },
    });
};

const cancelDeletion = () => {
    useForm({}).post(route('gdpr.cancel-deletion'));
};
</script>

<template>
    <Head title="Privacy Settings" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Page Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <Link :href="route('settings.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                            <ArrowLeftIcon class="w-4 h-4" />
                            Back to Settings
                        </Link>
                        <h1 class="text-2xl font-bold text-gray-900">Privacy & Data</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Manage your personal data and exercise your privacy rights.
                        </p>
                    </div>
                </div>

                <!-- Data Export Section -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Export Your Data</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Download a copy of all your personal data stored in PropManager.
                        This includes your profile information, lease history, invoices, payments, and uploaded documents.
                    </p>
                    <p class="text-xs text-gray-500 mb-4">
                        Under GDPR Article 20 and Kenya DPA Section 26, you have the right to receive your data in a portable format.
                    </p>
                    <div class="flex space-x-3">
                        <button
                            @click="showExportModal = true"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                        >
                            Request Data Export
                        </button>
                        <button
                            @click="immediateExport"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                        >
                            Download Now
                        </button>
                    </div>
                </div>

                <!-- Deletion Request Section -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Your Account</h3>

                    <!-- Active Deletion Request -->
                    <div v-if="deletionStatus" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-start">
                            <svg class="h-5 w-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-yellow-800">
                                    Deletion Scheduled
                                </h4>
                                <p class="mt-1 text-sm text-yellow-700">
                                    Your account is scheduled for deletion on <strong>{{ deletionStatus.scheduled_deletion_at }}</strong>.
                                    You have <strong>{{ deletionStatus.days_remaining }} days</strong> to cancel this request.
                                </p>
                                <button
                                    @click="cancelDeletion"
                                    class="mt-2 text-sm font-medium text-yellow-800 hover:text-yellow-900"
                                >
                                    Cancel Deletion Request →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Deletion Blockers -->
                    <div v-else-if="!canDelete.can_delete" class="mb-4">
                        <p class="text-sm text-gray-600 mb-3">
                            Account deletion is not available due to the following:
                        </p>
                        <ul class="list-disc list-inside space-y-1">
                            <li v-for="blocker in canDelete.blockers" :key="blocker" class="text-sm text-red-600">
                                {{ blocker }}
                            </li>
                        </ul>
                    </div>

                    <!-- Normal State -->
                    <div v-else>
                        <p class="text-sm text-gray-600 mb-4">
                            Permanently delete your account and all associated data.
                            This action cannot be undone after the {{ gracePeriodDays }}-day grace period.
                        </p>
                        <p class="text-xs text-gray-500 mb-4">
                            Under GDPR Article 17 and Kenya DPA Section 28, you have the right to erasure ("right to be forgotten").
                        </p>
                        <button
                            @click="showDeleteModal = true"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
                        >
                            Request Account Deletion
                        </button>
                    </div>
                </div>

                <!-- Data Processing Info -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Your Data Rights</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• <strong>Access:</strong> Request a copy of your personal data</li>
                        <li>• <strong>Portability:</strong> Receive your data in a machine-readable format</li>
                        <li>• <strong>Erasure:</strong> Request deletion of your personal data</li>
                        <li>• <strong>Rectification:</strong> Correct inaccurate data via your profile settings</li>
                        <li>• <strong>Object:</strong> Opt out of marketing communications</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div v-if="showExportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Export Your Data</h3>
                <p class="text-sm text-gray-600 mb-4">
                    We'll prepare a ZIP file containing all your personal data. This may take a few minutes
                    for larger accounts. You'll receive an email when your export is ready.
                </p>
                <div class="flex justify-end space-x-3">
                    <button
                        @click="showExportModal = false"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                    >
                        Cancel
                    </button>
                    <button
                        @click="requestExport"
                        :disabled="exportInProgress"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                    >
                        {{ exportInProgress ? 'Requesting...' : 'Request Export' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-medium text-red-600 mb-4">Delete Your Account</h3>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-red-800">
                        <strong>Warning:</strong> This will permanently delete your account and all associated data
                        after a {{ gracePeriodDays }}-day grace period. This action cannot be undone.
                    </p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Reason for leaving (optional)
                    </label>
                    <textarea
                        v-model="deleteForm.reason"
                        rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="Help us improve by sharing your reason..."
                    ></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false; deleteForm.reset()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                    >
                        Cancel
                    </button>
                    <button
                        @click="requestDeletion"
                        :disabled="deleteForm.processing"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
                    >
                        {{ deleteForm.processing ? 'Processing...' : 'Delete My Account' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
