<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    XCircleIcon,
    CheckIcon,
    BanknotesIcon,
    UserIcon,
    HomeIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const emit = defineEmits(['close', 'success']);

const store = useFinancesStore();
const { formatMoney } = useFormatters();

const modalData = computed(() => store.modals.forfeitDeposit);

const form = ref({
    reason: '',
});

const errors = ref({});
const success = ref(false);
const isProcessing = ref(false);

const forfeitReasons = [
    'Outstanding rent arrears',
    'Severe property damage',
    'Lease violation',
    'Abandonment',
    'Illegal activity',
    'Other',
];

watch(() => modalData.value.show, (newVal) => {
    if (newVal) {
        resetForm();
    }
});

const deposit = computed(() => modalData.value.deposit);

const resetForm = () => {
    form.value = {
        reason: '',
    };
    errors.value = {};
    success.value = false;
};

const close = () => {
    store.closeModal('forfeitDeposit');
    emit('close');
};

const validate = () => {
    errors.value = {};

    if (!form.value.reason) {
        errors.value.reason = 'Please provide a reason for forfeiting the deposit';
    }

    return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
    if (!validate()) return;

    isProcessing.value = true;

    router.post(route('finances.deposits.forfeit', deposit.value.id), {
        reason: form.value.reason,
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
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-red-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Deposit Forfeited</h3>
                            <p class="text-sm text-gray-500 mt-2">The deposit has been forfeited.</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-red-100 rounded-lg">
                                        <XCircleIcon class="w-5 h-5 text-red-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Forfeit Deposit</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <div class="flex gap-2">
                                        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 flex-shrink-0" />
                                        <p class="text-sm text-red-800">
                                            This action will forfeit the entire deposit. This cannot be undone.
                                        </p>
                                    </div>
                                </div>

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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Reason for Forfeiture <span class="text-red-500">*</span>
                                    </label>
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
                                        <option v-for="reason in forfeitReasons" :key="reason" :value="reason">
                                            {{ reason }}
                                        </option>
                                    </select>
                                    <p v-if="errors.reason" class="mt-1 text-sm text-red-600">{{ errors.reason }}</p>
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
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? 'Processing...' : 'Forfeit Deposit' }}
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
