<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';
import type { PaymentConfiguration, PaymentMethodsLookup } from '@/types';

const props = withDefaults(defineProps<{
    paymentConfig?: PaymentConfiguration;
    paymentMethods?: PaymentMethodsLookup;
}>(), {
    paymentConfig: () => ({} as PaymentConfiguration),
    paymentMethods: () => ({} as PaymentMethodsLookup),
});

const { t } = useI18n();

const form = useForm({
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods || ['cash'],
    bank_name: props.paymentConfig?.bank_name || '',
    bank_account_name: props.paymentConfig?.bank_account_name || '',
    bank_account_number: props.paymentConfig?.bank_account_number || '',
    bank_branch: props.paymentConfig?.bank_branch || '',
    mpesa_paybill: props.paymentConfig?.mpesa_paybill || '',
    mpesa_account_name: props.paymentConfig?.mpesa_account_name || '',
    mpesa_consumer_key: '',
    mpesa_consumer_secret: '',
    paystack_enabled: props.paymentConfig?.paystack_enabled || false,
    paystack_public_key: props.paymentConfig?.paystack_public_key || '',
    paystack_secret_key: '',
    intasend_enabled: props.paymentConfig?.intasend_enabled || false,
    intasend_publishable_key: props.paymentConfig?.intasend_publishable_key || '',
    intasend_secret_key: '',
    intasend_webhook_challenge: props.paymentConfig?.intasend_webhook_challenge || '',
    intasend_environment: props.paymentConfig?.intasend_environment || 'sandbox',
    mpesa_b2c_shortcode: props.paymentConfig?.mpesa_b2c_shortcode || '',
    mpesa_b2c_initiator: props.paymentConfig?.mpesa_b2c_initiator || '',
    mpesa_b2c_password: '',
    mpesa_b2c_security_credential: '',
});

const methodIcons = {
    cash: BanknotesIcon,
    bank_transfer: BuildingLibraryIcon,
    mobile_money: DevicePhoneMobileIcon,
    paystack: CreditCardIcon,
    intasend_mpesa: GlobeAltIcon,
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
const showIntasendDetails = computed(() => isMethodEnabled('intasend_mpesa'));

const hasIntasendSecretKey = computed(() => !!props.paymentConfig?.intasend_secret_key_last4);
const hasPaystackSecretKey = computed(() => !!props.paymentConfig?.paystack_secret_key_last4);
const hasMpesaConsumerKey = computed(() => !!props.paymentConfig?.mpesa_consumer_key_last4);
const hasMpesaB2CPassword = computed(() => !!props.paymentConfig?.mpesa_b2c_password_last4);
const hasMpesaB2CCredential = computed(() => !!props.paymentConfig?.mpesa_b2c_security_credential_last4);

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
            <h3 class="text-lg font-semibold text-gray-900">{{ t('payment_methods.heading') }}</h3>
            <p class="mt-1 text-sm text-gray-600">
                {{ t('payment_methods.subheading') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Payment Methods Selection -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('payment_methods.accepted.heading') }}</h4>
                <p class="text-sm text-gray-500">{{ t('payment_methods.accepted.help') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(label, key) in paymentMethods"
                        :key="key"
                        @click="toggleMethod(key)"
                        :class="['relative border-2 rounded-xl p-4 cursor-pointer transition-all', isMethodEnabled(key) ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-100']"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="['p-2 rounded-lg', isMethodEnabled(key) ? 'bg-indigo-100' : 'bg-gray-100']">
                                <component
                                    :is="methodIcons[key]"
                                    :class="['w-6 h-6', isMethodEnabled(key) ? 'text-indigo-600' : 'text-gray-500']"
                                />
                            </div>
                            <div class="flex-1">
                                <h5 :class="['text-sm font-medium', isMethodEnabled(key) ? 'text-indigo-900' : 'text-gray-900']">
                                    {{ label }}
                                </h5>
                            </div>
                            <div :class="['w-5 h-5 rounded-full border-2 flex items-center justify-center', isMethodEnabled(key) ? 'border-indigo-600 bg-indigo-600' : 'border-gray-300']">
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
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('payment_methods.bank.heading') }}</h4>
                </div>
                <p class="text-sm text-gray-500">{{ t('payment_methods.bank.help') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="bank_name" :value="t('payment_methods.bank.name_label')" />
                        <TextInput
                            id="bank_name"
                            v-model="form.bank_name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.bank.name_placeholder')"
                        />
                        <InputError :message="form.errors.bank_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_branch" :value="t('payment_methods.bank.branch_label')" />
                        <TextInput
                            id="bank_branch"
                            v-model="form.bank_branch"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.bank.branch_placeholder')"
                        />
                        <InputError :message="form.errors.bank_branch" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_account_name" :value="t('payment_methods.bank.account_name_label')" />
                        <TextInput
                            id="bank_account_name"
                            v-model="form.bank_account_name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.bank.account_name_placeholder')"
                        />
                        <InputError :message="form.errors.bank_account_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="bank_account_number" :value="t('payment_methods.bank.account_number_label')" />
                        <TextInput
                            id="bank_account_number"
                            v-model="form.bank_account_number"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.bank.account_number_placeholder')"
                        />
                        <InputError :message="form.errors.bank_account_number" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- M-Pesa Details -->
            <div v-if="showMpesaDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <DevicePhoneMobileIcon class="w-5 h-5 text-green-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('payment_methods.mpesa.heading') }}</h4>
                </div>
                <p class="text-sm text-gray-500">{{ t('payment_methods.mpesa.help') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="mpesa_paybill" :value="t('payment_methods.mpesa.paybill_label')" />
                        <TextInput
                            id="mpesa_paybill"
                            v-model="form.mpesa_paybill"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.mpesa.paybill_placeholder')"
                        />
                        <InputError :message="form.errors.mpesa_paybill" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="mpesa_account_name" :value="t('payment_methods.mpesa.account_name_label')" />
                        <TextInput
                            id="mpesa_account_name"
                            v-model="form.mpesa_account_name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.mpesa.account_name_placeholder')"
                        />
                        <InputError :message="form.errors.mpesa_account_name" class="mt-2" />
                    </div>
                </div>

                <!-- M-Pesa API Credentials Section -->
                <div class="pt-4 border-t border-gray-200 space-y-4">
                    <div>
                        <h5 class="text-sm font-medium text-gray-700">{{ t('payment_methods.mpesa.stk.heading') }}</h5>
                        <p class="text-sm text-gray-500">
                            {{ t('payment_methods.mpesa.stk.help') }}
                            <a href="https://developer.safaricom.co.ke/APIs/MpesaExpressSimulate" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                {{ t('payment_methods.mpesa.stk.link') }}
                            </a>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Consumer Key -->
                        <div>
                            <InputLabel for="mpesa_consumer_key">
                                {{ t('payment_methods.mpesa.consumer_key_label') }}
                                <span v-if="props.paymentConfig?.mpesa_consumer_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_consumer_key_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="mpesa_consumer_key"
                                v-model="form.mpesa_consumer_key"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasMpesaConsumerKey ? '••••••••••••' : t('payment_methods.mpesa.consumer_key_placeholder')"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.mpesa.credential_keep') }}</p>
                            <InputError :message="form.errors.mpesa_consumer_key" class="mt-2" />
                        </div>

                        <!-- Consumer Secret -->
                        <div>
                            <InputLabel for="mpesa_consumer_secret">
                                {{ t('payment_methods.mpesa.consumer_secret_label') }}
                                <span v-if="props.paymentConfig?.mpesa_consumer_secret_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_consumer_secret_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="mpesa_consumer_secret"
                                v-model="form.mpesa_consumer_secret"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasMpesaConsumerKey ? '••••••••••••' : t('payment_methods.mpesa.consumer_secret_placeholder')"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.mpesa.credential_keep') }}</p>
                            <InputError :message="form.errors.mpesa_consumer_secret" class="mt-2" />
                        </div>
                    </div>

                    <!-- Status Indicator -->
                    <div class="flex items-center gap-2 pt-2">
                        <div :class="[
                            'w-2 h-2 rounded-full',
                            hasMpesaConsumerKey || (form.mpesa_consumer_key && form.mpesa_consumer_secret)
                                ? 'bg-green-500'
                                : 'bg-gray-300'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ hasMpesaConsumerKey || (form.mpesa_consumer_key && form.mpesa_consumer_secret)
                                ? t('payment_methods.mpesa.stk.status_enabled')
                                : t('payment_methods.mpesa.stk.status_disabled')
                            }}
                        </span>
                    </div>
                </div>

                <!-- B2C Refund Credentials Section -->
                <div class="pt-4 border-t border-gray-200 space-y-4">
                    <div>
                        <h5 class="text-sm font-medium text-gray-700">{{ t('payment_methods.mpesa.b2c.heading') }}</h5>
                        <p class="text-sm text-gray-500">
                            {{ t('payment_methods.mpesa.b2c.help') }}
                            <a href="https://developer.safaricom.co.ke/APIs/BusinessToCustomer" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                {{ t('payment_methods.mpesa.b2c.link') }}
                            </a>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="mpesa_b2c_shortcode" :value="t('payment_methods.mpesa.b2c.shortcode_label')" />
                            <TextInput
                                id="mpesa_b2c_shortcode"
                                v-model="form.mpesa_b2c_shortcode"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="t('payment_methods.mpesa.b2c.shortcode_placeholder')"
                            />
                            <InputError :message="form.errors.mpesa_b2c_shortcode" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="mpesa_b2c_initiator" :value="t('payment_methods.mpesa.b2c.initiator_label')" />
                            <TextInput
                                id="mpesa_b2c_initiator"
                                v-model="form.mpesa_b2c_initiator"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="t('payment_methods.mpesa.b2c.initiator_placeholder')"
                            />
                            <InputError :message="form.errors.mpesa_b2c_initiator" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="mpesa_b2c_password">
                                {{ t('payment_methods.mpesa.b2c.password_label') }}
                                <span v-if="props.paymentConfig?.mpesa_b2c_password_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_b2c_password_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="mpesa_b2c_password"
                                v-model="form.mpesa_b2c_password"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasMpesaB2CPassword ? '••••••••••••' : t('payment_methods.mpesa.b2c.password_placeholder')"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.mpesa.credential_keep') }}</p>
                            <InputError :message="form.errors.mpesa_b2c_password" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="mpesa_b2c_security_credential">
                                {{ t('payment_methods.mpesa.b2c.security_label') }}
                                <span v-if="props.paymentConfig?.mpesa_b2c_security_credential_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_b2c_security_credential_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="mpesa_b2c_security_credential"
                                v-model="form.mpesa_b2c_security_credential"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasMpesaB2CCredential ? '••••••••••••' : t('payment_methods.mpesa.b2c.security_placeholder')"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.mpesa.b2c.security_keep') }}</p>
                            <InputError :message="form.errors.mpesa_b2c_security_credential" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <div :class="[
                            'w-2 h-2 rounded-full',
                            (hasMpesaB2CPassword && hasMpesaB2CCredential) || (form.mpesa_b2c_password && form.mpesa_b2c_security_credential)
                                ? 'bg-green-500'
                                : 'bg-gray-300'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ (hasMpesaB2CPassword && hasMpesaB2CCredential) || (form.mpesa_b2c_password && form.mpesa_b2c_security_credential)
                                ? t('payment_methods.mpesa.b2c.status_enabled')
                                : t('payment_methods.mpesa.b2c.status_disabled')
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Paystack Details -->
            <div v-if="showPaystackDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <CreditCardIcon class="w-5 h-5 text-blue-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('payment_methods.paystack.heading') }}</h4>
                </div>
                <p class="text-sm text-gray-500">
                    {{ t('payment_methods.paystack.help') }}
                    <a href="https://dashboard.paystack.com/#/settings/developers" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                        {{ t('payment_methods.paystack.link') }}
                    </a>
                </p>

                <!-- Enable/Disable Toggle -->
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.paystack_enabled"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        <span class="ms-3 text-sm font-medium text-gray-700">
                            {{ form.paystack_enabled ? t('payment_methods.toggle.enabled') : t('payment_methods.toggle.disabled') }}
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.paystack_enabled" class="mt-2" />

                <!-- Paystack Configuration (shown when enabled) -->
                <div v-if="form.paystack_enabled" class="space-y-4 pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Public Key -->
                        <div>
                            <InputLabel for="paystack_public_key" :value="t('payment_methods.paystack.public_key_label')" />
                            <TextInput
                                id="paystack_public_key"
                                v-model="form.paystack_public_key"
                                type="text"
                                class="mt-1 block w-full font-mono text-sm"
                                placeholder="pk_live_... or pk_test_..."
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.paystack.public_key_hint') }}</p>
                            <InputError :message="form.errors.paystack_public_key" class="mt-2" />
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <InputLabel for="paystack_secret_key">
                                {{ t('payment_methods.paystack.secret_key_label') }}
                                <span v-if="props.paymentConfig?.paystack_secret_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.paystack_secret_key_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="paystack_secret_key"
                                v-model="form.paystack_secret_key"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasPaystackSecretKey ? '••••••••••••' : 'sk_live_... or sk_test_...'"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.paystack.secret_key_hint') }}</p>
                            <InputError :message="form.errors.paystack_secret_key" class="mt-2" />
                        </div>
                    </div>

                    <!-- Status Indicator -->
                    <div class="flex items-center gap-2 pt-2">
                        <div :class="[
                            'w-2 h-2 rounded-full',
                            form.paystack_public_key && (hasPaystackSecretKey || form.paystack_secret_key)
                                ? 'bg-green-500'
                                : 'bg-yellow-500'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ form.paystack_public_key && (hasPaystackSecretKey || form.paystack_secret_key)
                                ? t('payment_methods.paystack.status_ready')
                                : t('payment_methods.paystack.status_pending')
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- IntaSend M-Pesa Details -->
            <div v-if="showIntasendDetails" class="bg-gray-50 rounded-xl p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <GlobeAltIcon class="w-5 h-5 text-green-600" />
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('payment_methods.intasend.heading') }}</h4>
                </div>
                <p class="text-sm text-gray-500">
                    {{ t('payment_methods.intasend.help') }}
                    <a href="https://developers.intasend.com/docs" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                        {{ t('payment_methods.intasend.link') }}
                    </a>
                </p>

                <!-- Enable/Disable Toggle -->
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.intasend_enabled"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        <span class="ms-3 text-sm font-medium text-gray-700">
                            {{ form.intasend_enabled ? t('payment_methods.toggle.enabled') : t('payment_methods.toggle.disabled') }}
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.intasend_enabled" class="mt-2" />

                <!-- IntaSend Configuration (shown when enabled) -->
                <div v-if="form.intasend_enabled" class="space-y-4 pt-4 border-t border-gray-200">
                    <!-- Environment -->
                    <div>
                        <InputLabel for="intasend_environment" :value="t('payment_methods.intasend.environment_label')" />
                        <select
                            id="intasend_environment"
                            v-model="form.intasend_environment"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="sandbox">{{ t('payment_methods.intasend.environment_sandbox') }}</option>
                            <option value="production">{{ t('payment_methods.intasend.environment_production') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.intasend.environment_hint') }}</p>
                        <InputError :message="form.errors.intasend_environment" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Publishable Key -->
                        <div>
                            <InputLabel for="intasend_publishable_key" :value="t('payment_methods.intasend.publishable_key_label')" />
                            <TextInput
                                id="intasend_publishable_key"
                                v-model="form.intasend_publishable_key"
                                type="text"
                                class="mt-1 block w-full font-mono text-sm"
                                placeholder="ISPubKey_..."
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.intasend.publishable_key_hint') }}</p>
                            <InputError :message="form.errors.intasend_publishable_key" class="mt-2" />
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <InputLabel for="intasend_secret_key">
                                {{ t('payment_methods.intasend.secret_key_label') }}
                                <span v-if="props.paymentConfig?.intasend_secret_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.intasend_secret_key_last4 }})</span>
                            </InputLabel>
                            <TextInput
                                id="intasend_secret_key"
                                v-model="form.intasend_secret_key"
                                type="password"
                                class="mt-1 block w-full font-mono text-sm"
                                :placeholder="hasIntasendSecretKey ? '••••••••••••' : 'ISSecretKey_...'"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('payment_methods.intasend.secret_key_hint') }}</p>
                            <InputError :message="form.errors.intasend_secret_key" class="mt-2" />
                        </div>
                    </div>

                    <!-- Webhook Challenge -->
                    <div>
                        <InputLabel for="intasend_webhook_challenge" :value="t('payment_methods.intasend.webhook_label')" />
                        <TextInput
                            id="intasend_webhook_challenge"
                            v-model="form.intasend_webhook_challenge"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('payment_methods.intasend.webhook_placeholder')"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('payment_methods.intasend.webhook_hint') }}
                        </p>
                        <InputError :message="form.errors.intasend_webhook_challenge" class="mt-2" />
                    </div>

                    <!-- Status Indicator -->
                    <div class="flex items-center gap-2 pt-2">
                        <div :class="[
                            'w-2 h-2 rounded-full',
                            form.intasend_publishable_key && (hasIntasendSecretKey || form.intasend_secret_key)
                                ? 'bg-green-500'
                                : 'bg-yellow-500'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ form.intasend_publishable_key && (hasIntasendSecretKey || form.intasend_secret_key)
                                ? t('payment_methods.intasend.status_ready')
                                : t('payment_methods.intasend.status_pending')
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                >
                    {{ form.processing ? t('payment_methods.save_processing') : t('payment_methods.save') }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
