<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TicketStatusBadge from '@/Components/TicketStatusBadge.vue';
import TicketPriorityBadge from '@/Components/TicketPriorityBadge.vue';
import { useFormatters, useAuth } from '@/composables';
import type { TicketsIndexPageProps, TicketStatus, TicketCategory, TicketPriority } from '@/types';
import {
    FunnelIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    ExclamationTriangleIcon,
    WrenchScrewdriverIcon,
    ChatBubbleBottomCenterTextIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<TicketsIndexPageProps>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const category = ref(props.filters.category || '');
const priority = ref(props.filters.priority || '');
const buildingId = ref(props.filters.building_id || null);
const wingId = ref(props.filters.wing_id || null);

const applyFilters = () => {
    router.get(route('tickets.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        category: category.value || undefined,
        priority: priority.value || undefined,
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
    status.value = '';
    category.value = '';
    priority.value = '';
    buildingId.value = null;
    wingId.value = null;
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return search.value || status.value || category.value || priority.value || buildingId.value || wingId.value;
});

// Use composables
const { formatDate } = useFormatters();
const { isLandlord, isTenant, isCaretaker } = useAuth();

const getCategoryIcon = (cat) => {
    return cat === 'issue' ? WrenchScrewdriverIcon : ChatBubbleBottomCenterTextIcon;
};

const getCategoryClass = (cat) => {
    return cat === 'issue'
        ? 'bg-orange-50 text-orange-700 border-orange-200'
        : 'bg-indigo-50 text-indigo-700 border-indigo-200';
};

const emptyStateTitle = computed(() => isTenant.value ? 'No issues reported' : 'No tickets found');
const emptyStateDescription = computed(() => {
    if (hasActiveFilters.value) return 'Try adjusting your filters.';
    if (isTenant.value) return 'Having a problem? Report it and we\'ll help you.';
    return 'No open issues or complaints to address.';
});
const emptyStateActionLabel = computed(() => isTenant.value ? 'Report an Issue' : 'Report Issue');
</script>

<template>
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ isTenant ? 'My Issues & Requests' : 'Issues & Complaints' }}</h1>
                        <p class="text-gray-600 mt-1">
                            {{ isTenant ? 'Track your reported issues and requests' : 'Track and manage maintenance issues and complaints' }}
                        </p>
                    </div>
                    <Link
                        :href="route('tickets.create')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <PlusIcon class="h-5 w-5 me-2" />
                        Report Issue
                    </Link>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="text-2xl font-bold text-gray-900">{{ stats.open }}</div>
                        <div class="text-sm text-gray-500">Open Tickets</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="text-2xl font-bold text-green-600">{{ stats.resolved }}</div>
                        <div class="text-sm text-gray-500">Resolved</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="text-2xl font-bold text-red-600">{{ stats.urgent }}</div>
                        <div class="text-sm text-gray-500">Urgent</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="text-2xl font-bold text-gray-600">{{ stats.total }}</div>
                        <div class="text-sm text-gray-500">Total</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4 mb-6 border">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search by title..."
                                    class="w-full ps-10 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select
                                v-model="status"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option v-for="(label, value) in statuses" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select
                                v-model="category"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">All</option>
                                <option v-for="(label, value) in categories" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Priority Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select
                                v-model="priority"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">All</option>
                                <option v-for="(label, value) in priorities" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Building/Wing Filter (for landlords/caretakers only) -->
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
                    </div>

                    <div v-if="hasActiveFilters" class="mt-4 flex justify-end">
                        <button
                            @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700"
                        >
                            Clear filters
                        </button>
                    </div>
                </div>

                <!-- Tickets List -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden border">
                    <div v-if="tickets.data.length > 0" class="divide-y divide-gray-200">
                        <Link
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            :href="route('tickets.show', ticket.id)"
                            class="block hover:bg-gray-50 transition"
                        >
                            <div class="px-4 py-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <component
                                            :is="getCategoryIcon(ticket.category)"
                                            :class="['h-5 w-5', ticket.category === 'issue' ? 'text-orange-500' : 'text-indigo-500']"
                                        />
                                        <p class="text-sm font-medium text-indigo-600 truncate">
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
                                            <span v-if="ticket.unit" class="ms-1">- Unit {{ ticket.unit.unit_number }}</span>
                                        </p>
                                        <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                            {{ ticket.subcategory }}
                                        </p>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                        <span>{{ formatDate(ticket.created_at) }}</span>
                                        <span v-if="ticket.assignee" class="ms-4">
                                            Assigned to {{ ticket.assignee.name }}
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
                        :title="emptyStateTitle"
                        :description="emptyStateDescription"
                        :action-label="emptyStateActionLabel"
                        :action-href="route('tickets.create')"
                    />

                    <!-- Pagination -->
                    <div v-if="tickets.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ tickets.from }} to {{ tickets.to }} of {{ tickets.total }} results
                            </div>
                            <div class="flex space-x-2">
                                <Link
                                    v-for="link in tickets.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :class="[
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                        'px-3 py-1 text-sm border rounded-md'
                                    ]"
                                    :disabled="!link.url"
                                >
                                    <PaginatorLink :label="link.label" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
