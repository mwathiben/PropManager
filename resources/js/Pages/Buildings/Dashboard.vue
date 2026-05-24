<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TenantProfileModal from '@/Pages/Tenants/Show.vue';
import Modal from '@/Components/Modal.vue';
import SlideOutPanel from '@/Components/SlideOutPanel.vue';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
const { t } = useI18n();
const { todayAsISODate } = useFormatters();
const { currencySymbol } = useCurrency();
import type {
    BuildingsDashboardPageProps,
    DashboardUnit,
    DashboardPayment,
} from '@/types';
import {
    UserGroupIcon,
    WrenchScrewdriverIcon,
    PlusIcon,
    CheckCircleIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    TicketIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    HomeModernIcon,
    ChevronRightIcon,
    ChevronDownIcon,
    UserPlusIcon,
    ArrowPathIcon,
    ArrowRightOnRectangleIcon,
    CreditCardIcon,
    BeakerIcon,
    FolderIcon,
    AdjustmentsHorizontalIcon,
    Squares2X2Icon,
    ListBulletIcon,
    FunnelIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<BuildingsDashboardPageProps>();

// --- STATE ---
const selectedUnit = ref<DashboardUnit | null>(null);
const showAddWingModal = ref(false);
const showProfileModal = ref(false);
const showMassHikeModal = ref(false);
const showPaymentPanel = ref(false);
const selectedPayment = ref<DashboardPayment | null>(null);
const viewMode = ref<'grid' | 'list'>('grid');

// --- FORMS ---
const wingForm = useForm({
    name: '',
    floors: 5,
    units_per_floor: 4
});

const massHikeForm = useForm({
    unit_ids: [],
    adjustment_type: 'percentage',
    value: 10,
    effective_date: todayAsISODate(),
    reason: t('buildings_dashboard.hike.default_reason')
});

// --- ACTIONS ---
const selectUnit = (unit) => {
    selectedUnit.value = unit;
};

const closePanel = () => {
    selectedUnit.value = null;
};

const switchBuilding = (id) => {
    router.visit(route('buildings.dashboard', id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => selectedUnit.value = null
    });
};

// --- FILTER STATE ---
const currentPeriod = ref(props.filters?.period || 'this_month');
const currentFloor = ref(props.filters?.floor || '');
const currentUnitType = ref(props.filters?.unit_type || '');
const currentStatus = ref(props.filters?.status || '');
const customStartDate = ref(props.filters?.start_date || '');
const customEndDate = ref(props.filters?.end_date || '');

const applyFilters = () => {
    const params = {
        period: currentPeriod.value,
    };
    if (currentPeriod.value === 'custom') {
        params.start_date = customStartDate.value;
        params.end_date = customEndDate.value;
    }
    if (currentFloor.value) params.floor = currentFloor.value;
    if (currentUnitType.value) params.unit_type = currentUnitType.value;
    if (currentStatus.value) params.status = currentStatus.value;

    router.get(route('buildings.dashboard', props.activeBuilding.id), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearUnitFilters = () => {
    currentFloor.value = '';
    currentUnitType.value = '';
    currentStatus.value = '';
    applyFilters();
};

const handleQuickAction = (action) => {
    switch (action) {
        case 'addTenant':
            // Navigate to add tenant for first vacant unit
            const vacantUnit = props.units.find(u => u.status === 'vacant');
            if (vacantUnit) {
                router.visit(route('leases.create', vacantUnit.id));
            }
            break;
        case 'recordPayment':
            // Open payment modal - can be enhanced
            router.visit(route('invoices.index'));
            break;
        case 'rentHike':
            showMassHikeModal.value = true;
            break;
    }
};

const submitWing = () => {
    wingForm.post(route('buildings.store', props.property.id), {
        onSuccess: () => {
            showAddWingModal.value = false;
            wingForm.reset();
        }
    });
};

const submitMassHike = () => {
    massHikeForm.unit_ids = props.units
        .filter(u => u.status === 'occupied')
        .map(u => u.id);

    if (confirm(t('buildings_dashboard.hike.confirm', { count: massHikeForm.unit_ids.length }))) {
        massHikeForm.post(route('leases.batch-adjust'), {
            onSuccess: () => showMassHikeModal.value = false
        });
    }
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

// Group units by floor (descending order - top floors first)
const unitsByFloor = computed(() => {
    const grouped = {};
    props.units.forEach(unit => {
        const floor = unit.floor_number || 1;
        if (!grouped[floor]) grouped[floor] = [];
        grouped[floor].push(unit);
    });
    return Object.entries(grouped)
        .map(([floor, units]) => ({ floor: parseInt(floor), units }))
        .sort((a, b) => b.floor - a.floor);
});

// Responsive grid style for floor-grouped view
const getFloorGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(auto-fill, minmax(80px, 1fr))`
}));

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return currentFloor.value || currentUnitType.value || currentStatus.value || currentPeriod.value !== 'this_month';
});

// Clear all filters
const clearAllFilters = () => {
    currentFloor.value = '';
    currentUnitType.value = '';
    currentStatus.value = '';
    currentPeriod.value = 'this_month';
    applyFilters();
};
</script>

<template>
    <Head :title="t('buildings_dashboard.title')" />

    <AuthenticatedLayout>
        <!-- Top Bar: Building Tabs + Actions -->
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-4">
                    <!-- Property Name -->
                    <h1 class="text-lg font-semibold text-gray-900">{{ property.name }}</h1>

                    <!-- Building Tabs -->
                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg">
                        <button
                            v-for="building in buildings"
                            :key="building.id"
                            @click="switchBuilding(building.id)"
                            :class="activeBuilding.id === building.id ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-all">
                            {{ building.name }}
                        </button>
                        <button @click="showAddWingModal = true"
                                class="px-2 py-1.5 text-gray-400 hover:text-indigo-600 hover:bg-white rounded-md transition-all"
                                :title="t('buildings_dashboard.add_wing_tooltip')">
                            <PlusIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Quick Actions Dropdown -->
                <Dropdown align="right" width="72">
                    <template #trigger>
                        <button class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                            {{ t('buildings_dashboard.quick_actions') }}
                            <ChevronDownIcon class="w-4 h-4 ms-1.5" />
                        </button>
                    </template>
                    <template #content>
                        <div class="py-1 max-h-96 overflow-y-auto">
                            <!-- Tenant Management -->
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
                                {{ t('buildings_dashboard.section.tenant_management') }}
                            </div>
                            <button @click="handleQuickAction('addTenant')" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 text-start">
                                <UserPlusIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.add_tenant') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.add_tenant_desc') }}</div>
                                </div>
                            </button>
                            <Link :href="route('bulk.index', { tab: 'leases' })" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <ArrowPathIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.lease_renewal') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.lease_renewal_desc') }}</div>
                                </div>
                            </Link>
                            <Link :href="route('bulk.index', { tab: 'terminate' })" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <ArrowRightOnRectangleIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.move_out') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.move_out_desc') }}</div>
                                </div>
                            </Link>

                            <!-- Financial -->
                            <div class="border-t border-gray-100 mt-1"></div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
                                {{ t('buildings_dashboard.section.financial') }}
                            </div>
                            <button @click="handleQuickAction('recordPayment')" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 text-start">
                                <CreditCardIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.record_payment') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.record_payment_desc') }}</div>
                                </div>
                            </button>
                            <Link :href="route('invoices.generate')" method="post" as="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 text-start">
                                <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.generate_invoice') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.generate_invoice_desc') }}</div>
                                </div>
                            </Link>
                            <button @click="handleQuickAction('rentHike')" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 text-start">
                                <ArrowTrendingUpIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.rent_hike') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.rent_hike_desc') }}</div>
                                </div>
                            </button>

                            <!-- Property -->
                            <div class="border-t border-gray-100 mt-1"></div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
                                {{ t('buildings_dashboard.section.property') }}
                            </div>
                            <Link :href="route('readings.index')" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <BeakerIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.water_readings') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.water_readings_desc') }}</div>
                                </div>
                            </Link>
                            <Link :href="route('tickets.create')" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <TicketIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.create_ticket') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.create_ticket_desc') }}</div>
                                </div>
                            </Link>
                            <Link :href="route('documents.index')" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <FolderIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.view_documents') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.view_documents_desc') }}</div>
                                </div>
                            </Link>

                            <!-- Settings -->
                            <div class="border-t border-gray-100 mt-1"></div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
                                {{ t('buildings_dashboard.section.settings') }}
                            </div>
                            <Link :href="route('buildings.edit', activeBuilding.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <WrenchScrewdriverIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.configure_building') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.configure_building_desc') }}</div>
                                </div>
                            </Link>
                            <Link :href="route('buildings.water-settings', activeBuilding.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <AdjustmentsHorizontalIcon class="w-4 h-4 text-gray-400" />
                                <div>
                                    <div class="font-medium">{{ t('buildings_dashboard.action.water_settings') }}</div>
                                    <div class="text-xs text-gray-500">{{ t('buildings_dashboard.action.water_settings_desc') }}</div>
                                </div>
                            </Link>
                        </div>
                    </template>
                </Dropdown>
            </div>
        </template>

        <!-- Filter Bar (matches main dashboard style) -->
        <div class="bg-white border-b border-gray-200 px-6 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <FunnelIcon class="w-4 h-4" />
                    <span>{{ t('buildings_dashboard.filter.label') }}</span>
                </div>

                <!-- Period Filter -->
                <select v-model="currentPeriod" @change="applyFilters"
                    class="text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1.5">
                    <option value="this_month">{{ t('buildings_dashboard.filter.period.this_month') }}</option>
                    <option value="last_month">{{ t('buildings_dashboard.filter.period.last_month') }}</option>
                    <option value="this_quarter">{{ t('buildings_dashboard.filter.period.this_quarter') }}</option>
                    <option value="this_year">{{ t('buildings_dashboard.filter.period.this_year') }}</option>
                </select>

                <!-- Floor Filter Chips -->
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="currentFloor = ''; applyFilters()"
                        :class="['px-3 py-1.5 text-sm font-medium rounded-full transition-colors', !currentFloor ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        {{ t('buildings_dashboard.filter.all_floors') }}
                    </button>
                    <button v-for="floor in availableFloors" :key="floor" @click="currentFloor = floor; applyFilters()"
                        :class="['px-3 py-1.5 text-sm font-medium rounded-full transition-colors', currentFloor == floor ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        {{ t('buildings_dashboard.filter.floor', { floor }) }}
                    </button>
                </div>

                <!-- Type Filter -->
                <select v-if="availableUnitTypes?.length" v-model="currentUnitType" @change="applyFilters"
                    class="text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1.5">
                    <option value="">{{ t('buildings_dashboard.filter.all_types') }}</option>
                    <option v-for="type in availableUnitTypes" :key="type" :value="type">{{ type }}</option>
                </select>

                <!-- Status Filter Chips -->
                <div class="flex items-center gap-1">
                    <button @click="currentStatus = ''; applyFilters()"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-full transition-colors flex items-center', currentStatus === '' ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        {{ t('buildings_dashboard.filter.status_all') }}
                    </button>
                    <button @click="currentStatus = 'occupied'; applyFilters()"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-full transition-colors flex items-center', currentStatus === 'occupied' ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        <span class="w-2 h-2 rounded-full bg-green-500 me-1.5"></span>
                        {{ t('buildings_dashboard.filter.status_occupied') }}
                    </button>
                    <button @click="currentStatus = 'vacant'; applyFilters()"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-full transition-colors flex items-center', currentStatus === 'vacant' ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        <span class="w-2 h-2 rounded-full bg-gray-400 me-1.5"></span>
                        {{ t('buildings_dashboard.filter.status_vacant') }}
                    </button>
                    <button @click="currentStatus = 'arrears'; applyFilters()"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-full transition-colors flex items-center', currentStatus === 'arrears' ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        <span class="w-2 h-2 rounded-full bg-red-500 me-1.5"></span>
                        {{ t('buildings_dashboard.filter.status_arrears') }}
                    </button>
                </div>

                <!-- Trend Indicator + Clear -->
                <div class="ms-auto flex items-center gap-3">
                    <span v-if="periodComparison?.revenue" class="text-sm"
                        :class="periodComparison.revenue.trend === 'up' ? 'text-green-600' : 'text-red-600'">
                        <component :is="periodComparison.revenue.trend === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4 inline" />
                        {{ t('buildings_dashboard.filter.vs_prev', { change: Math.abs(periodComparison.revenue.change) }) }}
                    </span>
                    <button v-if="hasActiveFilters" @click="clearAllFilters"
                        class="flex items-center gap-1 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        <XMarkIcon class="w-4 h-4" /> {{ t('buildings_dashboard.filter.clear') }}
                    </button>
                </div>
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
                    :title="t('buildings_dashboard.action_items.overdue_invoices')"
                    :description="t('buildings_dashboard.action_items.overdue_outstanding', { amount: formatMoney(actionItems.overdue_amount) })"
                    :actionLabel="t('buildings_dashboard.action_items.view_all')"
                    :actionHref="route('invoices.index', { status: 'overdue' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="DocumentTextIcon"
                    :count="0"
                    :title="t('buildings_dashboard.action_items.overdue_invoices')"
                    :description="t('buildings_dashboard.action_items.all_invoices_current')"
                    :actionLabel="t('buildings_dashboard.action_items.view_all')"
                    :actionHref="route('invoices.index')"
                />

                <ActionItemCard
                    v-if="actionItems.expiring_leases > 0"
                    urgency="high"
                    :icon="CalendarDaysIcon"
                    :count="actionItems.expiring_leases"
                    :title="t('buildings_dashboard.action_items.expiring_leases')"
                    :description="t('buildings_dashboard.action_items.within_30_days')"
                    :actionLabel="t('buildings_dashboard.action_items.view')"
                    :actionHref="route('leases.index')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="CalendarDaysIcon"
                    :count="0"
                    :title="t('buildings_dashboard.action_items.expiring_leases')"
                    :description="t('buildings_dashboard.action_items.no_leases_expiring')"
                    :actionLabel="t('buildings_dashboard.action_items.view_all')"
                    :actionHref="route('leases.index')"
                />

                <ActionItemCard
                    v-if="actionItems.urgent_tickets > 0"
                    urgency="critical"
                    :icon="TicketIcon"
                    :count="actionItems.urgent_tickets"
                    :title="t('buildings_dashboard.action_items.urgent_tickets')"
                    :description="t('buildings_dashboard.action_items.require_attention')"
                    :actionLabel="t('buildings_dashboard.action_items.view')"
                    :actionHref="route('tickets.index', { priority: 'urgent' })"
                />
                <ActionItemCard
                    v-else-if="actionItems.vacant_units > 0"
                    urgency="medium"
                    :icon="HomeModernIcon"
                    :count="actionItems.vacant_units"
                    :title="t('buildings_dashboard.action_items.vacant_units')"
                    :description="t('buildings_dashboard.action_items.available_for_lease')"
                    :actionLabel="t('buildings_dashboard.action_items.view')"
                    :actionHref="route('buildings.dashboard', { building: activeBuilding.id, status: 'vacant' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="TicketIcon"
                    :count="0"
                    :title="t('buildings_dashboard.action_items.urgent_tickets')"
                    :description="t('buildings_dashboard.action_items.no_urgent_issues')"
                    :actionLabel="t('buildings_dashboard.action_items.view_all')"
                    :actionHref="route('tickets.index')"
                />

                <ActionItemCard
                    v-if="actionItems.pending_readings > 0"
                    urgency="medium"
                    :icon="ClipboardDocumentListIcon"
                    :count="actionItems.pending_readings"
                    :title="t('buildings_dashboard.action_items.pending_readings')"
                    :description="t('buildings_dashboard.action_items.awaiting_approval')"
                    :actionLabel="t('buildings_dashboard.action_items.review')"
                    :actionHref="route('readings.review')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="ClipboardDocumentListIcon"
                    :count="0"
                    :title="t('buildings_dashboard.action_items.pending_readings')"
                    :description="t('buildings_dashboard.action_items.all_readings_processed')"
                    :actionLabel="t('buildings_dashboard.action_items.view_all')"
                    :actionHref="route('readings.index')"
                />
            </div>

            <!-- === KEY METRICS === -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <MetricCard
                    :title="t('buildings_dashboard.metrics.monthly_revenue')"
                    :value="financialMetrics.monthly_revenue"
                    format="currency"
                    :subtitle="t('buildings_dashboard.metrics.expected', { amount: formatMoney(financialMetrics.expected_revenue) })"
                    :icon="BanknotesIcon"
                    color="emerald"
                    :href="route('invoices.index')"
                    :trend="financialMetrics.collection_rate >= 80
                        ? { direction: 'up', value: financialMetrics.collection_rate + '%' }
                        : { direction: 'down', value: financialMetrics.collection_rate + '%' }"
                />

                <MetricCard
                    :title="t('buildings_dashboard.metrics.collection_rate')"
                    :value="financialMetrics.collection_rate"
                    format="percent"
                    :subtitle="t('buildings_dashboard.metrics.this_month')"
                    :icon="ChartBarIcon"
                    color="blue"
                    :href="route('reports.index')"
                />

                <MetricCard
                    :title="t('buildings_dashboard.metrics.total_arrears')"
                    :value="financialMetrics.total_arrears"
                    format="currency"
                    :subtitle="t('buildings_dashboard.metrics.outstanding_balance')"
                    :icon="ExclamationTriangleIcon"
                    color="red"
                    :href="route('invoices.index', { has_arrears: true })"
                />

                <MetricCard
                    :title="t('buildings_dashboard.metrics.occupancy_rate')"
                    :value="stats.occupancy_rate"
                    format="percent"
                    :subtitle="t('buildings_dashboard.metrics.units_ratio', { occupied: stats.occupied_units, total: stats.total_units })"
                    :icon="UserGroupIcon"
                    color="indigo"
                    :href="route('buildings.dashboard', { building: activeBuilding.id, status: 'vacant' })"
                />
            </div>

            <!-- === TWO-COLUMN: OCCUPANCY MAP + ARREARS === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- OCCUPANCY MAP (2 columns) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Header with View Toggle -->
                    <div class="p-4 sm:p-6 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">{{ activeBuilding.name }}</h3>
                                <p class="text-sm text-gray-500">{{ t('buildings_dashboard.occupancy.summary', { units: units.length, floors: unitsByFloor.length }) }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- View Toggle -->
                                <div class="flex bg-gray-100 rounded-lg p-1">
                                    <button @click="viewMode = 'grid'"
                                        :class="viewMode === 'grid' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                                        <Squares2X2Icon class="w-4 h-4" />
                                    </button>
                                    <button @click="viewMode = 'list'"
                                        :class="viewMode === 'list' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                                        <ListBulletIcon class="w-4 h-4" />
                                    </button>
                                </div>
                                <!-- Legend -->
                                <div class="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full"></span> {{ t('buildings_dashboard.occupancy.legend_occupied') }}</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-300 rounded-full"></span> {{ t('buildings_dashboard.occupancy.legend_vacant') }}</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span> {{ t('buildings_dashboard.occupancy.legend_arrears') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="max-h-[500px] overflow-y-auto">
                        <!-- GRID VIEW -->
                        <template v-if="viewMode === 'grid'">
                            <div v-for="floorGroup in unitsByFloor" :key="floorGroup.floor" class="border-b border-gray-100 last:border-b-0">
                                <div class="px-4 sm:px-6 py-2 bg-gray-50 sticky top-0 z-10">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('buildings_dashboard.occupancy.floor', { floor: floorGroup.floor }) }}</span>
                                    <span class="text-xs text-gray-400 ms-2">{{ t('buildings_dashboard.occupancy.floor_occupied', { occupied: floorGroup.units.filter(u => u.status === 'occupied').length, total: floorGroup.units.length }) }}</span>
                                </div>
                                <div class="p-4 sm:p-6 pt-3">
                                    <div class="grid gap-2 sm:gap-3" :style="getFloorGridStyle">
                                        <button v-for="unit in floorGroup.units" :key="unit.id" @click="selectUnit(unit)"
                                            :class="[selectedUnit?.id === unit.id ? 'ring-2 ring-indigo-500 ring-offset-1' : 'hover:scale-105',
                                                unit.status === 'occupied' ? 'bg-green-50 border-green-200 text-green-700' :
                                                unit.status === 'arrears' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-gray-50 border-gray-200 text-gray-500']"
                                            class="relative aspect-square rounded-lg border p-2 flex flex-col items-center justify-center transition-all text-center">
                                            <span class="text-sm font-bold">{{ unit.unit_number }}</span>
                                            <span class="text-[10px] uppercase tracking-wide mt-0.5">{{ unit.status === 'occupied' ? t('buildings_dashboard.occupancy.status_occ') : unit.status === 'arrears' ? t('buildings_dashboard.occupancy.status_late') : t('buildings_dashboard.occupancy.status_vac') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- LIST VIEW -->
                        <template v-else>
                            <div v-for="floorGroup in unitsByFloor" :key="floorGroup.floor">
                                <div class="px-4 sm:px-6 py-2 bg-gray-50 sticky top-0 z-10 border-b border-gray-100">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ t('buildings_dashboard.occupancy.floor', { floor: floorGroup.floor }) }}</span>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    <button v-for="unit in floorGroup.units" :key="unit.id" @click="selectUnit(unit)"
                                        :class="selectedUnit?.id === unit.id ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                                        class="w-full px-4 sm:px-6 py-3 flex items-center justify-between text-start transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm', unit.status === 'occupied' ? 'bg-green-100 text-green-700' : unit.status === 'arrears' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">
                                                {{ unit.unit_number }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ unit.unit_type || t('buildings_dashboard.occupancy.unit_fallback') }} {{ unit.unit_number }}</div>
                                                <div class="text-xs text-gray-500">{{ formatMoney(unit.target_rent) }}{{ t('buildings_dashboard.occupancy.per_month') }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span :class="['text-xs font-medium px-2 py-1 rounded-full', unit.status === 'occupied' ? 'bg-green-100 text-green-700' : unit.status === 'arrears' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">
                                                {{ unit.status === 'occupied' ? t('buildings_dashboard.occupancy.status_occupied') : unit.status === 'arrears' ? t('buildings_dashboard.occupancy.status_arrears') : t('buildings_dashboard.occupancy.status_vacant') }}
                                            </span>
                                            <ChevronRightIcon class="w-4 h-4 text-gray-400" />
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ARREARS AGING (1 column) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">{{ t('buildings_dashboard.arrears.title') }}</h3>

                    <div v-if="totalArrears > 0" class="space-y-4">
                        <!-- 0-30 Days -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <span class="text-sm text-gray-600">{{ t('buildings_dashboard.arrears.days_0_30') }}</span>
                            </div>
                            <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['0_30']) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-yellow-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['0_30']) + '%' }"></div>
                        </div>

                        <!-- 31-60 Days -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-orange-400"></div>
                                <span class="text-sm text-gray-600">{{ t('buildings_dashboard.arrears.days_31_60') }}</span>
                            </div>
                            <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['31_60']) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-orange-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['31_60']) + '%' }"></div>
                        </div>

                        <!-- 61-90 Days -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <span class="text-sm text-gray-600">{{ t('buildings_dashboard.arrears.days_61_90') }}</span>
                            </div>
                            <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['61_90']) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-red-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['61_90']) + '%' }"></div>
                        </div>

                        <!-- 90+ Days -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-red-700"></div>
                                <span class="text-sm text-gray-600">{{ t('buildings_dashboard.arrears.days_90_plus') }}</span>
                            </div>
                            <span class="font-semibold text-gray-900">{{ formatMoney(arrearsAging['90_plus']) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-red-700 h-2 rounded-full" :style="{ width: getArrearsPercentage(arrearsAging['90_plus']) + '%' }"></div>
                        </div>

                        <!-- Total -->
                        <div class="pt-4 border-t border-gray-200 mt-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">{{ t('buildings_dashboard.arrears.total_outstanding') }}</span>
                                <span class="text-xl font-bold text-gray-900">{{ formatMoney(totalArrears) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('buildings_dashboard.arrears.none') }}</p>
                        <p class="text-sm text-gray-400">{{ t('buildings_dashboard.arrears.all_current') }}</p>
                    </div>
                </div>

            </div>

            <!-- === RECENT PAYMENTS + EXPIRING LEASES === -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- RECENT PAYMENTS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">{{ t('buildings_dashboard.payments.title') }}</h3>
                        <Link :href="route('invoices.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            {{ t('buildings_dashboard.payments.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                        </Link>
                    </div>

                    <div v-if="recentPayments && recentPayments.length > 0" class="space-y-3">
                        <div v-for="payment in recentPayments" :key="payment.id"
                             @click="viewPayment(payment)"
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <BanknotesIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ payment.invoice?.lease?.tenant?.name || t('buildings_dashboard.payments.unknown') }}</p>
                                    <p class="text-xs text-gray-500">{{ payment.invoice?.lease?.unit?.unit_number || '-' }} • {{ formatDate(payment.payment_date) }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="font-bold text-green-600">{{ formatMoney(payment.amount) }}</p>
                                <p class="text-xs text-gray-500">{{ payment.payment_method }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <BanknotesIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('buildings_dashboard.payments.none') }}</p>
                    </div>
                </div>

                <!-- EXPIRING LEASES -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">{{ t('buildings_dashboard.leases.title') }}</h3>
                        <span class="text-sm text-gray-500">{{ t('buildings_dashboard.leases.next_60_days') }}</span>
                    </div>

                    <div v-if="expiringLeases && expiringLeases.length > 0" class="space-y-3">
                        <div v-for="lease in expiringLeases" :key="lease.id"
                             class="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-100">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                    <CalendarDaysIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ lease.tenant?.name || t('buildings_dashboard.leases.unknown') }}</p>
                                    <p class="text-xs text-gray-500">{{ lease.unit?.building?.name }} - {{ lease.unit?.unit_number }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="font-semibold text-orange-600">{{ formatRelativeDate(lease.end_date) }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(lease.end_date) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('buildings_dashboard.leases.none') }}</p>
                        <p class="text-sm text-gray-400">{{ t('buildings_dashboard.leases.all_current') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR (Unit Context Panel) - Using SlideOutPanel -->
        <SlideOutPanel :show="selectedUnit !== null" @close="closePanel" :title="t('buildings_dashboard.panel.unit_title', { number: selectedUnit?.unit_number || '' })" :subtitle="t('buildings_dashboard.panel.unit_subtitle', { floor: selectedUnit?.floor_number || '', type: selectedUnit?.unit_type || '' })">
            <template #default>
                <div v-if="selectedUnit" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('buildings_dashboard.panel.financials') }}</h3>
                        <div class="flex justify-between items-center p-4 bg-white border border-gray-200 rounded-lg">
                            <span class="text-gray-600">{{ t('buildings_dashboard.panel.monthly_rent') }}</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatMoney(selectedUnit.target_rent) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedUnit.status === 'vacant'" class="bg-indigo-50 p-6 rounded-xl border border-indigo-100 text-center">
                        <UserGroupIcon class="w-10 h-10 text-indigo-600 mx-auto mb-3"/>
                        <h3 class="font-bold text-indigo-900">{{ t('buildings_dashboard.panel.ready_to_lease') }}</h3>
                        <p class="text-sm text-indigo-700 mb-4">{{ t('buildings_dashboard.panel.ready_to_lease_desc') }}</p>
                        <Link :href="route('leases.create', selectedUnit.id)" class="block w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors">
                            {{ t('buildings_dashboard.panel.add_tenant') }}
                        </Link>
                    </div>

                    <div v-else-if="selectedUnit.active_lease" class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-lg">
                                {{ selectedUnit.active_lease.tenant?.name?.charAt(0) || '?' }}
                            </div>
                            <div>
                                <div class="font-bold text-gray-900">{{ selectedUnit.active_lease.tenant?.name || t('buildings_dashboard.payments.unknown') }}</div>
                                <div class="text-xs text-gray-500">{{ selectedUnit.active_lease.tenant?.email || '' }}</div>
                            </div>
                        </div>
                        <button @click="showProfileModal = true" class="w-full py-2 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 shadow-sm mb-2">{{ t('buildings_dashboard.panel.view_profile') }}</button>
                        <Link :href="route('invoices.index', { lease_id: selectedUnit.active_lease?.id })" class="block w-full py-2 bg-green-600 text-white text-center font-bold rounded-lg hover:bg-green-700 shadow-sm">{{ t('buildings_dashboard.panel.view_invoices') }}</Link>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('buildings_dashboard.panel.quick_actions') }}</h3>
                        <div class="space-y-2">
                            <Link v-if="selectedUnit.active_lease" :href="route('tickets.create', { unit_id: selectedUnit.id })" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <TicketIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">{{ t('buildings_dashboard.panel.create_ticket') }}</span>
                            </Link>
                            <Link :href="route('buildings.edit', activeBuilding.id)" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <WrenchScrewdriverIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">{{ t('buildings_dashboard.panel.edit_unit') }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </template>
        </SlideOutPanel>

        <!-- ADD WING MODAL -->
        <div v-if="showAddWingModal" class="fixed inset-0 z-50 overflow-y-auto">
             <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity backdrop-blur-sm" @click="showAddWingModal = false"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="relative z-50 inline-block align-bottom bg-white rounded-xl text-start overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ t('buildings_dashboard.wing.title') }}</h3>
                        <form @submit.prevent="submitWing" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.wing.name') }}</label>
                                <input v-model="wingForm.name" type="text" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :placeholder="t('buildings_dashboard.wing.name_placeholder')">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.wing.floors') }}</label>
                                    <input v-model="wingForm.floors" type="number" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.wing.units_per_floor') }}</label>
                                    <input v-model="wingForm.units_per_floor" type="number" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <button @click="showAddWingModal = false" type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium text-sm">{{ t('buildings_dashboard.wing.cancel') }}</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm shadow-sm" :disabled="wingForm.processing">{{ t('buildings_dashboard.wing.create') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- MASS HIKE MODAL -->
        <Modal :show="showMassHikeModal" @close="showMassHikeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ t('buildings_dashboard.hike.title') }}</h2>
                <p class="text-sm text-gray-500 mb-4">
                    {{ t('buildings_dashboard.hike.description') }} <strong>{{ t('buildings_dashboard.hike.occupied_units', { count: occupiedUnits }) }}</strong> {{ t('buildings_dashboard.hike.in_building') }} {{ activeBuilding.name }}.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.hike.adjustment_type') }}</label>
                        <select v-model="massHikeForm.adjustment_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="percentage">{{ t('buildings_dashboard.hike.type_percentage') }}</option>
                            <option value="fixed">{{ t('buildings_dashboard.hike.type_fixed', { currency: currencySymbol }) }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.hike.value') }}</label>
                        <input v-model="massHikeForm.value" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.hike.effective_date') }}</label>
                        <input v-model="massHikeForm.effective_date" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('buildings_dashboard.hike.reason') }}</label>
                        <input v-model="massHikeForm.reason" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="showMassHikeModal = false" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">{{ t('buildings_dashboard.hike.cancel') }}</button>
                    <button @click="submitMassHike" :disabled="massHikeForm.processing" class="ms-3 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-bold">
                        {{ t('buildings_dashboard.hike.apply') }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- TENANT PROFILE MODAL -->
        <TenantProfileModal
            :show="showProfileModal"
            @close="showProfileModal = false"
            :tenant="selectedUnit?.active_lease?.tenant"
            :lease="selectedUnit?.active_lease"
            :unit="selectedUnit"
        />

    </AuthenticatedLayout>
</template>
