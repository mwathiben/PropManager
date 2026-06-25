<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters, useTabFilters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import EyeIcon from '@heroicons/vue/24/outline/EyeIcon';
import BanknotesIcon from '@heroicons/vue/24/outline/BanknotesIcon';
import type { PaginatedResponse, Invoice, Building } from '@/types/finances';

interface StatusOption {
    value: string;
    label: string;
}

interface MonthOption {
    value: number;
    label: string;
}

interface InvoiceRow {
    id: number;
    invoice_number: string;
    tenant: string;
    unit: string;
    building: string;
    total_due: number;
    amount_paid: number;
    balance: number;
    status: string;
    due_date: string;
}

interface Props {
    invoices?: PaginatedResponse<Invoice>;
    filters?: Record<string, unknown>;
    statusOptions?: StatusOption[];
    buildings?: Building[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    statusOptions: () => [],
    buildings: () => [],
    loading: false,
});

const store = useFinancesStore();
const { formatDate } = useFormatters();
const { t } = useI18n();

const { localFilters, applyFilters, clearFilters, getExportParams } = useTabFilters({
    routeName: 'finances.invoices',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        status: { default: '' },
        buildingId: { urlKey: 'building_id', default: null },
        dateRange: { type: 'dateRange' },
    },
});

const columns = computed(() => [
    { key: 'invoice_number', label: t('finances_invoices_tab.columns.invoice_number'), sortable: true },
    { key: 'tenant', label: t('finances_invoices_tab.columns.tenant'), sortable: false },
    { key: 'unit', label: t('finances_invoices_tab.columns.unit'), sortable: false },
    { key: 'total_due', label: t('finances_invoices_tab.columns.amount'), align: 'right', sortable: true },
    { key: 'amount_paid', label: t('finances_invoices_tab.columns.paid'), align: 'right', sortable: true },
    { key: 'status', label: t('finances_invoices_tab.columns.status'), sortable: true },
    { key: 'due_date', label: t('finances_invoices_tab.columns.due_date'), sortable: true },
    { key: 'actions', label: '', align: 'right' },
]);

const tableData = computed((): InvoiceRow[] => {
    if (!props.invoices?.data) return [];
    return props.invoices.data.map(invoice => ({
        id: invoice.id,
        invoice_number: invoice.invoice_number,
        tenant: invoice.recipient?.name || invoice.lease?.tenant?.name || t('finances_invoices_tab.fallbacks.unknown_tenant'),
        unit: invoice.recipient?.context || invoice.lease?.unit?.unit_number || t('finances_invoices_tab.fallbacks.no_unit'),
        building: invoice.lease?.unit?.building?.name || '',
        total_due: invoice.total_due,
        amount_paid: invoice.amount_paid,
        balance: invoice.total_due - invoice.amount_paid,
        status: invoice.status,
        due_date: invoice.due_date,
    }));
});


const viewInvoice = (invoice: InvoiceRow): void => {
    store.openModal('invoiceDetail', { id: invoice.id });
};

const recordPayment = (invoice: InvoiceRow): void => {
    store.openModal('recordPayment', { invoiceId: invoice.id });
};

const showGenerateModal = ref(false);

const currentDate = new Date();
const generateForm = useForm({
    month: currentDate.getMonth() + 1,
    year: currentDate.getFullYear(),
});

const months = computed<MonthOption[]>(() => [
    { value: 1, label: t('finances_invoices_tab.months.january') },
    { value: 2, label: t('finances_invoices_tab.months.february') },
    { value: 3, label: t('finances_invoices_tab.months.march') },
    { value: 4, label: t('finances_invoices_tab.months.april') },
    { value: 5, label: t('finances_invoices_tab.months.may') },
    { value: 6, label: t('finances_invoices_tab.months.june') },
    { value: 7, label: t('finances_invoices_tab.months.july') },
    { value: 8, label: t('finances_invoices_tab.months.august') },
    { value: 9, label: t('finances_invoices_tab.months.september') },
    { value: 10, label: t('finances_invoices_tab.months.october') },
    { value: 11, label: t('finances_invoices_tab.months.november') },
    { value: 12, label: t('finances_invoices_tab.months.december') },
]);

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

const exportData = (format: string): void => {
    const params = getExportParams(format);
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
            :search-placeholder="t('finances_invoices_tab.search_placeholder')"
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
                        {{ t('finances_invoices_tab.actions.generate_invoices') }}
                    </button>
                </div>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="DocumentTextIcon"
            :empty-title="t('finances_invoices_tab.empty.title')"
            :empty-description="t('finances_invoices_tab.empty.description')"
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
                        :title="t('finances_invoices_tab.actions.view')"
                    >
                        <EyeIcon class="h-4 w-4" />
                    </button>
                    <button
                        v-if="row.status !== 'paid'"
                        @click.stop="recordPayment(row)"
                        class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded"
                        :title="t('finances_invoices_tab.actions.record_payment')"
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('finances_invoices_tab.generate_modal.title') }}</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ t('finances_invoices_tab.generate_modal.description') }}
                        </p>

                        <form @submit.prevent="submitGenerate">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="inv-gen-month" class="block text-sm font-medium text-gray-700">{{ t('finances_invoices_tab.generate_modal.month_label') }}</label>
                                    <select
                                        id="inv-gen-month"
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
                                    <label for="inv-gen-year" class="block text-sm font-medium text-gray-700">{{ t('finances_invoices_tab.generate_modal.year_label') }}</label>
                                    <select
                                        id="inv-gen-year"
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
                                    {{ t('finances_invoices_tab.actions.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="generateForm.processing"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {{ t('finances_invoices_tab.actions.generate_invoices') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
