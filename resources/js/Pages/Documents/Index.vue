<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useStatusColors, useAuth } from '@/composables';
import UploadDocumentModal from '@/Components/Modals/UploadDocumentModal.vue';
import {
    DocumentTextIcon,
    FolderIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    TrashIcon,
    PlusIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    documents: Object,
    buildings: Array,
    filters: Object
});

const showUploadModal = ref(false);

// Filter state
const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const modelFilter = ref(props.filters.model_type || '');
const buildingId = ref(props.filters.building_id || null);
const wingId = ref(props.filters.wing_id || null);

const applyFilters = () => {
    router.get(route('documents.index'), {
        search: search.value || undefined,
        type: typeFilter.value || undefined,
        model_type: modelFilter.value || undefined,
        building_id: buildingId.value || undefined,
        wing_id: wingId.value || undefined,
    }, {
        preserveState: true,
        replace: true
    });
};

const onBuildingFilterChange = ({ buildingId: bId, wingId: wId }) => {
    buildingId.value = bId;
    wingId.value = wId;
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    typeFilter.value = '';
    modelFilter.value = '';
    buildingId.value = null;
    wingId.value = null;
    applyFilters();
};

const downloadDocument = (documentId) => {
    window.location.href = route('documents.download', documentId);
};

const viewDocument = (documentId) => {
    window.open(route('documents.view', documentId), '_blank');
};

const deleteDocument = (documentId) => {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        router.delete(route('documents.destroy', documentId), {
            preserveScroll: true
        });
    }
};

const getDocumentTypeLabel = (type) => {
    const labels = {
        'lease_agreement': 'Lease Agreement',
        'tenant_id': 'Tenant ID',
        'tenant_passport': 'Passport',
        'bank_statement': 'Bank Statement',
        'payslip': 'Payslip',
        'reference_letter': 'Reference Letter',
        'utility_bill': 'Utility Bill',
        'other': 'Other'
    };
    return labels[type] || type;
};

// Use composables for document type colors and auth
const { documentTypeColor: getDocumentTypeColor } = useStatusColors();
const { isLandlord, isTenant, canUploadDocuments, canDeleteDocuments } = useAuth();

const getFileIcon = (document) => {
    if (document.is_pdf) return '📄';
    if (document.is_image) return '🖼️';
    return '📎';
};
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ isTenant ? 'My Documents' : 'Documents' }}</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ isTenant ? 'View your lease documents and files' : 'Manage lease agreements, tenant documents, and files' }}
                        </p>
                    </div>
                    <button
                        v-if="canUploadDocuments"
                        @click="showUploadModal = true"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 gap-2"
                    >
                        <PlusIcon class="w-5 h-5" />
                        Upload Document
                    </button>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-white shadow-sm rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search documents..."
                                    class="w-full pl-10 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                            <select
                                v-model="typeFilter"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Types</option>
                                <option value="lease_agreement">Lease Agreement</option>
                                <option value="tenant_id">Tenant ID</option>
                                <option value="tenant_passport">Passport</option>
                                <option value="bank_statement">Bank Statement</option>
                                <option value="payslip">Payslip</option>
                                <option value="reference_letter">Reference Letter</option>
                                <option value="utility_bill">Utility Bill</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Attached To</label>
                            <select
                                v-model="modelFilter"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All</option>
                                <option value="Lease">Leases</option>
                                <option value="User">Tenants</option>
                            </select>
                        </div>

                        <!-- Building/Wing Filter -->
                        <div v-if="!isTenant && buildings?.length > 0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Building / Wing</label>
                            <BuildingWingFilter
                                :buildings="buildings"
                                v-model:buildingId="buildingId"
                                v-model:wingId="wingId"
                                :showBadge="false"
                                @change="onBuildingFilterChange"
                            />
                        </div>

                        <div class="flex items-end gap-2">
                            <button
                                @click="applyFilters"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center gap-2"
                            >
                                <FunnelIcon class="w-4 h-4" />
                                Apply
                            </button>
                            <button
                                @click="clearFilters"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead v-once class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Attached To
                                </th>
                                <th class="hidden sm:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Size
                                </th>
                                <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Uploaded
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="document in documents.data" :key="document.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-3">{{ getFileIcon(document) }}</span>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ document.title }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ document.file_name }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getDocumentTypeColor(document.document_type)" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ getDocumentTypeLabel(document.document_type) }}
                                    </span>
                                </td>
                                <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ document.documentable_type }} #{{ document.documentable_id }}
                                </td>
                                <td class="hidden sm:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ document.file_size_formatted }}
                                </td>
                                <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ document.uploaded_at }}</div>
                                    <div class="text-xs text-gray-500">by {{ document.uploaded_by }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <button
                                        v-if="document.is_pdf || document.is_image"
                                        @click="viewDocument(document.id)"
                                        class="text-blue-600 hover:text-blue-900"
                                        title="View"
                                    >
                                        <EyeIcon class="w-5 h-5 inline" />
                                    </button>
                                    <button
                                        @click="downloadDocument(document.id)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Download"
                                    >
                                        <ArrowDownTrayIcon class="w-5 h-5 inline" />
                                    </button>
                                    <button
                                        v-if="canDeleteDocuments"
                                        @click="deleteDocument(document.id)"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete"
                                    >
                                        <TrashIcon class="w-5 h-5 inline" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="documents.data.length > 0" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Showing {{ documents.from }} to {{ documents.to }} of {{ documents.total }} documents
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-for="link in documents.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="[
                                    'px-3 py-1 rounded border',
                                    link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                    !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                ]"
                                :disabled="!link.url"
                                v-html="link.label"
                            />
                        </div>
                    </div>

                    <!-- Empty State -->
                    <EmptyState
                        v-if="documents.data.length === 0"
                        :icon="FolderIcon"
                        title="No documents found"
                        :description="isTenant ? 'No documents have been shared with you yet.' : 'Upload your first document to get started.'"
                        :action-label="canUploadDocuments ? 'Upload First Document' : null"
                        @action="showUploadModal = true"
                    />
                </div>
            </div>
        </div>

        <!-- Upload Modal (for users who can upload) -->
        <UploadDocumentModal
            v-if="canUploadDocuments"
            :show="showUploadModal"
            @close="showUploadModal = false"
        />
    </AuthenticatedLayout>
</template>
