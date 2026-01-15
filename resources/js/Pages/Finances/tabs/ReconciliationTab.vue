<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import { DataTable, PaymentMethodBadge, AmountDisplay } from '@/Components/Finances';
import {
    ArrowPathIcon,
    CheckCircleIcon,
    LinkIcon,
    ArrowUpTrayIcon,
    DocumentTextIcon,
    PlayIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';
import type { Payment } from '@/types/finances';

interface UnmatchedPayment extends Payment {
    tenant?: { name: string };
}

interface ReconciliationStats {
    total_unmatched: number;
    total_matched: number;
    pending_amount: number;
}

interface Props {
    unmatchedPayments?: UnmatchedPayment[];
    pendingReconciliation?: number;
    stats?: ReconciliationStats;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    unmatchedPayments: () => [],
    pendingReconciliation: 0,
    loading: false,
});

const store = useFinancesStore();
const { formatDate, formatCurrency } = useFormatters();

const showImportPanel = ref(false);
const showColumnMapping = ref(false);

const importForm = useForm({
    file: null,
    bank_code: '',
    column_mapping: {
        reference: '',
        amount: '',
        date: '',
        description: '',
    },
});

const bankOptions = [
    { value: 'equity', label: 'Equity Bank' },
    { value: 'kcb', label: 'KCB Bank' },
    { value: 'coop', label: 'Co-operative Bank' },
    { value: 'stanbic', label: 'Stanbic Bank' },
    { value: 'absa', label: 'Absa Bank' },
    { value: 'ncba', label: 'NCBA Bank' },
    { value: 'dtb', label: 'DTB Bank' },
    { value: 'i&m', label: 'I&M Bank' },
    { value: 'family', label: 'Family Bank' },
    { value: 'other', label: 'Other Bank' },
];

const columns = [
    { key: 'reference', label: 'Reference', sortable: false },
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'amount', label: 'Amount', align: 'right', sortable: true },
    { key: 'payment_method', label: 'Method', sortable: false },
    { key: 'payment_date', label: 'Date', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

const tableData = computed(() => {
    if (!props.unmatchedPayments) return [];
    return props.unmatchedPayments.map(payment => ({
        id: payment.id,
        reference: payment.reference || `PAY-${payment.id}`,
        tenant: payment.tenant_name || 'Unknown',
        tenant_name: payment.tenant_name || 'Unknown',
        unit: payment.unit || 'N/A',
        amount: payment.amount,
        payment_method: payment.payment_method,
        payment_date: payment.payment_date,
    }));
});

const matchPayment = (payment) => {
    store.openModal('matchPayment', { paymentId: payment.id, payment });
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        importForm.file = file;
    }
};

const submitImport = () => {
    importForm.post(route('finances.reconciliation.import'), {
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            showImportPanel.value = false;
            const fileInput = document.getElementById('bank-statement-file');
            if (fileInput) fileInput.value = '';
        },
    });
};

const processQueue = () => {
    useForm({}).post(route('finances.reconciliation.process-queue'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Bank Reconciliation</h2>
                <p class="text-sm text-gray-500">Import bank statements and match transactions to invoices</p>
            </div>
            <div class="flex gap-3">
                <button
                    v-if="pendingReconciliation > 0"
                    @click="processQueue"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlayIcon class="h-4 w-4" />
                    Auto-Match All
                </button>
                <button
                    @click="showImportPanel = !showImportPanel"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <ArrowUpTrayIcon class="h-4 w-4" />
                    Import Statement
                </button>
            </div>
        </div>

        <div v-if="showImportPanel" class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Import Bank Statement</h3>

            <form @submit.prevent="submitImport" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                        <select
                            v-model="importForm.bank_code"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            required
                        >
                            <option value="">Select bank...</option>
                            <option v-for="bank in bankOptions" :key="bank.value" :value="bank.value">
                                {{ bank.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CSV/Excel File</label>
                        <input
                            id="bank-statement-file"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            @change="handleFileChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500">Max 5MB. Supported: CSV, XLSX, XLS</p>
                    </div>
                </div>

                <div>
                    <button
                        type="button"
                        @click="showColumnMapping = !showColumnMapping"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
                    >
                        <ChevronDownIcon
                            class="h-4 w-4 transition-transform"
                            :class="{ 'rotate-180': showColumnMapping }"
                        />
                        Column Mapping (Optional)
                    </button>

                    <div v-if="showColumnMapping" class="mt-3 p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-3">
                            Specify column names if they differ from defaults (reference, amount, date, description)
                        </p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Reference Column</label>
                                <input
                                    v-model="importForm.column_mapping.reference"
                                    type="text"
                                    placeholder="reference"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Amount Column</label>
                                <input
                                    v-model="importForm.column_mapping.amount"
                                    type="text"
                                    placeholder="amount"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Date Column</label>
                                <input
                                    v-model="importForm.column_mapping.date"
                                    type="text"
                                    placeholder="date"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Description Column</label>
                                <input
                                    v-model="importForm.column_mapping.description"
                                    type="text"
                                    placeholder="description"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="importForm.errors.file" class="text-sm text-red-600">
                    {{ importForm.errors.file }}
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showImportPanel = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="importForm.processing || !importForm.file || !importForm.bank_code"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <ArrowUpTrayIcon v-if="!importForm.processing" class="h-4 w-4" />
                        <ArrowPathIcon v-else class="h-4 w-4 animate-spin" />
                        {{ importForm.processing ? 'Importing...' : 'Import' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Pending</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ stats.pending || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Unmatched</p>
                <p class="text-2xl font-semibold text-orange-600">{{ stats.unmatched || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Matched</p>
                <p class="text-2xl font-semibold text-emerald-600">{{ stats.matched || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Unmatched Amount</p>
                <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(stats.total_unmatched_amount || 0) }}</p>
            </div>
        </div>

        <div v-if="pendingReconciliation > 0" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <ArrowPathIcon class="h-5 w-5 text-yellow-600 mt-0.5" />
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">Pending Reconciliation</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        You have {{ pendingReconciliation }} payment(s) that need to be matched to invoices.
                    </p>
                </div>
            </div>
        </div>

        <div v-else-if="!showImportPanel" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <CheckCircleIcon class="h-5 w-5 text-emerald-600 mt-0.5" />
                <div>
                    <h3 class="text-sm font-medium text-emerald-800">All Reconciled</h3>
                    <p class="mt-1 text-sm text-emerald-700">
                        All payments have been matched to invoices. Import a bank statement to reconcile new transactions.
                    </p>
                </div>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="DocumentTextIcon"
            empty-title="No unmatched payments"
            empty-description="Import a bank statement to start reconciling transactions"
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

            <template #cell-payment_method="{ row }">
                <PaymentMethodBadge :method="row.payment_method" size="sm" />
            </template>

            <template #cell-payment_date="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.payment_date) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <button
                    @click.stop="matchPayment(row)"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                >
                    <LinkIcon class="h-3.5 w-3.5" />
                    Match
                </button>
            </template>
        </DataTable>
    </div>
</template>
