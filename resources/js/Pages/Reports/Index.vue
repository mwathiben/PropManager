<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { ReportsIndexPageProps } from '@/types/operations';
import {
    CalendarIcon,
    DocumentArrowDownIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ReportsIndexPageProps>();

const selectedPeriod = ref(props.analytics.period);
const showExportDropdown = ref(null);
const showDateRangeModal = ref(false);
const dateRange = ref({
    from: '',
    to: ''
});

const changePeriod = (period) => {
    selectedPeriod.value = period;
    dateRange.value = { from: '', to: '' };
    router.get(route('reports.index'), { period }, {
        preserveState: true,
        preserveScroll: true
    });
};

const exportReport = (reportType, format) => {
    showExportDropdown.value = null;

    const params = new URLSearchParams({
        report_type: reportType,
        format: format
    });

    if (dateRange.value.from && dateRange.value.to) {
        params.append('date_from', dateRange.value.from);
        params.append('date_to', dateRange.value.to);
    } else {
        params.append('period', selectedPeriod.value);
    }

    const url = format === 'pdf'
        ? route('reports.export.pdf')
        : route('reports.export.excel');

    window.location.href = `${url}?${params.toString()}`;
};

const toggleExportDropdown = (reportType) => {
    showExportDropdown.value = showExportDropdown.value === reportType ? null : reportType;
};

const applyDateRange = () => {
    showDateRangeModal.value = false;
    if (dateRange.value.from && dateRange.value.to) {
        selectedPeriod.value = 'custom';
    }
};

const clearDateRange = () => {
    dateRange.value = { from: '', to: '' };
    selectedPeriod.value = 'month';
    changePeriod('month');
};

const { t } = useI18n();

const formatDateRangeLabel = computed(() => {
    if (dateRange.value.from && dateRange.value.to) {
        return t('reports.index.date_range_label', { from: dateRange.value.from, to: dateRange.value.to });
    }
    return null;
});

// Use composables
const { formatMoney: formatCurrency, formatPercent: formatPercentage } = useFormatters();
</script>

<template>
    <Head :title="t('reports.index.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ t('reports.index.heading') }}</h1>
                                <p class="mt-1 text-sm text-gray-600">{{ t('reports.index.subtitle') }}</p>
                            </div>

                            <!-- Period Selector - scrollable on mobile -->
                            <div class="flex gap-2 overflow-x-auto pb-2 sm:pb-0 items-center">
                                <button
                                    v-for="(label, key) in availablePeriods"
                                    :key="key"
                                    @click="changePeriod(key)"
                                    :class="[
                                        /* i18n-ignore */ 'px-4 py-2 rounded-md text-sm font-medium transition-colors whitespace-nowrap shrink-0',
                                        selectedPeriod === key && !formatDateRangeLabel
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ label }}
                                </button>
                                <button
                                    @click="showDateRangeModal = true"
                                    :class="[
                                        /* i18n-ignore */ 'px-4 py-2 rounded-md text-sm font-medium transition-colors whitespace-nowrap shrink-0 flex items-center gap-2',
                                        formatDateRangeLabel
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    <CalendarIcon class="w-4 h-4" />
                                    {{ formatDateRangeLabel || t('reports.index.custom') }}
                                </button>
                                <button
                                    v-if="formatDateRangeLabel"
                                    @click="clearDateRange"
                                    class="px-2 py-2 text-gray-500 hover:text-gray-700"
                                    :title="t('reports.index.clear_date_range')"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total Revenue -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">{{ t('reports.index.total_revenue') }}</p>
                                    <p class="mt-2 text-3xl font-bold text-gray-900">
                                        {{ formatCurrency(analytics.financial.total_revenue) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-green-600">
                                {{ t('reports.index.collection', { percentage: formatPercentage(analytics.financial.collection_percentage) }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Occupancy Rate -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">{{ t('reports.index.occupancy_rate') }}</p>
                                    <p class="mt-2 text-3xl font-bold text-gray-900">
                                        {{ formatPercentage(analytics.occupancy.occupancy_rate) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ t('reports.index.units_occupied', { occupied: analytics.occupancy.occupied, total: analytics.occupancy.total_units }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Outstanding Arrears -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">{{ t('reports.index.outstanding_arrears') }}</p>
                                    <p class="mt-2 text-3xl font-bold text-gray-900">
                                        {{ formatCurrency(analytics.arrears.total_arrears) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-red-600">
                                {{ t('reports.index.overdue_invoices', { count: analytics.arrears.count }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Water Consumption -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">{{ t('reports.index.water_consumption') }}</p>
                                    <p class="mt-2 text-3xl font-bold text-gray-900">
                                        {{ analytics.water_consumption.total_consumption }} <span class="text-lg">{{ t('reports.index.units') }}</span>
                                    </p>
                                </div>
                                <div class="p-3 bg-cyan-100 rounded-full">
                                    <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ t('reports.index.cost', { cost: formatCurrency(analytics.water_consumption.total_cost) }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Financial Report -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-gray-900">{{ t('reports.index.financial_summary') }}</h2>
                            <div class="relative">
                                <button
                                    @click="toggleExportDropdown('financial')"
                                    class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors flex items-center gap-1"
                                >
                                    <DocumentArrowDownIcon class="w-4 h-4" />
                                    {{ t('reports.index.export') }}
                                    <ChevronDownIcon class="w-4 h-4" />
                                </button>
                                <div
                                    v-if="showExportDropdown === 'financial'"
                                    class="absolute end-0 mt-2 w-40 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-10"
                                >
                                    <button @click="exportReport('financial', 'pdf')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                                        <span class="text-red-500 me-2">PDF</span> {{ t('reports.index.export_pdf_document') }}
                                    </button>
                                    <button @click="exportReport('financial', 'xlsx')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                                        <span class="text-green-500 me-2">XLSX</span> {{ t('reports.index.export_xlsx_excel') }}
                                    </button>
                                    <button @click="exportReport('financial', 'csv')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                                        <span class="text-blue-500 me-2">CSV</span> {{ t('reports.index.export_csv_spreadsheet') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b">
                                        <span class="text-sm text-gray-600">{{ t('reports.index.expected_rent') }}</span>
                                        <span class="text-sm font-semibold">{{ formatCurrency(analytics.financial.expected_rent) }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b">
                                        <span class="text-sm text-gray-600">{{ t('reports.index.collected_rent') }}</span>
                                        <span class="text-sm font-semibold text-green-600">{{ formatCurrency(analytics.financial.collected_rent) }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b">
                                        <span class="text-sm text-gray-600">{{ t('reports.index.water_charges') }}</span>
                                        <span class="text-sm font-semibold">{{ formatCurrency(analytics.financial.water_charges) }}</span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-sm text-gray-600">{{ t('reports.index.outstanding') }}</span>
                                        <span class="text-sm font-semibold text-red-600">{{ formatCurrency(analytics.financial.outstanding) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ t('reports.index.revenue_breakdown') }}</h3>
                                <div class="space-y-2">
                                    <div v-for="(amount, category) in analytics.financial.revenue_breakdown" :key="category" class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <div class="flex justify-between mb-1">
                                                <span class="text-sm capitalize">{{ category }}</span>
                                                <span class="text-sm font-semibold">{{ formatCurrency(amount) }}</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div
                                                    :class="[
                                                        'h-2 rounded-full',
                                                        category === 'rent' ? 'bg-blue-600' : category === 'water' ? 'bg-cyan-600' : 'bg-green-600'
                                                    ]"
                                                    :style="`width: ${(amount / analytics.financial.total_revenue) * 100}%`"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Occupancy & Arrears -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Occupancy Breakdown -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-900">{{ t('reports.index.occupancy_breakdown') }}</h2>
                                <button
                                    @click="exportReport('occupancy', 'csv')"
                                    class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition-colors"
                                >
                                    {{ t('reports.index.export') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(count, status) in analytics.occupancy.status_breakdown" :key="status" class="flex items-center justify-between py-2 border-b">
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'w-3 h-3 rounded-full',
                                                status === 'occupied' ? 'bg-green-500' :
                                                status === 'vacant' ? 'bg-gray-400' :
                                                status === 'maintenance' ? 'bg-orange-500' : 'bg-red-500'
                                            ]"
                                        ></div>
                                        <span class="text-sm font-medium capitalize">{{ status }}</span>
                                    </div>
                                    <span class="text-sm font-bold">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Arrears Aging -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-900">{{ t('reports.index.arrears_aging') }}</h2>
                                <button
                                    @click="exportReport('arrears', 'pdf')"
                                    class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition-colors"
                                >
                                    {{ t('reports.index.export') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(amount, period) in analytics.arrears.aging" :key="period" class="flex items-center justify-between py-2 border-b">
                                    <span class="text-sm font-medium">{{ t('reports.index.aging_days', { period }) }}</span>
                                    <span class="text-sm font-bold text-red-600">{{ formatCurrency(amount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performers & Water Consumption -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Performing Units -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">{{ t('reports.index.top_performing_units') }}</h2>

                            <div v-if="analytics.top_performing_units.length === 0" class="text-center py-8 text-gray-500">
                                <p>{{ t('reports.index.no_performance_data') }}</p>
                            </div>

                            <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                                <div v-for="(unit, index) in analytics.top_performing_units" :key="index" class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-bold text-gray-900">{{ unit.unit }}</div>
                                            <div class="text-sm text-gray-600">{{ unit.tenant }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-lg font-bold text-green-600">{{ formatPercentage(unit.collection_rate) }}</div>
                                            <div class="text-xs text-gray-500">{{ t('reports.index.on_time_payments', { onTime: unit.on_time_payments, total: unit.total_invoices }) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Water Top Consumers -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-900">{{ t('reports.index.top_water_consumers') }}</h2>
                                <button
                                    @click="exportReport('water', 'csv')"
                                    class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition-colors"
                                >
                                    {{ t('reports.index.export') }}
                                </button>
                            </div>

                            <div v-if="analytics.water_consumption.top_consumers.length === 0" class="text-center py-8 text-gray-500">
                                <p>{{ t('reports.index.no_water_data') }}</p>
                            </div>

                            <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                                <div v-for="(consumer, index) in analytics.water_consumption.top_consumers" :key="index" class="flex justify-between items-center py-2 border-b">
                                    <div>
                                        <div class="font-bold text-gray-900">{{ consumer.unit }}</div>
                                        <div class="text-xs text-gray-500">{{ t('reports.index.consumption_units', { consumption: consumer.consumption }) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="font-semibold text-cyan-600">{{ formatCurrency(consumer.cost) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Date Range Modal -->
        <Modal :show="showDateRangeModal" @close="showDateRangeModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('reports.index.custom_date_range') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="date-range-from" class="block text-sm font-medium text-gray-700 mb-1">{{ t('reports.index.from') }}</label>
                        <input
                            id="date-range-from"
                            type="date"
                            v-model="dateRange.from"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="date-range-to" class="block text-sm font-medium text-gray-700 mb-1">{{ t('reports.index.to') }}</label>
                        <input
                            id="date-range-to"
                            type="date"
                            v-model="dateRange.to"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    {{ t('reports.index.date_range_hint') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showDateRangeModal = false"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                    >
                        {{ t('reports.index.cancel') }}
                    </button>
                    <button
                        @click="applyDateRange"
                        :disabled="!dateRange.from || !dateRange.to"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ t('reports.index.apply') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
