<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import TenantProfileModal from '@/Components/Modals/TenantProfileModal.vue';
import SlideOutPanel from '@/Components/SlideOutPanel.vue';
import AddWingModal from '@/Components/Modals/AddWingModal.vue';
import MassHikeModal from '@/Components/Modals/MassHikeModal.vue';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { useFormatters } from '@/composables';
import {
    UserGroupIcon,
    WrenchScrewdriverIcon,
    PlusIcon,
    CheckCircleIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    MegaphoneIcon,
    DocumentTextIcon,
    TicketIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    HomeModernIcon,
    ChevronRightIcon,
    ChevronDownIcon,
    BuildingOffice2Icon,
    Squares2X2Icon,
    ListBulletIcon,
    FunnelIcon,
    XMarkIcon,
    PhoneIcon,
    IdentificationIcon,
    CheckBadgeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    properties: Array,
    property: Object,
    buildings: Array,
    activeBuilding: Object,
    wings: Array,
    hasWings: Boolean,
    activeWingId: Number,
    activeFloor: Number,
    allFloors: Array,
    units: Array,
    allUnits: Array,
    unitsByWing: Array,
    actionItems: Object,
    financialMetrics: Object,
    arrearsAging: Object,
    stats: Object,
    recentPayments: Array,
    recentTickets: Array,
    expiringLeases: Array,
    tenantKycStats: Object,
});

// --- STATE ---
const selectedUnit = ref(null);
const showAddWingModal = ref(false);
const showProfileModal = ref(false);
const showMassHikeModal = ref(false);
const showPaymentPanel = ref(false);
const selectedPayment = ref(null);
const viewMode = ref('grid'); // 'grid' or 'list'
const unitDetail = ref(null);
const loadingUnitDetail = ref(false);

// Wing/Floor filter state (initialized from props)
const activeWingFilter = ref(props.activeWingId || null);
const activeFloorFilter = ref(props.activeFloor || null);

// Fetch unit detail when a unit is selected
watch(() => selectedUnit.value, async (unit) => {
    if (unit && unit.status === 'occupied') {
        loadingUnitDetail.value = true;
        try {
            const response = await fetch(route('units.detail', unit.id));
            unitDetail.value = await response.json();
        } catch (e) {
            console.error('Failed to load unit detail', e);
            unitDetail.value = null;
        } finally {
            loadingUnitDetail.value = false;
        }
    } else {
        unitDetail.value = null;
    }
}, { immediate: false });

// --- ACTIONS ---
const selectUnit = (unit) => {
    selectedUnit.value = unit;
};

const closePanel = () => {
    selectedUnit.value = null;
};

const switchLocation = (propertyId, buildingId) => {
    router.get(route('dashboard'), {
        property_id: propertyId,
        building_id: buildingId
    }, {
        preserveState: false,
        preserveScroll: false,
        onSuccess: () => selectedUnit.value = null
    });
};

const isActiveBuilding = (propertyId, buildingId) => {
    return props.property?.id === propertyId && props.activeBuilding?.id === buildingId;
};

// Wing/Floor filter actions
const setWingFilter = (wingId) => {
    activeWingFilter.value = wingId;
    activeFloorFilter.value = null; // Reset floor when wing changes
    router.get(route('dashboard'), {
        property_id: props.property?.id,
        building_id: props.activeBuilding?.id,
        wing_id: wingId,
        floor: null
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const setFloorFilter = (floor) => {
    activeFloorFilter.value = floor;
    router.get(route('dashboard'), {
        property_id: props.property?.id,
        building_id: props.activeBuilding?.id,
        wing_id: activeWingFilter.value,
        floor: floor
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    activeWingFilter.value = null;
    activeFloorFilter.value = null;
    router.get(route('dashboard'), {
        property_id: props.property?.id,
        building_id: props.activeBuilding?.id
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const viewPayment = (payment) => {
    selectedPayment.value = payment;
    showPaymentPanel.value = true;
};

// --- HELPERS (from composables) ---
const { formatMoney, formatDate, formatRelativeDate } = useFormatters();

const totalArrears = computed(() => {
    return (props.arrearsAging?.['0_30'] || 0) +
           (props.arrearsAging?.['31_60'] || 0) +
           (props.arrearsAging?.['61_90'] || 0) +
           (props.arrearsAging?.['90_plus'] || 0);
});

const getArrearsPercentage = (amount) => {
    if (totalArrears.value === 0) return 0;
    return Math.round((amount / totalArrears.value) * 100);
};

const occupiedUnits = computed(() => props.stats?.occupied_units || 0);

const occupiedUnitIds = computed(() =>
    props.units?.filter(u => u.status === 'occupied').map(u => u.id) || []
);

// Computed style for grid layout (avoids inline string concatenation)
const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.activeBuilding?.units_per_floor || 4}, minmax(80px, 1fr))`
}));

// Display units (respects wing/floor filters)
const displayUnits = computed(() => props.units || []);

// Group units by floor (descending order - top floors first)
const unitsByFloor = computed(() => {
    const grouped = {};
    displayUnits.value.forEach(unit => {
        const floor = unit.floor_number || 1;
        if (!grouped[floor]) grouped[floor] = [];
        grouped[floor].push(unit);
    });
    // Return as sorted array of [floor, units] pairs (descending)
    return Object.entries(grouped)
        .map(([floor, units]) => ({ floor: parseInt(floor), units }))
        .sort((a, b) => b.floor - a.floor);
});

// Get maximum floors across all wings for side-by-side display
const maxFloor = computed(() => {
    if (!props.allFloors || props.allFloors.length === 0) return 1;
    return Math.max(...props.allFloors);
});

// Check if filters are active
const hasActiveFilters = computed(() => {
    return activeWingFilter.value !== null || activeFloorFilter.value !== null;
});

// Per-floor grid style (responsive columns)
const getFloorGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(auto-fill, minmax(80px, 1fr))`
}));

// Units to display in the wing grid (filtered or all)
const displayedUnits = computed(() => {
    if (!activeWingFilter.value) return props.allUnits || [];
    return (props.allUnits || []).filter(u => u.building_id === activeWingFilter.value);
});

// Get active wing name for display
const activeWingName = computed(() => {
    if (!activeWingFilter.value) return '';
    const wing = props.wings?.find(w => w.id === activeWingFilter.value);
    return wing?.name || '';
});

// Scroll to occupancy map section
const scrollToOccupancyMap = () => {
    document.getElementById('occupancy-map')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
};

// Select unit by ID and scroll to map
const selectUnitById = (unitId) => {
    const unit = props.units?.find(u => u.id === unitId);
    if (unit) {
        selectUnit(unit);
        scrollToOccupancyMap();
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!-- Top Bar: Location Dropdown + Actions -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Unified Property/Building Dropdown -->
                    <Dropdown align="left" width="72">
                        <template #trigger>
                            <button class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <BuildingOffice2Icon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-semibold text-gray-900 truncate max-w-[250px]">
                                    {{ property.name }}{{ activeBuilding.name !== property.name ? ' - ' + activeBuilding.name : '' }}
                                </span>
                                <ChevronDownIcon class="w-4 h-4 text-gray-400" />
                            </button>
                        </template>
                        <template #content>
                            <div class="py-1 max-h-80 overflow-y-auto">
                                <template v-for="prop in properties" :key="prop.id">
                                    <!-- Property Header -->
                                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50 sticky top-0">
                                        {{ prop.name }}
                                    </div>
                                    <!-- Buildings under this property -->
                                    <button
                                        v-for="building in prop.buildings"
                                        :key="building.id"
                                        @click="switchLocation(prop.id, building.id)"
                                        :class="[
                                            'w-full text-left px-4 py-2 pl-6 text-sm transition-colors flex items-center gap-2',
                                            isActiveBuilding(prop.id, building.id)
                                                ? 'bg-indigo-50 text-indigo-700 font-medium'
                                                : 'text-gray-700 hover:bg-gray-50'
                                        ]"
                                    >
                                        <CheckCircleIcon v-if="isActiveBuilding(prop.id, building.id)" class="w-4 h-4" />
                                        <span :class="{ 'ml-6': !isActiveBuilding(prop.id, building.id) }">{{ building.name }}</span>
                                    </button>
                                </template>
                                <!-- Add Wing option -->
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <button
                                        @click="showAddWingModal = true"
                                        class="w-full text-left px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 flex items-center gap-2"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        Add Wing
                                    </button>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- Quick Actions - Wrap on mobile -->
                <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
                    <button @click="showMassHikeModal = true"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                        <MegaphoneIcon class="w-4 h-4 mr-1.5" /> Rent Hike
                    </button>
                    <Link :href="route('buildings.edit', activeBuilding.id)"
                          class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        <WrenchScrewdriverIcon class="w-4 h-4 mr-1.5" /> Architect
                    </Link>
                </div>
            </div>
        </template>

        <!-- Wing/Floor Filter Bar (only shown when building has wings) -->
        <div v-if="hasWings" class="bg-white border-b border-gray-200 px-6 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <FunnelIcon class="w-4 h-4" />
                    <span>Filter:</span>
                </div>

                <!-- Wing Filter Chips -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        @click="setWingFilter(null)"
                        :class="[
                            'px-3 py-1.5 text-sm font-medium rounded-full transition-colors',
                            activeWingFilter === null
                                ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        ]"
                    >
                        All Wings
                    </button>
                    <button
                        v-for="wing in wings"
                        :key="wing.id"
                        @click="setWingFilter(wing.id)"
                        :class="[
                            'px-3 py-1.5 text-sm font-medium rounded-full transition-colors',
                            activeWingFilter === wing.id
                                ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        ]"
                    >
                        {{ wing.name }}
                    </button>
                </div>

                <!-- Floor Filter Dropdown -->
                <div v-if="allFloors && allFloors.length > 1" class="flex items-center gap-2 ml-4">
                    <span class="text-sm text-gray-500">Floor:</span>
                    <select
                        :value="activeFloorFilter"
                        @change="setFloorFilter($event.target.value ? parseInt($event.target.value) : null)"
                        class="text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1.5"
                    >
                        <option value="">All Floors</option>
                        <option v-for="floor in allFloors" :key="floor" :value="floor">
                            Floor {{ floor }}
                        </option>
                    </select>
                </div>

                <!-- Clear Filters -->
                <button
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="ml-auto flex items-center gap-1 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <XMarkIcon class="w-4 h-4" />
                    Clear Filters
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="p-6 lg:p-8 space-y-6">
            <!-- === ACTION ITEMS (Top - Color-coded urgency) === -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <ActionItemCard
                    v-if="actionItems.overdue_invoices > 0"
                    urgency="critical"
                    :icon="DocumentTextIcon"
                    :count="actionItems.overdue_invoices"
                    title="Overdue Invoices"
                    :description="formatMoney(actionItems.overdue_amount) + ' outstanding'"
                    actionLabel="View All"
                    :actionHref="route('invoices.index', { status: 'overdue' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="DocumentTextIcon"
                    :count="0"
                    title="Overdue Invoices"
                    description="All invoices are current"
                    actionLabel="View All"
                    :actionHref="route('invoices.index')"
                />

                <ActionItemCard
                    v-if="actionItems.expiring_leases > 0"
                    urgency="high"
                    :icon="CalendarDaysIcon"
                    :count="actionItems.expiring_leases"
                    title="Expiring Leases"
                    description="Within the next 30 days"
                    actionLabel="View"
                    :actionHref="route('leases.index')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="CalendarDaysIcon"
                    :count="0"
                    title="Expiring Leases"
                    description="No leases expiring soon"
                    actionLabel="View All"
                    :actionHref="route('leases.index')"
                />

                <ActionItemCard
                    v-if="actionItems.urgent_tickets > 0"
                    urgency="critical"
                    :icon="TicketIcon"
                    :count="actionItems.urgent_tickets"
                    title="Urgent Tickets"
                    description="Require immediate attention"
                    actionLabel="View"
                    :actionHref="route('tickets.index', { priority: 'urgent' })"
                />
                <ActionItemCard
                    v-else-if="actionItems.vacant_units > 0"
                    urgency="medium"
                    :icon="HomeModernIcon"
                    :count="actionItems.vacant_units"
                    title="Vacant Units"
                    description="Available for lease"
                    actionLabel="View"
                    :actionHref="route('dashboard', { status: 'vacant' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="TicketIcon"
                    :count="0"
                    title="Urgent Tickets"
                    description="No urgent issues"
                    actionLabel="View All"
                    :actionHref="route('tickets.index')"
                />

                <ActionItemCard
                    v-if="actionItems.pending_readings > 0"
                    urgency="medium"
                    :icon="ClipboardDocumentListIcon"
                    :count="actionItems.pending_readings"
                    title="Pending Readings"
                    description="Awaiting approval"
                    actionLabel="Review"
                    :actionHref="route('readings.review')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="ClipboardDocumentListIcon"
                    :count="0"
                    title="Pending Readings"
                    description="All readings processed"
                    actionLabel="View All"
                    :actionHref="route('readings.index')"
                />

                <ActionItemCard
                    v-if="tenantKycStats?.incomplete > 0"
                    urgency="medium"
                    :icon="IdentificationIcon"
                    :count="tenantKycStats.incomplete"
                    title="Incomplete KYC"
                    :description="tenantKycStats.rate + '% verified'"
                    actionLabel="View"
                    :actionHref="route('tenants.index', { kyc_status: 'incomplete' })"
                />
                <ActionItemCard
                    v-else-if="tenantKycStats?.total > 0"
                    urgency="low"
                    :icon="IdentificationIcon"
                    :count="tenantKycStats.total"
                    title="Tenant KYC"
                    description="All tenants verified"
                    actionLabel="View All"
                    :actionHref="route('tenants.index')"
                />
            </div>

            <!-- === KEY METRICS === -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <MetricCard
                    title="Monthly Revenue"
                    :value="formatMoney(financialMetrics.monthly_revenue)"
                    :subtitle="'Expected: ' + formatMoney(financialMetrics.expected_revenue)"
                    :icon="BanknotesIcon"
                    iconBgColor="bg-green-100"
                    iconColor="text-green-600"
                    :href="route('invoices.index')"
                    :trend="financialMetrics.collection_rate >= 80
                        ? { direction: 'up', value: financialMetrics.collection_rate + '%' }
                        : { direction: 'down', value: financialMetrics.collection_rate + '%' }"
                />

                <MetricCard
                    title="Collection Rate"
                    :value="financialMetrics.collection_rate + '%'"
                    subtitle="This month"
                    :icon="ChartBarIcon"
                    iconBgColor="bg-blue-100"
                    iconColor="text-blue-600"
                    :href="route('reports.index')"
                />

                <MetricCard
                    title="Total Arrears"
                    :value="formatMoney(financialMetrics.total_arrears)"
                    subtitle="Outstanding balance"
                    :icon="ExclamationTriangleIcon"
                    iconBgColor="bg-red-100"
                    iconColor="text-red-600"
                    :href="route('invoices.index', { has_arrears: true })"
                />

                <MetricCard
                    title="Occupancy Rate"
                    :value="stats.occupancy_rate + '%'"
                    :subtitle="stats.occupied_units + ' / ' + stats.total_units + ' units'"
                    :icon="UserGroupIcon"
                    iconBgColor="bg-indigo-100"
                    iconColor="text-indigo-600"
                    :href="route('dashboard', { status: 'vacant' })"
                />
            </div>

            <!-- === TWO-COLUMN: OCCUPANCY MAP + ARREARS AGING === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- OCCUPANCY MAP (2 columns) -->
                <div id="occupancy-map" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Header with View Toggle -->
                    <div class="p-4 sm:p-6 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">{{ activeBuilding.name }}</h3>
                                <p class="text-sm text-gray-500">
                                    <template v-if="hasWings && !activeWingFilter">
                                        {{ allUnits?.length || 0 }} units across {{ wings.length }} wings
                                    </template>
                                    <template v-else-if="hasWings && activeWingFilter">
                                        {{ displayedUnits?.length || 0 }} units in {{ activeWingName }}
                                    </template>
                                    <template v-else>
                                        {{ units.length }} units across {{ unitsByFloor.length }} floors
                                    </template>
                                </p>
                            </div>

                            <!-- View Toggle + Legend -->
                            <div class="flex items-center gap-4">
                                <!-- View Toggle (only show for single wing/building view) -->
                                <div v-if="!hasWings || activeWingFilter" class="flex bg-gray-100 rounded-lg p-1">
                                    <button
                                        @click="viewMode = 'grid'"
                                        :class="viewMode === 'grid' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                                    >
                                        <Squares2X2Icon class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="viewMode = 'list'"
                                        :class="viewMode === 'list' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                                    >
                                        <ListBulletIcon class="w-4 h-4" />
                                    </button>
                                </div>

                                <!-- Legend -->
                                <div class="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full"></span> Occupied</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-300 rounded-full"></span> Vacant</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span> Arrears</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scrollable Content Area -->
                    <div class="max-h-[500px] overflow-y-auto">

                        <!-- MODERN TABBED WINGS VIEW (when building has wings) -->
                        <template v-if="hasWings">
                            <!-- Wing Tabs -->
                            <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 overflow-x-auto">
                                <button
                                    @click="setWingFilter(null)"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap',
                                        !activeWingFilter
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    ]"
                                >
                                    All Wings
                                    <span class="ml-1.5 text-xs opacity-75">({{ allUnits?.length }})</span>
                                </button>
                                <button
                                    v-for="wing in wings"
                                    :key="wing.id"
                                    @click="setWingFilter(wing.id)"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap',
                                        activeWingFilter === wing.id
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ wing.name }}
                                    <span class="ml-1.5 text-xs opacity-75">({{ wing.units?.length || unitsByWing.find(w => w.wing.id === wing.id)?.units?.length || 0 }})</span>
                                </button>
                            </div>

                            <!-- Unified Grid (All Wings or Filtered) -->
                            <div class="p-4">
                                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
                                    <button
                                        v-for="unit in displayedUnits"
                                        :key="unit.id"
                                        @click="selectUnit(unit)"
                                        :class="[
                                            'relative aspect-square rounded-xl border-2 p-1 flex flex-col items-center justify-center transition-all shadow-sm',
                                            selectedUnit?.id === unit.id ? 'ring-2 ring-indigo-500 ring-offset-2' : 'hover:shadow-md hover:-translate-y-0.5',
                                            unit.status === 'occupied' ? 'bg-green-50 border-green-200' :
                                            unit.status === 'arrears' ? 'bg-red-50 border-red-200' :
                                            'bg-white border-gray-200'
                                        ]"
                                        :title="'Unit ' + unit.unit_number + ' (' + (unit.wing_name || unit.building?.name || '') + ') - ' + (unit.status === 'occupied' ? 'Occupied' : unit.status === 'arrears' ? 'Arrears' : 'Vacant')"
                                    >
                                        <!-- Wing Badge -->
                                        <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-indigo-100 text-indigo-700 shadow-sm">
                                            {{ unit.building?.unit_prefix || unit.wing_name?.charAt(0) || '' }}
                                        </span>
                                        <span class="text-xs font-bold text-gray-700">{{ unit.unit_number }}</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- SINGLE BUILDING/WING VIEW (normal grid or list) -->
                        <template v-else>
                            <!-- GRID VIEW -->
                            <template v-if="viewMode === 'grid'">
                                <div v-for="floorGroup in unitsByFloor" :key="floorGroup.floor" class="border-b border-gray-100 last:border-b-0">
                                    <!-- Floor Header -->
                                    <div class="px-4 sm:px-6 py-2 bg-gray-50 sticky top-0 z-10">
                                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Floor {{ floorGroup.floor }}
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2">
                                            {{ floorGroup.units.filter(u => u.status === 'occupied').length }}/{{ floorGroup.units.length }} occupied
                                        </span>
                                    </div>

                                    <!-- Floor Units Grid -->
                                    <div class="p-4 sm:p-6 pt-3">
                                        <div class="grid gap-2 sm:gap-3" :style="getFloorGridStyle">
                                            <button
                                                v-for="unit in floorGroup.units"
                                                :key="unit.id"
                                                @click="selectUnit(unit)"
                                                :class="[
                                                    selectedUnit?.id === unit.id
                                                        ? 'ring-2 ring-indigo-500 ring-offset-1'
                                                        : 'hover:scale-105',
                                                    unit.status === 'occupied' ? 'bg-green-50 border-green-200 text-green-700' :
                                                    unit.status === 'arrears' ? 'bg-red-50 border-red-200 text-red-700' :
                                                    'bg-gray-50 border-gray-200 text-gray-500'
                                                ]"
                                                class="relative aspect-square rounded-lg border p-2 flex flex-col items-center justify-center transition-all text-center"
                                            >
                                                <span class="text-sm font-bold">{{ unit.unit_number }}</span>
                                                <span class="text-[10px] uppercase tracking-wide mt-0.5">
                                                    {{ unit.status === 'occupied' ? 'Occ' : unit.status === 'arrears' ? 'Late' : 'Vac' }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                        <!-- LIST VIEW -->
                        <template v-else>
                            <div v-for="floorGroup in unitsByFloor" :key="floorGroup.floor">
                                <!-- Floor Header -->
                                <div class="px-4 sm:px-6 py-2 bg-gray-50 sticky top-0 z-10 border-b border-gray-100">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Floor {{ floorGroup.floor }}
                                    </span>
                                </div>

                                <!-- Floor Units List -->
                                <div class="divide-y divide-gray-100">
                                    <button
                                        v-for="unit in floorGroup.units"
                                        :key="unit.id"
                                        @click="selectUnit(unit)"
                                        :class="selectedUnit?.id === unit.id ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                                        class="w-full px-4 sm:px-6 py-3 flex items-center justify-between text-left transition-colors"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div :class="[
                                                'w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm',
                                                unit.status === 'occupied' ? 'bg-green-100 text-green-700' :
                                                unit.status === 'arrears' ? 'bg-red-100 text-red-700' :
                                                'bg-gray-100 text-gray-500'
                                            ]">
                                                {{ unit.unit_number }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ unit.unit_type || 'Unit' }} {{ unit.unit_number }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ formatMoney(unit.target_rent) }}/month
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span :class="[
                                                'text-xs font-medium px-2 py-1 rounded-full',
                                                unit.status === 'occupied' ? 'bg-green-100 text-green-700' :
                                                unit.status === 'arrears' ? 'bg-red-100 text-red-700' :
                                                'bg-gray-100 text-gray-500'
                                            ]">
                                                {{ unit.status === 'occupied' ? 'Occupied' : unit.status === 'arrears' ? 'Arrears' : 'Vacant' }}
                                            </span>
                                            <ChevronRightIcon class="w-4 h-4 text-gray-400" />
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </template>
                        </template>
                    </div>
                </div>

                <!-- ARREARS AGING (1 column) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">
                        Arrears Aging
                        <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ml-1">({{ activeWingName }})</span>
                    </h3>

                    <div v-if="totalArrears > 0" class="space-y-4">
                        <!-- 0-30 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '0_30' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                    <span class="text-sm text-gray-600">0-30 days</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['0_30']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-yellow-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['0_30']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 31-60 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '31_60' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-orange-400"></div>
                                    <span class="text-sm text-gray-600">31-60 days</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['31_60']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-orange-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['31_60']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 61-90 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '61_90' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                    <span class="text-sm text-gray-600">61-90 days</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['61_90']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-red-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['61_90']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 90+ Days -->
                        <Link :href="route('invoices.index', { arrears_age: '90_plus' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-700"></div>
                                    <span class="text-sm text-gray-600">90+ days</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['90_plus']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-red-700 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['90_plus']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- Total -->
                        <Link :href="route('invoices.index', { status: 'overdue' })" class="block pt-4 border-t border-gray-200 mt-4 hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Total Outstanding</span>
                                <span class="text-xl font-bold text-gray-900">{{ formatMoney(totalArrears) }}</span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">No outstanding arrears</p>
                        <p class="text-sm text-gray-400">All invoices are current</p>
                    </div>
                </div>
            </div>

            <!-- === RECENT PAYMENTS + TICKETS + EXPIRING LEASES === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- RECENT PAYMENTS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            Recent Payments
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ml-1">({{ activeWingName }})</span>
                        </h3>
                        <Link :href="route('invoices.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            View All <ChevronRightIcon class="w-4 h-4 ml-1" />
                        </Link>
                    </div>

                    <div v-if="recentPayments && recentPayments.length > 0" class="space-y-3">
                        <Link v-for="payment in recentPayments" :key="payment.id"
                             :href="route('invoices.show', payment.invoice?.id)"
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <BanknotesIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ payment.invoice?.lease?.tenant?.name || 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500">{{ payment.invoice?.lease?.unit?.unit_number || '-' }} • {{ formatDate(payment.payment_date) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600">{{ formatMoney(payment.amount) }}</p>
                                <p class="text-xs text-gray-500">{{ payment.payment_method }}</p>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8">
                        <BanknotesIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">No recent payments</p>
                    </div>
                </div>

                <!-- RECENT TICKETS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            Recent Tickets
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ml-1">({{ activeWingName }})</span>
                        </h3>
                        <Link :href="route('tickets.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            View All <ChevronRightIcon class="w-4 h-4 ml-1" />
                        </Link>
                    </div>

                    <div v-if="recentTickets && recentTickets.length > 0" class="space-y-3">
                        <Link v-for="ticket in recentTickets" :key="ticket.id"
                             :href="route('tickets.show', ticket.id)"
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                            <div class="flex items-center gap-3">
                                <div :class="[
                                    'h-10 w-10 rounded-full flex items-center justify-center',
                                    ticket.priority === 'urgent' ? 'bg-red-100 text-red-600' :
                                    ticket.priority === 'high' ? 'bg-orange-100 text-orange-600' :
                                    'bg-gray-100 text-gray-600'
                                ]">
                                    <TicketIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 truncate max-w-[140px]">{{ ticket.title }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ ticket.unit_number || 'Building' }} • {{ ticket.reporter_name || 'Unknown' }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span :class="[
                                    'text-xs px-2 py-1 rounded-full font-medium',
                                    ticket.status === 'open' ? 'bg-yellow-100 text-yellow-800' :
                                    ticket.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                    ticket.status === 'resolved' ? 'bg-green-100 text-green-800' :
                                    'bg-gray-100 text-gray-800'
                                ]">
                                    {{ ticket.status?.replace('_', ' ') }}
                                </span>
                                <p class="text-xs text-gray-500 mt-1">{{ formatRelativeDate(ticket.created_at) }}</p>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8">
                        <TicketIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">No recent tickets</p>
                    </div>
                </div>

                <!-- EXPIRING LEASES -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            Expiring Leases
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ml-1">({{ activeWingName }})</span>
                        </h3>
                        <span class="text-sm text-gray-500">Next 60 days</span>
                    </div>

                    <div v-if="expiringLeases && expiringLeases.length > 0" class="space-y-3">
                        <button v-for="lease in expiringLeases" :key="lease.id"
                             @click="selectUnitById(lease.unit?.id)"
                             class="w-full flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-100 hover:bg-orange-100 cursor-pointer transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                    <CalendarDaysIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ lease.tenant?.name || 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500">{{ lease.unit?.building?.name }} - {{ lease.unit?.unit_number }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-orange-600">{{ formatRelativeDate(lease.end_date) }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(lease.end_date) }}</p>
                            </div>
                        </button>
                    </div>
                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">No leases expiring soon</p>
                        <p class="text-sm text-gray-400">All leases are current</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR (Unit Context Panel) - Using SlideOutPanel -->
        <SlideOutPanel :show="selectedUnit !== null" @close="closePanel" :title="'Unit ' + (selectedUnit?.unit_number || '')" :subtitle="'Floor ' + (selectedUnit?.floor_number || '') + ' - ' + (selectedUnit?.unit_type || '')">
            <template #default>
                <div v-if="selectedUnit" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Financials</h3>
                        <div class="flex justify-between items-center p-4 bg-white border border-gray-200 rounded-lg">
                            <span class="text-gray-600">Monthly Rent</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatMoney(selectedUnit.target_rent) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedUnit.status === 'vacant'" class="bg-indigo-50 p-6 rounded-xl border border-indigo-100 text-center">
                        <UserGroupIcon class="w-10 h-10 text-indigo-600 mx-auto mb-3"/>
                        <h3 class="font-bold text-indigo-900">Ready to Lease</h3>
                        <p class="text-sm text-indigo-700 mb-4">Add a tenant to start collecting rent.</p>
                        <Link :href="route('leases.create', selectedUnit.id)" class="block w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors">
                            + Add Tenant
                        </Link>
                    </div>

                    <div v-else-if="selectedUnit.active_lease" class="space-y-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Tenant Profile</h3>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden">
                                    <img v-if="unitDetail?.tenant?.profile_photo_url"
                                         :src="unitDetail.tenant.profile_photo_url"
                                         class="h-full w-full object-cover" />
                                    <span v-else class="text-indigo-700 font-bold text-lg">
                                        {{ selectedUnit.active_lease.tenant?.name?.charAt(0) || '?' }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900">{{ selectedUnit.active_lease.tenant?.name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ selectedUnit.active_lease.tenant?.email || '' }}</div>
                                </div>
                            </div>

                            <div v-if="loadingUnitDetail" class="text-center py-2">
                                <span class="text-sm text-gray-400">Loading...</span>
                            </div>
                            <div v-else-if="unitDetail?.tenant" class="space-y-2 text-sm mb-4">
                                <div v-if="unitDetail.tenant.phone" class="flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-gray-400" />
                                    <span>{{ unitDetail.tenant.phone }}</span>
                                </div>
                                <div v-if="unitDetail.tenant.emergency_contact" class="flex items-center gap-2">
                                    <ExclamationTriangleIcon class="w-4 h-4 text-gray-400" />
                                    <span class="text-gray-600">{{ unitDetail.tenant.emergency_contact }}</span>
                                </div>
                                <div class="pt-2 border-t border-gray-200">
                                    <span v-if="unitDetail.tenant.kyc_complete"
                                          class="inline-flex items-center gap-1 text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">
                                        <CheckBadgeIcon class="w-3 h-3" />
                                        KYC Verified
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center gap-1 text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                                        <ExclamationTriangleIcon class="w-3 h-3" />
                                        KYC Incomplete
                                    </span>
                                </div>
                            </div>

                            <button @click="showProfileModal = true" class="w-full py-2 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 shadow-sm mb-2">View Full Profile</button>
                            <Link :href="route('invoices.index')" class="block w-full py-2 bg-green-600 text-white text-center font-bold rounded-lg hover:bg-green-700 shadow-sm">View Invoices</Link>
                        </div>

                        <!-- Unit Tickets -->
                        <div v-if="unitDetail?.tickets?.length > 0">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Unit Tickets</h3>
                            <div class="space-y-2">
                                <Link v-for="ticket in unitDetail.tickets" :key="ticket.id"
                                      :href="route('tickets.show', ticket.id)"
                                      class="block p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ ticket.title }}</p>
                                        <span :class="[
                                            'text-xs px-1.5 py-0.5 rounded-full',
                                            ticket.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'
                                        ]">{{ ticket.priority }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ formatRelativeDate(ticket.created_at) }}</p>
                                </Link>
                            </div>
                        </div>

                        <!-- Payment History -->
                        <div v-if="unitDetail?.payments?.length > 0">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Recent Payments</h3>
                            <div class="space-y-2">
                                <div v-for="payment in unitDetail.payments" :key="payment.id"
                                     class="flex justify-between items-center p-3 bg-white border border-gray-200 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-green-600">{{ formatMoney(payment.amount) }}</p>
                                        <p class="text-xs text-gray-500">{{ payment.payment_method }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500">{{ formatDate(payment.payment_date) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Quick Actions</h3>
                        <div class="space-y-2">
                            <Link v-if="selectedUnit.active_lease" :href="route('tickets.create', { unit_id: selectedUnit.id })" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <TicketIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">Create Ticket</span>
                            </Link>
                            <Link :href="route('buildings.edit', activeBuilding.id)" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <WrenchScrewdriverIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">Edit Unit</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </template>
        </SlideOutPanel>

        <!-- ADD WING MODAL -->
        <AddWingModal
            :show="showAddWingModal"
            :property-id="property.id"
            :buildings="buildings"
            :default-building-id="activeBuilding?.id"
            @close="showAddWingModal = false"
        />

        <!-- MASS HIKE MODAL -->
        <MassHikeModal
            :show="showMassHikeModal"
            :building-name="activeBuilding.name"
            :occupied-units="occupiedUnits"
            :unit-ids="occupiedUnitIds"
            @close="showMassHikeModal = false"
        />

        <!-- TENANT PROFILE MODAL -->
        <TenantProfileModal
            :show="showProfileModal"
            :tenant-id="selectedUnit?.active_lease?.tenant?.id"
            @close="showProfileModal = false"
        />

    </AuthenticatedLayout>
</template>
