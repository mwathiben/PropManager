<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { ImportsShowPageProps } from '@/types/operations';

const props = defineProps<ImportsShowPageProps>();

const { t } = useI18n();

// Use composables
const { formatDateTime: formatDate } = useFormatters();

const getStatusColor = (status) => {
    return {
        'pending': 'bg-gray-100 text-gray-800',
        'processing': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800'
    }[status] || 'bg-gray-100 text-gray-800';
};

const goBack = () => {
    router.visit(route('imports.index'));
};

const reprocess = () => {
    if (confirm(t('imports.show.confirm_reprocess'))) {
        router.post(route('imports.reprocess', props.importRecord.id));
    }
};
</script>

<template>
    <Head :title="t('imports.show.head_title', { file: importRecord.file_name })" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <button
                                    @click="goBack"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 mb-2 flex items-center gap-1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    {{ t('imports.show.back') }}
                                </button>
                                <h1 class="text-2xl font-bold text-gray-900">{{ t('imports.show.heading') }}</h1>
                                <p class="mt-1 text-sm text-gray-600">{{ importRecord.file_name }}</p>
                            </div>
                            <span :class="['px-3 py-1 text-sm font-medium rounded-full', getStatusColor(importRecord.status)]">
                                {{ importRecord.status }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">{{ t('imports.show.summary_heading') }}</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-sm text-gray-600 mb-1">{{ t('imports.show.import_type') }}</div>
                                <div class="text-xl font-bold text-gray-900 capitalize">
                                    {{ importRecord.type.replace('_', ' ') }}
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-sm text-gray-600 mb-1">{{ t('imports.show.imported_by') }}</div>
                                <div class="text-xl font-bold text-gray-900">
                                    {{ importRecord.importer.name }}
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-sm text-gray-600 mb-1">{{ t('imports.show.import_date') }}</div>
                                <div class="text-xl font-bold text-gray-900">
                                    {{ formatDate(importRecord.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div v-if="importRecord.status === 'completed' || importRecord.status === 'failed'" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">{{ t('imports.show.results_heading') }}</h2>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <div class="text-sm text-gray-600 mb-1">{{ t('imports.show.total_rows') }}</div>
                                <div class="text-3xl font-bold text-gray-900">
                                    {{ importRecord.total_rows }}
                                </div>
                            </div>

                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="text-sm text-green-600 mb-1">{{ t('imports.show.successful') }}</div>
                                <div class="text-3xl font-bold text-green-600">
                                    {{ importRecord.successful_rows }}
                                </div>
                            </div>

                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="text-sm text-red-600 mb-1">{{ t('imports.show.failed') }}</div>
                                <div class="text-3xl font-bold text-red-600">
                                    {{ importRecord.failed_rows }}
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="text-sm text-blue-600 mb-1">{{ t('imports.show.success_rate') }}</div>
                                <div class="text-3xl font-bold text-blue-600">
                                    {{ importRecord.success_rate }}%
                                </div>
                            </div>
                        </div>

                        <!-- Processing Time -->
                        <div v-if="importRecord.started_at && importRecord.completed_at" class="text-sm text-gray-600 mb-4">
                            <strong>{{ t('imports.show.processing_time') }}</strong>
                            {{ t('imports.show.started', { time: formatDate(importRecord.started_at) }) }} -
                            {{ t('imports.show.completed', { time: formatDate(importRecord.completed_at) }) }}
                        </div>

                        <!-- Summary Details -->
                        <div v-if="importRecord.summary" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <h3 class="font-semibold text-blue-900 mb-2">{{ t('imports.show.summary_heading') }}</h3>
                            <div class="space-y-1 text-sm text-blue-800">
                                <div v-for="(value, key) in importRecord.summary" :key="key">
                                    <strong class="capitalize">{{ key.replace('_', ' ') }}:</strong> {{ value }}
                                </div>
                            </div>
                        </div>

                        <!-- Reprocess Button -->
                        <button
                            v-if="importRecord.failed_rows > 0"
                            @click="reprocess"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                        >
                            {{ t('imports.show.reprocess_failed') }}
                        </button>
                    </div>
                </div>

                <!-- Errors -->
                <div v-if="importRecord.errors && importRecord.errors.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            {{ t('imports.show.errors_heading', { count: importRecord.errors.length }) }}
                        </h2>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-red-800">
                                <strong>{{ t('imports.show.note_label') }}</strong> {{ t('imports.show.note_body') }}
                            </p>
                        </div>

                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <div
                                v-for="(error, index) in importRecord.errors"
                                :key="index"
                                class="border border-red-200 rounded-lg p-4 bg-white"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ error.row ? t('imports.show.row_label', { row: error.row }) : t('imports.show.error_label', { index: index + 1 }) }}
                                    </span>
                                </div>

                                <!-- Error Messages -->
                                <div v-if="error.errors" class="mb-3">
                                    <div class="text-sm font-medium text-red-700 mb-1">{{ t('imports.show.issues_label') }}</div>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li v-for="(msg, idx) in error.errors" :key="idx" class="text-sm text-red-600">
                                            {{ msg }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- Data Preview -->
                                <div v-if="error.data" class="bg-gray-50 rounded p-3">
                                    <div class="text-xs font-medium text-gray-700 mb-2">{{ t('imports.show.row_data_label') }}</div>
                                    <div class="space-y-1">
                                        <div v-for="(value, key) in error.data" :key="key" class="text-xs">
                                            <span class="text-gray-600 font-medium capitalize">{{ key.replace('_', ' ') }}:</span>
                                            <span class="text-gray-900">{{ value || t('imports.show.empty_value') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Simple Message -->
                                <div v-if="error.message && !error.errors" class="text-sm text-red-600">
                                    {{ error.message }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
