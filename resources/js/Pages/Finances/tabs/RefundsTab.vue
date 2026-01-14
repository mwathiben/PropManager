<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters, useStatusColors, useTabFilters } from '@/composables';
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
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    statusOptions: () => [],
});

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

const columns = [
    { key: 'payment_ref', label: 'Payment Ref', sortable: false },
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'amount', label: 'Amount', align: 'right', sortable: true },
    { key: 'reason', label: 'Reason', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Requested', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

const tableData = computed(() => {
    if (!props.refunds?.data) return [];
    return props.refunds.data.map(refund => ({
        id: refund.id,
        payment_id: refund.payment?.id,
        payment_ref: refund.payment?.reference || `PAY-${refund.payment?.id}`,
        tenant: refund.payment?.lease?.tenant?.name || 'Unknown',
        unit: refund.payment?.lease?.unit?.unit_number || 'N/A',
        amount: refund.amount,
        reason: refund.reason,
        status: refund.status,
        created_at: refund.created_at,
    }));
});

const statusLabels = {
    pending: 'Pending',
    approved: 'Approved',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
};


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
            search-placeholder="Search refunds..."
            @filter="applyFilters"
            @clear="clearFilters"
        >
            <template #actions>
                <Link
                    :href="route('finances.refunds.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="h-4 w-4" />
                    Process Refund
                </Link>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="false"
            row-key="id"
            :empty-icon="ArrowUturnLeftIcon"
            empty-title="No refunds found"
            empty-description="Refund requests will appear here"
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
                    {{ statusLabels[row.status] || row.status }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.created_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <button
                    @click.stop="viewRefund(row)"
                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                    title="View"
                >
                    <EyeIcon class="h-4 w-4" />
                </button>
            </template>
        </DataTable>

        <Pagination :links="refunds?.links" />
    </div>
</template>
