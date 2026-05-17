<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useFormatters, usePayments } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    ArrowPathIcon,
    CheckIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import type { Payment } from '@/types/finances';

interface PaymentWithMeta extends Payment {
    tenant_name?: string;
    payment_date?: string;
    refund_status?: string;
}

interface RefundMethod {
    id: string;
    label: string;
}

interface RefundForm {
    payment_id: number | null;
    amount: string | number;
    reason: string;
    refund_method: string;
}

interface Props {
    payments?: PaymentWithMeta[];
}

const props = withDefaults(defineProps<Props>(), {
    payments: () => [],
});

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();
const { createRefund, isProcessing, error: refundError } = usePayments();

const modalData = computed(() => store.modals.refund);

const form = ref({
    payment_id: null,
    amount: '',
    reason: '',
    refund_method: 'original',
});

const errors = ref({});
const success = ref(false);

const refundMethods = [
    { id: 'original', label: 'Original Payment Method' },
    { id: 'cash', label: 'Cash' },
    { id: 'bank_transfer', label: 'Bank Transfer' },
    { id: 'mobile_money', label: 'M-Pesa' },
];

const refundReasons = [
    'Overpayment',
    'Duplicate Payment',
    'Tenant Moved Out',
    'Billing Error',
    'Service Not Provided',
    'Other',
];

watch(() => modalData.value.show, (newVal) => {
    if (newVal) {
        resetForm();
        if (modalData.value.paymentId) {
            form.value.payment_id = modalData.value.paymentId;
            const payment = props.payments.find(p => p.id === modalData.value.paymentId);
            if (payment) {
                form.value.amount = payment.amount;
            }
        }
    }
});

const selectedPayment = computed(() => {
    return props.payments.find(p => p.id === form.value.payment_id);
});

const maxAmount = computed(() => {
    return selectedPayment.value?.amount || 0;
});

const resetForm = () => {
    form.value = {
        payment_id: null,
        amount: '',
        reason: '',
        refund_method: 'original',
    };
    errors.value = {};
    success.value = false;
};

const close = () => {
    store.closeModal('refund');
    emit('close');
};

const validate = () => {
    errors.value = {};

    if (!form.value.payment_id) {
        errors.value.payment_id = 'Please select a payment';
    }

    if (!form.value.amount || form.value.amount <= 0) {
        errors.value.amount = 'Please enter a valid amount';
    } else if (form.value.amount > maxAmount.value) {
        errors.value.amount = `Amount cannot exceed ${formatMoney(maxAmount.value)}`;
    }

    if (!form.value.reason) {
        errors.value.reason = 'Please select a reason';
    }

    if (!form.value.refund_method) {
        errors.value.refund_method = 'Please select a refund method';
    }

    return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
    if (!validate()) return;

    try {
        await createRefund(form.value.payment_id, {
            amount: form.value.amount,
            reason: form.value.reason,
            refund_method: form.value.refund_method,
        });

        success.value = true;
        emit('success');

        setTimeout(() => {
            close();
            router.reload({ only: ['payments', 'refunds', 'stats'] });
        }, 1500);
    } catch (err) {
        errors.value.general = refundError.value || 'Failed to create refund';
    }
};

const setFullAmount = () => {
    if (selectedPayment.value) {
        form.value.amount = selectedPayment.value.amount;
    }
};
</script>

<template>
    <Modal :show="modalData.show" max-width="md" @close="close">
                        <div v-if="success" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-emerald-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Refund Initiated!</h3>
                            <p class="text-sm text-gray-500 mt-2">The refund request has been submitted.</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-orange-100 rounded-lg">
                                        <ArrowPathIcon class="w-5 h-5 text-orange-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Initiate Refund</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex gap-2">
                                        <ExclamationTriangleIcon class="w-5 h-5 text-amber-500 shrink-0" />
                                        <p class="text-sm text-amber-800">
                                            Refunds may take 3-5 business days to process depending on the payment method.
                                        </p>
                                    </div>
                                </div>

                                <div v-if="errors.general" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ errors.general }}
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment</label>
                                    <select
                                        v-model="form.payment_id"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.payment_id
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option :value="null">Select a payment</option>
                                        <option
                                            v-for="payment in payments"
                                            :key="payment.id"
                                            :value="payment.id"
                                            :disabled="payment.refund_status === 'refunded'"
                                        >
                                            {{ formatDate(payment.payment_date) }} - {{ payment.tenant_name }} ({{ formatMoney(payment.amount) }})
                                            {{ payment.refund_status === 'refunded' ? ' (Already Refunded)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="errors.payment_id" class="mt-1 text-sm text-red-600">{{ errors.payment_id }}</p>
                                </div>

                                <div v-if="selectedPayment" class="p-3 bg-gray-50 rounded-lg text-sm">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-gray-500">Original Amount</span>
                                        <span class="font-medium text-gray-900">{{ formatMoney(selectedPayment.amount) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Payment Method</span>
                                        <span class="font-medium text-gray-900 capitalize">{{ selectedPayment.payment_method?.replace('_', ' ') }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Amount</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.amount"
                                            type="number"
                                            min="0"
                                            :max="maxAmount"
                                            step="0.01"
                                            :class="[
                                                'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors pe-20',
                                                errors.amount
                                                    ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                    : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                            ]"
                                            placeholder="0.00"
                                        />
                                        <button
                                            v-if="selectedPayment"
                                            type="button"
                                            @click="setFullAmount"
                                            class="absolute end-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            Full Amount
                                        </button>
                                    </div>
                                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                                    <select
                                        v-model="form.reason"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.reason
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option value="">Select a reason</option>
                                        <option v-for="reason in refundReasons" :key="reason" :value="reason">
                                            {{ reason }}
                                        </option>
                                    </select>
                                    <p v-if="errors.reason" class="mt-1 text-sm text-red-600">{{ errors.reason }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Method</label>
                                    <select
                                        v-model="form.refund_method"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.refund_method
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option v-for="method in refundMethods" :key="method.id" :value="method.id">
                                            {{ method.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.refund_method" class="mt-1 text-sm text-red-600">{{ errors.refund_method }}</p>
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
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? 'Processing...' : 'Initiate Refund' }}
                                    </button>
                                </div>
                            </form>
                        </template>
    </Modal>
</template>
