<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { MetricCard, AmountDisplay } from '@/Components/Finances';
import {
    ChartBarIcon,
    CurrencyDollarIcon,
    BuildingOfficeIcon,
    ClockIcon,
    ArrowDownTrayIcon,
    ChartPieIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    revenueData: { type: Array, default: () => [] },
    collectionRate: { type: Array, default: () => [] },
    occupancyData: { type: Object, default: () => ({ buildings: [], totals: {} }) },
    arrearsAging: { type: Object, default: () => ({}) },
    expensesByCategory: { type: Object, default: () => ({ categories: [], total: 0 }) },
    waterConsumption: { type: Object, default: () => ({ top_consumers: [], total_consumption: 0, total_cost: 0 }) },
    topPerformingUnits: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ period: '12' }) },
});

const { formatMoney, formatNumber, formatPercent } = useFormatters();

const selectedPeriod = ref(props.filters?.period || '12');
const showExportMenu = ref(false);

const periodOptions = [
    { value: '3', label: 'Last 3 Months' },
    { value: '6', label: 'Last 6 Months' },
    { value: '12', label: 'Last 12 Months' },
];

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

const changePeriod = () => {
    router.get(route('finances.reports'), { period: selectedPeriod.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportReport = (format) => {
    showExportMenu.value = false;
    const params = new URLSearchParams({ format, period: selectedPeriod.value });
    window.location.href = route('finances.reports.export') + '?' + params.toString();
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Financial Reports</h2>
                <p class="text-sm text-gray-500">Analyze revenue, expenses, and collection performance</p>
            </div>
            <div class="flex items-center gap-3">
                <select
                    v-model="selectedPeriod"
                    @change="changePeriod"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                >
                    <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <div class="relative">
                    <button
                        @click="showExportMenu = !showExportMenu"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        Export
                    </button>
                    <div
                        v-if="showExportMenu"
                        class="absolute right-0 z-10 mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
                    >
                        <button
                            @click="exportReport('xlsx')"
                            class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50"
                        >
                            Export as Excel
                        </button>
                        <button
                            @click="exportReport('pdf')"
                            class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50"
                        >
                            Export as PDF
                        </button>
                        <button
                            @click="exportReport('csv')"
                            class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50"
                        >
                            Export as CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Total Invoiced"
                :value="summaryStats.totalInvoiced"
                format="currency"
                :icon="CurrencyDollarIcon"
                color="blue"
            />
            <MetricCard
                title="Total Collected"
                :value="summaryStats.totalCollected"
                format="currency"
                :icon="ChartBarIcon"
                color="emerald"
            />
            <MetricCard
                title="Total Expenses"
                :value="summaryStats.totalExpenses"
                format="currency"
                :icon="ChartPieIcon"
                color="red"
            />
            <MetricCard
                title="Avg Collection Rate"
                :value="summaryStats.avgCollectionRate"
                format="percent"
                :icon="ArrowTrendingUpIcon"
                color="indigo"
            />
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
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-blue-500 rounded" />
                            Invoiced
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-emerald-500 rounded" />
                            Collected
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-red-400 rounded" />
                            Expenses
                        </span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500 text-center py-8">No revenue data available</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Collection Rate Trend</h3>
                <div v-if="collectionRate?.length" class="space-y-3">
                    <div v-for="month in collectionRate" :key="month.month" class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ month.month }}</span>
                            <span class="font-medium" :class="month.rate >= 80 ? 'text-emerald-600' : month.rate >= 60 ? 'text-yellow-600' : 'text-red-600'">
                                {{ month.rate }}%
                            </span>
                        </div>
                        <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                :class="[
                                    'h-full rounded-full transition-all duration-300',
                                    month.rate >= 80 ? 'bg-emerald-500' : month.rate >= 60 ? 'bg-yellow-500' : 'bg-red-500'
                                ]"
                                :style="{ width: `${month.rate}%` }"
                            />
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500 text-center py-8">No collection data available</p>
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
                                    <span
                                        :class="[
                                            'inline-flex px-2 py-0.5 text-xs font-medium rounded-full',
                                            building.occupancy_rate >= 80 ? 'bg-emerald-100 text-emerald-700' :
                                            building.occupancy_rate >= 50 ? 'bg-yellow-100 text-yellow-700' :
                                            'bg-red-100 text-red-700'
                                        ]"
                                    >
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
                <p v-else class="text-sm text-gray-500 text-center py-8">No building data available</p>
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
                                <div
                                    :class="[bucket.color, 'h-full rounded-full transition-all duration-300']"
                                    :style="{ width: `${bucket.percentage}%` }"
                                />
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
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="none"
                                    :stroke="cat.color"
                                    stroke-width="20"
                                    :stroke-dasharray="`${cat.percentage * 2.51} 251`"
                                    :stroke-dashoffset="`${-expensesByCategory.categories.slice(0, index).reduce((sum, c) => sum + c.percentage, 0) * 2.51}`"
                                />
                            </template>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-xs text-gray-500">Total</p>
                            <p class="text-lg font-semibold text-gray-900">{{ formatMoney(expensesByCategory.total) }}</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="cat in expensesByCategory.categories"
                        :key="cat.category"
                        class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50"
                    >
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
            <p v-else class="text-sm text-gray-500 text-center py-8">No expense data available</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
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
                    <div
                        v-for="(consumer, index) in waterConsumption.top_consumers"
                        :key="index"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50"
                    >
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
                <p v-else class="text-sm text-gray-500 text-center py-8">No water consumption data</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <ArrowTrendingUpIcon class="w-5 h-5 text-emerald-500" />
                    <h3 class="text-sm font-semibold text-gray-900">Top Performing Units</h3>
                </div>
                <div v-if="topPerformingUnits?.length" class="space-y-2 max-h-64 overflow-y-auto">
                    <div
                        v-for="(unit, index) in topPerformingUnits"
                        :key="index"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50"
                    >
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
                <p v-else class="text-sm text-gray-500 text-center py-8">No performance data available</p>
            </div>
        </div>
    </div>
</template>
