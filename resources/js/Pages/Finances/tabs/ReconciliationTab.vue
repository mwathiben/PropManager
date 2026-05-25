<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
    ExclamationTriangleIcon,
    XCircleIcon,
    ShieldCheckIcon,
    ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline';
import type { Payment } from '@/types/finances';

interface PaystackReport {
    id: number;
    provider: string;
    status: string;
    period_from: string;
    period_to: string;
    local_count: number;
    remote_count: number;
    matched_count: number;
    discrepancy_count: number;
    error_message: string | null;
    alert_sent: boolean;
    reconciled_at: string;
}

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
    paystackReport?: PaystackReport | null;
}

const props = withDefaults(defineProps<Props>(), {
    unmatchedPayments: () => [],
    pendingReconciliation: 0,
    loading: false,
    paystackReport: null,
});

const { t } = useI18n();

const paystackStatusConfig = computed(() => {
    if (!props.paystackReport) return null;

    const report = props.paystackReport;
    if (report.status === 'failed') {
        return {
            border: 'border-red-200',
            bg: 'bg-red-50',
            icon: XCircleIcon,
            iconColor: 'text-red-600',
            titleColor: 'text-red-800',
            textColor: 'text-red-700',
            badgeBg: 'bg-red-100',
            badgeText: 'text-red-800',
            label: t('finances_reconciliation.paystack.status.failed'),
        };
    }

    if (report.discrepancy_count > 0) {
        return {
            border: 'border-yellow-200',
            bg: 'bg-yellow-50',
            icon: ExclamationTriangleIcon,
            iconColor: 'text-yellow-600',
            titleColor: 'text-yellow-800',
            textColor: 'text-yellow-700',
            badgeBg: 'bg-yellow-100',
            badgeText: 'text-yellow-800',
            label: t('finances_reconciliation.paystack.status.discrepancies', { count: report.discrepancy_count }),
        };
    }

    return {
        border: 'border-emerald-200',
        bg: 'bg-emerald-50',
        icon: ShieldCheckIcon,
        iconColor: 'text-emerald-600',
        titleColor: 'text-emerald-800',
        textColor: 'text-emerald-700',
        badgeBg: 'bg-emerald-100',
        badgeText: 'text-emerald-800',
        label: t('finances_reconciliation.paystack.status.clean'),
    };
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

const bankOptions = computed(() => [
    { value: 'equity', label: t('finances_reconciliation.banks.equity') },
    { value: 'kcb', label: t('finances_reconciliation.banks.kcb') },
    { value: 'coop', label: t('finances_reconciliation.banks.coop') },
    { value: 'stanbic', label: t('finances_reconciliation.banks.stanbic') },
    { value: 'absa', label: t('finances_reconciliation.banks.absa') },
    { value: 'ncba', label: t('finances_reconciliation.banks.ncba') },
    { value: 'dtb', label: t('finances_reconciliation.banks.dtb') },
    { value: 'i&m', label: t('finances_reconciliation.banks.i_and_m') },
    { value: 'family', label: t('finances_reconciliation.banks.family') },
    { value: 'other', label: t('finances_reconciliation.banks.other') },
]);

const columns = computed(() => [
    { key: 'reference', label: t('finances_reconciliation.table.reference'), sortable: false },
    { key: 'tenant', label: t('finances_reconciliation.table.tenant'), sortable: false },
    { key: 'amount', label: t('finances_reconciliation.table.amount'), align: 'right', sortable: true },
    { key: 'payment_method', label: t('finances_reconciliation.table.method'), sortable: false },
    { key: 'payment_date', label: t('finances_reconciliation.table.date'), sortable: true },
    { key: 'actions', label: '', align: 'right' },
]);

const tableData = computed(() => {
    if (!props.unmatchedPayments) return [];
    return props.unmatchedPayments.map(payment => ({
        id: payment.id,
        reference: payment.reference || `PAY-${payment.id}`,
        tenant: payment.tenant_name || t('finances_reconciliation.fallback.unknown_tenant'),
        tenant_name: payment.tenant_name || t('finances_reconciliation.fallback.unknown_tenant'),
        unit: payment.unit || t('finances_reconciliation.fallback.no_unit'),
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
                <h2 class="text-lg font-semibold text-gray-900">{{ t('finances_reconciliation.heading') }}</h2>
                <p class="text-sm text-gray-500">{{ t('finances_reconciliation.subtitle') }}</p>
            </div>
            <div class="flex gap-3">
                <Link
                    :href="route('gateway-reconciliation.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <ArrowsRightLeftIcon class="h-4 w-4" />
                    {{ $t('gateway_reconciliation.title') }}
                </Link>
                <button
                    v-if="pendingReconciliation > 0"
                    @click="processQueue"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlayIcon class="h-4 w-4" />
                    {{ t('finances_reconciliation.auto_match_all') }}
                </button>
                <button
                    @click="showImportPanel = !showImportPanel"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <ArrowUpTrayIcon class="h-4 w-4" />
                    {{ t('finances_reconciliation.import_statement') }}
                </button>
            </div>
        </div>

        <div v-if="paystackReport && paystackStatusConfig" :class="[paystackStatusConfig.bg, paystackStatusConfig.border]" class="border rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div class="flex items-start gap-3">
                    <component :is="paystackStatusConfig.icon" :class="paystackStatusConfig.iconColor" class="h-5 w-5 mt-0.5" />
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 :class="paystackStatusConfig.titleColor" class="text-sm font-semibold">{{ t('finances_reconciliation.paystack.heading') }}</h3>
                            <span :class="[paystackStatusConfig.badgeBg, paystackStatusConfig.badgeText]" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ paystackStatusConfig.label }}
                            </span>
                        </div>
                        <p :class="paystackStatusConfig.textColor" class="mt-1 text-sm">
                            <template v-if="paystackReport.status === 'failed'">
                                {{ t('finances_reconciliation.paystack.last_run_failed', { message: paystackReport.error_message }) }}
                            </template>
                            <template v-else>
                                {{ formatDate(paystackReport.period_from) }} &ndash; {{ formatDate(paystackReport.period_to) }}
                            </template>
                        </p>
                    </div>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatDate(paystackReport.reconciled_at) }}</span>
            </div>
            <div v-if="paystackReport.status === 'completed'" class="mt-3 ms-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="text-center">
                    <p class="text-lg font-semibold text-gray-900">{{ paystackReport.matched_count }}</p>
                    <p class="text-xs text-gray-500">{{ t('finances_reconciliation.paystack.matched') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-semibold text-gray-900">{{ paystackReport.local_count }}</p>
                    <p class="text-xs text-gray-500">{{ t('finances_reconciliation.paystack.local') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-semibold text-gray-900">{{ paystackReport.remote_count }}</p>
                    <p class="text-xs text-gray-500">{{ t('finances_reconciliation.paystack.remote') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-semibold" :class="paystackReport.discrepancy_count > 0 ? 'text-yellow-700' : 'text-gray-900'">{{ paystackReport.discrepancy_count }}</p>
                    <p class="text-xs text-gray-500">{{ t('finances_reconciliation.paystack.discrepancies') }}</p>
                </div>
            </div>
        </div>

        <div v-if="showImportPanel" class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_reconciliation.import.heading') }}</h3>

            <form @submit.prevent="submitImport" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_reconciliation.import.bank_label') }}</label>
                        <select
                            v-model="importForm.bank_code"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            required
                        >
                            <option value="">{{ t('finances_reconciliation.import.bank_placeholder') }}</option>
                            <option v-for="bank in bankOptions" :key="bank.value" :value="bank.value">
                                {{ bank.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_reconciliation.import.file_label') }}</label>
                        <input
                            id="bank-statement-file"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            @change="handleFileChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 file:me-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500">{{ t('finances_reconciliation.import.file_hint') }}</p>
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
                        {{ t('finances_reconciliation.import.column_mapping_toggle') }}
                    </button>

                    <div v-if="showColumnMapping" class="mt-3 p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-3">
                            {{ t('finances_reconciliation.import.column_mapping_hint') }}
                        </p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('finances_reconciliation.import.reference_column') }}</label>
                                <input
                                    v-model="importForm.column_mapping.reference"
                                    type="text"
                                    :placeholder="t('finances_reconciliation.placeholders.reference')"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('finances_reconciliation.import.amount_column') }}</label>
                                <input
                                    v-model="importForm.column_mapping.amount"
                                    type="text"
                                    :placeholder="t('finances_reconciliation.placeholders.amount')"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('finances_reconciliation.import.date_column') }}</label>
                                <input
                                    v-model="importForm.column_mapping.date"
                                    type="text"
                                    :placeholder="t('finances_reconciliation.placeholders.date')"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('finances_reconciliation.import.description_column') }}</label>
                                <input
                                    v-model="importForm.column_mapping.description"
                                    type="text"
                                    :placeholder="t('finances_reconciliation.placeholders.description')"
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
                        {{ t('finances_reconciliation.import.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="importForm.processing || !importForm.file || !importForm.bank_code"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <ArrowUpTrayIcon v-if="!importForm.processing" class="h-4 w-4" />
                        <ArrowPathIcon v-else class="h-4 w-4 animate-spin" />
                        {{ importForm.processing ? t('finances_reconciliation.import.importing') : t('finances_reconciliation.import.submit') }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">{{ t('finances_reconciliation.stats.pending') }}</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ stats.pending || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">{{ t('finances_reconciliation.stats.unmatched') }}</p>
                <p class="text-2xl font-semibold text-orange-600">{{ stats.unmatched || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">{{ t('finances_reconciliation.stats.matched') }}</p>
                <p class="text-2xl font-semibold text-emerald-600">{{ stats.matched || 0 }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">{{ t('finances_reconciliation.stats.unmatched_amount') }}</p>
                <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(stats.total_unmatched_amount || 0) }}</p>
            </div>
        </div>

        <div v-if="pendingReconciliation > 0" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <ArrowPathIcon class="h-5 w-5 text-yellow-600 mt-0.5" />
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">{{ t('finances_reconciliation.pending.heading') }}</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        {{ t('finances_reconciliation.pending.body', { count: pendingReconciliation }) }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else-if="!showImportPanel" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <CheckCircleIcon class="h-5 w-5 text-emerald-600 mt-0.5" />
                <div>
                    <h3 class="text-sm font-medium text-emerald-800">{{ t('finances_reconciliation.reconciled.heading') }}</h3>
                    <p class="mt-1 text-sm text-emerald-700">
                        {{ t('finances_reconciliation.reconciled.body') }}
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
            :empty-title="t('finances_reconciliation.table.empty_title')"
            :empty-description="t('finances_reconciliation.table.empty_description')"
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
                    {{ t('finances_reconciliation.table.match') }}
                </button>
            </template>
        </DataTable>
    </div>
</template>
