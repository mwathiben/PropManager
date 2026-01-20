<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters, usePayments } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    BanknotesIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import type { Invoice } from '@/types/finances';

interface InvoiceWithBalance extends Invoice {
    balance: number;
    tenant_name?: string;
}

interface PaymentMethod {
    id: string;
    label: string;
}

interface PaymentForm {
    invoice_id: number | null;
    amount: string | number;
    payment_method: string;
    payment_date: string;
    reference: string;
    notes: string;
}

interface Props {
    invoices?: InvoiceWithBalance[];
}

const props = withDefaults(defineProps<Props>(), {
    invoices: () => [],
});

const emit = defineEmits(['close', 'success']);

const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();
const { recordManualPayment, isProcessing, error: paymentError } = usePayments();

const modalData = computed(() => store.modals.recordPayment);

const form = ref({
    invoice_id: null,
    amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    reference: '',
    notes: '',
});

const errors = ref({});
const success = ref(false);

const paymentMethods = [
    { id: 'cash', label: 'Cash' },
    { id: 'bank_transfer', label: 'Bank Transfer' },
    { id: 'mobile_money', label: 'M-Pesa' },
    { id: 'paystack', label: 'Paystack (Online)' },
];

watch(() => modalData.value.show, (newVal) => {
    if (newVal) {
        resetForm();
        if (modalData.value.invoiceId) {
            form.value.invoice_id = modalData.value.invoiceId;
            const invoice = props.invoices.find(i => i.id === modalData.value.invoiceId);
            if (invoice) {
                form.value.amount = invoice.balance;
            }
        }
    }
});

const selectedInvoice = computed(() => {
    return props.invoices.find(i => i.id === form.value.invoice_id);
});

const maxAmount = computed(() => {
    return selectedInvoice.value?.balance || 0;
});

const resetForm = () => {
    form.value = {
        invoice_id: null,
        amount: '',
        payment_method: 'cash',
        payment_date: new Date().toISOString().split('T')[0],
        reference: '',
        notes: '',
    };
    errors.value = {};
    success.value = false;
};

const close = () => {
    store.closeModal('recordPayment');
    emit('close');
};

const validate = () => {
    errors.value = {};

    if (!form.value.invoice_id) {
        errors.value.invoice_id = 'Please select an invoice';
    }

    if (!form.value.amount || form.value.amount <= 0) {
        errors.value.amount = 'Please enter a valid amount';
    } else if (form.value.amount > maxAmount.value) {
        errors.value.amount = `Amount cannot exceed ${formatMoney(maxAmount.value)}`;
    }

    if (!form.value.payment_method) {
        errors.value.payment_method = 'Please select a payment method';
    }

    if (!form.value.payment_date) {
        errors.value.payment_date = 'Please select a payment date';
    }

    return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
    if (!validate()) return;

    try {
        await recordManualPayment(form.value.invoice_id, {
            amount: form.value.amount,
            payment_method: form.value.payment_method,
            payment_date: form.value.payment_date,
            reference: form.value.reference,
            notes: form.value.notes,
        });

        success.value = true;
        emit('success');

        setTimeout(() => {
            close();
            router.reload({ only: ['invoices', 'payments', 'stats'] });
        }, 1500);
    } catch (err) {
        errors.value.general = paymentError.value || 'Failed to record payment';
    }
};

const setFullAmount = () => {
    if (selectedInvoice.value) {
        form.value.amount = selectedInvoice.value.balance;
    }
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
                        class="relative w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden"
                    >
                        <div v-if="success" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-emerald-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Payment Recorded!</h3>
                            <p class="text-sm text-gray-500 mt-2">The payment has been successfully recorded.</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Record Payment</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                                <div v-if="errors.general" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ errors.general }}
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
                                    <select
                                        v-model="form.invoice_id"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.invoice_id
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option :value="null">Select an invoice</option>
                                        <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">
                                            {{ invoice.invoice_number }} - {{ invoice.tenant_name }} ({{ formatMoney(invoice.balance) }} due)
                                        </option>
                                    </select>
                                    <p v-if="errors.invoice_id" class="mt-1 text-sm text-red-600">{{ errors.invoice_id }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.amount"
                                            type="number"
                                            min="0"
                                            :max="maxAmount"
                                            step="0.01"
                                            :class="[
                                                'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors pr-20',
                                                errors.amount
                                                    ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                    : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                            ]"
                                            placeholder="0.00"
                                        />
                                        <button
                                            v-if="selectedInvoice"
                                            type="button"
                                            @click="setFullAmount"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            Full Amount
                                        </button>
                                    </div>
                                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                                    <p v-else-if="selectedInvoice" class="mt-1 text-xs text-gray-500">
                                        Balance due: {{ formatMoney(selectedInvoice.balance) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                    <select
                                        v-model="form.payment_method"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.payment_method
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                            {{ method.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.payment_method" class="mt-1 text-sm text-red-600">{{ errors.payment_method }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                                    <input
                                        v-model="form.payment_date"
                                        type="date"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.payment_date
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    />
                                    <p v-if="errors.payment_date" class="mt-1 text-sm text-red-600">{{ errors.payment_date }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference (optional)</label>
                                    <input
                                        v-model="form.reference"
                                        type="text"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        placeholder="e.g., Receipt number, transaction ID"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="2"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors resize-none"
                                        placeholder="Any additional notes..."
                                    />
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
                                        type="submit"
                                        :disabled="isProcessing"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? 'Recording...' : 'Record Payment' }}
                                    </button>
                                </div>
                            </form>
                        </template>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
