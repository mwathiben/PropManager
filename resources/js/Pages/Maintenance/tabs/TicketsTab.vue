<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import TicketStatusBadge from '@/Components/TicketStatusBadge.vue';
import TicketPriorityBadge from '@/Components/TicketPriorityBadge.vue';
import { useFormatters, useAuth } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { MaintenanceTicketsTabProps } from '@/types/tickets';
import {
    MagnifyingGlassIcon,
    PlusIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<MaintenanceTicketsTabProps>();

const { t } = useI18n();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const priority = ref(props.filters?.priority || '');
const buildingId = ref(props.filters?.building_id || null);
const wingId = ref(props.filters?.wing_id || null);

const applyFilters = () => {
    router.get(route('maintenance.hub', { tab: 'tickets' }), {
        search: search.value || undefined,
        status: status.value || undefined,
        priority: priority.value || undefined,
        building_id: buildingId.value || undefined,
        wing_id: wingId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const onBuildingFilterChange = ({ buildingId: bId, wingId: wId }) => {
    buildingId.value = bId;
    wingId.value = wId;
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    priority.value = '';
    buildingId.value = null;
    wingId.value = null;
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return search.value || status.value || priority.value || buildingId.value || wingId.value;
});

const { formatDate } = useFormatters();
const { isLandlord, isTenant } = useAuth();
</script>

<template>
    <div>
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-gray-900">{{ stats?.open || 0 }}</div>
                <div class="text-sm text-gray-500">{{ t('maintenance_tickets.stats.open') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-yellow-600">{{ stats?.in_progress || 0 }}</div>
                <div class="text-sm text-gray-500">{{ t('maintenance_tickets.stats.in_progress') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-green-600">{{ stats?.resolved || 0 }}</div>
                <div class="text-sm text-gray-500">{{ t('maintenance_tickets.stats.resolved') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-red-600">{{ stats?.urgent || 0 }}</div>
                <div class="text-sm text-gray-500">{{ t('maintenance_tickets.stats.urgent') }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            :placeholder="t('maintenance_tickets.search_placeholder')"
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                        />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>

                <select
                    v-model="status"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                >
                    <option value="">{{ t('maintenance_tickets.filters.all_status') }}</option>
                    <option value="active">{{ t('maintenance_tickets.filters.active') }}</option>
                    <option v-for="(label, value) in statuses" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>

                <select
                    v-model="priority"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                >
                    <option value="">{{ t('maintenance_tickets.filters.all_priority') }}</option>
                    <option v-for="(label, value) in priorities" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>

                <BuildingWingFilter
                    v-if="!isTenant && buildings?.length > 0"
                    :buildings="buildings"
                    v-model:buildingId="buildingId"
                    v-model:wingId="wingId"
                    :showBadge="false"
                    @change="onBuildingFilterChange"
                    class="min-w-[200px]"
                />

                <Link
                    :href="route('tickets.create')"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium"
                >
                    <PlusIcon class="h-5 w-5 me-1" />
                    {{ t('maintenance_tickets.report_issue') }}
                </Link>
            </div>

            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">
                    {{ t('maintenance_tickets.clear_filters') }}
                </button>
            </div>
        </div>

        <!-- Tickets List -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div v-if="tickets?.data?.length > 0" class="divide-y divide-gray-200">
                <Link
                    v-for="ticket in tickets.data"
                    :key="ticket.id"
                    :href="route('tickets.show', ticket.id)"
                    class="block hover:bg-gray-50 transition"
                >
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <WrenchScrewdriverIcon class="h-5 w-5 text-orange-500" />
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ ticket.title }}
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <TicketPriorityBadge :priority="ticket.priority" />
                                <TicketStatusBadge :status="ticket.status" />
                            </div>
                        </div>
                        <div class="mt-2 sm:flex sm:justify-between">
                            <div class="sm:flex sm:space-x-4">
                                <p class="flex items-center text-sm text-gray-500">
                                    <span class="truncate">{{ ticket.building?.name }}</span>
                                    <span v-if="ticket.unit" class="ms-1">{{ t('maintenance_tickets.unit_prefix', { number: ticket.unit.unit_number }) }}</span>
                                </p>
                                <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                    {{ ticket.subcategory }}
                                </p>
                            </div>
                            <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                <span>{{ formatDate(ticket.created_at) }}</span>
                                <span v-if="ticket.assignee" class="ms-4">
                                    {{ t('maintenance_tickets.assigned_to', { name: ticket.assignee.name }) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-else
                :icon="WrenchScrewdriverIcon"
                :title="t('maintenance_tickets.empty.title')"
                :description="hasActiveFilters ? t('maintenance_tickets.empty.filtered') : t('maintenance_tickets.empty.none')"
                :action-label="t('maintenance_tickets.report_issue')"
                :action-href="route('tickets.create')"
            />

            <!-- Pagination -->
            <div v-if="tickets?.data?.length > 0" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        {{ t('maintenance_tickets.pagination.showing', { from: tickets.from, to: tickets.to, total: tickets.total }) }}
                    </div>
                    <div class="flex space-x-2">
                        <Link
                            v-for="link in tickets.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[link.active ? 'bg-orange-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']"
                            :disabled="!link.url"
                        >
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
