<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    paymentConfig: {
        type: Object,
        default: () => ({}),
    },
    paymentMethods: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods || ['cash'],
    bank_name: props.paymentConfig?.bank_name || '',
    bank_account_name: props.paymentConfig?.bank_account_name || '',
    bank_account_number: props.paymentConfig?.bank_account_number || '',
    bank_branch: props.paymentConfig?.bank_branch || '',
    mpesa_paybill: props.paymentConfig?.mpesa_paybill || '',
    mpesa_account_name: props.paymentConfig?.mpesa_account_name || '',
    paystack_enabled: props.paymentConfig?.paystack_enabled || false,
});

const methodIcons = {
    cash: BanknotesIcon,
    bank_transfer: BuildingLibraryIcon,
    mobile_money: DevicePhoneMobileIcon,
    paystack: CreditCardIcon,
};

const toggleMethod = (method) => {
    const index = form.accepted_payment_methods.indexOf(method);
    if (index > -1) {
        // Don't allow removing the last method
        if (form.accepted_payment_methods.length > 1) {
            form.accepted_payment_methods.splice(index, 1);
        }
    } else {
        form.accepted_payment_methods.push(method);
    }
};

const isMethodEnabled = (method) => {
    return form.accepted_payment_methods.includes(method);
};

const showBankDetails = computed(() => isMethodEnabled('bank_transfer'));
const showMpesaDetails = computed(() => isMethodEnabled('mobile_money'));
const showPaystackDetails = computed(() => isMethodEnabled('paystack'));

const submit = () => {
    form.post(route('settings.payment.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Payment Methods</h3>
            <p class="mt-1 text-sm text-gray-600">
                Configure how tenants can pay their rent and other charges.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Payment Methods Selection -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Accepted Payment Methods</h4>
                <p class="text-sm text-gray-500">Select at least one payment method that tenants can use.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(label, key) in paymentMethods"
                        :key="key"
                        @click="toggleMethod(key)"
                        :class="[
                            'relative border-2 rounded-xl p-4 cursor-pointer transition-all',
                            isMethodEnabled(key)
                                ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600'
                                : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-100'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="[
                                'p-2 rounded-lg',
                                isMethodEnabled(key) ? 'bg-indigo-100' : 'bg-gray-100'
                            ]">
                                <component
                                    :is="methodIcons[key]"
                                    :class="[
                                        'w-6 h-6',
                                        isMethodEnabled(key) ? 'text-indigo-600' : 'text-gray-500'
                                    ]"
                                />
                            </div>
                            <div class="flex-1">
                                <h5 :class="[
                                    'text-sm font-medium',
                                    isMethodEnabled(key) ? 'text-indigo-900' : 'text-gray-900'
                                ]">
                                    {{ label }}
                                </h5>
                            </div>
                            <div :class="[
                                'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                                isMethodEnabled(key)
                                    ? 'border-indigo-600 bg-indigo-600'
                                    : 'border-gray-300'
                            ]">
                                <svg v-if="isMethodEnabled(key)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.accepted_payment_methods" class="mt-2" />
            </div>

            <!-- Bank Transfer Details -->
            <div v-if="showBankDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <BuildingLibraryIcon class="w-5 h-5 text-gray-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Bank Account Details</h4>
                </div>
                <p class="text-sm text-gray-500">These details will be shown to tenants when they choose bank transfer.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="bank_name" value="Bank Name" />
                        <TextInput
                            id="bank_name"
                            v-model="form.bank_name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Equity Bank"
                        />
                        <InputError :message="form.errors.bank_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_branch" value="Branch" />
                        <TextInput
                            id="bank_branch"
                            v-model="form.bank_branch"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Westlands Branch"
                        />
                        <InputError :message="form.errors.bank_branch" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_account_name" value="Account Name" />
                        <TextInput
                            id="bank_account_name"
                            v-model="form.bank_account_name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., ABC Properties Ltd"
                        />
                        <InputError :message="form.errors.bank_account_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_account_number" value="Account Number" />
                        <TextInput
                            id="bank_account_number"
                            v-model="form.bank_account_number"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., 1234567890"
                        />
                        <InputError :message="form.errors.bank_account_number" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- M-Pesa Details -->
            <div v-if="showMpesaDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <DevicePhoneMobileIcon class="w-5 h-5 text-green-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">M-Pesa Details</h4>
                </div>
                <p class="text-sm text-gray-500">These details will be shown to tenants when they choose M-Pesa payment.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="mpesa_paybill" value="Paybill / Till Number" />
                        <TextInput
                            id="mpesa_paybill"
                            v-model="form.mpesa_paybill"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., 123456"
                        />
                        <InputError :message="form.errors.mpesa_paybill" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="mpesa_account_name" value="Account Name (for reference)" />
                        <TextInput
                            id="mpesa_account_name"
                            v-model="form.mpesa_account_name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Unit Number or Tenant Name"
                        />
                        <InputError :message="form.errors.mpesa_account_name" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Paystack Details -->
            <div v-if="showPaystackDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <CreditCardIcon class="w-5 h-5 text-blue-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Paystack Online Payments</h4>
                </div>
                <p class="text-sm text-gray-500">Enable online card payments via Paystack. API keys are configured in Admin Settings.</p>

                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.paystack_enabled"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            {{ form.paystack_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.paystack_enabled" class="mt-2" />
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                >
                    {{ form.processing ? 'Saving...' : 'Save Payment Methods' }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
