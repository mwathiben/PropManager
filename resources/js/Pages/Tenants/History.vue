<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { TenantHistoryPageProps } from '@/types/finances';
import {
    ArchiveBoxIcon,
    MagnifyingGlassIcon,
    UserIcon,
    CalendarDaysIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantHistoryPageProps>();

const { formatDate } = useFormatters();
const { t } = useI18n();

// Filter state
const search = ref(props.filters.search || '');
const buildingId = ref(props.filters.building_id || '');

// Apply filters
const applyFilters = () => {
    router.get(route('tenants.history'), {
        search: search.value || undefined,
        building_id: buildingId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    buildingId.value = '';
    applyFilters();
};

// Get move-out reason badge
const getMoveOutReasonBadge = (reason) => {
    if (!reason) return 'bg-gray-100 text-gray-800';
    switch (reason.toLowerCase()) {
        case 'end_of_lease':
            return 'bg-blue-100 text-blue-800';
        case 'relocation':
            return 'bg-yellow-100 text-yellow-800';
        case 'eviction':
            return 'bg-red-100 text-red-800';
        case 'mutual_termination':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Format move-out reason for display
const formatReason = (reason) => {
    if (!reason) return t('tenants.history.na');
    return reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<template>
    <Head :title="t('tenants.history.head_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">{{ t('tenants.history.heading') }}</h1>
                    <p class="text-gray-600 mt-1">{{ t('tenants.history.subtitle') }}</p>
                </div>

                <!-- Stats Card -->
                <div class="mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-gray-100 rounded-full">
                                <ArchiveBoxIcon class="w-6 h-6 text-gray-600" />
                            </div>
                            <div class="ms-4">
                                <p class="text-sm font-medium text-gray-500">{{ t('tenants.history.total_past_tenants') }}</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.total_past_tenants }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-white shadow-sm rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label for="history-search" class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.history.search_label') }}</label>
                            <div class="relative">
                                <input
                                    id="history-search"
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    :placeholder="t('tenants.history.search_placeholder')"
                                    class="w-full ps-10 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                            </div>
                        </div>

                        <div v-if="buildings?.length > 0">
                            <label for="history-building" class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.history.building_label') }}</label>
                            <select
                                id="history-building"
                                v-model="buildingId"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">{{ t('tenants.history.all_buildings') }}</option>
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
                                {{ t('tenants.history.clear') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Past Tenants Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.tenant') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.last_unit') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.lease_period') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.duration') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.move_out_reason') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants.history.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="tenant in pastTenants.data" :key="tenant.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="shrink-0 h-10 w-10 bg-gray-200 rounded-full flex items-center justify-center">
                                            <UserIcon class="w-5 h-5 text-gray-500" />
                                        </div>
                                        <div class="ms-4">
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.name }}</div>
                                            <div class="text-sm text-gray-500">{{ tenant.email }}</div>
                                            <div class="text-xs text-gray-400" v-if="tenant.mobile_number">{{ tenant.mobile_number }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <template v-if="tenant.last_lease">
                                        {{ tenant.last_lease.unit_number }}
                                        <span class="text-gray-500">({{ tenant.last_lease.building_name }})</span>
                                    </template>
                                    <span v-else class="text-gray-400">{{ t('tenants.history.na') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <template v-if="tenant.last_lease">
                                        <div class="flex items-center gap-1">
                                            <CalendarDaysIcon class="w-4 h-4 text-gray-400" />
                                            {{ formatDate(tenant.last_lease.start_date) }}
                                        </div>
                                        <div class="flex items-center gap-1" v-if="tenant.last_lease.end_date">
                                            <ArrowRightOnRectangleIcon class="w-4 h-4 text-gray-400" />
                                            {{ formatDate(tenant.last_lease.end_date) }}
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400">{{ t('tenants.history.na') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <template v-if="tenant.last_lease?.duration_months">
                                        {{ t('tenants.history.duration_months', { count: tenant.last_lease.duration_months }) }}
                                    </template>
                                    <span v-else class="text-gray-400">{{ t('tenants.history.na') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        v-if="tenant.last_lease?.move_out?.reason"
                                        :class="getMoveOutReasonBadge(tenant.last_lease.move_out.reason)"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ formatReason(tenant.last_lease.move_out.reason) }}
                                    </span>
                                    <span v-else class="text-gray-400 text-sm">{{ t('tenants.history.not_specified') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <Link
                                        :href="route('tenants.show', tenant.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ t('tenants.history.view_profile') }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="pastTenants.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <ArchiveBoxIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                                    <p class="text-lg font-medium">{{ t('tenants.history.empty.title') }}</p>
                                    <p class="text-sm">{{ t('tenants.history.empty.description') }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination v-if="pastTenants.data.length > 0" :links="pastTenants.links" color="indigo" class="mt-6" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
