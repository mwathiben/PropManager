<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useEcho, useErrorHandler, useDashboardStats } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import TenantProfileModal from '@/Components/Modals/TenantProfileModal.vue';
import SlideOutPanel from '@/Components/SlideOutPanel.vue';
import AddWingModal from '@/Components/Modals/AddWingModal.vue';
import MassHikeModal from '@/Components/Modals/MassHikeModal.vue';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import Dropdown from '@/Components/Dropdown.vue';
// Phase-36 INSIGHT-LANDLORD-2: growth widgets
import EngagementScoreCard from '@/Components/Insight/EngagementScoreCard.vue';
import ReferralCountCard from '@/Components/Insight/ReferralCountCard.vue';
import UsageRatioCard from '@/Components/Insight/UsageRatioCard.vue';
import { useFormatters } from '@/composables';
import type {
    DashboardPageProps,
    DashboardUnit,
    DashboardPayment,
    DashboardTicket,
    FinancialMetrics,
    ArrearsAging,
    LandlordActionItems,
    PlatformFeeTier,
} from '@/types';
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

const props = defineProps<DashboardPageProps>();

const { t } = useI18n();

// --- STATE ---
const { logError } = useErrorHandler();
const selectedUnit = ref<DashboardUnit | null>(null);
const showAddWingModal = ref(false);
const showProfileModal = ref(false);
const showMassHikeModal = ref(false);
const showPaymentPanel = ref(false);
const selectedPayment = ref<DashboardPayment | null>(null);
const viewMode = ref<'grid' | 'list'>('grid');
const unitDetail = ref<Record<string, unknown> | null>(null);
const loadingUnitDetail = ref(false);

// Wing/Floor filter state (initialized from props)
const activeWingFilter = ref<number | null>(props.activeWingId ?? null);
const activeFloorFilter = ref<number | null>(props.activeFloor ?? null);

// Local state for real-time updates (initialized from props)
const localRecentPayments = ref<DashboardPayment[]>([...(props.recentPayments || [])]);
const localFinancialMetrics = ref<FinancialMetrics>({ ...props.financialMetrics });
const localArrearsAging = ref<ArrearsAging>({ ...props.arrearsAging });
const localRecentTickets = ref<DashboardTicket[]>([...(props.recentTickets || [])]);
const localActionItems = ref<LandlordActionItems>({ ...props.actionItems });
const metricsUpdating = ref(false);

// Fetch unit detail when a unit is selected
watch(() => selectedUnit.value, async (unit) => {
    if (unit && unit.status === 'occupied') {
        loadingUnitDetail.value = true;
        try {
            const response = await fetch(route('units.detail', unit.id));
            unitDetail.value = await response.json();
        } catch (e) {
            logError(e, { component: 'Dashboard', action: 'loadUnitDetail' });
            unitDetail.value = null;
        } finally {
            loadingUnitDetail.value = false;
        }
    } else {
        unitDetail.value = null;
    }
}, { immediate: false });

// --- ACTIONS ---
const selectUnit = (unit: DashboardUnit) => {
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

// Phase-74 CROSS-BUILDING + Phase-105 PORTFOLIO-HOME: persist the scope, then reload INTO
// the building dashboard (bare /dashboard is now the portfolio landing). All-buildings uses
// the 'all' sentinel (a specific building_id always scopes to one building); active-building
// reloads the current building.
const setScope = (scope) => {
    const buildingId = scope === 'all_buildings' ? 'all' : props.activeBuilding?.id;
    router.patch(route('dashboard.scope.update'), { scope }, {
        preserveScroll: true,
        onSuccess: () => router.get(route('dashboard'), { property_id: props.property?.id, building_id: buildingId }, { preserveScroll: true }),
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

const viewPayment = (payment: DashboardPayment) => {
    selectedPayment.value = payment;
    showPaymentPanel.value = true;
};

// --- HELPERS (from composables) ---
const { formatMoney, formatDate, formatRelativeDate, todayAsISODate } = useFormatters();

const leaseStateBadgeLabel = (state: string): string => {
    if (state === 'ended') return t('dashboard.lease_state.ended');
    if (state === 'soft_deleted') return t('dashboard.lease_state.archived');
    return t('dashboard.lease_state.unknown');
};

const leaseStateBadgeClass = (state: string): string => {
    if (state === 'ended') return 'bg-gray-200 text-gray-700';
    if (state === 'soft_deleted') return 'bg-rose-100 text-rose-800';
    return 'bg-gray-100 text-gray-600';
};

// Phase-55 WIDGET-ORDERING-2: native HTML5 drag-and-drop reorder for the
// bottom-row widgets (recent-payments, recent-tickets, expiring-leases).
// CSS `order` reflows the existing grid; no library required.
const DEFAULT_WIDGET_ORDER = ['recent-payments', 'recent-tickets', 'expiring-leases'] as const;
type WidgetId = (typeof DEFAULT_WIDGET_ORDER)[number];

const widgetOrder = ref<WidgetId[]>(
    (props.widgetOrder && props.widgetOrder.length === DEFAULT_WIDGET_ORDER.length)
        ? (props.widgetOrder as WidgetId[])
        : [...DEFAULT_WIDGET_ORDER],
);

const widgetOrderIndex = (id: WidgetId): number => {
    const idx = widgetOrder.value.indexOf(id);
    return idx === -1 ? widgetOrder.value.length : idx;
};

const draggingWidget = ref<WidgetId | null>(null);

const onWidgetDragStart = (id: WidgetId, event: DragEvent) => {
    draggingWidget.value = id;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', id);
    }
};

const onWidgetDrop = (targetId: WidgetId, event: DragEvent) => {
    event.preventDefault();
    const sourceId = draggingWidget.value;
    draggingWidget.value = null;
    if (!sourceId || sourceId === targetId) return;

    const next = [...widgetOrder.value];
    const sourceIdx = next.indexOf(sourceId);
    const targetIdx = next.indexOf(targetId);
    if (sourceIdx === -1 || targetIdx === -1) return;

    next.splice(sourceIdx, 1);
    next.splice(targetIdx, 0, sourceId);
    widgetOrder.value = next;

    router.patch(route('dashboards.preferences.update'), { widget_order: next }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const totalArrears = computed(() => {
    return (localArrearsAging.value?.['0_30'] || 0) +
           (localArrearsAging.value?.['31_60'] || 0) +
           (localArrearsAging.value?.['61_90'] || 0) +
           (localArrearsAging.value?.['90_plus'] || 0);
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

// --- TIER COMPUTED ---
const nextTier = computed<PlatformFeeTier | null>(() => {
    if (!props.currentTier || !props.allTiers?.length) return null;
    const currentIndex = props.allTiers.findIndex(t => t.id === props.currentTier?.id);
    if (currentIndex < 0 || currentIndex >= props.allTiers.length - 1) return null;
    return props.allTiers[currentIndex + 1];
});

const tierProgressPercent = computed(() => {
    if (!nextTier.value || !props.currentTier) return 100;
    const rangeStart = props.currentTier.min_volume;
    const rangeEnd = nextTier.value.min_volume;
    const range = rangeEnd - rangeStart;
    if (range <= 0) return 100;
    const progress = Math.max(0, Math.min(((props.mtdVolume ?? 0) - rangeStart) / range, 1));
    return Math.round(progress * 100);
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

// --- REAL-TIME UPDATES ---
const { subscribePrivate, unsubscribe, shouldUseFallback, isConnected } = useEcho();
const { latestStats, pollNow } = useDashboardStats({ shouldUseFallback, isConnected });

// Sync local state with props on navigation
watch(() => props.financialMetrics, (newVal) => {
    if (newVal) Object.assign(localFinancialMetrics.value, newVal);
}, { deep: true });
watch(() => props.arrearsAging, (newVal) => {
    if (newVal) Object.assign(localArrearsAging.value, newVal);
}, { deep: true });
watch(() => props.recentPayments, (newVal) => {
    if (newVal) localRecentPayments.value = [...newVal];
}, { deep: true });
watch(() => props.recentTickets, (newVal) => {
    if (newVal) localRecentTickets.value = [...newVal];
}, { deep: true });
watch(() => props.actionItems, (newVal) => {
    if (newVal) Object.assign(localActionItems.value, newVal);
}, { deep: true });

watch(latestStats, (stats) => {
    if (!stats) return;
    Object.assign(localFinancialMetrics.value, stats.financial);
    Object.assign(localArrearsAging.value, stats.arrears_aging);
    localActionItems.value.overdue_invoices = stats.action_items.overdue_invoices;
    localActionItems.value.overdue_amount = stats.action_items.overdue_amount;
    localActionItems.value.open_tickets = stats.action_items.open_tickets;
});

// Get user ID for landlord channel subscription
const userId = computed(() => {
    // For landlords: use their own ID
    // For caretakers: use their landlord_id (if available on page props)
    return window.__auth?.user?.id;
});

onMounted(() => {
    if (userId.value) {
        subscribePrivate(`landlord.${userId.value}`, 'PaymentReceived', (data) => {
            // Add new payment to the top of the list
            localRecentPayments.value.unshift({
                id: data.payment_id,
                amount: data.amount,
                payment_method: data.payment_method,
                payment_date: todayAsISODate(),
                // Split payment details (for IntaSend/Paystack)
                platform_fee: data.platform_fee,
                landlord_amount: data.landlord_amount,
                split_provider: data.split_provider,
                invoice: {
                    id: data.invoice_id,
                    lease: {
                        tenant: { name: data.tenant_name },
                        unit: { unit_number: data.unit_name }
                    }
                }
            });
            // Keep only the 10 most recent
            if (localRecentPayments.value.length > 10) {
                localRecentPayments.value.pop();
            }

            // Update financial metrics in real-time
            if (data.updated_metrics) {
                metricsUpdating.value = true;
                Object.assign(localFinancialMetrics.value, data.updated_metrics.financial);
                Object.assign(localArrearsAging.value, data.updated_metrics.arrears_aging);
                setTimeout(() => metricsUpdating.value = false, 2000);
            }
            pollNow();
        });

        // Listen for ticket status changes
        subscribePrivate(`landlord.${userId.value}`, 'TicketStatusChanged', (data) => {
            // Update action items count
            if (data.landlord_open_count !== undefined) {
                localActionItems.value.open_tickets = data.landlord_open_count;
            }

            // Update recentTickets if the changed ticket is in the list
            const ticketIndex = localRecentTickets.value.findIndex(t => t.id === data.ticket_id);
            if (ticketIndex !== -1) {
                localRecentTickets.value[ticketIndex].status = data.new_status;
            }
        });
    }
});

onUnmounted(() => {
    if (userId.value) {
        unsubscribe(`landlord.${userId.value}`);
    }
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <!-- Top Bar: Location Dropdown + Actions -->
        <template #header>
            <!-- Phase-23 A11Y-SR-2: page heading for the document outline.
                 sr-only — the header bar is a property switcher, not a title. -->
            <h1 class="sr-only">{{ t('dashboard.title') }}</h1>
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
                                        :class="['w-full text-start px-4 py-2 ps-6 text-sm transition-colors flex items-center gap-2', isActiveBuilding(prop.id, building.id) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50']"
                                    >
                                        <CheckCircleIcon v-if="isActiveBuilding(prop.id, building.id)" class="w-4 h-4" />
                                        <span :class="{ 'ms-6': !isActiveBuilding(prop.id, building.id) }">{{ building.name }}</span>
                                    </button>
                                </template>
                                <!-- Add Wing option -->
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <button
                                        @click="showAddWingModal = true"
                                        class="w-full text-start px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 flex items-center gap-2"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        {{ t('dashboard.header.add_wing') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- Quick Actions - Wrap on mobile -->
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <button @click="showMassHikeModal = true"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                        <MegaphoneIcon class="w-4 h-4 me-1.5" /> {{ t('dashboard.header.rent_hike') }}
                    </button>
                    <Link :href="route('buildings.edit', activeBuilding.id)"
                          class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        <WrenchScrewdriverIcon class="w-4 h-4 me-1.5" /> {{ t('dashboard.header.architect') }}
                    </Link>
                </div>
            </div>
        </template>

        <!-- Wing/Floor Filter Bar (only shown when building has wings) -->
        <div v-if="hasWings" class="bg-white border-b border-gray-200 px-6 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <FunnelIcon class="w-4 h-4" />
                    <span>{{ t('dashboard.filter.label') }}</span>
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
                        {{ t('dashboard.filter.all_wings') }}
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
                <div v-if="allFloors && allFloors.length > 1" class="flex items-center gap-2 ms-4">
                    <span class="text-sm text-gray-500">{{ t('dashboard.filter.floor_label') }}</span>
                    <select
                        :value="activeFloorFilter"
                        @change="setFloorFilter($event.target.value ? parseInt($event.target.value) : null)"
                        class="text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1.5"
                    >
                        <option value="">{{ t('dashboard.filter.all_floors') }}</option>
                        <option v-for="floor in allFloors" :key="floor" :value="floor">
                            {{ t('dashboard.filter.floor_option', { floor }) }}
                        </option>
                    </select>
                </div>

                <!-- Clear Filters -->
                <button
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="ms-auto flex items-center gap-1 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <XMarkIcon class="w-4 h-4" />
                    {{ t('dashboard.filter.clear') }}
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="p-6 lg:p-8 space-y-6">
            <!-- === ACTION ITEMS (Top - Color-coded urgency) === -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <ActionItemCard
                    v-if="localActionItems.overdue_invoices > 0"
                    urgency="critical"
                    :icon="DocumentTextIcon"
                    :count="localActionItems.overdue_invoices"
                    :title="t('dashboard.action_items.overdue_invoices')"
                    :description="t('dashboard.action_items.overdue_outstanding', { amount: formatMoney(localActionItems.overdue_amount) })"
                    :actionLabel="t('dashboard.action_items.view_all')"
                    :actionHref="route('invoices.index', { status: 'overdue' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="DocumentTextIcon"
                    :count="0"
                    :title="t('dashboard.action_items.overdue_invoices')"
                    :description="t('dashboard.action_items.invoices_current')"
                    :actionLabel="t('dashboard.action_items.view_all')"
                    :actionHref="route('invoices.index')"
                />

                <ActionItemCard
                    v-if="localActionItems.expiring_leases > 0"
                    urgency="high"
                    :icon="CalendarDaysIcon"
                    :count="localActionItems.expiring_leases"
                    :title="t('dashboard.action_items.expiring_leases')"
                    :description="t('dashboard.action_items.within_30_days')"
                    :actionLabel="t('dashboard.action_items.view')"
                    :actionHref="route('leases.index')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="CalendarDaysIcon"
                    :count="0"
                    :title="t('dashboard.action_items.expiring_leases')"
                    :description="t('dashboard.action_items.no_leases_soon')"
                    :actionLabel="t('dashboard.action_items.view_all')"
                    :actionHref="route('leases.index')"
                />

                <ActionItemCard
                    v-if="localActionItems.urgent_tickets > 0"
                    urgency="critical"
                    :icon="TicketIcon"
                    :count="localActionItems.urgent_tickets"
                    :title="t('dashboard.action_items.urgent_tickets')"
                    :description="t('dashboard.action_items.immediate_attention')"
                    :actionLabel="t('dashboard.action_items.view')"
                    :actionHref="route('tickets.index', { priority: 'urgent' })"
                />
                <ActionItemCard
                    v-else-if="actionItems.vacant_units > 0"
                    urgency="medium"
                    :icon="HomeModernIcon"
                    :count="localActionItems.vacant_units"
                    :title="t('dashboard.action_items.vacant_units')"
                    :description="t('dashboard.action_items.available_for_lease')"
                    :actionLabel="t('dashboard.action_items.view')"
                    :actionHref="route('dashboard', { property_id: property.id, building_id: activeBuilding?.id, status: 'vacant' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="TicketIcon"
                    :count="0"
                    :title="t('dashboard.action_items.urgent_tickets')"
                    :description="t('dashboard.action_items.no_urgent_issues')"
                    :actionLabel="t('dashboard.action_items.view_all')"
                    :actionHref="route('tickets.index')"
                />

                <!-- Phase-80 ESCALATION-VIEW-2: caretaker escalations awaiting the landlord. -->
                <ActionItemCard
                    v-if="localActionItems.escalated_tickets > 0"
                    urgency="critical"
                    :icon="ExclamationTriangleIcon"
                    :count="localActionItems.escalated_tickets"
                    :title="t('dashboard.action_items.escalated_tickets')"
                    :description="t('dashboard.action_items.caretaker_needs_help')"
                    :actionLabel="t('dashboard.action_items.review')"
                    :actionHref="route('tickets.index', { escalated: 1 })"
                />

                <!-- Phase-82 DOC-EXPIRY-2: renewable documents about to expire. -->
                <ActionItemCard
                    v-if="localActionItems.expiring_documents > 0"
                    urgency="medium"
                    :icon="DocumentTextIcon"
                    :count="localActionItems.expiring_documents"
                    :title="t('dashboard.action_items.expiring_documents')"
                    :description="t('dashboard.action_items.renew_before_lapse')"
                    :actionLabel="t('dashboard.action_items.review')"
                    :actionHref="route('archive.hub', { tab: 'documents', expiry: 'expiring' })"
                />

                <!-- Phase-79 DASHBOARD-WATER-1: water reading review is a Water
                     hub concern, not a landlord-dashboard widget. The landlord
                     reviews in the Water hub; the caretaker records there. -->

                <ActionItemCard
                    v-if="tenantKycStats?.incomplete > 0"
                    urgency="medium"
                    :icon="IdentificationIcon"
                    :count="tenantKycStats.incomplete"
                    :title="t('dashboard.action_items.incomplete_kyc')"
                    :description="t('dashboard.action_items.kyc_verified_rate', { rate: tenantKycStats.rate })"
                    :actionLabel="t('dashboard.action_items.view')"
                    :actionHref="route('tenants.index', { kyc_status: 'incomplete' })"
                />
                <ActionItemCard
                    v-else-if="tenantKycStats?.total > 0"
                    urgency="low"
                    :icon="IdentificationIcon"
                    :count="tenantKycStats.total"
                    :title="t('dashboard.action_items.tenant_kyc')"
                    :description="t('dashboard.action_items.all_tenants_verified')"
                    :actionLabel="t('dashboard.action_items.view_all')"
                    :actionHref="route('tenants.index')"
                />
            </div>

            <!-- === KEY METRICS === -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div :class="['transition-all duration-500 rounded-2xl', metricsUpdating ? 'ring-2 ring-green-400 ring-opacity-60' : '']">
                    <MetricCard
                        :title="t('dashboard.metrics.monthly_revenue')"
                        :value="localFinancialMetrics.monthly_revenue"
                        format="currency"
                        :subtitle="t('dashboard.metrics.expected', { amount: formatMoney(localFinancialMetrics.expected_revenue) })"
                        :icon="BanknotesIcon"
                        color="emerald"
                        :href="route('invoices.index')"
                        :trend="localFinancialMetrics.collection_rate >= 80
                            ? { direction: 'up', value: localFinancialMetrics.collection_rate + '%' }
                            : { direction: 'down', value: localFinancialMetrics.collection_rate + '%' }"
                    />
                </div>

                <div :class="['transition-all duration-500 rounded-2xl', metricsUpdating ? 'ring-2 ring-green-400 ring-opacity-60' : '']">
                    <MetricCard
                        :title="t('dashboard.metrics.collection_rate')"
                        :value="localFinancialMetrics.collection_rate"
                        format="percent"
                        :subtitle="t('dashboard.metrics.this_month')"
                        :icon="ChartBarIcon"
                        color="blue"
                        :href="route('reports.index')"
                    />
                </div>

                <div :class="['transition-all duration-500 rounded-2xl', metricsUpdating ? 'ring-2 ring-green-400 ring-opacity-60' : '']">
                    <MetricCard
                        :title="t('dashboard.metrics.total_arrears')"
                        :value="localFinancialMetrics.total_arrears"
                        format="currency"
                        :subtitle="t('dashboard.metrics.outstanding_balance')"
                        :icon="ExclamationTriangleIcon"
                        color="red"
                        :href="route('invoices.index', { has_arrears: true })"
                    />
                </div>

                <MetricCard
                    :title="t('dashboard.metrics.occupancy_rate')"
                    :value="stats.occupancy_rate"
                    format="percent"
                    :subtitle="t('dashboard.metrics.units_count', { occupied: stats.occupied_units, total: stats.total_units })"
                    :icon="UserGroupIcon"
                    color="indigo"
                    :href="route('dashboard', { property_id: property.id, building_id: activeBuilding?.id, status: 'vacant' })"
                />
            </div>

            <!-- Phase-36 INSIGHT-LANDLORD-1/2: growth widgets -->
            <div v-if="growth" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <EngagementScoreCard :score="growth.engagement_score" :delta="growth.engagement_score_delta_7d" />
                <ReferralCountCard :count="growth.referral_count_30d" />
                <UsageRatioCard :ratios="growth.usage_ratios" />
            </div>

            <!-- === PLATFORM FEE TIER (conditionally rendered) === -->
            <div v-if="currentTier" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">{{ t('dashboard.tier.title') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ t('dashboard.tier.current') }} <span class="font-semibold text-indigo-600">{{ currentTier.name }}</span>
                            {{ t('dashboard.tier.at') }} <span class="font-semibold">{{ currentTier.fee_percentage }}%</span> {{ t('dashboard.tier.per_transaction') }}
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="text-sm text-gray-500">{{ t('dashboard.tier.mtd_volume') }}</p>
                        <p class="text-xl font-bold text-gray-900">{{ formatMoney(mtdVolume ?? 0) }}</p>
                    </div>
                </div>

                <div v-if="nextTier" class="mt-4">
                    <div class="flex items-center justify-between text-sm text-gray-500 mb-1">
                        <span>{{ t('dashboard.tier.progress_to', { name: nextTier.name, percentage: nextTier.fee_percentage }) }}</span>
                        <span>{{ t('dashboard.tier.remaining', { amount: formatMoney(Math.max(0, nextTier.min_volume - (mtdVolume ?? 0))) }) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500" :style="{ width: tierProgressPercent + '%' }"></div>
                    </div>
                </div>
                <div v-else class="mt-4">
                    <p class="text-sm text-green-600 font-medium">{{ t('dashboard.tier.highest') }}</p>
                </div>

                <div v-if="allTiers && allTiers.length > 1" class="mt-4 pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div v-for="tier in allTiers" :key="tier.id"
                             :class="[
                                 'p-3 rounded-lg border text-center',
                                 tier.id === currentTier.id
                                     ? 'border-indigo-300 bg-indigo-50'
                                     : 'border-gray-200 bg-gray-50'
                             ]">
                            <p :class="['text-sm font-semibold', tier.id === currentTier.id ? 'text-indigo-700' : 'text-gray-700']">
                                {{ tier.name }}
                            </p>
                            <p :class="['text-lg font-bold', tier.id === currentTier.id ? 'text-indigo-600' : 'text-gray-900']">
                                {{ tier.fee_percentage }}%
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ tier.max_volume ? formatMoney(tier.min_volume) + ' - ' + formatMoney(tier.max_volume) : formatMoney(tier.min_volume) + '+' }}
                            </p>
                        </div>
                    </div>
                </div>
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
                                        {{ t('dashboard.occupancy.units_across_wings', { units: allUnits?.length || 0, wings: wings.length }) }}
                                    </template>
                                    <template v-else-if="hasWings && activeWingFilter">
                                        {{ t('dashboard.occupancy.units_in_wing', { units: displayedUnits?.length || 0, wing: activeWingName }) }}
                                    </template>
                                    <template v-else>
                                        {{ t('dashboard.occupancy.units_across_floors', { units: units.length, floors: unitsByFloor.length }) }}
                                    </template>
                                </p>
                            </div>

                            <!-- View Toggle + Legend -->
                            <div class="flex items-center gap-4">
                                <!-- View Toggle (only show for single wing/building view) -->
                                <div v-if="!hasWings || activeWingFilter" class="flex bg-gray-100 rounded-lg p-1">
                                    <button
                                        @click="viewMode = 'grid'"
                                        :aria-label="t('dashboard.occupancy.grid_view')"
                                        :aria-pressed="viewMode === 'grid'"
                                        :class="viewMode === 'grid' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                                    >
                                        <Squares2X2Icon class="w-4 h-4" aria-hidden="true" />
                                    </button>
                                    <button
                                        @click="viewMode = 'list'"
                                        :aria-label="t('dashboard.occupancy.list_view')"
                                        :aria-pressed="viewMode === 'list'"
                                        :class="viewMode === 'list' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                                    >
                                        <ListBulletIcon class="w-4 h-4" aria-hidden="true" />
                                    </button>
                                </div>

                                <!-- Legend -->
                                <div class="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full"></span> {{ t('dashboard.occupancy.occupied') }}</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-300 rounded-full"></span> {{ t('dashboard.occupancy.vacant') }}</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span> {{ t('dashboard.occupancy.arrears') }}</span>
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
                                    :class="['px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap', !activeWingFilter ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
                                >
                                    {{ t('dashboard.occupancy.all_wings') }}
                                    <span class="ms-1.5 text-xs opacity-75">({{ allUnits?.length }})</span>
                                </button>
                                <button
                                    v-for="wing in wings"
                                    :key="wing.id"
                                    @click="setWingFilter(wing.id)"
                                    :class="['px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap', activeWingFilter === wing.id ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
                                >
                                    {{ wing.name }}
                                    <span class="ms-1.5 text-xs opacity-75">({{ wing.units?.length || unitsByWing.find(w => w.wing.id === wing.id)?.units?.length || 0 }})</span>
                                </button>
                            </div>

                            <!-- Unified Grid (All Wings or Filtered) -->
                            <div class="p-4">
                                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
                                    <button
                                        v-for="unit in displayedUnits"
                                        :key="unit.id"
                                        @click="selectUnit(unit)"
                                        :class="['relative aspect-square rounded-xl border-2 p-1 flex flex-col items-center justify-center transition-all shadow-sm', selectedUnit?.id === unit.id ? 'ring-2 ring-indigo-500 ring-offset-2' : 'hover:shadow-md hover:-translate-y-0.5', unit.status === 'occupied' ? 'bg-green-50 border-green-200' : unit.status === 'arrears' ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200']"
                                        :title="t('dashboard.occupancy.unit_title', { number: unit.unit_number, wing: (unit.wing_name || unit.building?.name || ''), status: (unit.status === 'occupied' ? t('dashboard.occupancy.occupied') : unit.status === 'arrears' ? t('dashboard.occupancy.arrears') : t('dashboard.occupancy.vacant')) })"
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
                                            {{ t('dashboard.occupancy.floor', { floor: floorGroup.floor }) }}
                                        </span>
                                        <span class="text-xs text-gray-400 ms-2">
                                            {{ t('dashboard.occupancy.floor_occupied', { occupied: floorGroup.units.filter(u => u.status === 'occupied').length, total: floorGroup.units.length }) }}
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
                                                    {{ unit.status === 'occupied' ? t('dashboard.occupancy.status_occ') : unit.status === 'arrears' ? t('dashboard.occupancy.status_late') : t('dashboard.occupancy.status_vac') }}
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
                                        {{ t('dashboard.occupancy.floor', { floor: floorGroup.floor }) }}
                                    </span>
                                </div>

                                <!-- Floor Units List -->
                                <div class="divide-y divide-gray-100">
                                    <button
                                        v-for="unit in floorGroup.units"
                                        :key="unit.id"
                                        @click="selectUnit(unit)"
                                        :class="selectedUnit?.id === unit.id ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                                        class="w-full px-4 sm:px-6 py-3 flex items-center justify-between text-start transition-colors"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm', unit.status === 'occupied' ? 'bg-green-100 text-green-700' : unit.status === 'arrears' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">
                                                {{ unit.unit_number }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ unit.unit_type || t('dashboard.occupancy.unit_fallback') }} {{ unit.unit_number }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ t('dashboard.occupancy.rent_per_month', { amount: formatMoney(unit.target_rent) }) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span :class="['text-xs font-medium px-2 py-1 rounded-full', unit.status === 'occupied' ? 'bg-green-100 text-green-700' : unit.status === 'arrears' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">
                                                {{ unit.status === 'occupied' ? t('dashboard.occupancy.occupied') : unit.status === 'arrears' ? t('dashboard.occupancy.arrears') : t('dashboard.occupancy.vacant') }}
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
                        {{ t('dashboard.arrears.title') }}
                        <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ms-1">({{ activeWingName }})</span>
                    </h3>

                    <div v-if="totalArrears > 0" class="space-y-4">
                        <!-- 0-30 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '0_30' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                    <span class="text-sm text-gray-600">{{ t('dashboard.arrears.days_0_30') }}</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(localArrearsAging['0_30']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-yellow-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(localArrearsAging['0_30']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 31-60 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '31_60' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-orange-400"></div>
                                    <span class="text-sm text-gray-600">{{ t('dashboard.arrears.days_31_60') }}</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(localArrearsAging['31_60']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-orange-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(localArrearsAging['31_60']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 61-90 Days -->
                        <Link :href="route('invoices.index', { arrears_age: '61_90' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                    <span class="text-sm text-gray-600">{{ t('dashboard.arrears.days_61_90') }}</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(localArrearsAging['61_90']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-red-400 h-2 rounded-full" :style="{ width: getArrearsPercentage(localArrearsAging['61_90']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- 90+ Days -->
                        <Link :href="route('invoices.index', { arrears_age: '90_plus' })" class="block hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-700"></div>
                                    <span class="text-sm text-gray-600">{{ t('dashboard.arrears.days_90_plus') }}</span>
                                </div>
                                <span class="font-semibold text-gray-900">{{ formatMoney(localArrearsAging['90_plus']) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                                <div class="bg-red-700 h-2 rounded-full" :style="{ width: getArrearsPercentage(localArrearsAging['90_plus']) + '%' }"></div>
                            </div>
                        </Link>

                        <!-- Total -->
                        <Link :href="route('invoices.index', { status: 'overdue' })" class="block pt-4 border-t border-gray-200 mt-4 hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors cursor-pointer">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">{{ t('dashboard.arrears.total_outstanding') }}</span>
                                <span class="text-xl font-bold text-gray-900">{{ formatMoney(totalArrears) }}</span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('dashboard.arrears.none') }}</p>
                        <p class="text-sm text-gray-400">{{ t('dashboard.arrears.all_current') }}</p>
                    </div>
                </div>
            </div>

            <!-- === BUILDING FILTER CHIP === -->
            <div v-if="buildings && buildings.length > 1" class="flex items-center gap-2 text-sm">
                <span class="text-gray-500">{{ t('dashboard.building_chip.showing') }}</span>
                <span
                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 font-medium"
                    :class="allBuildingsMode ? 'bg-indigo-100 text-indigo-800' : 'bg-emerald-100 text-emerald-800'"
                    data-testid="dashboard-building-chip"
                >
                    {{ allBuildingsMode ? t('dashboard.building_chip.all_buildings') : (activeBuilding?.name || t('dashboard.building_chip.building_fallback')) }}
                    <Link
                        v-if="!allBuildingsMode"
                        :href="route('dashboard', { property_id: property.id, building_id: 'all' })"
                        preserve-state
                        class="-me-1 ms-1 rounded-full text-emerald-800 hover:bg-emerald-200"
                    >
                        <span class="sr-only">{{ $t('dashboard.clear_building_filter') }}</span>
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </Link>
                </span>

                <!-- Phase-74 CROSS-BUILDING: persisted scope toggle -->
                <div class="inline-flex overflow-hidden rounded-lg border border-gray-200" data-testid="dashboard-scope-toggle">
                    <button
                        type="button"
                        class="px-3 py-1 text-xs font-medium"
                        :class="dashboardScope === 'active_building' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        @click="setScope('active_building')"
                    >
                        {{ $t('dashboard.scope.active_building') }}
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 text-xs font-medium"
                        :class="dashboardScope === 'all_buildings' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        @click="setScope('all_buildings')"
                    >
                        {{ $t('dashboard.scope.all_buildings') }}
                    </button>
                </div>
            </div>

            <!-- === RECENT PAYMENTS + TICKETS + EXPIRING LEASES === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- RECENT PAYMENTS -->
                <div
                    :draggable="true"
                    :style="{ order: widgetOrderIndex('recent-payments') }"
                    data-testid="widget-recent-payments"
                    class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6"
                    @dragstart="onWidgetDragStart('recent-payments', $event)"
                    @dragover.prevent
                    @drop="onWidgetDrop('recent-payments', $event)"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            {{ t('dashboard.payments.title') }}
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ms-1">({{ activeWingName }})</span>
                        </h3>
                        <Link :href="route('invoices.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            {{ t('dashboard.payments.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                        </Link>
                    </div>

                    <div v-if="localRecentPayments && localRecentPayments.length > 0" class="space-y-3">
                        <Link v-for="payment in localRecentPayments" :key="payment.id"
                             :href="route('payments.detail.show', payment.id)"
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <BanknotesIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{ payment.invoice?.lease?.tenant?.name || t('dashboard.payments.unknown') }}
                                        <span
                                            v-if="payment.lease_state && payment.lease_state !== 'active'"
                                            class="ms-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                            :class="leaseStateBadgeClass(payment.lease_state)"
                                            :aria-label="leaseStateBadgeLabel(payment.lease_state)"
                                            data-testid="lease-state-badge"
                                        >
                                            {{ leaseStateBadgeLabel(payment.lease_state) }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-500">{{ payment.invoice?.lease?.unit?.unit_number || '-' }} • {{ formatDate(payment.payment_date) }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p
                                    class="font-bold text-green-600"
                                    :title="payment.platform_fee ? t('dashboard.payments.net_fee', { net: formatMoney(payment.landlord_amount), fee: formatMoney(payment.platform_fee) }) : undefined"
                                >
                                    {{ formatMoney(payment.amount) }}
                                </p>
                                <p class="text-xs text-gray-500">{{ payment.payment_method }}</p>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8">
                        <BanknotesIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('dashboard.payments.none') }}</p>
                    </div>
                </div>

                <!-- RECENT TICKETS -->
                <div
                    :draggable="true"
                    :style="{ order: widgetOrderIndex('recent-tickets') }"
                    data-testid="widget-recent-tickets"
                    class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6"
                    @dragstart="onWidgetDragStart('recent-tickets', $event)"
                    @dragover.prevent
                    @drop="onWidgetDrop('recent-tickets', $event)"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            {{ t('dashboard.tickets.title') }}
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ms-1">({{ activeWingName }})</span>
                        </h3>
                        <Link :href="route('tickets.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            {{ t('dashboard.tickets.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                        </Link>
                    </div>

                    <div v-if="localRecentTickets && localRecentTickets.length > 0" class="space-y-3">
                        <Link v-for="ticket in localRecentTickets" :key="ticket.id"
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
                                        {{ ticket.unit_number || t('dashboard.tickets.building_fallback') }} • {{ ticket.reporter_name || t('dashboard.tickets.unknown') }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-end">
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
                        <p class="text-gray-500">{{ t('dashboard.tickets.none') }}</p>
                    </div>
                </div>

                <!-- EXPIRING LEASES -->
                <div
                    :draggable="true"
                    :style="{ order: widgetOrderIndex('expiring-leases') }"
                    data-testid="widget-expiring-leases"
                    class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6"
                    @dragstart="onWidgetDragStart('expiring-leases', $event)"
                    @dragover.prevent
                    @drop="onWidgetDrop('expiring-leases', $event)"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            {{ t('dashboard.leases.title') }}
                            <span v-if="activeWingFilter" class="text-sm font-normal text-indigo-600 ms-1">({{ activeWingName }})</span>
                        </h3>
                        <span class="text-sm text-gray-500">{{ t('dashboard.leases.next_60_days') }}</span>
                    </div>

                    <div v-if="expiringLeases && expiringLeases.length > 0" class="space-y-3">
                        <button v-for="lease in expiringLeases" :key="lease.id"
                             @click="selectUnitById(lease.unit?.id)"
                             class="w-full flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-100 hover:bg-orange-100 cursor-pointer transition-colors text-start">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                    <CalendarDaysIcon class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ lease.tenant?.name || t('dashboard.leases.unknown') }}</p>
                                    <p class="text-xs text-gray-500">{{ lease.unit?.building?.name }} - {{ lease.unit?.unit_number }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="font-semibold text-orange-600">{{ formatRelativeDate(lease.end_date) }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(lease.end_date) }}</p>
                            </div>
                        </button>
                    </div>
                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-12 h-12 text-green-500 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('dashboard.leases.none') }}</p>
                        <p class="text-sm text-gray-400">{{ t('dashboard.leases.all_current') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR (Unit Context Panel) - Using SlideOutPanel -->
        <SlideOutPanel :show="selectedUnit !== null" @close="closePanel" :title="t('dashboard.panel.unit_title', { number: selectedUnit?.unit_number || '' })" :subtitle="t('dashboard.panel.unit_subtitle', { floor: selectedUnit?.floor_number || '', type: selectedUnit?.unit_type || '' })">
            <template #default>
                <div v-if="selectedUnit" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('dashboard.panel.financials') }}</h3>
                        <div class="flex justify-between items-center p-4 bg-white border border-gray-200 rounded-lg">
                            <span class="text-gray-600">{{ t('dashboard.panel.monthly_rent') }}</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatMoney(selectedUnit.target_rent) }}</span>
                        </div>
                    </div>

                    <div v-if="selectedUnit.status === 'vacant'" class="bg-indigo-50 p-6 rounded-xl border border-indigo-100 text-center">
                        <UserGroupIcon class="w-10 h-10 text-indigo-600 mx-auto mb-3"/>
                        <h3 class="font-bold text-indigo-900">{{ t('dashboard.panel.ready_to_lease') }}</h3>
                        <p class="text-sm text-indigo-700 mb-4">{{ t('dashboard.panel.add_tenant_prompt') }}</p>
                        <Link :href="route('leases.create', selectedUnit.id)" class="block w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors">
                            {{ t('dashboard.panel.add_tenant') }}
                        </Link>
                    </div>

                    <div v-else-if="selectedUnit.active_lease" class="space-y-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('dashboard.panel.tenant_profile') }}</h3>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden">
                                    <img v-if="unitDetail?.tenant?.profile_photo_url"
                                         :src="unitDetail.tenant.profile_photo_url"
                                         loading="lazy"
                                         decoding="async"
                                         alt=""
                                         class="h-full w-full object-cover" />
                                    <span v-else class="text-indigo-700 font-bold text-lg">
                                        {{ selectedUnit.active_lease.tenant?.name?.charAt(0) || '?' }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900">{{ selectedUnit.active_lease.tenant?.name || t('dashboard.panel.unknown') }}</div>
                                    <div class="text-xs text-gray-500">{{ selectedUnit.active_lease.tenant?.email || '' }}</div>
                                </div>
                            </div>

                            <div v-if="loadingUnitDetail" class="text-center py-2">
                                <span class="text-sm text-gray-400">{{ t('dashboard.panel.loading') }}</span>
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
                                        {{ t('dashboard.panel.kyc_verified') }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                                        <ExclamationTriangleIcon class="w-3 h-3" />
                                        {{ t('dashboard.panel.kyc_incomplete') }}
                                    </span>
                                </div>
                            </div>

                            <button @click="showProfileModal = true" class="w-full py-2 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 shadow-sm mb-2">{{ t('dashboard.panel.view_full_profile') }}</button>
                            <Link :href="route('invoices.index')" class="block w-full py-2 bg-green-600 text-white text-center font-bold rounded-lg hover:bg-green-700 shadow-sm">{{ t('dashboard.panel.view_invoices') }}</Link>
                        </div>

                        <!-- Unit Tickets -->
                        <div v-if="unitDetail?.tickets?.length > 0">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('dashboard.panel.unit_tickets') }}</h3>
                            <div class="space-y-2">
                                <Link v-for="ticket in unitDetail.tickets" :key="ticket.id"
                                      :href="route('tickets.show', ticket.id)"
                                      class="block p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ ticket.title }}</p>
                                        <span :class="['text-xs px-1.5 py-0.5 rounded-full', ticket.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600']">{{ ticket.priority }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ formatRelativeDate(ticket.created_at) }}</p>
                                </Link>
                            </div>
                        </div>

                        <!-- Payment History -->
                        <div v-if="unitDetail?.payments?.length > 0">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('dashboard.panel.recent_payments') }}</h3>
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
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ t('dashboard.panel.quick_actions') }}</h3>
                        <div class="space-y-2">
                            <Link v-if="selectedUnit.active_lease" :href="route('tickets.create', { unit_id: selectedUnit.id })" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <TicketIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">{{ t('dashboard.panel.create_ticket') }}</span>
                            </Link>
                            <Link :href="route('buildings.edit', activeBuilding.id)" class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                                <WrenchScrewdriverIcon class="w-5 h-5 text-gray-500" />
                                <span class="text-sm font-medium text-gray-700">{{ t('dashboard.panel.edit_unit') }}</span>
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
