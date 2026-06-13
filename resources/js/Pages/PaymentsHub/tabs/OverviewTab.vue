<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import {
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ClockIcon,
    CheckBadgeIcon,
    ExclamationTriangleIcon,
    CreditCardIcon,
} from '@heroicons/vue/24/outline';

interface OverviewStats {
    total_collected: number;
    this_month: number;
    pending_amount: number;
    payment_count: number;
    collection_rate: number;
}

interface RecentPayment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    tenant_name: string;
    unit: string;
    building: string;
    reference: string;
}

interface PayoutAccountSummary {
    has_accounts: boolean;
    has_verified: boolean;
    primary_account: { id: number; bank_name: string; masked_account_number: string; status: string } | null;
    account_count: number;
}

interface QuickAction {
    id: string;
    label: string;
    description: string;
    route: string;
    icon: string;
}

interface Props {
    stats?: OverviewStats;
    recentPayments?: RecentPayment[];
    pendingInvoices?: number;
    collectionStatus?: 'excellent' | 'good' | 'needs_attention' | 'critical';
    payoutAccountSummary?: PayoutAccountSummary;
    quickActions?: QuickAction[];
}

const props = withDefaults(defineProps<Props>(), {
    recentPayments: () => [],
    pendingInvoices: 0,
    quickActions: () => [],
});

const { formatMoney, formatDate } = useFormatters();
const { t } = useI18n();

const collectionStatusConfig = computed(() => {
    const map: Record<string, { label: string; classes: string }> = {
        excellent: { label: 'Excellent', classes: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' },
        good: { label: 'Good', classes: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
        needs_attention: { label: 'Needs Attention', classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
        critical: { label: 'Critical', classes: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' },
    };
    return map[props.collectionStatus ?? 'good'] ?? map.good;
});

const methodLabel = (method: string): string => {
    const labels: Record<string, string> = {
        cash: 'Cash',
        bank_transfer: 'Bank Transfer',
        mobile_money: 'Mobile Money',
        paystack: 'Paystack',
    };
    return labels[method] ?? method;
};

const methodBadgeClass = (method: string): string => {
    const map: Record<string, string> = {
        cash: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        bank_transfer: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        mobile_money: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        paystack: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    };
    return map[method] ?? 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <div class="space-y-6">
        <!-- Stats grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total collected -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('payments_hub.overview.total_collected') }}</p>
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                        <BanknotesIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(stats?.total_collected) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.overview.all_time') }}</p>
            </div>

            <!-- This month -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('payments_hub.overview.this_month') }}</p>
                    <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                        <ArrowTrendingUpIcon class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(stats?.this_month) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('payments_hub.overview.payment_count', { count: stats?.payment_count ?? 0 }) }}
                </p>
            </div>

            <!-- Pending -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('payments_hub.overview.pending') }}</p>
                    <div class="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                        <ClockIcon class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatMoney(stats?.pending_amount) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('payments_hub.overview.outstanding_invoices', { count: pendingInvoices }) }}
                </p>
            </div>

            <!-- Collection rate -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('payments_hub.overview.collection_rate') }}</p>
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <CheckBadgeIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ stats?.collection_rate ?? 0 }}%
                </p>
                <span
                    v-if="collectionStatus"
                    :class="['mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-medium', collectionStatusConfig.classes]"
                >
                    {{ collectionStatusConfig.label }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent payments -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.overview.recent_payments') }}</h2>
                    <Link
                        :href="route('finances.payments')"
                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        {{ t('payments_hub.overview.view_all') }}
                    </Link>
                </div>

                <div v-if="recentPayments.length > 0">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li
                            v-for="payment in recentPayments"
                            :key="payment.id"
                            class="px-5 py-4 flex items-center justify-between gap-4"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ payment.tenant_name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ payment.unit }} &middot; {{ payment.building }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatMoney(payment.amount) }}
                                </p>
                                <div class="flex items-center justify-end gap-2 mt-0.5">
                                    <span :class="['inline-block px-1.5 py-0.5 rounded text-xs font-medium', methodBadgeClass(payment.payment_method)]">
                                        {{ methodLabel(payment.payment_method) }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ formatDate(payment.payment_date) }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else>
                    <EmptyState
                        :icon="BanknotesIcon"
                        :title="t('payments_hub.overview.no_payments_title')"
                        :description="t('payments_hub.overview.no_payments_desc')"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Right column: quick actions + payout summary -->
            <div class="space-y-4">
                <!-- Quick actions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ t('payments_hub.overview.quick_actions') }}</h2>
                    <div class="space-y-2">
                        <Link
                            v-for="action in quickActions"
                            :key="action.id"
                            :href="route(action.route)"
                            class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                        >
                            <div class="p-1.5 bg-indigo-50 dark:bg-indigo-900/30 rounded-md group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 transition-colors">
                                <CreditCardIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ action.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</p>
                            </div>
                        </Link>

                        <div v-if="!quickActions.length" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            {{ t('payments_hub.overview.no_quick_actions') }}
                        </div>
                    </div>
                </div>

                <!-- Payout account summary -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ t('payments_hub.overview.payout_account') }}</h2>

                    <div v-if="payoutAccountSummary?.primary_account">
                        <div class="flex items-center gap-2 mb-2">
                            <CheckBadgeIcon class="w-5 h-5 text-green-500" />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ payoutAccountSummary.primary_account.bank_name }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ payoutAccountSummary.primary_account.masked_account_number }}
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ t('payments_hub.overview.accounts_total', { count: payoutAccountSummary.account_count }) }}
                        </p>
                    </div>

                    <div v-else>
                        <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 mb-2">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                            <span class="text-sm font-medium">{{ t('payments_hub.overview.no_payout_account') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            {{ t('payments_hub.overview.no_payout_account_desc') }}
                        </p>
                        <Link
                            :href="route('payments-hub.collection')"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            {{ t('payments_hub.overview.setup_now') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
