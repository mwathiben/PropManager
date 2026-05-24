<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useFinancesStore } from '@/stores/finances';
import {
    MetricCard,
    InvoiceStatusBadge,
    PaymentMethodBadge,
    AmountDisplay,
} from '@/Components/Finances';
import {
    BanknotesIcon,
    ClockIcon,
    ChartBarIcon,
    ExclamationTriangleIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    DocumentTextIcon,
    DocumentDuplicateIcon,
    PlusIcon,
    BellIcon,
    ReceiptRefundIcon,
} from '@heroicons/vue/24/outline';
import type { FinanceStats, TrendDataPoint, Invoice, Payment } from '@/types/finances';

interface OverviewStats extends FinanceStats {
    this_month: number;
    last_month: number;
}

type CollectionStatus = 'excellent' | 'good' | 'needs_attention' | 'critical';

interface Props {
    stats?: OverviewStats;
    recentPayments?: Payment[];
    recentInvoices?: Invoice[];
    collectionStatus?: CollectionStatus;
    monthlyTrend?: TrendDataPoint[];
}

const props = withDefaults(defineProps<Props>(), {
    recentPayments: () => [],
    recentInvoices: () => [],
    monthlyTrend: () => [],
});

const { formatMoney, formatDate, formatRelativeTime } = useFormatters();
const { t } = useI18n();
const store = useFinancesStore();

const statusColors = {
    excellent: 'text-emerald-600 bg-emerald-100',
    good: 'text-blue-600 bg-blue-100',
    needs_attention: 'text-yellow-600 bg-yellow-100',
    critical: 'text-red-600 bg-red-100',
};

const statusLabels = computed(() => ({
    excellent: t('finances_overview.status.excellent'),
    good: t('finances_overview.status.good'),
    needs_attention: t('finances_overview.status.needs_attention'),
    critical: t('finances_overview.status.critical'),
}));

const monthTrendIcon = computed(() => {
    return props.stats?.month_trend >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;
});

const monthTrendClass = computed(() => {
    return props.stats?.month_trend >= 0 ? 'text-emerald-600' : 'text-red-600';
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                :title="t('finances_overview.metrics.this_month')"
                :value="stats?.this_month"
                format="currency"
                :subtitle="stats?.month_trend ? t('finances_overview.metrics.this_month_subtitle', { trend: `${stats.month_trend > 0 ? '+' : ''}${stats.month_trend}` }) : null"
                :trend="stats?.month_trend ? { direction: stats.month_trend >= 0 ? 'up' : 'down', value: `${Math.abs(stats.month_trend)}%` } : null"
                :icon="BanknotesIcon"
                color="emerald"
            />

            <MetricCard
                :title="t('finances_overview.metrics.pending_amount')"
                :value="stats?.pending_amount"
                format="currency"
                :subtitle="t('finances_overview.metrics.overdue_invoices', { count: stats?.overdue_count || 0 })"
                :icon="ClockIcon"
                color="yellow"
            />

            <MetricCard
                :title="t('finances_overview.metrics.collection_rate')"
                :value="stats?.collection_rate"
                format="percent"
                :subtitle="t('finances_overview.metrics.collection_rate_subtitle')"
                :icon="ChartBarIcon"
                color="blue"
            />

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ t('finances_overview.metrics.collection_status') }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span
                                :class="[
                                    'px-2.5 py-1 rounded-full text-sm font-medium', /* i18n-ignore */
                                    statusColors[collectionStatus] || statusColors.needs_attention
                                ]"
                            >
                                {{ statusLabels[collectionStatus] || t('finances_overview.status.unknown') }}
                            </span>
                        </div>
                    </div>
                    <div :class="['p-2.5 rounded-lg', collectionStatus === 'excellent' || collectionStatus === 'good' ? 'bg-emerald-100' : 'bg-yellow-100']">
                        <ChartBarIcon :class="['h-5 w-5', collectionStatus === 'excellent' || collectionStatus === 'good' ? 'text-emerald-600' : 'text-yellow-600']" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_overview.quick_actions.title') }}</h3>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    <Link
                        :href="route('invoices.generate')"
                        method="post"
                        as="button"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50 transition-colors text-start"
                    >
                        <div class="p-2 bg-emerald-100 rounded-lg">
                            <DocumentTextIcon class="h-4 w-4 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.generate_invoices') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.generate_invoices_subtitle') }}</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('finances.payments.record')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors"
                    >
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <PlusIcon class="h-4 w-4 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.record_payment') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.record_payment_subtitle') }}</p>
                        </div>
                    </Link>

                    <button
                        @click="store.openModal('sendReminders')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 transition-colors text-start"
                    >
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <BellIcon class="h-4 w-4 text-orange-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.send_reminders') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.send_reminders_subtitle') }}</p>
                        </div>
                    </button>

                    <Link
                        :href="route('finances.arrears')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 transition-colors"
                    >
                        <div class="p-2 bg-red-100 rounded-lg">
                            <ExclamationTriangleIcon class="h-4 w-4 text-red-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.view_arrears') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.view_arrears_subtitle') }}</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('finances.templates')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-colors"
                    >
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <DocumentDuplicateIcon class="h-4 w-4 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.manage_templates') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.manage_templates_subtitle') }}</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('credit-notes.index')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-violet-300 hover:bg-violet-50 transition-colors"
                    >
                        <div class="p-2 bg-violet-100 rounded-lg">
                            <ReceiptRefundIcon class="h-4 w-4 text-violet-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ t('finances_overview.quick_actions.credit_notes') }}</p>
                            <p class="text-xs text-gray-500">{{ t('finances_overview.quick_actions.credit_notes_subtitle') }}</p>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_overview.trend.title') }}</h3>
                </div>
                <div v-if="monthlyTrend?.length" class="space-y-2">
                    <div
                        v-for="month in monthlyTrend"
                        :key="`${month.month}-${month.year}`"
                        class="flex items-center gap-3"
                    >
                        <span class="text-xs text-gray-500 w-12">{{ month.month }}</span>
                        <div class="flex-1 h-6 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                :style="{ width: `${Math.min(100, (month.collected / (month.invoiced || 1)) * 100)}%` }"
                            />
                        </div>
                        <span class="text-xs font-medium text-gray-900 w-20 text-end">
                            {{ formatMoney(month.collected, { maximumFractionDigits: 0 }) }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500 text-center py-4">{{ t('finances_overview.trend.empty') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_overview.recent_payments.title') }}</h3>
                    <Link
                        :href="route('finances.payments')"
                        class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                    >
                        {{ t('finances_overview.recent_payments.view_all') }}
                    </Link>
                </div>
                <div v-if="recentPayments?.length" class="divide-y divide-gray-200">
                    <div
                        v-for="payment in recentPayments"
                        :key="payment.id"
                        class="px-5 py-3 flex items-center justify-between hover:bg-gray-50"
                    >
                        <div class="flex items-center gap-3">
                            <div class="shrink-0">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <BanknotesIcon class="h-4 w-4 text-emerald-600" />
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ payment.tenant_name }}</p>
                                <p class="text-xs text-gray-500">{{ payment.unit }} - {{ payment.building }}</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <AmountDisplay :amount="payment.amount" size="sm" />
                            <div class="mt-0.5">
                                <PaymentMethodBadge :method="payment.payment_method" size="sm" :show-icon="false" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">{{ t('finances_overview.recent_payments.empty') }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_overview.recent_invoices.title') }}</h3>
                    <Link
                        :href="route('finances.invoices')"
                        class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                    >
                        {{ t('finances_overview.recent_invoices.view_all') }}
                    </Link>
                </div>
                <div v-if="recentInvoices?.length" class="divide-y divide-gray-200">
                    <div
                        v-for="invoice in recentInvoices"
                        :key="invoice.id"
                        class="px-5 py-3 flex items-center justify-between hover:bg-gray-50"
                    >
                        <div class="flex items-center gap-3">
                            <div class="shrink-0">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <DocumentTextIcon class="h-4 w-4 text-blue-600" />
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                <p class="text-xs text-gray-500">{{ invoice.tenant_name }}</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <AmountDisplay :amount="invoice.total_due" size="sm" />
                            <div class="mt-0.5">
                                <InvoiceStatusBadge :status="invoice.status" size="sm" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">{{ t('finances_overview.recent_invoices.empty') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
