<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    ArrowUturnLeftIcon,
    CheckIcon,
    BanknotesIcon,
    UserIcon,
    HomeIcon,
} from '@heroicons/vue/24/outline';
import type { Deposit } from '@/types/finances';

interface RefundDepositForm {
    refund_amount: number;
    deductions: number;
    deduction_reason: string;
}

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const store = useFinancesStore();
const { formatMoney, formatDate } = useFormatters();

const modalData = computed(() => store.modals.refundDeposit);

const form = ref({
    refund_amount: 0,
    deductions: 0,
    deduction_reason: '',
});

const errors = ref({});
const success = ref(false);
const isProcessing = ref(false);

const deductionReasons = [
    'Unpaid rent',
    'Property damage',
    'Cleaning fees',
    'Unpaid utilities',
    'Early termination fee',
    'Other',
];

watch(() => modalData.value.show, (newVal) => {
    if (newVal && modalData.value.deposit) {
        resetForm();
        form.value.refund_amount = modalData.value.deposit.amount;
    }
});

const deposit = computed(() => modalData.value.deposit);

const netRefund = computed(() => {
    return Math.max(0, (form.value.refund_amount || 0) - (form.value.deductions || 0));
});

const maxRefund = computed(() => deposit.value?.amount || 0);

const resetForm = () => {
    form.value = {
        refund_amount: deposit.value?.amount || 0,
        deductions: 0,
        deduction_reason: '',
    };
    errors.value = {};
    success.value = false;
};

const close = () => {
    store.closeModal('refundDeposit');
    emit('close');
};

const validate = () => {
    errors.value = {};

    if (form.value.refund_amount <= 0) {
        errors.value.refund_amount = 'Refund amount must be greater than 0';
    } else if (form.value.refund_amount > maxRefund.value) {
        errors.value.refund_amount = `Refund amount cannot exceed ${formatMoney(maxRefund.value)}`;
    }

    if (form.value.deductions < 0) {
        errors.value.deductions = 'Deductions cannot be negative';
    }

    if ((form.value.refund_amount + form.value.deductions) > maxRefund.value) {
        errors.value.general = 'Refund amount plus deductions cannot exceed deposit amount';
    }

    if (form.value.deductions > 0 && !form.value.deduction_reason) {
        errors.value.deduction_reason = 'Please provide a reason for deductions';
    }

    return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
    if (!validate()) return;

    isProcessing.value = true;

    router.post(route('finances.deposits.refund', deposit.value.id), {
        refund_amount: form.value.refund_amount,
        deductions: form.value.deductions,
        deduction_reason: form.value.deduction_reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            success.value = true;
            emit('success');
            setTimeout(() => {
                close();
                router.reload({ only: ['deposits', 'stats'] });
            }, 1500);
        },
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            isProcessing.value = false;
        },
    });
};

const setFullRefund = () => {
    form.value.refund_amount = maxRefund.value;
    form.value.deductions = 0;
    form.value.deduction_reason = '';
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
                            <h3 class="text-lg font-semibold text-gray-900">Deposit Refunded!</h3>
                            <p class="text-sm text-gray-500 mt-2">The deposit refund has been processed.</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <ArrowUturnLeftIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Refund Deposit</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                                <div v-if="deposit" class="p-4 bg-gray-50 rounded-lg space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-blue-100 rounded-lg">
                                            <BanknotesIcon class="w-5 h-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Deposit Amount</p>
                                            <p class="text-lg font-bold text-gray-900">{{ formatMoney(deposit.amount) }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <UserIcon class="w-4 h-4 text-gray-400" />
                                            <div>
                                                <p class="text-xs text-gray-500">Tenant</p>
                                                <p class="text-sm font-medium text-gray-900">{{ deposit.tenant_name }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <HomeIcon class="w-4 h-4 text-gray-400" />
                                            <div>
                                                <p class="text-xs text-gray-500">Unit</p>
                                                <p class="text-sm font-medium text-gray-900">{{ deposit.unit_number }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="errors.general" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ errors.general }}
                                </div>
                                <div v-if="errors.error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ errors.error }}
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Amount</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.refund_amount"
                                            type="number"
                                            min="0"
                                            :max="maxRefund"
                                            step="0.01"
                                            :class="[
                                                'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors pr-24',
                                                errors.refund_amount
                                                    ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                    : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                            ]"
                                            placeholder="0.00"
                                        />
                                        <button
                                            type="button"
                                            @click="setFullRefund"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            Full Amount
                                        </button>
                                    </div>
                                    <p v-if="errors.refund_amount" class="mt-1 text-sm text-red-600">{{ errors.refund_amount }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deductions (if any)</label>
                                    <input
                                        v-model.number="form.deductions"
                                        type="number"
                                        min="0"
                                        :max="maxRefund"
                                        step="0.01"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.deductions
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                        placeholder="0.00"
                                    />
                                    <p v-if="errors.deductions" class="mt-1 text-sm text-red-600">{{ errors.deductions }}</p>
                                </div>

                                <div v-if="form.deductions > 0">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Deductions</label>
                                    <select
                                        v-model="form.deduction_reason"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.deduction_reason
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option value="">Select a reason</option>
                                        <option v-for="reason in deductionReasons" :key="reason" :value="reason">
                                            {{ reason }}
                                        </option>
                                    </select>
                                    <p v-if="errors.deduction_reason" class="mt-1 text-sm text-red-600">{{ errors.deduction_reason }}</p>
                                </div>

                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-emerald-800">Net Refund to Tenant</span>
                                        <span class="text-lg font-bold text-emerald-700">{{ formatMoney(netRefund) }}</span>
                                    </div>
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
                                        {{ isProcessing ? 'Processing...' : 'Process Refund' }}
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
