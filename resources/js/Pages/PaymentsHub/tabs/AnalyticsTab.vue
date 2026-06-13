<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import {
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ChartBarIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';

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

interface PlatformFees {
    this_month: number;
    total_fees: number;
    total_gross: number;
    total_net: number;
}

interface Props {
    period?: string;
    collectionRates?: CollectionRates;
    paymentMethodBreakdown?: PaymentMethodBreakdownItem[];
    platformFees?: PlatformFees;
}

const props = withDefaults(defineProps<Props>(), {
    period: 'month',
    paymentMethodBreakdown: () => [],
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

        <!-- Finance Hub reports deep-link -->
        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-200 dark:border-indigo-700 p-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">{{ t('payments_hub.analytics.full_reports_cta') }}</p>
            </div>
            <Link
                :href="route('finances.reports')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shrink-0"
            >
                {{ t('payments_hub.analytics.full_reports_btn') }}
                <ArrowTopRightOnSquareIcon class="w-4 h-4" />
            </Link>
        </div>
    </div>
</template>
