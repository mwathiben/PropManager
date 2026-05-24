<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { PrivacySettingsPageProps } from '@/types/operations';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';

const props = defineProps<PrivacySettingsPageProps>();

const { t } = useI18n();

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
    <Head :title="t('privacy.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Page Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <Link :href="route('settings.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                            <ArrowLeftIcon class="w-4 h-4" />
                            {{ t('privacy.back_to_settings') }}
                        </Link>
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('privacy.heading') }}</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ t('privacy.subheading') }}
                        </p>
                    </div>
                </div>

                <!-- Data Export Section -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ t('privacy.export.heading') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('privacy.export.description_line1') }}
                        {{ t('privacy.export.description_line2') }}
                    </p>
                    <p class="text-xs text-gray-500 mb-4">
                        {{ t('privacy.export.legal_note') }}
                    </p>
                    <div class="flex space-x-3">
                        <button
                            @click="showExportModal = true"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                        >
                            {{ t('privacy.export.request_button') }}
                        </button>
                        <button
                            @click="immediateExport"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                        >
                            {{ t('privacy.export.download_now') }}
                        </button>
                    </div>
                </div>

                <!-- Deletion Request Section -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ t('privacy.delete.heading') }}</h3>

                    <!-- Active Deletion Request -->
                    <div v-if="deletionStatus" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-start">
                            <svg class="h-5 w-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ms-3">
                                <h4 class="text-sm font-medium text-yellow-800">
                                    {{ t('privacy.delete.scheduled_title') }}
                                </h4>
                                <p class="mt-1 text-sm text-yellow-700">
                                    {{ t('privacy.delete.scheduled_prefix') }} <strong>{{ deletionStatus.scheduled_deletion_at }}</strong>{{ t('privacy.delete.scheduled_suffix') }}
                                    {{ t('privacy.delete.days_remaining_prefix') }} <strong>{{ t('privacy.delete.days_remaining_value', { days: deletionStatus.days_remaining }) }}</strong> {{ t('privacy.delete.days_remaining_suffix') }}
                                </p>
                                <button
                                    @click="cancelDeletion"
                                    class="mt-2 text-sm font-medium text-yellow-800 hover:text-yellow-900"
                                >
                                    {{ t('privacy.delete.cancel_request') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Deletion Blockers -->
                    <div v-else-if="!canDelete.can_delete" class="mb-4">
                        <p class="text-sm text-gray-600 mb-3">
                            {{ t('privacy.delete.blockers_intro') }}
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
                            {{ t('privacy.delete.normal_description_line1') }}
                            {{ t('privacy.delete.normal_description_line2', { days: gracePeriodDays }) }}
                        </p>
                        <p class="text-xs text-gray-500 mb-4">
                            {{ t('privacy.delete.legal_note') }}
                        </p>
                        <button
                            @click="showDeleteModal = true"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
                        >
                            {{ t('privacy.delete.request_button') }}
                        </button>
                    </div>
                </div>

                <!-- Data Processing Info -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">{{ t('privacy.rights.heading') }}</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• <strong>{{ t('privacy.rights.access_label') }}</strong> {{ t('privacy.rights.access_body') }}</li>
                        <li>• <strong>{{ t('privacy.rights.portability_label') }}</strong> {{ t('privacy.rights.portability_body') }}</li>
                        <li>• <strong>{{ t('privacy.rights.erasure_label') }}</strong> {{ t('privacy.rights.erasure_body') }}</li>
                        <li>• <strong>{{ t('privacy.rights.rectification_label') }}</strong> {{ t('privacy.rights.rectification_body') }}</li>
                        <li>• <strong>{{ t('privacy.rights.object_label') }}</strong> {{ t('privacy.rights.object_body') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div v-if="showExportModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showExportModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('privacy.export.modal_heading') }}</h3>
                <p class="text-sm text-gray-600 mb-4">
                    {{ t('privacy.export.modal_body_line1') }}
                    {{ t('privacy.export.modal_body_line2') }}
                </p>
                <div class="flex justify-end space-x-3">
                    <button
                        @click="showExportModal = false"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                    >
                        {{ t('privacy.delete.cancel') }}
                    </button>
                    <button
                        @click="requestExport"
                        :disabled="exportInProgress"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                    >
                        {{ exportInProgress ? t('privacy.export.requesting') : t('privacy.export.request_export') }}
                    </button>
                </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showDeleteModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-medium text-red-600 mb-4">{{ t('privacy.delete.modal_heading') }}</h3>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-red-800">
                        <strong>{{ t('privacy.delete.warning_label') }}</strong> {{ t('privacy.delete.warning_body', { days: gracePeriodDays }) }}
                    </p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('privacy.delete.reason_label') }}
                    </label>
                    <textarea
                        v-model="deleteForm.reason"
                        rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        :placeholder="t('privacy.delete.reason_placeholder')"
                    ></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false; deleteForm.reset()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm"
                    >
                        {{ t('privacy.delete.cancel') }}
                    </button>
                    <button
                        @click="requestDeletion"
                        :disabled="deleteForm.processing"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
                    >
                        {{ deleteForm.processing ? t('privacy.delete.processing') : t('privacy.delete.confirm_button') }}
                    </button>
                </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
