<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    FilterBar,
    DataTable,
    PaymentMethodBadge,
    AmountDisplay,
    EmptyState,
} from '@/Components/Finances';
import { CreditCardIcon, EyeIcon, ArrowUturnLeftIcon, DocumentArrowDownIcon, ArrowDownTrayIcon, PlusIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    payments: Object,
    filters: Object,
    paymentMethodOptions: Array,
    buildings: Array,
});

const { formatDate } = useFormatters();
const store = useFinancesStore();

const localFilters = ref({
    search: props.filters?.search || '',
    paymentMethod: props.filters?.method || '',
    buildingId: props.filters?.building_id || null,
    dateRange: {
        from: props.filters?.date_from || null,
        to: props.filters?.date_to || null,
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

const applyFilters = () => {
    router.get(route('finances.payments'), {
        search: localFilters.value.search || undefined,
        method: localFilters.value.paymentMethod || undefined,
        building_id: localFilters.value.buildingId || undefined,
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
        paymentMethod: '',
        buildingId: null,
        dateRange: { from: null, to: null },
    };
    router.get(route('finances.payments'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

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

const showExportMenu = ref(false);

const exportData = (format) => {
    const params = new URLSearchParams();
    params.append('format', format);
    if (localFilters.value.paymentMethod) params.append('method', localFilters.value.paymentMethod);
    if (localFilters.value.buildingId) params.append('building_id', localFilters.value.buildingId);
    if (localFilters.value.dateRange?.from) params.append('date_from', localFilters.value.dateRange.from);
    if (localFilters.value.dateRange?.to) params.append('date_to', localFilters.value.dateRange.to);

    window.location.href = route('finances.payments.export') + '?' + params.toString();
    showExportMenu.value = false;
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
                <div class="relative">
                    <button
                        @click="showExportMenu = !showExportMenu"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Export
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="showExportMenu"
                            class="absolute right-0 z-10 mt-1 w-36 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
                        >
                            <button
                                @click="exportData('xlsx')"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Excel (.xlsx)
                            </button>
                            <button
                                @click="exportData('pdf')"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                PDF
                            </button>
                        </div>
                    </Transition>
                </div>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="false"
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

        <div v-if="payments?.links?.length > 3" class="flex justify-center">
            <nav class="flex items-center gap-1">
                <template v-for="link in payments.links" :key="link.label">
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
