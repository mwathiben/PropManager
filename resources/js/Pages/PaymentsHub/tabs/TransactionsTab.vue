<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    XMarkIcon,
    CreditCardIcon,
} from '@heroicons/vue/24/outline';

interface PlatformFee {
    fee_amount: number;
    net_amount: number;
}

interface TransactionPayment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    reference: string;
    invoice: { invoice_number: string; total_due: number } | null;
    lease: {
        tenant: { name: string; email: string };
        unit: { unit_number: string; building: { name: string } };
    } | null;
    platform_fee: PlatformFee | null;
}

interface PaginatedPayments {
    data: TransactionPayment[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Building {
    id: number;
    name: string;
}

interface TransactionFilters {
    search?: string | null;
    method?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    building_id?: number | null;
}

interface Props {
    payments?: PaginatedPayments;
    paymentMethods?: PaymentMethodOption[];
    buildings?: Building[];
    filters?: TransactionFilters;
}

const props = withDefaults(defineProps<Props>(), {
    paymentMethods: () => [],
    buildings: () => [],
    filters: () => ({}),
});

const { formatMoney, formatDate } = useFormatters();
const { t } = useI18n();

const localFilters = reactive<TransactionFilters>({
    search: props.filters?.search ?? '',
    method: props.filters?.method ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    building_id: props.filters?.building_id ?? null,
});

const showFilters = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        route('payments-hub.transactions'),
        {
            search: localFilters.search || undefined,
            method: localFilters.method || undefined,
            date_from: localFilters.date_from || undefined,
            date_to: localFilters.date_to || undefined,
            building_id: localFilters.building_id || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const clearFilters = () => {
    localFilters.search = '';
    localFilters.method = '';
    localFilters.date_from = '';
    localFilters.date_to = '';
    localFilters.building_id = null;
    applyFilters();
};

const hasActiveFilters = () => {
    return !!(
        localFilters.search ||
        localFilters.method ||
        localFilters.date_from ||
        localFilters.date_to ||
        localFilters.building_id
    );
};

watch(() => localFilters.search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
});

const methodBadgeClass = (method: string): string => {
    const map: Record<string, string> = {
        cash: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        bank_transfer: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        mobile_money: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        paystack: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    };
    return map[method] ?? 'bg-gray-100 text-gray-700';
};

const methodLabel = (method: string): string => {
    return props.paymentMethods.find(m => m.value === method)?.label ?? method;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-48">
                <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                <input
                    v-model="localFilters.search"
                    type="text"
                    :placeholder="t('payments_hub.transactions.search_placeholder')"
                    :aria-label="t('payments_hub.transactions.search_placeholder')"
                    class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>

            <!-- Toggle filters -->
            <button
                type="button"
                :class="[
                    'inline-flex items-center gap-2 px-3 py-2 text-sm rounded-lg border transition-colors',
                    showFilters
                        ? 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                ]"
                @click="showFilters = !showFilters"
            >
                <FunnelIcon class="w-4 h-4" />
                {{ t('payments_hub.transactions.filters') }}
            </button>

            <!-- Clear filters -->
            <button
                v-if="hasActiveFilters()"
                type="button"
                class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                @click="clearFilters"
            >
                <XMarkIcon class="w-4 h-4" />
                {{ t('payments_hub.transactions.clear') }}
            </button>
        </div>

        <!-- Expanded filters -->
        <div
            v-if="showFilters"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700"
        >
            <div>
                <label for="filter_method" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ t('payments_hub.transactions.filter_method') }}</label>
                <select
                    id="filter_method"
                    v-model="localFilters.method"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    @change="applyFilters"
                >
                    <option value="">{{ t('payments_hub.transactions.filter_all_methods') }}</option>
                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>

            <div>
                <label for="filter_building" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ t('payments_hub.transactions.filter_building') }}</label>
                <select
                    id="filter_building"
                    v-model="localFilters.building_id"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    @change="applyFilters"
                >
                    <option :value="null">{{ t('payments_hub.transactions.filter_all_buildings') }}</option>
                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>

            <div>
                <label for="filter_date_from" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ t('payments_hub.transactions.filter_from') }}</label>
                <input
                    id="filter_date_from"
                    v-model="localFilters.date_from"
                    type="date"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    @change="applyFilters"
                />
            </div>

            <div>
                <label for="filter_date_to" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ t('payments_hub.transactions.filter_to') }}</label>
                <input
                    id="filter_date_to"
                    v-model="localFilters.date_to"
                    type="date"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    @change="applyFilters"
                />
            </div>
        </div>

        <!-- Results summary -->
        <div v-if="payments?.total !== undefined" class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
            <span>
                <template v-if="payments.total > 0">
                    {{ t('payments_hub.transactions.showing', { from: payments.from, to: payments.to, total: payments.total }) }}
                </template>
                <template v-else>{{ t('payments_hub.transactions.no_results') }}</template>
            </span>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div v-if="payments && payments.data.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.reference') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.tenant_unit') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.invoice') }}</th>
                            <th class="px-5 py-3 text-end text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.amount') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.method') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.date') }}</th>
                            <th class="px-5 py-3 text-end text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ t('payments_hub.transactions.columns.net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr
                            v-for="payment in payments.data"
                            :key="payment.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                        >
                            <td class="px-5 py-4">
                                <span class="text-sm font-mono text-gray-700 dark:text-gray-300">
                                    {{ payment.reference || `PAY-${payment.id}` }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ payment.lease?.tenant?.name ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ payment.lease?.unit?.unit_number ?? '' }}
                                    <span v-if="payment.lease?.unit?.building?.name">
                                        &middot; {{ payment.lease.unit.building.name }}
                                    </span>
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ payment.invoice?.invoice_number ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatMoney(payment.amount) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span :class="['inline-block px-2 py-0.5 rounded text-xs font-medium', methodBadgeClass(payment.payment_method)]">
                                    {{ methodLabel(payment.payment_method) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDate(payment.payment_date) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span v-if="payment.platform_fee" class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatMoney(payment.platform_fee.net_amount) }}
                                </span>
                                <span v-else class="text-sm text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else>
                <EmptyState
                    :icon="CreditCardIcon"
                    :title="t('payments_hub.transactions.no_transactions_title')"
                    :description="t('payments_hub.transactions.no_transactions_desc')"
                    size="md"
                />
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="payments && payments.last_page > 1" class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ t('payments_hub.transactions.page_of', { current: payments.current_page, total: payments.last_page }) }}
            </p>
            <div class="flex items-center gap-1">
                <template v-for="link in payments.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :class="[
                            'px-3 py-1.5 text-sm rounded-lg border transition-colors',
                            link.active
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                        ]"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="px-3 py-1.5 text-sm text-gray-400 dark:text-gray-600"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
