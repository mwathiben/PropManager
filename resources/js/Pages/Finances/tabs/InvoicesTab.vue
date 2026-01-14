<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    FilterBar,
    DataTable,
    InvoiceStatusBadge,
    AmountDisplay,
    EmptyState,
    Pagination,
    ExportDropdown,
} from '@/Components/Finances';
import { DocumentTextIcon, EyeIcon, BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    invoices: Object,
    filters: Object,
    statusOptions: Array,
    buildings: Array,
});

const store = useFinancesStore();
const { formatDate } = useFormatters();

const localFilters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    buildingId: props.filters?.building_id || null,
    dateRange: {
        from: props.filters?.date_from || null,
        to: props.filters?.date_to || null,
    },
});

const columns = [
    { key: 'invoice_number', label: 'Invoice #', sortable: true },
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'unit', label: 'Unit', sortable: false },
    { key: 'total_due', label: 'Amount', align: 'right', sortable: true },
    { key: 'amount_paid', label: 'Paid', align: 'right', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'due_date', label: 'Due Date', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

const tableData = computed(() => {
    if (!props.invoices?.data) return [];
    return props.invoices.data.map(invoice => ({
        id: invoice.id,
        invoice_number: invoice.invoice_number,
        tenant: invoice.lease?.tenant?.name || 'Unknown',
        unit: invoice.lease?.unit?.unit_number || 'N/A',
        building: invoice.lease?.unit?.building?.name || '',
        total_due: invoice.total_due,
        amount_paid: invoice.amount_paid,
        balance: invoice.total_due - invoice.amount_paid,
        status: invoice.status,
        due_date: invoice.due_date,
    }));
});

const applyFilters = () => {
    router.get(route('finances.invoices'), {
        search: localFilters.value.search || undefined,
        status: localFilters.value.status || undefined,
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
        status: '',
        buildingId: null,
        dateRange: { from: null, to: null },
    };
    router.get(route('finances.invoices'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const viewInvoice = (invoice) => {
    store.openModal('invoiceDetail', { id: invoice.id });
};

const recordPayment = (invoice) => {
    store.openModal('recordPayment', { invoiceId: invoice.id });
};

const showGenerateModal = ref(false);

const currentDate = new Date();
const generateForm = useForm({
    month: currentDate.getMonth() + 1,
    year: currentDate.getFullYear(),
});

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
});

const submitGenerate = () => {
    generateForm.post(route('invoices.generate'), {
        onSuccess: () => {
            showGenerateModal.value = false;
        },
    });
};

const exportData = (format) => {
    const params = new URLSearchParams();
    params.append('format', format);
    if (localFilters.value.status) params.append('status', localFilters.value.status);
    if (localFilters.value.buildingId) params.append('building_id', localFilters.value.buildingId);
    if (localFilters.value.dateRange?.from) params.append('date_from', localFilters.value.dateRange.from);
    if (localFilters.value.dateRange?.to) params.append('date_to', localFilters.value.dateRange.to);

    window.location.href = route('finances.invoices.export') + '?' + params.toString();
};
</script>

<template>
    <div class="space-y-4">
        <FilterBar
            v-model="localFilters"
            :status-options="statusOptions"
            :buildings="buildings"
            :show-payment-method="false"
            search-placeholder="Search invoices..."
            @filter="applyFilters"
            @clear="clearFilters"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <ExportDropdown @export="exportData" />
                    <button
                        @click="showGenerateModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <DocumentTextIcon class="h-4 w-4" />
                        Generate Invoices
                    </button>
                </div>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="false"
            row-key="id"
            :empty-icon="DocumentTextIcon"
            empty-title="No invoices found"
            empty-description="Generate invoices to get started"
            @row-click="viewInvoice"
        >
            <template #cell-tenant="{ row }">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ row.tenant }}</p>
                    <p class="text-xs text-gray-500">{{ row.building }}</p>
                </div>
            </template>

            <template #cell-total_due="{ row }">
                <AmountDisplay :amount="row.total_due" size="sm" />
            </template>

            <template #cell-amount_paid="{ row }">
                <AmountDisplay :amount="row.amount_paid" size="sm" :colorize="row.amount_paid > 0" />
            </template>

            <template #cell-status="{ row }">
                <InvoiceStatusBadge :status="row.status" size="sm" />
            </template>

            <template #cell-due_date="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.due_date) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        @click.stop="viewInvoice(row)"
                        class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                        title="View"
                    >
                        <EyeIcon class="h-4 w-4" />
                    </button>
                    <button
                        v-if="row.status !== 'paid'"
                        @click.stop="recordPayment(row)"
                        class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded"
                        title="Record Payment"
                    >
                        <BanknotesIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>

        <Pagination :links="invoices?.links" />

        <Teleport to="body">
            <div v-if="showGenerateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-30" @click="showGenerateModal = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate Invoices</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Generate invoices for all active leases for the selected billing period.
                        </p>

                        <form @submit.prevent="submitGenerate">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Month</label>
                                    <select
                                        v-model="generateForm.month"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option v-for="m in months" :key="m.value" :value="m.value">
                                            {{ m.label }}
                                        </option>
                                    </select>
                                    <p v-if="generateForm.errors.month" class="mt-1 text-sm text-red-600">
                                        {{ generateForm.errors.month }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Year</label>
                                    <select
                                        v-model="generateForm.year"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option v-for="y in years" :key="y" :value="y">
                                            {{ y }}
                                        </option>
                                    </select>
                                    <p v-if="generateForm.errors.year" class="mt-1 text-sm text-red-600">
                                        {{ generateForm.errors.year }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="showGenerateModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="generateForm.processing"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    Generate Invoices
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
