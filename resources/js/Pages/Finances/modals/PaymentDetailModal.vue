<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import SlideOutPanel from '@/Components/SlideOutPanel.vue';
import Modal from '@/Components/Modal.vue';
import { useFormatters, usePayments, useSWR } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useFinancesStore } from '@/stores/finances';
import {
    PaymentMethodBadge,
    AmountDisplay,
} from '@/Components/Finances';
import {
    XMarkIcon,
    BanknotesIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    DocumentTextIcon,
    CalendarIcon,
    HomeIcon,
    UserIcon,
    HashtagIcon,
    CheckCircleIcon,
    EnvelopeIcon,
    NoSymbolIcon,
} from '@heroicons/vue/24/outline';
import type { Payment, Invoice } from '@/types/finances';

interface PaymentDetail extends Payment {
    tenant_name?: string;
    unit_number?: string;
    payment_date?: string;
    refund_status?: string;
    refund_date?: string;
    is_voided?: boolean;
    voided_at?: string;
    void_reason?: string;
    invoice?: Invoice;
    invoice_id?: number;
    mpesa_transaction_id?: string;
    mpesa_checkout_request_id?: string;
}

interface Props {
    show?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    show: false,
});

const emit = defineEmits<{
    close: [];
    refund: [];
    viewInvoice: [];
}>();

const store = useFinancesStore();
const { t } = useI18n();
const { formatMoney, formatDate } = useFormatters();
const { downloadReceipt, sendReceipt, voidPayment, isProcessing } = usePayments();

const showVoidConfirm = ref(false);
const voidReason = ref('');

const modalData = computed(() => store.modals.paymentDetail);

const swrKey = computed(() => {
    if (!modalData.value.show || !modalData.value.id) return '';
    return `payment-detail-${modalData.value.id}`;
});

const { data: paymentData, error: swrError, isLoading: loading, refresh: refreshPayment } = useSWR(
    () => swrKey.value,
    async (key) => {
        const id = key.replace('payment-detail-', '');
        const response = await fetch(route('finances.payments.detail', id));
        if (!response.ok) throw new Error(t('finances_payment_detail.fetch_error'));
        return response.json();
    },
    { immediate: false, staleTime: 60000, cacheTime: 300000 }
);

const payment = computed(() => paymentData.value?.payment ?? null);
const error = computed(() => swrError.value?.message ?? null);

watch(() => modalData.value.show, async (newVal) => {
    if (newVal && modalData.value.id) {
        await refreshPayment();
    }
});

const close = () => {
    store.closeModal('paymentDetail');
    emit('close');
};

const handleRefund = () => {
    store.openModal('refund', { paymentId: payment.value?.id });
    close();
};

const handleViewInvoice = () => {
    if (payment.value?.invoice_id) {
        store.openModal('invoiceDetail', { id: payment.value.invoice_id });
        close();
    }
};

const handleDownloadReceipt = () => {
    if (payment.value) {
        downloadReceipt(payment.value.id);
    }
};

const handleSendReceipt = async () => {
    if (payment.value) {
        await sendReceipt(payment.value.id);
    }
};

const canRefund = computed(() => {
    if (!payment.value) return false;
    return payment.value.refund_status !== 'refunded' && payment.value.refund_status !== 'pending' && !payment.value.is_voided;
});

const canVoid = computed(() => {
    if (!payment.value) return false;
    return !payment.value.is_voided && payment.value.refund_status !== 'refunded';
});

const handleVoid = async () => {
    if (!payment.value || !voidReason.value.trim()) return;
    await voidPayment(payment.value.id, voidReason.value);
    showVoidConfirm.value = false;
    voidReason.value = '';
    await refreshPayment();
};
</script>

<template>
    <SlideOutPanel
        :show="modalData.show"
        width="lg"
        :title="t('finances_payment_detail.panel_title')"
        :subtitle="payment?.reference"
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

                            <div v-else-if="payment" class="p-6 space-y-6">
                                <div class="text-center py-4">
                                    <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                                        <CheckCircleIcon class="w-8 h-8 text-emerald-600" />
                                    </div>
                                    <p class="text-sm text-gray-500">{{ t('finances_payment_detail.amount_received') }}</p>
                                    <p class="text-4xl font-bold text-emerald-600 mt-1">
                                        {{ formatMoney(payment.amount) }}
                                    </p>
                                    <PaymentMethodBadge :method="payment.payment_method" class="mt-3" />
                                </div>

                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <UserIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_payment_detail.tenant') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ payment.tenant_name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <HomeIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_payment_detail.unit') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ payment.unit_number }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <CalendarIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_payment_detail.payment_date') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ formatDate(payment.payment_date) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <HashtagIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_payment_detail.reference') }}</p>
                                            <p class="text-sm font-medium text-gray-900">{{ payment.reference || '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="payment.invoice" class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_payment_detail.applied_to_invoice') }}</h3>
                                    </div>
                                    <button
                                        @click="handleViewInvoice"
                                        class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-blue-100 rounded-lg">
                                                <DocumentTextIcon class="w-4 h-4 text-blue-600" />
                                            </div>
                                            <div class="text-start">
                                                <p class="text-sm font-medium text-gray-900">{{ payment.invoice.invoice_number }}</p>
                                                <p class="text-xs text-gray-500">{{ t('finances_payment_detail.due', { date: formatDate(payment.invoice.due_date) }) }}</p>
                                            </div>
                                        </div>
                                        <AmountDisplay :amount="payment.invoice.total_due" size="sm" />
                                    </button>
                                </div>

                                <div v-if="payment.mpesa_transaction_id || payment.mpesa_checkout_request_id" class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_payment_detail.mpesa_details') }}</h3>
                                    </div>
                                    <div class="p-4 space-y-2 text-sm">
                                        <div v-if="payment.mpesa_transaction_id" class="flex justify-between">
                                            <span class="text-gray-500">{{ t('finances_payment_detail.transaction_id') }}</span>
                                            <span class="font-mono text-gray-900">{{ payment.mpesa_transaction_id }}</span>
                                        </div>
                                        <div v-if="payment.mpesa_checkout_request_id" class="flex justify-between">
                                            <span class="text-gray-500">{{ t('finances_payment_detail.checkout_request') }}</span>
                                            <span class="font-mono text-gray-900 text-xs">{{ payment.mpesa_checkout_request_id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="payment.refund_status === 'refunded'" class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <ArrowPathIcon class="w-5 h-5 text-orange-500 shrink-0" />
                                        <div>
                                            <p class="text-sm font-medium text-orange-800">{{ t('finances_payment_detail.refunded_message') }}</p>
                                            <p v-if="payment.refund_date" class="text-sm text-orange-700 mt-1">
                                                {{ t('finances_payment_detail.refunded_on', { date: formatDate(payment.refund_date) }) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="payment.is_voided" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <NoSymbolIcon class="w-5 h-5 text-red-500 shrink-0" />
                                        <div>
                                            <p class="text-sm font-medium text-red-800">{{ t('finances_payment_detail.voided_message') }}</p>
                                            <p v-if="payment.voided_at" class="text-sm text-red-700 mt-1">
                                                {{ t('finances_payment_detail.voided_on', { date: formatDate(payment.voided_at) }) }}
                                            </p>
                                            <p v-if="payment.void_reason" class="text-sm text-red-600 mt-1">
                                                {{ t('finances_payment_detail.reason', { reason: payment.void_reason }) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
        </div>

        <template #footer v-if="payment">
            <div class="flex flex-wrap gap-2">
                                <button
                                    @click="handleDownloadReceipt"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                    {{ t('finances_payment_detail.download_receipt') }}
                                </button>

                                <button
                                    @click="handleSendReceipt"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    {{ t('finances_payment_detail.send_receipt') }}
                                </button>

                                <button
                                    v-if="canRefund"
                                    @click="handleRefund"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition-colors"
                                >
                                    <ArrowPathIcon class="w-4 h-4" />
                                    {{ t('finances_payment_detail.initiate_refund') }}
                                </button>

                                <button
                                    v-if="canVoid"
                                    @click="showVoidConfirm = true"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    <NoSymbolIcon class="w-4 h-4" />
                                    {{ t('finances_payment_detail.void_payment') }}
                                </button>
            </div>
        </template>
    </SlideOutPanel>

    <!-- Void Confirmation Dialog -->
    <Modal :show="showVoidConfirm" max-width="md" @close="showVoidConfirm = false">
        <div class="p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-red-100 rounded-full">
                                    <NoSymbolIcon class="w-6 h-6 text-red-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_payment_detail.void_payment') }}</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">
                                {{ t('finances_payment_detail.void_warning') }}
                            </p>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('finances_payment_detail.reason_for_voiding') }} <span class="text-red-500">*</span>
                                </label>
                                <textarea
                                    v-model="voidReason"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm"
                                    :placeholder="t('finances_payment_detail.void_reason_placeholder')"
                                ></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button
                                    @click="showVoidConfirm = false"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    {{ t('finances_payment_detail.cancel') }}
                                </button>
                                <button
                                    @click="handleVoid"
                                    :disabled="!voidReason.trim() || isProcessing"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ isProcessing ? t('finances_payment_detail.voiding') : t('finances_payment_detail.void_payment') }}
                                </button>
                            </div>
        </div>
    </Modal>
</template>
