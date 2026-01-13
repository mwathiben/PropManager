<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters, useStatusColors } from '@/composables';
import { FilterBar, DataTable, AmountDisplay, EmptyState } from '@/Components/Finances';
import { ArrowUturnLeftIcon, EyeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    refunds: Object,
    filters: Object,
    statusOptions: Array,
});

const { formatDate } = useFormatters();
const { refundStatusColor } = useStatusColors();

const localFilters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    dateRange: {
        from: props.filters?.date_from || null,
        to: props.filters?.date_to || null,
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

const applyFilters = () => {
    router.get(route('finances.refunds'), {
        search: localFilters.value.search || undefined,
        status: localFilters.value.status || undefined,
        date_from: localFilters.value.dateRange?.from || undefined,
        date_to: localFilters.value.dateRange?.to || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    localFilters.value = {
        search: '',
        status: '',
        dateRange: { from: null, to: null },
    };
    router.get(route('finances.refunds'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
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
        />

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

        <div v-if="refunds?.links?.length > 3" class="flex justify-center">
            <nav class="flex items-center gap-1">
                <template v-for="link in refunds.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.visit(link.url)"
                        :class="[
                            'px-3 py-1.5 text-sm rounded-lg transition-colors',
                            link.active
                                ? 'bg-emerald-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        ]"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="px-3 py-1.5 text-sm text-gray-400"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
