<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    LinkIcon,
    CheckIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import type { Invoice, Payment } from '@/types/finances';

interface InvoiceWithBalance extends Invoice {
    balance: number;
}

interface PaymentWithMeta extends Payment {
    tenant_name?: string;
    payment_date?: string;
}

interface Props {
    invoices?: InvoiceWithBalance[];
    payments?: PaymentWithMeta[];
}

const props = withDefaults(defineProps<Props>(), {
    invoices: () => [],
    payments: () => [],
});

const emit = defineEmits(['close', 'success']);

const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();

const modalData = computed(() => store.modals.matchPayment);

const selectedInvoiceId = ref(null);
const isProcessing = ref(false);
const success = ref(false);
const error = ref(null);

watch(() => modalData.value.show, (newVal) => {
    if (newVal) {
        selectedInvoiceId.value = null;
        success.value = false;
        error.value = null;
    }
});

const selectedPayment = computed(() => {
    if (!modalData.value.paymentId) return null;
    return props.payments.find(p => p.id === modalData.value.paymentId);
});

const eligibleInvoices = computed(() => {
    if (!selectedPayment.value) return [];
    return props.invoices.filter(inv =>
        inv.status !== 'paid' && inv.status !== 'voided'
    );
});

const selectedInvoice = computed(() => {
    if (!selectedInvoiceId.value) return null;
    return props.invoices.find(inv => inv.id === selectedInvoiceId.value);
});

const close = () => {
    store.closeModal('matchPayment');
    emit('close');
};

const handleSubmit = () => {
    if (!selectedInvoiceId.value || !selectedPayment.value) return;

    isProcessing.value = true;
    error.value = null;

    router.post(route('finances.payments.match', selectedPayment.value.id), {
        invoice_id: selectedInvoiceId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isProcessing.value = false;
            success.value = true;
            emit('success');

            setTimeout(() => {
                close();
                router.reload({ only: ['unmatchedPayments', 'pendingReconciliation', 'invoices'] });
            }, 1500);
        },
        onError: (errors) => {
            isProcessing.value = false;
            error.value = Object.values(errors)[0] || 'Failed to match payment';
        },
    });
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
            <div v-if="modalData.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="modalData.show"
                        class="relative w-full max-w-lg bg-white rounded-xl shadow-xl overflow-hidden"
                    >
                        <div v-if="success" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-emerald-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Payment Matched!</h3>
                            <p class="text-sm text-gray-500 mt-2">The payment has been linked to the invoice.</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <LinkIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Match Payment to Invoice</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <div class="p-6 space-y-4">
                                <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ error }}
                                </div>

                                <div v-if="selectedPayment" class="p-4 bg-gray-50 rounded-lg">
                                    <p class="text-xs font-medium text-gray-500 mb-1">Payment Details</p>
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ selectedPayment.tenant_name }}</p>
                                            <p class="text-xs text-gray-500">{{ formatDate(selectedPayment.payment_date) }}</p>
                                        </div>
                                        <p class="text-lg font-semibold text-emerald-600">{{ formatMoney(selectedPayment.amount) }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Invoice</label>
                                    <div v-if="eligibleInvoices.length === 0" class="p-4 text-center text-sm text-gray-500 bg-gray-50 rounded-lg">
                                        No eligible invoices found for this tenant.
                                    </div>
                                    <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                                        <label
                                            v-for="invoice in eligibleInvoices"
                                            :key="invoice.id"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors',
                                                selectedInvoiceId === invoice.id
                                                    ? 'border-emerald-500 bg-emerald-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            ]"
                                        >
                                            <input
                                                type="radio"
                                                :value="invoice.id"
                                                v-model="selectedInvoiceId"
                                                class="sr-only"
                                            />
                                            <div class="p-2 bg-gray-100 rounded-lg">
                                                <DocumentTextIcon :class="['w-4 h-4', selectedInvoiceId === invoice.id ? 'text-emerald-600' : 'text-gray-400']" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                                <p class="text-xs text-gray-500">Due: {{ formatDate(invoice.due_date) }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-semibold text-gray-900">{{ formatMoney(invoice.balance) }}</p>
                                                <p class="text-xs text-gray-500">due</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="selectedInvoice && selectedPayment" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm">
                                    <p class="text-blue-800">
                                        <span class="font-medium">{{ formatMoney(selectedPayment.amount) }}</span>
                                        will be applied to invoice
                                        <span class="font-medium">{{ selectedInvoice.invoice_number }}</span>
                                        <span v-if="selectedPayment.amount >= selectedInvoice.balance" class="text-emerald-600 font-medium"> (Full payment)</span>
                                        <span v-else class="text-orange-600 font-medium"> (Partial payment)</span>
                                    </p>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="close"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        @click="handleSubmit"
                                        :disabled="!selectedInvoiceId || isProcessing"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? 'Matching...' : 'Match Payment' }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
