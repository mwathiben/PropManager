<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
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
    PlusIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: Object,
    recentPayments: Array,
    recentInvoices: Array,
    collectionStatus: String,
    monthlyTrend: Array,
});

const { formatMoney, formatDate, formatRelativeTime } = useFormatters();
const store = useFinancesStore();

const statusColors = {
    excellent: 'text-emerald-600 bg-emerald-100',
    good: 'text-blue-600 bg-blue-100',
    needs_attention: 'text-yellow-600 bg-yellow-100',
    critical: 'text-red-600 bg-red-100',
};

const statusLabels = {
    excellent: 'Excellent',
    good: 'Good',
    needs_attention: 'Needs Attention',
    critical: 'Critical',
};

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
                title="This Month"
                :value="stats?.this_month"
                format="currency"
                :subtitle="stats?.month_trend ? `${stats.month_trend > 0 ? '+' : ''}${stats.month_trend}% vs last month` : null"
                :trend="stats?.month_trend ? { direction: stats.month_trend >= 0 ? 'up' : 'down', value: `${Math.abs(stats.month_trend)}%` } : null"
                :icon="BanknotesIcon"
                color="emerald"
            />

            <MetricCard
                title="Pending Amount"
                :value="stats?.pending_amount"
                format="currency"
                :subtitle="`${stats?.overdue_count || 0} overdue invoices`"
                :icon="ClockIcon"
                color="yellow"
            />

            <MetricCard
                title="Collection Rate"
                :value="stats?.collection_rate"
                format="percent"
                subtitle="This month"
                :icon="ChartBarIcon"
                color="blue"
            />

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Collection Status</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span
                                :class="[
                                    'px-2.5 py-1 rounded-full text-sm font-medium',
                                    statusColors[collectionStatus] || statusColors.needs_attention
                                ]"
                            >
                                {{ statusLabels[collectionStatus] || 'Unknown' }}
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
                    <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Link
                        :href="route('invoices.generate')"
                        method="post"
                        as="button"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50 transition-colors text-left"
                    >
                        <div class="p-2 bg-emerald-100 rounded-lg">
                            <DocumentTextIcon class="h-4 w-4 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Generate Invoices</p>
                            <p class="text-xs text-gray-500">Create monthly invoices</p>
                        </div>
                    </Link>

                    <Link
                        :href="route('finances.invoices')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors"
                    >
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <PlusIcon class="h-4 w-4 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Record Payment</p>
                            <p class="text-xs text-gray-500">Manual cash/bank</p>
                        </div>
                    </Link>

                    <button
                        @click="store.openModal('sendReminders')"
                        class="flex items-center gap-2 p-3 bg-white rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 transition-colors text-left"
                    >
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <BellIcon class="h-4 w-4 text-orange-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Send Reminders</p>
                            <p class="text-xs text-gray-500">Payment reminders</p>
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
                            <p class="text-sm font-medium text-gray-900">View Arrears</p>
                            <p class="text-xs text-gray-500">Overdue payments</p>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Monthly Trend</h3>
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
                        <span class="text-xs font-medium text-gray-900 w-20 text-right">
                            {{ formatMoney(month.collected, { maximumFractionDigits: 0 }) }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500 text-center py-4">No trend data available</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Payments</h3>
                    <Link
                        :href="route('finances.payments')"
                        class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                    >
                        View all
                    </Link>
                </div>
                <div v-if="recentPayments?.length" class="divide-y divide-gray-200">
                    <div
                        v-for="payment in recentPayments"
                        :key="payment.id"
                        class="px-5 py-3 flex items-center justify-between hover:bg-gray-50"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <BanknotesIcon class="h-4 w-4 text-emerald-600" />
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ payment.tenant_name }}</p>
                                <p class="text-xs text-gray-500">{{ payment.unit }} - {{ payment.building }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <AmountDisplay :amount="payment.amount" size="sm" />
                            <div class="mt-0.5">
                                <PaymentMethodBadge :method="payment.payment_method" size="sm" :show-icon="false" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">No recent payments</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Invoices</h3>
                    <Link
                        :href="route('finances.invoices')"
                        class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                    >
                        View all
                    </Link>
                </div>
                <div v-if="recentInvoices?.length" class="divide-y divide-gray-200">
                    <div
                        v-for="invoice in recentInvoices"
                        :key="invoice.id"
                        class="px-5 py-3 flex items-center justify-between hover:bg-gray-50"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <DocumentTextIcon class="h-4 w-4 text-blue-600" />
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                <p class="text-xs text-gray-500">{{ invoice.tenant_name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <AmountDisplay :amount="invoice.total_due" size="sm" />
                            <div class="mt-0.5">
                                <InvoiceStatusBadge :status="invoice.status" size="sm" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">No recent invoices</p>
                </div>
            </div>
        </div>
    </div>
</template>
