<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useFormatters, usePayments } from '@/composables';
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

const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();
const { downloadInvoice, sendInvoice, sendReminder, voidInvoice, previewInvoice, reissueInvoice, isProcessing } = usePayments();

const invoice = ref(null);
const loading = ref(false);
const error = ref(null);
const showVoidConfirm = ref(false);
const voidReason = ref('');

const modalData = computed(() => store.modals.invoiceDetail);

watch(() => modalData.value.show, async (newVal) => {
    if (newVal && modalData.value.id) {
        await fetchInvoiceDetail(modalData.value.id);
    }
});

const fetchInvoiceDetail = async (id) => {
    loading.value = true;
    error.value = null;
    try {
        const response = await fetch(route('finances.invoices.detail', id));
        if (!response.ok) throw new Error('Failed to fetch invoice');
        invoice.value = await response.json();
    } catch (err) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
};

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
    await fetchInvoiceDetail(invoice.value.id);
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
    await fetchInvoiceDetail(invoice.value.id);
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="modalData.show" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div
                        v-if="modalData.show"
                        class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-xl overflow-hidden flex flex-col"
                    >
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <DocumentTextIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Invoice Details</h2>
                                    <p v-if="invoice" class="text-sm text-gray-500">{{ invoice.invoice_number }}</p>
                                </div>
                            </div>
                            <button
                                @click="close"
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

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
                                        <p class="text-sm text-gray-500">Amount Due</p>
                                        <p class="text-3xl font-bold text-gray-900 mt-1">
                                            {{ formatMoney(balance) }}
                                        </p>
                                    </div>
                                    <InvoiceStatusBadge :status="invoice.status" />
                                </div>

                                <div v-if="invoice.total_due > 0" class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Payment Progress</span>
                                        <span class="font-medium text-gray-900">{{ paymentProgress }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                            :style="{ width: `${paymentProgress}%` }"
                                        />
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Paid: {{ formatMoney(invoice.amount_paid) }}</span>
                                        <span>Total: {{ formatMoney(invoice.total_due) }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <UserIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">Tenant</p>
                                            <p class="text-sm font-medium text-gray-900">{{ invoice.tenant_name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <HomeIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">Unit</p>
                                            <p class="text-sm font-medium text-gray-900">{{ invoice.unit_number }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <CalendarIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">Due Date</p>
                                            <p class="text-sm font-medium text-gray-900">{{ formatDate(invoice.due_date) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <CalendarIcon class="w-4 h-4 text-gray-400 mt-0.5" />
                                        <div>
                                            <p class="text-xs text-gray-500">Billing Period</p>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ formatDate(invoice.billing_period_start) }} - {{ formatDate(invoice.billing_period_end) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">Line Items</h3>
                                    </div>
                                    <div class="divide-y divide-gray-200">
                                        <div v-if="invoice.rent_amount > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">Rent</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.rent_amount) }}</span>
                                        </div>
                                        <div v-if="invoice.water_charges > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">Water Charges</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.water_charges) }}</span>
                                        </div>
                                        <div v-if="invoice.arrears_amount > 0" class="flex justify-between px-4 py-3">
                                            <span class="text-sm text-gray-600">Previous Arrears</span>
                                            <span class="text-sm font-medium text-gray-900">{{ formatMoney(invoice.arrears_amount) }}</span>
                                        </div>
                                        <div class="flex justify-between px-4 py-3 bg-gray-50 font-semibold">
                                            <span class="text-sm text-gray-900">Total</span>
                                            <span class="text-sm text-gray-900">{{ formatMoney(invoice.total_due) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="invoice.payments?.length" class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                        <h3 class="text-sm font-semibold text-gray-900">Payments Applied</h3>
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

                        <div v-if="invoice" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="invoice.status !== 'paid'"
                                    @click="handleRecordPayment"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                                >
                                    <BanknotesIcon class="w-4 h-4" />
                                    Record Payment
                                </button>

                                <button
                                    v-if="invoice.status === 'draft'"
                                    @click="handleSendInvoice"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    Send Invoice
                                </button>

                                <button
                                    v-if="invoice.status !== 'paid' && invoice.status !== 'draft'"
                                    @click="handleSendReminder"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    Send Reminder
                                </button>

                                <button
                                    @click="handlePreview"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <EyeIcon class="w-4 h-4" />
                                    Preview
                                </button>

                                <button
                                    @click="handleDownload"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                    Download
                                </button>

                                <button
                                    v-if="canVoid"
                                    @click="showVoidConfirm = true"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    <NoSymbolIcon class="w-4 h-4" />
                                    Void
                                </button>

                                <button
                                    v-if="isVoided"
                                    @click="handleReissue"
                                    :disabled="isProcessing"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors disabled:opacity-50"
                                >
                                    <ArrowPathIcon class="w-4 h-4" />
                                    Reissue
                                </button>
                            </div>

                            <div v-if="showVoidConfirm" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <h4 class="text-sm font-medium text-red-800 mb-2">Void this invoice?</h4>
                                <p class="text-xs text-red-600 mb-3">This action cannot be undone. The invoice will be marked as voided.</p>
                                <textarea
                                    v-model="voidReason"
                                    placeholder="Enter reason for voiding..."
                                    rows="2"
                                    class="w-full px-3 py-2 text-sm border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-3"
                                />
                                <div class="flex gap-2">
                                    <button
                                        @click="handleVoid"
                                        :disabled="!voidReason.trim() || isProcessing"
                                        class="flex-1 px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
                                    >
                                        {{ isProcessing ? 'Voiding...' : 'Confirm Void' }}
                                    </button>
                                    <button
                                        @click="showVoidConfirm = false; voidReason = ''"
                                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
