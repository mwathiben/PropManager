<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { MetricCard, AmountDisplay, ExportDropdown } from '@/Components/Finances';
import {
    ChartBarIcon,
    CurrencyDollarIcon,
    BuildingOfficeIcon,
    ClockIcon,
    ChartPieIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    FunnelIcon,
    CalendarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import type { Building } from '@/types/finances';

interface RevenueDataPoint {
    month: string;
    invoiced: number;
    collected: number;
}

interface CollectionRatePoint {
    month: string;
    rate: number;
}

interface OccupancyData {
    buildings: Array<{ name: string; occupied: number; total: number; rate: number }>;
    totals: { occupied: number; total: number; rate: number };
}

interface ArrearsAging {
    current: number;
    '1-30': number;
    '31-60': number;
    '61-90': number;
    '90+': number;
    total: number;
}

interface ExpensesByCategory {
    categories: Array<{ name: string; amount: number; percentage: number }>;
    total: number;
}

interface WaterConsumption {
    total_usage: number;
    total_cost: number;
    by_building: Array<{ name: string; usage: number; cost: number }>;
}

interface TopPerformingUnit {
    unit_number: string;
    building: string;
    revenue: number;
    collection_rate: number;
}

interface ReportTotals {
    total_invoiced: number;
    total_collected: number;
    total_outstanding: number;
    average_collection_rate: number;
}

interface ReportFilters {
    period?: string;
    building_id?: string | number;
    date_from?: string;
    date_to?: string;
    compare?: boolean;
}

interface FeatureAccess {
    water_module?: boolean;
    expenses_module?: boolean;
}

interface Props {
    revenueData?: RevenueDataPoint[];
    collectionRate?: CollectionRatePoint[];
    occupancyData?: OccupancyData;
    arrearsAging?: ArrearsAging;
    expensesByCategory?: ExpensesByCategory;
    waterConsumption?: WaterConsumption | null;
    topPerformingUnits?: TopPerformingUnit[];
    totals?: ReportTotals;
    previousPeriodData?: ReportTotals | null;
    buildings?: Building[];
    filters?: ReportFilters;
    featureAccess?: FeatureAccess;
}

const props = withDefaults(defineProps<Props>(), {
    revenueData: () => [],
    collectionRate: () => [],
    occupancyData: () => ({ buildings: [], totals: {} }),
    arrearsAging: () => ({}),
    expensesByCategory: () => ({ categories: [], total: 0 }),
    waterConsumption: null,
    topPerformingUnits: () => [],
    totals: () => ({}),
    previousPeriodData: null,
    buildings: () => [],
    filters: () => ({ period: '12' }),
    featureAccess: () => ({}),
});

const { formatMoney, formatNumber, formatPercent } = useFormatters();

const localFilters = ref({
    period: props.filters?.period || '12',
    buildingId: props.filters?.building_id || '',
    dateFrom: props.filters?.date_from || '',
    dateTo: props.filters?.date_to || '',
    compare: props.filters?.compare || false,
});

const showFilters = ref(false);

const exportFormats = [
    { value: 'xlsx', label: 'Excel (.xlsx)' },
    { value: 'pdf', label: 'PDF' },
    { value: 'csv', label: 'CSV' },
];

const periodOptions = [
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_quarter', label: 'This Quarter' },
    { value: 'last_quarter', label: 'Last Quarter' },
    { value: 'ytd', label: 'Year to Date' },
    { value: 'this_fy', label: 'This Fiscal Year' },
    { value: 'last_fy', label: 'Last Fiscal Year' },
    { value: '12', label: 'Last 12 Months' },
    { value: '6', label: 'Last 6 Months' },
    { value: '3', label: 'Last 3 Months' },
    { value: 'custom', label: 'Custom Range' },
];

const isCustomPeriod = computed(() => localFilters.value.period === 'custom');

const summaryStats = computed(() => {
    const totalInvoiced = props.revenueData?.reduce((sum, m) => sum + (m.invoiced || 0), 0) || 0;
    const totalCollected = props.revenueData?.reduce((sum, m) => sum + (m.collected || 0), 0) || 0;
    const totalExpenses = props.revenueData?.reduce((sum, m) => sum + (m.expenses || 0), 0) || 0;
    const avgCollectionRate = props.collectionRate?.length > 0
        ? props.collectionRate.reduce((sum, m) => sum + (m.rate || 0), 0) / props.collectionRate.length
        : 0;

    return {
        totalInvoiced,
        totalCollected,
        totalExpenses,
        netIncome: totalCollected - totalExpenses,
        avgCollectionRate: Math.round(avgCollectionRate * 10) / 10,
        grossMargin: totalCollected > 0 ? Math.round(((totalCollected - totalExpenses) / totalCollected) * 100 * 10) / 10 : 0,
    };
});

const trendData = computed(() => {
    if (!props.previousPeriodData?.totals || !localFilters.value.compare) return null;

    const prev = props.previousPeriodData.totals;
    const curr = props.totals;

    const calcTrend = (current, previous) => {
        if (!previous || previous === 0) return { percent: 0, direction: 'neutral' };
        const change = ((current - previous) / previous) * 100;
        return {
            percent: Math.abs(Math.round(change * 10) / 10),
            direction: change > 0 ? 'up' : change < 0 ? 'down' : 'neutral',
        };
    };

    return {
        invoiced: calcTrend(curr.invoiced, prev.invoiced),
        collected: calcTrend(curr.collected, prev.collected),
        expenses: calcTrend(curr.expenses, prev.expenses),
        collectionRate: calcTrend(curr.collection_rate, prev.collection_rate),
    };
});

const maxRevenue = computed(() => {
    const values = props.revenueData?.flatMap(m => [m.invoiced, m.collected, m.expenses]) || [];
    return Math.max(...values, 1);
});

const arrearsTotal = computed(() => {
    if (!props.arrearsAging) return 0;
    return Object.values(props.arrearsAging).reduce((sum, bucket) => sum + (bucket.amount || 0), 0);
});

const agingBuckets = computed(() => {
    const buckets = ['current', '1-30', '31-60', '61-90', '90+'];
    const labels = {
        'current': 'Current',
        '1-30': '1-30 Days',
        '31-60': '31-60 Days',
        '61-90': '61-90 Days',
        '90+': '90+ Days',
    };
    const colors = {
        'current': 'bg-emerald-500',
        '1-30': 'bg-blue-500',
        '31-60': 'bg-yellow-500',
        '61-90': 'bg-orange-500',
        '90+': 'bg-red-500',
    };

    return buckets.map(key => ({
        key,
        label: labels[key],
        color: colors[key],
        count: props.arrearsAging?.[key]?.count || 0,
        amount: props.arrearsAging?.[key]?.amount || 0,
        percentage: arrearsTotal.value > 0
            ? Math.round(((props.arrearsAging?.[key]?.amount || 0) / arrearsTotal.value) * 100)
            : 0,
    }));
});

const hasActiveFilters = computed(() => {
    return localFilters.value.buildingId || localFilters.value.compare ||
        (isCustomPeriod.value && localFilters.value.dateFrom && localFilters.value.dateTo);
});

const applyFilters = () => {
    const params = {
        period: localFilters.value.period,
    };

    if (localFilters.value.buildingId) {
        params.building_id = localFilters.value.buildingId;
    }

    if (isCustomPeriod.value && localFilters.value.dateFrom && localFilters.value.dateTo) {
        params.date_from = localFilters.value.dateFrom;
        params.date_to = localFilters.value.dateTo;
    }

    if (localFilters.value.compare) {
        params.compare = true;
    }

    router.get(route('finances.reports'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    localFilters.value = {
        period: '12',
        buildingId: '',
        dateFrom: '',
        dateTo: '',
        compare: false,
    };
    applyFilters();
};

const exportReport = (format) => {
    const params = new URLSearchParams({ format, period: localFilters.value.period });

    if (localFilters.value.buildingId) {
        params.append('building_id', localFilters.value.buildingId);
    }
    if (isCustomPeriod.value && localFilters.value.dateFrom) {
        params.append('date_from', localFilters.value.dateFrom);
        params.append('date_to', localFilters.value.dateTo);
    }

    window.location.href = route('finances.reports.export') + '?' + params.toString();
};

watch(() => localFilters.value.period, (newVal) => {
    if (newVal !== 'custom') {
        applyFilters();
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Financial Reports</h2>
                <p class="text-sm text-gray-500">Analyze revenue, expenses, and collection performance</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="localFilters.period"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                >
                    <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>

                <button
                    @click="showFilters = !showFilters"
                    :class="[
                        'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border transition-colors',
                        hasActiveFilters
                            ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    <FunnelIcon class="w-4 h-4" />
                    Filters
                    <span v-if="hasActiveFilters" class="w-2 h-2 bg-emerald-500 rounded-full" />
                </button>

                <ExportDropdown :formats="exportFormats" @export="exportReport" />
            </div>
        </div>

        <div v-if="showFilters" class="bg-gray-50 rounded-xl p-4 border border-gray-200">
            <div class="flex flex-wrap items-end gap-4">
                <div v-if="buildings?.length > 1" class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Building</label>
                    <select
                        v-model="localFilters.buildingId"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                    >
                        <option value="">All Buildings</option>
                        <option v-for="building in buildings" :key="building.id" :value="building.id">
                            {{ building.name }}
                        </option>
                    </select>
                </div>

                <div v-if="isCustomPeriod" class="flex-1 min-w-[150px]">
                    <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                    <input
                        type="date"
                        v-model="localFilters.dateFrom"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <div v-if="isCustomPeriod" class="flex-1 min-w-[150px]">
                    <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                    <input
                        type="date"
                        v-model="localFilters.dateTo"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="localFilters.compare"
                            class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                        />
                        <span class="text-sm text-gray-700">Compare to previous period</span>
                    </label>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700"
                    >
                        Apply
                    </button>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <CurrencyDollarIcon class="w-5 h-5 text-blue-600" />
                    </div>
                    <div v-if="trendData?.invoiced" class="flex items-center gap-1 text-xs font-medium"
                         :class="trendData.invoiced.direction === 'up' ? 'text-emerald-600' : trendData.invoiced.direction === 'down' ? 'text-red-600' : 'text-gray-500'">
                        <component :is="trendData.invoiced.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4" />
                        {{ trendData.invoiced.percent }}%
                    </div>
                </div>
                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ formatMoney(summaryStats.totalInvoiced) }}</p>
                <p class="text-sm text-gray-500">Total Invoiced</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-emerald-100 rounded-lg">
                        <ChartBarIcon class="w-5 h-5 text-emerald-600" />
                    </div>
                    <div v-if="trendData?.collected" class="flex items-center gap-1 text-xs font-medium"
                         :class="trendData.collected.direction === 'up' ? 'text-emerald-600' : trendData.collected.direction === 'down' ? 'text-red-600' : 'text-gray-500'">
                        <component :is="trendData.collected.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4" />
                        {{ trendData.collected.percent }}%
                    </div>
                </div>
                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ formatMoney(summaryStats.totalCollected) }}</p>
                <p class="text-sm text-gray-500">Total Collected</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <ChartPieIcon class="w-5 h-5 text-red-600" />
                    </div>
                    <div v-if="trendData?.expenses" class="flex items-center gap-1 text-xs font-medium"
                         :class="trendData.expenses.direction === 'down' ? 'text-emerald-600' : trendData.expenses.direction === 'up' ? 'text-red-600' : 'text-gray-500'">
                        <component :is="trendData.expenses.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4" />
                        {{ trendData.expenses.percent }}%
                    </div>
                </div>
                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ formatMoney(summaryStats.totalExpenses) }}</p>
                <p class="text-sm text-gray-500">Total Expenses</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <ArrowTrendingUpIcon class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div v-if="trendData?.collectionRate" class="flex items-center gap-1 text-xs font-medium"
                         :class="trendData.collectionRate.direction === 'up' ? 'text-emerald-600' : trendData.collectionRate.direction === 'down' ? 'text-red-600' : 'text-gray-500'">
                        <component :is="trendData.collectionRate.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4" />
                        {{ trendData.collectionRate.percent }}%
                    </div>
                </div>
                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summaryStats.avgCollectionRate }}%</p>
                <p class="text-sm text-gray-500">Avg Collection Rate</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Revenue vs Expenses</h3>
                <div v-if="revenueData?.length" class="space-y-3">
                    <div v-for="month in revenueData" :key="month.month" class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ month.month }}</span>
                            <span class="font-medium" :class="month.net >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                Net: {{ formatMoney(month.net) }}
                            </span>
                        </div>
                        <div class="flex gap-1 h-6">
                            <div
                                class="bg-blue-500 rounded-l transition-all duration-300"
                                :style="{ width: `${(month.invoiced / maxRevenue) * 45}%` }"
                                :title="`Invoiced: ${formatMoney(month.invoiced)}`"
                            />
                            <div
                                class="bg-emerald-500 transition-all duration-300"
                                :style="{ width: `${(month.collected / maxRevenue) * 45}%` }"
                                :title="`Collected: ${formatMoney(month.collected)}`"
                            />
                            <div
                                class="bg-red-400 rounded-r transition-all duration-300"
                                :style="{ width: `${(month.expenses / maxRevenue) * 45}%` }"
                                :title="`Expenses: ${formatMoney(month.expenses)}`"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-4 pt-2 text-xs border-t border-gray-100">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-500 rounded" /> Invoiced</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-emerald-500 rounded" /> Collected</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-400 rounded" /> Expenses</span>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <ChartBarIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-gray-500">No revenue data</p>
                    <p class="text-xs text-gray-400 mt-1">Try adjusting your filters or date range</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Collection Rate Trend</h3>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span class="w-6 border-t-2 border-dashed border-emerald-400" />
                        <span>85% Target</span>
                    </div>
                </div>
                <div v-if="collectionRate?.length" class="space-y-3 relative">
                    <div v-for="month in collectionRate" :key="month.month" class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ month.month }}</span>
                            <span class="font-medium" :class="month.rate >= 85 ? 'text-emerald-600' : month.rate >= 70 ? 'text-yellow-600' : 'text-red-600'">
                                {{ month.rate }}%
                            </span>
                        </div>
                        <div class="relative h-4 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="absolute left-[85%] top-0 bottom-0 w-0.5 bg-emerald-400 opacity-50"
                                title="85% Target"
                            />
                            <div
                                :class="[
                                    'h-full rounded-full transition-all duration-300',
                                    month.rate >= 85 ? 'bg-emerald-500' : month.rate >= 70 ? 'bg-yellow-500' : 'bg-red-500'
                                ]"
                                :style="{ width: `${month.rate}%` }"
                            />
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <ArrowTrendingUpIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-gray-500">No collection data</p>
                    <p class="text-xs text-gray-400 mt-1">Data will appear when invoices are created</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900">Occupancy by Building</h3>
                </div>
                <div v-if="occupancyData?.buildings?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                                <th class="pb-2 font-medium">Building</th>
                                <th class="pb-2 font-medium text-center">Units</th>
                                <th class="pb-2 font-medium text-center">Occupied</th>
                                <th class="pb-2 font-medium text-center">Vacant</th>
                                <th class="pb-2 font-medium text-right">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="building in occupancyData.buildings" :key="building.building" class="hover:bg-gray-50">
                                <td class="py-2 font-medium text-gray-900">{{ building.building }}</td>
                                <td class="py-2 text-center text-gray-600">{{ building.total_units }}</td>
                                <td class="py-2 text-center text-emerald-600">{{ building.occupied }}</td>
                                <td class="py-2 text-center text-gray-400">{{ building.vacant }}</td>
                                <td class="py-2 text-right">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs font-medium rounded-full',
                                        building.occupancy_rate >= 85 ? 'bg-emerald-100 text-emerald-700' :
                                        building.occupancy_rate >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
                                        {{ building.occupancy_rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="occupancyData.totals" class="border-t-2 border-gray-200">
                            <tr class="font-semibold">
                                <td class="pt-2 text-gray-900">Total</td>
                                <td class="pt-2 text-center">{{ occupancyData.totals.total_units }}</td>
                                <td class="pt-2 text-center text-emerald-600">{{ occupancyData.totals.occupied }}</td>
                                <td class="pt-2 text-center text-gray-400">{{ occupancyData.totals.vacant }}</td>
                                <td class="pt-2 text-right">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                                        {{ occupancyData.totals.occupancy_rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div v-else class="text-center py-12">
                    <BuildingOfficeIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-gray-500">No buildings found</p>
                    <p class="text-xs text-gray-400 mt-1">Add properties to see occupancy data</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <ClockIcon class="w-5 h-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900">Arrears Aging</h3>
                </div>
                <div v-if="arrearsTotal > 0" class="space-y-4">
                    <div class="text-center pb-3 border-b border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Total Outstanding</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ formatMoney(arrearsTotal) }}</p>
                    </div>
                    <div class="space-y-2">
                        <div v-for="bucket in agingBuckets" :key="bucket.key" class="flex items-center gap-3">
                            <div class="w-20 text-xs text-gray-600">{{ bucket.label }}</div>
                            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                                <div :class="[bucket.color, 'h-full rounded-full transition-all duration-300']" :style="{ width: `${bucket.percentage}%` }" />
                            </div>
                            <div class="w-24 text-right">
                                <span class="text-xs font-medium text-gray-900">{{ formatMoney(bucket.amount) }}</span>
                                <span class="text-xs text-gray-400 ml-1">({{ bucket.count }})</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <ArrowTrendingUpIcon class="w-6 h-6 text-emerald-600" />
                    </div>
                    <p class="text-sm font-medium text-gray-900">No Outstanding Arrears</p>
                    <p class="text-xs text-gray-500 mt-1">All invoices are paid on time</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 mb-4">
                <ChartPieIcon class="w-5 h-5 text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-900">Expenses by Category</h3>
            </div>
            <div v-if="expensesByCategory?.categories?.length" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-center justify-center">
                    <div class="relative w-48 h-48">
                        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                            <template v-for="(cat, index) in expensesByCategory.categories" :key="cat.category">
                                <circle cx="50" cy="50" r="40" fill="none" :stroke="cat.color" stroke-width="20"
                                    :stroke-dasharray="`${cat.percentage * 2.51} 251`"
                                    :stroke-dashoffset="`${-expensesByCategory.categories.slice(0, index).reduce((sum, c) => sum + c.percentage, 0) * 2.51}`" />
                            </template>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-xs text-gray-500">Total</p>
                            <p class="text-lg font-semibold text-gray-900">{{ formatMoney(expensesByCategory.total) }}</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div v-for="cat in expensesByCategory.categories" :key="cat.category" class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ cat.category }}</p>
                            <p class="text-xs text-gray-500">{{ cat.count }} expense{{ cat.count !== 1 ? 's' : '' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900">{{ formatMoney(cat.amount) }}</p>
                            <p class="text-xs text-gray-500">{{ cat.percentage }}%</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <ChartPieIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-medium text-gray-500">No expenses recorded</p>
                <p class="text-xs text-gray-400 mt-1">Expenses will appear here when added</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-if="featureAccess?.water_billing" class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <h3 class="text-sm font-semibold text-gray-900">Water Consumption</h3>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold text-cyan-600">{{ formatNumber(waterConsumption?.total_consumption || 0) }} <span class="text-xs font-normal">units</span></p>
                        <p class="text-xs text-gray-500">{{ formatMoney(waterConsumption?.total_cost || 0) }} total cost</p>
                    </div>
                </div>
                <div v-if="waterConsumption?.top_consumers?.length" class="space-y-2 max-h-64 overflow-y-auto">
                    <p class="text-xs font-medium text-gray-500 mb-2">Top Consumers</p>
                    <div v-for="(consumer, index) in waterConsumption.top_consumers" :key="index" class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ consumer.unit }}</p>
                            <p class="text-xs text-gray-500">{{ consumer.building }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-cyan-600">{{ formatNumber(consumer.consumption) }} units</p>
                            <p class="text-xs text-gray-500">{{ formatMoney(consumer.cost) }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-sm text-gray-500">No water consumption data</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <ArrowTrendingUpIcon class="w-5 h-5 text-emerald-500" />
                    <h3 class="text-sm font-semibold text-gray-900">Top Performing Units</h3>
                </div>
                <div v-if="topPerformingUnits?.length" class="space-y-2 max-h-64 overflow-y-auto">
                    <div v-for="(unit, index) in topPerformingUnits" :key="index" class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ unit.unit }}</p>
                            <p class="text-xs text-gray-500">{{ unit.tenant }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold" :class="unit.collection_rate >= 90 ? 'text-emerald-600' : unit.collection_rate >= 70 ? 'text-yellow-600' : 'text-red-600'">
                                {{ unit.collection_rate }}%
                            </p>
                            <p class="text-xs text-gray-500">{{ unit.on_time_payments }}/{{ unit.total_invoices }} on-time</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <ArrowTrendingUpIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-gray-500">No performance data</p>
                    <p class="text-xs text-gray-400 mt-1">Data appears when invoices are generated</p>
                </div>
            </div>
        </div>
    </div>
</template>
