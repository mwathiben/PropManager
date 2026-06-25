<script setup lang="ts">
import { computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useFormatters, usePayments, usePaymentForm } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { PaymentMethodSelector } from '@/Components/Finances';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    BanknotesIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import type { Invoice, PaymentMethodOption } from '@/types/finances';

interface InvoiceWithBalance extends Invoice {
    balance: number;
    tenant_name?: string;
}

interface Props {
    invoices?: InvoiceWithBalance[];
}

const props = withDefaults(defineProps<Props>(), {
    invoices: () => [],
});

const emit = defineEmits(['close', 'success']);

const store = useFinancesStore();
const { t } = useI18n();
const { formatMoney } = useFormatters();
const { recordManualPayment, isProcessing, error: paymentError, paymentMethods: methodsRecord } = usePayments();
const { form, errors, isSuccess, resetForm, validate } = usePaymentForm();

const modalData = computed(() => store.modals.recordPayment);

const paymentMethodOptions: PaymentMethodOption[] = Object.entries(methodsRecord).map(([id, info]) => ({
    id,
    label: info.label,
}));

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

const close = () => {
    store.closeModal('recordPayment');
    emit('close');
};

const handleValidate = () => {
    return validate(() => {
        const extra: Record<string, string> = {};
        if (!form.value.invoice_id) {
            extra.invoice_id = t('finances_record_payment.errors.select_invoice');
        }
        if (form.value.amount && Number(form.value.amount) > maxAmount.value && maxAmount.value > 0) {
            extra.amount = t('finances_record_payment.errors.amount_exceeds', { max: formatMoney(maxAmount.value) });
        }
        return extra;
    });
};

const handleSubmit = async () => {
    if (!handleValidate()) return;

    try {
        await recordManualPayment(form.value.invoice_id!, {
            amount: form.value.amount,
            payment_method: form.value.payment_method,
            payment_date: form.value.payment_date,
            reference: form.value.reference,
            notes: form.value.notes,
        });

        isSuccess.value = true;
        emit('success');

        setTimeout(() => {
            close();
            router.reload({ only: ['invoices', 'payments', 'stats'] });
        }, 1500);
    } catch (err) {
        errors.value = { ...errors.value, general: paymentError.value || t('finances_record_payment.errors.failed') };
    }
};

const setFullAmount = () => {
    if (selectedInvoice.value) {
        form.value.amount = selectedInvoice.value.balance;
    }
};
</script>

<template>
    <Modal :show="modalData.show" max-width="md" @close="close">
                        <div v-if="isSuccess" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-emerald-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_record_payment.success_title') }}</h3>
                            <p class="text-sm text-gray-500 mt-2">{{ t('finances_record_payment.success_body') }}</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">{{ t('finances_record_payment.heading') }}</h2>
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
                                    <label for="rp-invoice-id" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.invoice_label') }}</label>
                                    <select
                                        id="rp-invoice-id"
                                        v-model="form.invoice_id"
                                        :class="['w-full px-3 py-2.5 text-sm border rounded-lg transition-colors', errors.invoice_id ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']"
                                    >
                                        <option :value="null">{{ t('finances_record_payment.select_invoice') }}</option>
                                        <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">
                                            {{ invoice.invoice_number }} - {{ invoice.tenant_name }} ({{ formatMoney(invoice.balance) }} due)
                                        </option>
                                    </select>
                                    <p v-if="errors.invoice_id" class="mt-1 text-sm text-red-600">{{ errors.invoice_id }}</p>
                                </div>

                                <div>
                                    <label for="rp-amount" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.amount_label') }}</label>
                                    <div class="relative">
                                        <input
                                            id="rp-amount"
                                            v-model.number="form.amount"
                                            type="number"
                                            min="0"
                                            :max="maxAmount"
                                            step="0.01"
                                            :class="['w-full px-3 py-2.5 text-sm border rounded-lg transition-colors pe-20', errors.amount ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']"
                                            placeholder="0.00"
                                        />
                                        <button
                                            v-if="selectedInvoice"
                                            type="button"
                                            @click="setFullAmount"
                                            class="absolute end-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            {{ t('finances_record_payment.full_amount') }}
                                        </button>
                                    </div>
                                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                                    <p v-else-if="selectedInvoice" class="mt-1 text-xs text-gray-500">
                                        {{ t('finances_record_payment.balance_due', { amount: formatMoney(selectedInvoice.balance) }) }}
                                    </p>
                                </div>

                                <div>
                                    <label for="payment-method-select" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.payment_method') }}</label>
                                    <PaymentMethodSelector
                                        v-model="form.payment_method"
                                        :methods="paymentMethodOptions"
                                        :error="errors.payment_method"
                                    />
                                </div>

                                <div>
                                    <label for="rp-payment-date" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.payment_date') }}</label>
                                    <input
                                        id="rp-payment-date"
                                        v-model="form.payment_date"
                                        type="date"
                                        :class="['w-full px-3 py-2.5 text-sm border rounded-lg transition-colors', errors.payment_date ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500']"
                                    />
                                    <p v-if="errors.payment_date" class="mt-1 text-sm text-red-600">{{ errors.payment_date }}</p>
                                </div>

                                <div>
                                    <label for="rp-reference" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.reference_label') }}</label>
                                    <input
                                        id="rp-reference"
                                        v-model="form.reference"
                                        type="text"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        :placeholder="t('finances_record_payment.reference_placeholder')"
                                    />
                                </div>

                                <div>
                                    <label for="rp-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_record_payment.notes_label') }}</label>
                                    <textarea
                                        id="rp-notes"
                                        v-model="form.notes"
                                        rows="2"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors resize-none"
                                        :placeholder="t('finances_record_payment.notes_placeholder')"
                                    />
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="close"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        {{ t('finances_record_payment.cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="isProcessing"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? t('finances_record_payment.recording') : t('finances_record_payment.heading') }}
                                    </button>
                                </div>
                            </form>
                        </template>
    </Modal>
</template>
