<script setup lang="ts">
import { ref } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import type { OperationsImportsTabProps } from '@/types/operations';
import {
    DocumentArrowDownIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<OperationsImportsTabProps>();

const { formatDate } = useFormatters();

const showUploadModal = ref(false);
const selectedTemplate = ref('');

const uploadForm = useForm({
    file: null,
    type: '',
});

const handleFileChange = (event) => {
    uploadForm.file = event.target.files[0];
};

const startImport = (templateType) => {
    selectedTemplate.value = templateType;
    uploadForm.type = templateType;
    showUploadModal.value = true;
};

const submitImport = () => {
    uploadForm.post(route('imports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showUploadModal.value = false;
            uploadForm.reset();
        },
    });
};

const getStatusIcon = (status) => {
    const icons = {
        completed: CheckCircleIcon,
        failed: XCircleIcon,
        processing: ClockIcon,
        pending: ClockIcon,
    };
    return icons[status] || ClockIcon;
};

const getStatusColor = (status) => {
    const colors = {
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        processing: 'bg-blue-100 text-blue-800',
        pending: 'bg-yellow-100 text-yellow-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const templates = [
    {
        type: 'tenants',
        name: 'Tenants',
        description: 'Import tenant information from CSV',
        fields: ['name', 'email', 'phone', 'unit_id'],
    },
    {
        type: 'units',
        name: 'Units',
        description: 'Import unit data from CSV',
        fields: ['unit_number', 'building_id', 'rent', 'status'],
    },
    {
        type: 'payments',
        name: 'Payments',
        description: 'Import payment records from CSV',
        fields: ['tenant_id', 'amount', 'date', 'method'],
    },
];
</script>

<template>
    <div>
        <!-- Import Templates -->
        <div class="mb-8">
            <h3 class="font-semibold text-gray-900 mb-4">Import Data</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="template in templates"
                    :key="template.type"
                    class="bg-white border border-gray-200 rounded-lg p-4"
                >
                    <h4 class="font-medium text-gray-900">{{ template.name }}</h4>
                    <p class="text-sm text-gray-500 mt-1">{{ template.description }}</p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span
                            v-for="field in template.fields"
                            :key="field"
                            class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded"
                        >
                            {{ field }}
                        </span>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <a
                            :href="route('imports.template', template.type)"
                            class="flex-1 inline-flex items-center justify-center px-3 py-1.5 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                            Template
                        </a>
                        <button
                            @click="startImport(template.type)"
                            class="flex-1 inline-flex items-center justify-center px-3 py-1.5 text-sm text-white bg-purple-600 rounded hover:bg-purple-700"
                        >
                            <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                            Import
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import History -->
        <div>
            <h3 class="font-semibold text-gray-900 mb-4">Import History</h3>
            <div v-if="imports?.data?.length > 0" class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Records</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="imp in imports.data" :key="imp.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                {{ imp.type }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ imp.original_filename }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-center">
                                <span class="text-green-600">{{ imp.success_count || 0 }}</span>
                                /
                                <span class="text-red-600">{{ imp.error_count || 0 }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    :class="getStatusColor(imp.status)"
                                    class="px-2 py-1 text-xs rounded-full inline-flex items-center gap-1"
                                >
                                    <component :is="getStatusIcon(imp.status)" class="w-3 h-3" />
                                    {{ imp.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                {{ formatDate(imp.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                <DocumentArrowDownIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No imports yet</h3>
                <p class="mt-1 text-sm text-gray-500">Import history will appear here.</p>
            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showUploadModal = false"></div>

                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Import {{ selectedTemplate }}
                    </h3>

                    <form @submit.prevent="submitImport" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                            <input
                                type="file"
                                accept=".csv"
                                @change="handleFileChange"
                                class="w-full border border-gray-300 rounded-lg p-2"
                                required
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Download the template first to see the required format.
                            </p>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                type="button"
                                @click="showUploadModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="uploadForm.processing"
                                class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
                            >
                                Start Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
