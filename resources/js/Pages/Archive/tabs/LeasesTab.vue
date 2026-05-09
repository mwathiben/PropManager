<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import type { ArchiveLeasesTabProps } from '@/types';
import {
    MagnifyingGlassIcon,
    DocumentDuplicateIcon,
    EyeIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<ArchiveLeasesTabProps>();

const { formatDate, formatCurrency } = useFormatters();

const search = ref(props.filters?.search || '');
const buildingId = ref(props.filters?.building_id || '');
const status = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('archive.hub', { tab: 'leases' }), {
        search: search.value || undefined,
        building_id: buildingId.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    search.value = '';
    buildingId.value = '';
    status.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return search.value || buildingId.value || status.value;
});
</script>

<template>
    <div>
        <!-- Filters -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Search by tenant..."
                            class="w-full pl-10 border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                        />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                    </div>
                </div>

                <select
                    v-model="buildingId"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                >
                    <option value="">All Buildings</option>
                    <option v-for="building in buildings" :key="building.id" :value="building.id">
                        {{ building.name }}
                    </option>
                </select>

                <select
                    v-model="status"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>

            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">
                    Clear filters
                </button>
            </div>
        </div>

        <!-- Leases Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="leases?.data?.length > 0" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tenant
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Unit
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Period
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Rent
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="lease in leases.data" :key="lease.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ lease.tenant?.name || 'Unknown' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ lease.tenant?.email }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                Unit {{ lease.unit?.unit_number }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ lease.unit?.building?.name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>{{ formatDate(lease.start_date) }}</div>
                            <div class="text-xs">to {{ formatDate(lease.end_date) || 'Ongoing' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            {{ formatCurrency(lease.rent_amount) }}/mo
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span
                                :class="lease.is_active
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-gray-100 text-gray-800'"
                                class="px-2 py-1 text-xs font-medium rounded-full"
                            >
                                {{ lease.is_active ? 'Active' : 'Ended' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link
                                    :href="route('leases.show', lease.id)"
                                    class="text-gray-600 hover:text-gray-900"
                                >
                                    <EyeIcon class="w-5 h-5" />
                                </Link>
                                <a
                                    v-if="lease.document_path"
                                    :href="route('leases.download', lease.id)"
                                    class="text-gray-600 hover:text-gray-900"
                                >
                                    <ArrowDownTrayIcon class="w-5 h-5" />
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <EmptyState
                v-else
                :icon="DocumentDuplicateIcon"
                title="No leases found"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'Lease agreements will appear here.'"
            />

            <!-- Pagination -->
            <div v-if="leases?.data?.length > 0 && leases.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ leases.from }} to {{ leases.to }} of {{ leases.total }} results
                    </div>
                    <div class="flex space-x-2">
                        <Link
                            v-for="link in leases.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                'px-3 py-1 text-sm border rounded-md'
                            ]"
                            v-html="link.label"
                            :disabled="!link.url"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
