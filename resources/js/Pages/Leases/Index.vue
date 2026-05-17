<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import type { LeasesIndexPageProps } from '@/types/finances';
import {
    DocumentDuplicateIcon,
    MagnifyingGlassIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentArrowDownIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<LeasesIndexPageProps>();

const { formatCurrency, formatDate } = useFormatters();

// Filter state
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const buildingId = ref(props.filters.building_id || '');

// Apply filters
const applyFilters = () => {
    router.get(route('leases.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        building_id: buildingId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    status.value = '';
    buildingId.value = '';
    applyFilters();
};

// Get status badge class
const getStatusBadge = (isActive) => {
    return isActive
        ? 'bg-green-100 text-green-800'
        : 'bg-gray-100 text-gray-800';
};

// Calculate lease duration
const getLeaseDuration = (startDate, endDate) => {
    if (!startDate) return 'N/A';
    const start = new Date(startDate);
    const end = endDate ? new Date(endDate) : new Date();
    const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30));
    return months > 0 ? `${months} months` : 'Less than a month';
};
</script>

<template>
    <Head title="Lease Agreements" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Lease Agreements</h1>
                    <p class="text-gray-600 mt-1">View and manage all lease agreements</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-indigo-100 rounded-full">
                                <DocumentDuplicateIcon class="w-6 h-6 text-indigo-600" />
                            </div>
                            <div class="ms-4">
                                <p class="text-sm font-medium text-gray-500">Total Leases</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.total_leases }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-full">
                                <CheckCircleIcon class="w-6 h-6 text-green-600" />
                            </div>
                            <div class="ms-4">
                                <p class="text-sm font-medium text-gray-500">Active Leases</p>
                                <p class="text-2xl font-bold text-green-600">{{ stats.active_leases }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-gray-100 rounded-full">
                                <XCircleIcon class="w-6 h-6 text-gray-600" />
                            </div>
                            <div class="ms-4">
                                <p class="text-sm font-medium text-gray-500">Terminated Leases</p>
                                <p class="text-2xl font-bold text-gray-600">{{ stats.terminated_leases }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-white shadow-sm rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search by tenant name or unit..."
                                    class="w-full ps-10 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select
                                v-model="status"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>

                        <div v-if="buildings?.length > 0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Building</label>
                            <select
                                v-model="buildingId"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Buildings</option>
                                <option v-for="building in buildings" :key="building.id" :value="building.id">
                                    {{ building.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <button
                                @click="clearFilters"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Leases Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Rent</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="lease in leases.data" :key="lease.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ lease.tenant?.name || 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ lease.tenant?.email || '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ lease.unit?.unit_number || 'N/A' }}
                                    <span class="text-gray-500" v-if="lease.unit?.building">
                                        ({{ lease.unit.building.name }})
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(lease.start_date) }}
                                    <div class="text-xs text-gray-400">
                                        {{ getLeaseDuration(lease.start_date, lease.end_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ formatCurrency(lease.rent_amount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusBadge(lease.is_active)" class="px-2 py-1 text-xs font-medium rounded-full">
                                        {{ lease.is_active ? 'Active' : 'Terminated' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span v-if="lease.documents?.length > 0" class="flex items-center gap-1">
                                        <DocumentArrowDownIcon class="w-4 h-4 text-green-600" />
                                        {{ lease.documents.length }} doc(s)
                                    </span>
                                    <span v-else class="text-gray-400">No documents</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <Link
                                        v-if="lease.tenant"
                                        :href="route('tenants.show', lease.tenant.id)"
                                        class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                        View Tenant
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="leases.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <DocumentDuplicateIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                                    <p class="text-lg font-medium">No lease agreements found</p>
                                    <p class="text-sm">Lease agreements will appear here when tenants are added</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination v-if="leases.data.length > 0" :links="leases.links" color="indigo" class="mt-6" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
