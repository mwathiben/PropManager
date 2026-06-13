<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import {
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ChartBarIcon,
    BanknotesIcon,
    TrophyIcon,
} from '@heroicons/vue/24/outline';

interface RevenueData {
    total: number;
    previous_total: number;
    trend: number;
    trend_direction: 'up' | 'down';
}

interface CollectionRates {
    current_month: number;
    previous_month: number;
    trend: 'up' | 'down';
}

interface PaymentMethodBreakdownItem {
    method: string;
    label: string;
    count: number;
    total: number;
}

interface MonthlyTrendItem {
    month: string;
    short_month: string;
    amount: number;
}

interface TopPayingUnit {
    unit: string;
    building: string;
    total_paid: number;
    payment_count: number;
}

interface PlatformFees {
    this_month: number;
    total_fees: number;
    total_gross: number;
    total_net: number;
}

interface Props {
    period?: string;
    revenueData?: RevenueData;
    collectionRates?: CollectionRates;
    paymentMethodBreakdown?: PaymentMethodBreakdownItem[];
    monthlyTrend?: MonthlyTrendItem[];
    topPayingUnits?: TopPayingUnit[];
    platformFees?: PlatformFees;
}

const props = withDefaults(defineProps<Props>(), {
    period: 'month',
    paymentMethodBreakdown: () => [],
    monthlyTrend: () => [],
    topPayingUnits: () => [],
});

const { formatMoney, formatPercent } = useFormatters();
const { t } = useI18n();

const periods = [
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'quarter', label: 'This Quarter' },
    { value: 'year', label: 'This Year' },
];

const changePeriod = (newPeriod: string) => {
    router.get(route('payments-hub.analytics'), { period: newPeriod }, { preserveState: true, preserveScroll: true, replace: true });
};

const maxMonthlyAmount = computed(() => {
    if (!props.monthlyTrend?.length) return 1;
    return Math.max(...props.monthlyTrend.map(m => m.amount), 1);
});

const barHeight = (amount: number): number => {
    return Math.max((amount / maxMonthlyAmount.value) * 100, 2);
};

const methodColors: Record<string, string> = {
    cash: 'bg-gray-400',
    bank_transfer: 'bg-blue-500',
    mobile_money: 'bg-green-500',
    paystack: 'bg-purple-500',
};

const methodColor = (method: string): string => methodColors[method] ?? 'bg-indigo-500';

const breakdownTotal = computed(() => {
    return props.paymentMethodBreakdown.reduce((sum, item) => sum + item.total, 0) || 1;
});

const periodBtnBase = 'px-4 py-2 text-sm font-medium rounded-lg border transition-colors';
const periodBtnInactive = 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
const trendBadgeBase = 'flex items-center gap-1 px-2.5 py-1 rounded-full text-sm font-medium';
const rankCircleBase = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0';
</script>

<template>
    <div class="space-y-6">
        <!-- Period selector -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="p in periods"
                :key="p.value"
                type="button"
                :class="[
                    periodBtnBase,
                    period === p.value
                        ? 'bg-indigo-600 border-indigo-600 text-white'
                        : periodBtnInactive,
                ]"
                @click="changePeriod(p.value)"
            >
                {{ p.label }}
            </button>
        </div>

        <!-- Revenue + collection rates row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Revenue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.revenue') }}</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ formatMoney(revenueData?.total) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            vs {{ formatMoney(revenueData?.previous_total) }} {{ t('payments_hub.analytics.previous') }}
                        </p>
                    </div>
                    <div
                        v-if="revenueData"
                        :class="[
                            trendBadgeBase,
                            revenueData.trend_direction === 'up'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        ]"
                    >
                        <ArrowTrendingUpIcon v-if="revenueData.trend_direction === 'up'" class="w-4 h-4" />
                        <ArrowTrendingDownIcon v-else class="w-4 h-4" />
                        {{ Math.abs(revenueData.trend) }}%
                    </div>
                </div>
            </div>

            <!-- Collection rate -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.collection_rate') }}</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ formatPercent(collectionRates?.current_month, 1) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            vs {{ formatPercent(collectionRates?.previous_month, 1) }} {{ t('payments_hub.analytics.last_month') }}
                        </p>
                    </div>
                    <div
                        v-if="collectionRates"
                        :class="[
                            trendBadgeBase,
                            collectionRates.trend === 'up'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        ]"
                    >
                        <ArrowTrendingUpIcon v-if="collectionRates.trend === 'up'" class="w-4 h-4" />
                        <ArrowTrendingDownIcon v-else class="w-4 h-4" />
                        {{ collectionRates.trend === 'up' ? t('payments_hub.analytics.trend_up') : t('payments_hub.analytics.trend_down') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly trend chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-5">{{ t('payments_hub.analytics.trend_chart_title') }}</h2>

            <div v-if="monthlyTrend && monthlyTrend.length > 0">
                <div class="flex items-end gap-1 h-40">
                    <div
                        v-for="item in monthlyTrend"
                        :key="item.month"
                        class="flex flex-col items-center gap-1 flex-1 min-w-0"
                    >
                        <div
                            class="w-full bg-indigo-500 dark:bg-indigo-400 rounded-t-sm transition-all duration-300 hover:bg-indigo-600 dark:hover:bg-indigo-300"
                            :style="{ height: barHeight(item.amount) + '%' }"
                            :title="`${item.month}: ${formatMoney(item.amount)}`"
                        />
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate w-full text-center">
                            {{ item.short_month }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else>
                <EmptyState
                    :icon="ChartBarIcon"
                    :title="t('payments_hub.analytics.no_trend_title')"
                    :description="t('payments_hub.analytics.no_trend_desc')"
                    size="sm"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Payment method breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ t('payments_hub.analytics.method_breakdown_title') }}</h2>

                <div v-if="paymentMethodBreakdown.length > 0" class="space-y-3">
                    <div
                        v-for="item in paymentMethodBreakdown"
                        :key="item.method"
                        class="flex items-center gap-3"
                    >
                        <div class="w-28 shrink-0">
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ item.label }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.payments_count', { count: item.count }) }}</p>
                        </div>
                        <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                            <div
                                :class="['h-2 rounded-full transition-all', methodColor(item.method)]"
                                :style="{ width: ((item.total / breakdownTotal) * 100).toFixed(1) + '%' }"
                            />
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-24 text-right shrink-0">
                            {{ formatMoney(item.total) }}
                        </span>
                    </div>
                </div>

                <div v-else>
                    <EmptyState
                        :icon="ChartBarIcon"
                        :title="t('payments_hub.analytics.no_breakdown_title')"
                        :description="t('payments_hub.analytics.no_breakdown_desc')"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Top paying units -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ t('payments_hub.analytics.top_units_title') }}</h2>

                <div v-if="topPayingUnits.length > 0" class="space-y-3">
                    <div
                        v-for="(unit, index) in topPayingUnits"
                        :key="`${unit.unit}-${unit.building}`"
                        class="flex items-center gap-3"
                    >
                        <div
                            :class="[
                                rankCircleBase,
                                index === 0
                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                            ]"
                        >
                            {{ index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ unit.unit }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ unit.building }}</p>
                        </div>
                        <div class="text-end shrink-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatMoney(unit.total_paid) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.payments_count', { count: unit.payment_count }) }}</p>
                        </div>
                    </div>
                </div>

                <div v-else>
                    <EmptyState
                        :icon="TrophyIcon"
                        :title="t('payments_hub.analytics.no_units_title')"
                        :description="t('payments_hub.analytics.no_units_desc')"
                        size="sm"
                    />
                </div>
            </div>
        </div>

        <!-- Platform fees summary -->
        <div
            v-if="platformFees"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5"
        >
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ t('payments_hub.analytics.platform_fees_title') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.fees_this_month') }}</p>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatMoney(platformFees.this_month) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.fees_total') }}</p>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatMoney(platformFees.total_fees) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.fees_gross') }}</p>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatMoney(platformFees.total_gross) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.analytics.fees_net') }}</p>
                    <p class="mt-1 text-lg font-bold text-green-700 dark:text-green-400">{{ formatMoney(platformFees.total_net) }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
