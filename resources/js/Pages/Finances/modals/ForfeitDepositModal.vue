<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
import type { Deposit } from '@/types/finances';

interface ForfeitForm {
    reason: string;
}

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const store = useFinancesStore();
const { t } = useI18n();
const { formatMoney } = useFormatters();

const modalData = computed(() => store.modals.forfeitDeposit);

const form = ref({
    reason: '',
});

const errors = ref({});
const success = ref(false);
const isProcessing = ref(false);

const forfeitReasons = computed(() => [
    { value: 'Outstanding rent arrears', label: t('finances_forfeit_deposit.reasons.rent_arrears') },
    { value: 'Severe property damage', label: t('finances_forfeit_deposit.reasons.property_damage') },
    { value: 'Lease violation', label: t('finances_forfeit_deposit.reasons.lease_violation') },
    { value: 'Abandonment', label: t('finances_forfeit_deposit.reasons.abandonment') },
    { value: 'Illegal activity', label: t('finances_forfeit_deposit.reasons.illegal_activity') },
    { value: 'Other', label: t('finances_forfeit_deposit.reasons.other') },
]);

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
        errors.value.reason = t('finances_forfeit_deposit.errors.reason_required');
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
    <Modal :show="modalData.show" max-width="md" @close="close">
                        <div v-if="success" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                                <CheckIcon class="w-8 h-8 text-red-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_forfeit_deposit.success_title') }}</h3>
                            <p class="text-sm text-gray-500 mt-2">{{ t('finances_forfeit_deposit.success_body') }}</p>
                        </div>

                        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-red-100 rounded-lg">
                                        <XCircleIcon class="w-5 h-5 text-red-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">{{ t('finances_forfeit_deposit.heading') }}</h2>
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
                                        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 shrink-0" />
                                        <p class="text-sm text-red-800">
                                            {{ t('finances_forfeit_deposit.warning') }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="deposit" class="p-4 bg-gray-50 rounded-lg space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-blue-100 rounded-lg">
                                            <BanknotesIcon class="w-5 h-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">{{ t('finances_forfeit_deposit.deposit_amount') }}</p>
                                            <p class="text-lg font-bold text-gray-900">{{ formatMoney(deposit.amount) }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <UserIcon class="w-4 h-4 text-gray-400" />
                                            <div>
                                                <p class="text-xs text-gray-500">{{ t('finances_forfeit_deposit.tenant') }}</p>
                                                <p class="text-sm font-medium text-gray-900">{{ deposit.tenant_name }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <HomeIcon class="w-4 h-4 text-gray-400" />
                                            <div>
                                                <p class="text-xs text-gray-500">{{ t('finances_forfeit_deposit.unit') }}</p>
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
                                        {{ t('finances_forfeit_deposit.reason_label') }} <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="form.reason"
                                        :class="[
                                            /* i18n-ignore */ 'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.reason
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option value="">{{ t('finances_forfeit_deposit.select_reason') }}</option>
                                        <option v-for="reason in forfeitReasons" :key="reason.value" :value="reason.value">
                                            {{ reason.label }}
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
                                        {{ t('finances_forfeit_deposit.cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="isProcessing"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? t('finances_forfeit_deposit.processing') : t('finances_forfeit_deposit.forfeit_deposit') }}
                                    </button>
                                </div>
                            </form>
                        </template>
    </Modal>
</template>
