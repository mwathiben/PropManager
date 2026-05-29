<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters, useStatusColors, useTabFilters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { FilterBar, DataTable, AmountDisplay, EmptyState, Pagination } from '@/Components/Finances';
import ArrowUturnLeftIcon from '@heroicons/vue/24/outline/ArrowUturnLeftIcon';
import EyeIcon from '@heroicons/vue/24/outline/EyeIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import type { PaginatedResponse, Refund, RefundStatus } from '@/types/finances';

interface StatusOption {
    value: string;
    label: string;
}

interface Props {
    refunds?: PaginatedResponse<Refund>;
    filters?: Record<string, unknown>;
    statusOptions?: StatusOption[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    statusOptions: () => [],
    loading: false,
});

const { t } = useI18n();
const { formatDate } = useFormatters();
const { refundStatusColor } = useStatusColors();

const { localFilters, applyFilters, clearFilters } = useTabFilters({
    routeName: 'finances.refunds',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        status: { default: '' },
        dateRange: { type: 'dateRange' },
    },
});

const columns = computed(() => [
    { key: 'payment_ref', label: t('finances_refunds_tab.columns.payment_ref'), sortable: false },
    { key: 'tenant', label: t('finances_refunds_tab.columns.tenant'), sortable: false },
    { key: 'amount', label: t('finances_refunds_tab.columns.amount'), align: 'right', sortable: true },
    { key: 'reason', label: t('finances_refunds_tab.columns.reason'), sortable: false },
    { key: 'status', label: t('finances_refunds_tab.columns.status'), sortable: true },
    { key: 'created_at', label: t('finances_refunds_tab.columns.requested'), sortable: true },
    { key: 'actions', label: '', align: 'right' },
]);

const tableData = computed(() => {
    if (!props.refunds?.data) return [];
    return props.refunds.data.map(refund => ({
        id: refund.id,
        payment_id: refund.payment?.id,
        payment_ref: refund.payment?.reference || `PAY-${refund.payment?.id}`,
        tenant: refund.payment?.lease?.tenant?.name || t('finances_refunds_tab.fallbacks.unknown_tenant'),
        unit: refund.payment?.lease?.unit?.unit_number || t('finances_refunds_tab.fallbacks.no_unit'),
        amount: refund.amount,
        reason: refund.reason,
        status: refund.status,
        created_at: refund.created_at,
    }));
});

const viewRefund = (refund) => {
    if (refund.payment_id) {
        router.get(route('finances.payments.detail', refund.payment_id));
    }
};
</script>

<template>
    <div class="space-y-4">
        <FilterBar
            v-model="localFilters"
            :status-options="statusOptions"
            :show-building="false"
            :show-payment-method="false"
            :search-placeholder="t('finances_refunds_tab.search_placeholder')"
            @filter="applyFilters"
            @clear="clearFilters"
        >
            <template #actions>
                <Link
                    :href="route('finances.refunds.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="h-4 w-4" />
                    {{ t('finances_refunds_tab.actions.process_refund') }}
                </Link>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="ArrowUturnLeftIcon"
            :empty-title="t('finances_refunds_tab.empty.title')"
            :empty-description="t('finances_refunds_tab.empty.description')"
            @row-click="viewRefund"
        >
            <template #cell-tenant="{ row }">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ row.tenant }}</p>
                    <p class="text-xs text-gray-500">{{ row.unit }}</p>
                </div>
            </template>

            <template #cell-amount="{ row }">
                <AmountDisplay :amount="row.amount" size="sm" />
            </template>

            <template #cell-reason="{ row }">
                <span class="text-sm text-gray-600 truncate max-w-[200px] block">{{ row.reason || '-' }}</span>
            </template>

            <template #cell-status="{ row }">
                <span :class="['inline-flex px-2 py-1 text-xs font-medium rounded-full', refundStatusColor(row.status)]">
                    {{ t(`finances_refunds_tab.status.${row.status}`, row.status ?? '') }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.created_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <button
                    @click.stop="viewRefund(row)"
                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                    :title="t('finances_refunds_tab.actions.view')"
                >
                    <EyeIcon class="h-4 w-4" />
                </button>
            </template>
        </DataTable>

        <Pagination :links="refunds?.links" />
    </div>
</template>
