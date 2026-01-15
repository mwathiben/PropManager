<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters, useTabFilters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    FilterBar,
    DataTable,
    PaymentMethodBadge,
    AmountDisplay,
    EmptyState,
    Pagination,
    ExportDropdown,
} from '@/Components/Finances';
import CreditCardIcon from '@heroicons/vue/24/outline/CreditCardIcon';
import EyeIcon from '@heroicons/vue/24/outline/EyeIcon';
import ArrowUturnLeftIcon from '@heroicons/vue/24/outline/ArrowUturnLeftIcon';
import DocumentArrowDownIcon from '@heroicons/vue/24/outline/DocumentArrowDownIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import ArrowUpTrayIcon from '@heroicons/vue/24/outline/ArrowUpTrayIcon';
import type { PaginatedResponse, Payment, Building } from '@/types/finances';

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Props {
    payments?: PaginatedResponse<Payment>;
    filters?: Record<string, unknown>;
    paymentMethodOptions?: PaymentMethodOption[];
    buildings?: Building[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    paymentMethodOptions: () => [],
    buildings: () => [],
    loading: false,
});

const { formatDate } = useFormatters();
const store = useFinancesStore();

const { localFilters, applyFilters, clearFilters, getExportParams } = useTabFilters({
    routeName: 'finances.payments',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        paymentMethod: { urlKey: 'method', default: '' },
        buildingId: { urlKey: 'building_id', default: null },
        dateRange: { type: 'dateRange' },
    },
});

const columns = [
    { key: 'reference', label: 'Reference', sortable: true },
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'invoice', label: 'Invoice', sortable: false },
    { key: 'amount', label: 'Amount', align: 'right', sortable: true },
    { key: 'payment_method', label: 'Method', sortable: true },
    { key: 'payment_date', label: 'Date', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

const tableData = computed(() => {
    if (!props.payments?.data) return [];
    return props.payments.data.map(payment => ({
        id: payment.id,
        reference: payment.reference || `PAY-${payment.id}`,
        tenant: payment.lease?.tenant?.name || 'Unknown',
        unit: payment.lease?.unit?.unit_number || 'N/A',
        building: payment.lease?.unit?.building?.name || '',
        invoice: payment.invoice?.invoice_number || '-',
        invoice_id: payment.invoice?.id,
        amount: payment.amount,
        payment_method: payment.payment_method,
        payment_date: payment.payment_date,
    }));
});


const viewPayment = (payment) => {
    if (payment.invoice_id) {
        router.visit(route('invoices.show', payment.invoice_id));
    }
};

const downloadReceipt = (payment) => {
    window.open(route('payments.downloadReceipt', payment.id), '_blank');
};

const initiateRefund = (payment) => {
    store.openModal('refund', { paymentId: payment.id });
};

const exportData = (format) => {
    const params = getExportParams(format);
    window.location.href = route('finances.payments.export') + '?' + params.toString();
};
</script>

<template>
    <div class="space-y-4">
        <FilterBar
            v-model="localFilters"
            :payment-method-options="paymentMethodOptions"
            :buildings="buildings"
            :show-status="false"
            :show-payment-method="true"
            search-placeholder="Search payments..."
            @filter="applyFilters"
            @clear="clearFilters"
        >
            <template #actions>
                <Link
                    :href="route('finances.payments.record')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="h-4 w-4" />
                    Record Payment
                </Link>
                <Link
                    :href="route('finances.payments.bulk-import')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <ArrowUpTrayIcon class="h-4 w-4" />
                    Bulk Import
                </Link>
                <ExportDropdown @export="exportData" />
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="CreditCardIcon"
            empty-title="No payments found"
            empty-description="Payments will appear here once recorded"
            @row-click="viewPayment"
        >
            <template #cell-tenant="{ row }">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ row.tenant }}</p>
                    <p class="text-xs text-gray-500">{{ row.unit }} - {{ row.building }}</p>
                </div>
            </template>

            <template #cell-amount="{ row }">
                <AmountDisplay :amount="row.amount" size="sm" />
            </template>

            <template #cell-payment_method="{ row }">
                <PaymentMethodBadge :method="row.payment_method" size="sm" />
            </template>

            <template #cell-payment_date="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.payment_date) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        @click.stop="downloadReceipt(row)"
                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded"
                        title="Download Receipt"
                    >
                        <DocumentArrowDownIcon class="h-4 w-4" />
                    </button>
                    <button
                        @click.stop="initiateRefund(row)"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                        title="Refund"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>

        <Pagination :links="payments?.links" />
    </div>
</template>
