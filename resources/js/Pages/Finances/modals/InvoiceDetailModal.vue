<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import SlideOutPanel from '@/Components/SlideOutPanel.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters, usePayments, useSWR } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    InvoiceStatusBadge,
    PaymentMethodBadge,
    AmountDisplay,
} from '@/Components/Finances';
import {
    XMarkIcon,
    DocumentTextIcon,
    PrinterIcon,
    EnvelopeIcon,
    BanknotesIcon,
    ArrowDownTrayIcon,
    CalendarIcon,
    HomeIcon,
    UserIcon,
    NoSymbolIcon,
    EyeIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';
import type { Invoice, Payment } from '@/types/finances';

interface InvoiceDetail extends Invoice {
    tenant_name?: string;
    unit_number?: string;
    billing_period_start?: string;
    billing_period_end?: string;
    payments?: Payment[];
}

interface Props {
    show?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    show: false,
});

const emit = defineEmits<{
    close: [];
    recordPayment: [];
    sendReminder: [id: number];
}>();

const { t } = useI18n();
const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();
const { downloadInvoice, sendInvoice, sendReminder, voidInvoice, previewInvoice, reissueInvoice, isProcessing } = usePayments();

const showVoidConfirm = ref(false);
const voidReason = ref('');

const modalData = computed(() => store.modals.invoiceDetail);

const swrKey = computed(() => {
    if (!modalData.value.show || !modalData.value.id) return '';
    return `invoice-detail-${modalData.value.id}`;
});

const { data: invoiceData, error: swrError, isLoading: loading, refresh: refreshInvoice } = useSWR(
    () => swrKey.value,
    async (key) => {
        const id = key.replace('invoice-detail-', '');
        const response = await fetch(route('finances.invoices.detail', id));
        if (!response.ok) throw new Error(t('finances_invoice_detail.fetch_error'));
        return response.json();
    },
    { immediate: false, staleTime: 60000, cacheTime: 300000 }
);

const invoice = computed(() => invoiceData.value?.invoice ?? null);
const error = computed(() => swrError.value?.message ?? null);

watch(() => modalData.value.show, async (newVal) => {
    if (newVal && modalData.value.id) {
        await refreshInvoice();
    }
});

const close = () => {
    store.closeModal('invoiceDetail');
    emit('close');
};

const handleRecordPayment = () => {
    store.openModal('recordPayment', { invoiceId: invoice.value?.id });
    close();
};

const handleSendInvoice = async () => {
    if (!invoice.value) return;
    await sendInvoice(invoice.value.id);
    await refreshInvoice();
};

const handleSendReminder = async () => {
    if (!invoice.value) return;
    await sendReminder(invoice.value.id);
    emit('sendReminder', invoice.value.id);
};

const handleDownload = () => {
    if (invoice.value) {
        downloadInvoice(invoice.value.id);
    }
};

const balance = computed(() => {
    if (!invoice.value) return 0;
    const totalDue = Number(invoice.value.total_due) || 0;
    const amountPaid = Number(invoice.value.amount_paid) || 0;
    return totalDue - amountPaid;
});

const paymentProgress = computed(() => {
    if (!invoice.value) return 0;
    const totalDue = Number(invoice.value.total_due) || 0;
    if (totalDue === 0) return 0;
    const amountPaid = Number(invoice.value.amount_paid) || 0;
    return Math.round((amountPaid / totalDue) * 100);
});

const canVoid = computed(() => {
    if (!invoice.value) return false;
    return ['draft', 'sent'].includes(invoice.value.status) && invoice.value.amount_paid === 0;
});

const isVoided = computed(() => invoice.value?.status === 'voided');

const handlePreview = () => {
    if (invoice.value) {
        previewInvoice(invoice.value.id);
    }
};

const handleReissue = async () => {
    if (!invoice.value) return;
    await reissueInvoice(invoice.value.id);
    close();
};

const handleVoid = async () => {
    if (!invoice.value || !voidReason.value.trim()) return;
    await voidInvoice(invoice.value.id, voidReason.value);
    showVoidConfirm.value = false;
    voidReason.value = '';
    await refreshInvoice();
};
</script>

<template>
    <SlideOutPanel
        :show="modalData.show"
        width="lg"
        :title="t('finances_invoice_detail.title')"
        :subtitle="invoice?.invoice_number"
        @close="close"
    >
        <div class="flex-1 overflow-y-auto">
                            <div v-if="loading" class="flex items-center justify-center h-64">
                                <div class="animate-spin rounded-full h-8 w-8 border-2 border-emerald-500 border-t-transparent" />
                            </div>

                            <div v-else-if="error" class="p-6">
                                <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div v-else-if="invoice" class="p-6 space-y-6">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">{{ t('finances_invoice_detail.amount_due') }}</p>
                                        <p class="text-3xl font-bold text-gray-900 mt-1">
                                            {{ formatMoney(balance) }}
                                        </p>
                                    </div>
                                    <InvoiceStatusBadge :status="invoice.status" />
                                </div>

                                <div v-if="invoice.total_due > 0" class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">{{ t('finances_invoice_detail.payment_progress') }}</span>
                                        <span class="font-medium text-gray-900">{{ paymentProgress }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                            :style="{ width: `${paymentProgress}%` }"
                                        />
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>{{ t('finances_invoice_detail.paid_amount', { amount: formatMoney(invoice.amount_paid) }) }}</span>
                                        <span>{{ t('finances_invoice_detail.total_amount', { amount: formatMoney(invoice.total_due) }) }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <UserIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_invoice_detail.tenant') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ invoice.tenant_name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <HomeIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_invoice_detail.unit') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ invoice.unit_number }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <CalendarIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_invoice_detail.due_date') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ formatDate(invoice.due_date) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <CalendarIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_invoice_detail.billing_period') }}</p>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ formatDate(invoice.billing_period_start) }} - {{ formatDate(invoice.billing_period_end) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_invoice_detail.line_items') }}</h3>
                                    </div>
                                    <div class="divide-y divide-gray-200">
                                        <div v-if="invoice.rent_amount > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ t('finances_invoice_detail.rent') }}</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.rent_amount) }}</span>
                                        </div>
                                        <div v-if="invoice.water_charges > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ t('finances_invoice_detail.water_charges') }}</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.water_charges) }}</span>
                                        </div>
                                        <div v-if="invoice.arrears_amount > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ t('finances_invoice_detail.previous_arrears') }}</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.arrears_amount) }}</span>
                                        </div>
                                        <div class="flex justify-between px-4 py-3 bg-gray-50 font-semibold">
                                            <span class="text-sm text-gray-900">{{ t('finances_invoice_detail.total') }}</span>
                                            <span class="text-sm text-gray-900">{{ formatMoney(invoice.total_due) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="invoice.payments?.length" class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_invoice_detail.payments_applied') }}</h3>
                                    </div>
                                    <div class="divide-y divide-gray-200">
                                        <div
                                            v-for="payment in invoice.payments"
                                            :key="payment.id"
                                            class="flex items-center justify-between px-4 py-3"
                                        >
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ formatMoney(payment.amount) }}</p>
                                                <p class="text-xs text-gray-500">{{ formatDate(payment.payment_date) }}</p>
                                            </div>
                                            <PaymentMethodBadge :method="payment.payment_method" size="sm" />
                                        </div>
                                    </div>
                                </div>
                            </div>
        </div>

        <template #footer v-if="invoice">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="invoice.status !== 'paid'"
                                    @click="handleRecordPayment"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    <BanknotesIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.record_payment') }}
                                </button>

                                <button
                                    v-if="invoice.status === 'draft'"
                                    @click="handleSendInvoice"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.send_invoice') }}
                                </button>

                                <button
                                    v-if="invoice.status !== 'paid' && invoice.status !== 'draft'"
                                    @click="handleSendReminder"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.send_reminder') }}
                                </button>

                                <button
                                    @click="handlePreview"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <EyeIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.preview') }}
                                </button>

                                <button
                                    @click="handleDownload"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.download') }}
                                </button>

                                <button
                                    v-if="canVoid"
                                    @click="showVoidConfirm = true"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    <NoSymbolIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.void') }}
                                </button>

                                <button
                                    v-if="isVoided"
                                    @click="handleReissue"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors disabled:opacity-50"
                                >
                                    <ArrowPathIcon class="w-4 h-4" />
                                    {{ t('finances_invoice_detail.actions.reissue') }}
                                </button>
                            </div>

                            <div v-if="showVoidConfirm" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <h4 class="text-sm font-medium text-red-800 mb-2">{{ t('finances_invoice_detail.void.title') }}</h4>
                                <p class="text-xs text-red-600 mb-3">{{ t('finances_invoice_detail.void.warning') }}</p>
                                <textarea
                                    v-model="voidReason"
                                    :placeholder="t('finances_invoice_detail.void.reason_placeholder')"
                                    rows="2"
                                    class="w-full px-3 py-2 text-sm border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-3"
                                />
                                <div class="flex gap-2">
                                    <button
                                        @click="handleVoid"
                                        :disabled="!voidReason.trim() || isProcessing"
                                        class="flex-1 px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
                                    >
                                        {{ isProcessing ? t('finances_invoice_detail.void.voiding') : t('finances_invoice_detail.void.confirm') }}
                                    </button>
                                    <button
                                        @click="showVoidConfirm = false; voidReason = ''"
                                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        {{ t('finances_invoice_detail.void.cancel') }}
                                    </button>
                                </div>
                            </div>
        </template>
    </SlideOutPanel>
</template>
