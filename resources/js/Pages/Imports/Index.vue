<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useAuth } from '@/composables/useAuth';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import type { ImportsIndexPageProps } from '@/types/operations';

const props = defineProps<ImportsIndexPageProps>();
const { can } = useAuth();
const { t } = useI18n();

const selectedType = ref('');
const selectedFile = ref(null);

// Building/Wing filter for import scope
const buildingId = ref(props.filters?.building_id || null);
const wingId = ref(props.filters?.wing_id || null);

const uploadForm = useForm({
    file: null,
    type: '',
    building_id: null,
    wing_id: null
});

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (!file.name.endsWith('.csv')) {
            alert(t('imports.alert_csv_only'));
            event.target.value = '';
            return;
        }
        selectedFile.value = file;
        uploadForm.file = file;
    }
};

const uploadImport = () => {
    if (!selectedFile.value || !selectedType.value) {
        alert(t('imports.alert_select_both'));
        return;
    }

    uploadForm.type = selectedType.value;
    uploadForm.building_id = buildingId.value;
    uploadForm.wing_id = wingId.value;

    uploadForm.post(route('imports.upload'), {
        forceFormData: true,
        onSuccess: () => {
            selectedFile.value = null;
            selectedType.value = '';
            uploadForm.reset();
            document.getElementById('file-input').value = '';
        }
    });
};

// Filter import history by building
const applyFilters = () => {
    router.get(route('imports.index'), {
        building_id: buildingId.value || '',
        wing_id: wingId.value || ''
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const onBuildingFilterChange = () => {
    applyFilters();
};

const downloadTemplate = (type) => {
    window.location.href = route('imports.template') + '?type=' + type;
};

const viewDetails = (importId) => {
    router.visit(route('imports.show', importId));
};

const deleteImport = (importId) => {
    if (confirm(t('imports.confirm_delete'))) {
        router.delete(route('imports.destroy', importId));
    }
};

const reprocessImport = (importId) => {
    if (confirm(t('imports.confirm_reprocess'))) {
        router.post(route('imports.reprocess', importId));
    }
};

const getStatusColor = (status) => {
    return {
        'pending': 'bg-gray-100 text-gray-800',
        'processing': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800'
    }[status] || 'bg-gray-100 text-gray-800';
};

// Use composables
const { formatDateTime: formatDate } = useFormatters();
</script>

<template>
    <Head :title="t('imports.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('imports.heading') }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ t('imports.description') }}</p>
                    </div>
                </div>

                <!-- Upload Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">{{ t('imports.upload_heading') }}</h2>

                        <!-- Building/Wing Scope (for imports that require it) -->
                        <div v-if="buildings?.length > 0" class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ t('imports.target_scope_label') }}
                            </label>
                            <BuildingWingFilter
                                :buildings="buildings"
                                v-model:buildingId="buildingId"
                                v-model:wingId="wingId"
                                :showBadge="true"
                                :buildingPlaceholder="t('imports.all_buildings')"
                                :wingPlaceholder="t('imports.all_wings')"
                            />
                            <p class="mt-2 text-xs text-gray-500">
                                {{ t('imports.scope_hint') }}
                            </p>
                        </div>

                        <!-- Import Type Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">{{ t('imports.select_type_label') }}</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div
                                    v-for="(typeInfo, key) in importTypes"
                                    :key="key"
                                    @click="selectedType = key"
                                    :class="['border rounded-lg p-4 cursor-pointer transition-all', selectedType === key ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-600' : 'border-gray-200 hover:border-indigo-300']"
                                >
                                    <div class="flex items-start gap-3">
                                        <input
                                            type="radio"
                                            :value="key"
                                            v-model="selectedType"
                                            class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <div class="flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900">{{ typeInfo.label }}</h3>
                                            <p class="mt-1 text-xs text-gray-600">{{ typeInfo.description }}</p>
                                            <button
                                                v-if="selectedType === key"
                                                @click.stop="downloadTemplate(key)"
                                                class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                            >
                                                {{ t('imports.download_template') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('imports.csv_file_label') }}</label>
                            <div class="flex items-center gap-3">
                                <label class="flex-1 cursor-pointer">
                                    <div class="flex items-center justify-center gap-2 px-4 py-3 bg-white border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-400 transition-colors">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <span class="text-sm text-gray-700">
                                            {{ selectedFile ? selectedFile.name : t('imports.choose_file') }}
                                        </span>
                                    </div>
                                    <input
                                        id="file-input"
                                        type="file"
                                        accept=".csv"
                                        class="hidden"
                                        @change="handleFileSelect"
                                    >
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ t('imports.file_size_hint') }}</p>
                        </div>

                        <!-- Upload Button -->
                        <button
                            @click="uploadImport"
                            :disabled="!selectedFile || !selectedType || uploadForm.processing"
                            class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
                        >
                            {{ uploadForm.processing ? t('imports.processing') : t('imports.upload_button') }}
                        </button>
                    </div>
                </div>

                <!-- Import History -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                            <h2 class="text-xl font-bold text-gray-900">{{ t('imports.history_heading') }}</h2>

                            <!-- Filter by Building -->
                            <div v-if="buildings?.length > 0" class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">{{ t('imports.filter_by') }}</span>
                                <BuildingWingFilter
                                    :buildings="buildings"
                                    v-model:buildingId="buildingId"
                                    v-model:wingId="wingId"
                                    :showBadge="false"
                                    :buildingPlaceholder="t('imports.all_buildings')"
                                    :wingPlaceholder="t('imports.all_wings')"
                                    @change="onBuildingFilterChange"
                                />
                            </div>
                        </div>

                        <div v-if="imports.data.length === 0" class="text-center py-12 text-gray-500">
                            <p class="text-lg">{{ t('imports.no_imports') }}</p>
                            <p class="text-sm mt-2">{{ t('imports.no_imports_hint') }}</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_type') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_file') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_status') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_results') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_imported_by') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_date') }}</th>
                                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('imports.th_actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="importRecord in imports.data" :key="importRecord.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-gray-900 capitalize">
                                                {{ importRecord.type.replace('_', ' ') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600">{{ importRecord.file_name }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusColor(importRecord.status)]">
                                                {{ importRecord.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div v-if="importRecord.status === 'completed' || importRecord.status === 'failed'">
                                                <span class="text-green-600">✓ {{ importRecord.successful_rows }}</span>
                                                <span class="text-gray-400 mx-1">/</span>
                                                <span class="text-red-600">✗ {{ importRecord.failed_rows }}</span>
                                                <span class="text-gray-400 mx-1">/</span>
                                                <span class="text-gray-600">{{ importRecord.total_rows }} {{ t('imports.total_suffix') }}</span>
                                            </div>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            {{ importRecord.importer.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            {{ formatDate(importRecord.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex gap-2">
                                                <button
                                                    @click="viewDetails(importRecord.id)"
                                                    class="text-indigo-600 hover:text-indigo-800 font-medium"
                                                >
                                                    {{ t('imports.details') }}
                                                </button>
                                                <button
                                                    v-if="importRecord.status === 'failed' || importRecord.failed_rows > 0"
                                                    @click="reprocessImport(importRecord.id)"
                                                    class="text-blue-600 hover:text-blue-800 font-medium"
                                                >
                                                    {{ t('imports.reprocess') }}
                                                </button>
                                                <button
                                                    v-if="can('imports:manage')"
                                                    @click="deleteImport(importRecord.id)"
                                                    class="text-red-600 hover:text-red-800 font-medium"
                                                >
                                                    {{ t('imports.delete') }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="imports.data.length > 0" class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                {{ t('imports.showing', { from: imports.from, to: imports.to, total: imports.total }) }}
                            </div>
                            <div class="flex gap-2">
                                <a
                                    v-for="link in imports.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="['px-3 py-1 rounded-md text-sm', link.active ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300']"
                                    class="transition-colors"
                                >
                                    <PaginatorLink :label="link.label" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
