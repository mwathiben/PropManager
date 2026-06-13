<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import {
    BanknotesIcon,
    PlusIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ClockIcon,
    StarIcon,
    TrashIcon,
    ArrowPathIcon,
    CreditCardIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';

interface PaymentConfig {
    accepted_payment_methods: string[];
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_branch: string | null;
    mpesa_paybill: string | null;
    mpesa_account_name: string | null;
    mpesa_shortcode: string | null;
    mpesa_shortcode_type: string | null;
    mpesa_b2c_shortcode: string | null;
    mpesa_b2c_initiator: string | null;
    mpesa_environment: string | null;
    paystack_enabled: boolean;
    paystack_public_key: string | null;
    intasend_enabled: boolean;
    intasend_publishable_key: string | null;
    intasend_webhook_challenge: string | null;
    intasend_environment: string | null;
    // Last-4 masked values (secrets stripped server-side)
    paystack_secret_key_last4: string | null;
    mpesa_consumer_key_last4: string | null;
    mpesa_consumer_secret_last4: string | null;
    intasend_secret_key_last4: string | null;
    mpesa_b2c_password_last4: string | null;
    mpesa_b2c_security_credential_last4: string | null;
}

interface PayoutAccount {
    id: number;
    provider: string;
    provider_label: string;
    account_type: string;
    account_name: string;
    masked_account_number: string;
    bank_name: string;
    business_name: string;
    verification_status: string;
    status_label: string;
    status_color: string;
    is_primary: boolean;
    is_active: boolean;
    can_receive_payments: boolean;
    created_at: string;
}

interface BillingSettings {
    transaction_fee_percentage: number;
    minimum_fee: number;
    billing_model: string;
}

interface Bank {
    code: string;
    name: string;
}

interface Props {
    paymentMethods: Record<string, string>;
    paymentConfig: PaymentConfig | null;
    payoutAccounts?: PayoutAccount[];
    billingSettings?: BillingSettings;
}

const props = withDefaults(defineProps<Props>(), {
    payoutAccounts: () => [],
});

const { formatMoney } = useFormatters();
const { t } = useI18n();

// ── Credential + methods form ─────────────────────────────────────────────────

const credForm = useForm({
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods ?? ['cash'],
    bank_name: props.paymentConfig?.bank_name ?? '',
    bank_account_name: props.paymentConfig?.bank_account_name ?? '',
    bank_account_number: props.paymentConfig?.bank_account_number ?? '',
    bank_branch: props.paymentConfig?.bank_branch ?? '',
    mpesa_paybill: props.paymentConfig?.mpesa_paybill ?? '',
    mpesa_account_name: props.paymentConfig?.mpesa_account_name ?? '',
    mpesa_shortcode: props.paymentConfig?.mpesa_shortcode ?? '',
    mpesa_shortcode_type: props.paymentConfig?.mpesa_shortcode_type ?? 'paybill',
    mpesa_environment: props.paymentConfig?.mpesa_environment ?? 'sandbox',
    // Secrets start empty — blank = keep existing (server-side blank-preserve)
    mpesa_consumer_key: '',
    mpesa_consumer_secret: '',
    mpesa_passkey: '',
    mpesa_b2c_shortcode: props.paymentConfig?.mpesa_b2c_shortcode ?? '',
    mpesa_b2c_initiator: props.paymentConfig?.mpesa_b2c_initiator ?? '',
    mpesa_b2c_password: '',
    mpesa_b2c_security_credential: '',
    paystack_enabled: props.paymentConfig?.paystack_enabled ?? false,
    paystack_public_key: props.paymentConfig?.paystack_public_key ?? '',
    paystack_secret_key: '',
    intasend_enabled: props.paymentConfig?.intasend_enabled ?? false,
    intasend_publishable_key: props.paymentConfig?.intasend_publishable_key ?? '',
    intasend_secret_key: '',
    intasend_webhook_challenge: props.paymentConfig?.intasend_webhook_challenge ?? '',
    intasend_environment: props.paymentConfig?.intasend_environment ?? 'sandbox',
});

const wantsBankTransfer = computed(() => credForm.accepted_payment_methods.includes('bank_transfer'));
const wantsMobileMoney = computed(() => credForm.accepted_payment_methods.includes('mobile_money'));
const wantsPaystack = computed(() => credForm.accepted_payment_methods.includes('paystack'));
const wantsIntasend = computed(() => credForm.accepted_payment_methods.includes('intasend_mpesa'));

const hasPaystackSecretKey = computed(() => !!props.paymentConfig?.paystack_secret_key_last4);
const hasMpesaConsumerKey = computed(() => !!props.paymentConfig?.mpesa_consumer_key_last4);
const hasMpesaB2CPassword = computed(() => !!props.paymentConfig?.mpesa_b2c_password_last4);
const hasMpesaB2CCredential = computed(() => !!props.paymentConfig?.mpesa_b2c_security_credential_last4);
const hasIntasendSecretKey = computed(() => !!props.paymentConfig?.intasend_secret_key_last4);

const methodEntries = computed(() =>
    Object.entries(props.paymentMethods).map(([value, label]) => ({ value, label }))
);

const submitCredForm = () => {
    credForm.post(route('payments-hub.payment-methods.update'), {
        preserveScroll: true,
    });
};

// ── Payout accounts ───────────────────────────────────────────────────────────

const showAddModal = ref(false);
const banks = ref<Bank[]>([]);
const loadingBanks = ref(false);
const verifyingAccount = ref(false);
const verifiedAccountName = ref('');

const addForm = useForm({
    business_name: '',
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_name: '',
});

const selectedBank = computed(() => banks.value.find(b => b.code === addForm.bank_code));

const openAddModal = async () => {
    showAddModal.value = true;
    await loadBanks();
};

const closeAddModal = () => {
    showAddModal.value = false;
    addForm.reset();
    verifiedAccountName.value = '';
};

const loadBanks = async () => {
    if (banks.value.length > 0) {
        return;
    }
    loadingBanks.value = true;
    try {
        const response = await fetch(route('payments-hub.banks'));
        const data = await response.json() as { status: string; banks: Bank[] };
        banks.value = data.banks ?? [];
    } catch {
        banks.value = [];
    } finally {
        loadingBanks.value = false;
    }
};

const onBankSelected = () => {
    if (selectedBank.value) {
        addForm.bank_name = selectedBank.value.name;
    }
    verifiedAccountName.value = '';
};

const verifyAccount = async () => {
    if (!addForm.account_number || !addForm.bank_code) {
        return;
    }
    verifyingAccount.value = true;
    verifiedAccountName.value = '';

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
        const response = await fetch(route('payments-hub.verify-account'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ account_number: addForm.account_number, bank_code: addForm.bank_code }),
        });
        const data = await response.json() as { status: string; success: boolean; account_name?: string; message?: string };
        if (data.status === 'success') {
            verifiedAccountName.value = data.account_name ?? '';
            addForm.account_name = data.account_name ?? '';
        } else {
            alert(data.message ?? 'Could not verify account. Please check the details.');
        }
    } catch {
        alert('Verification request failed. Please try again.');
    } finally {
        verifyingAccount.value = false;
    }
};

const submitAddForm = () => {
    addForm.post(route('payments-hub.payout.store'), {
        onSuccess: () => closeAddModal(),
    });
};

const setPrimary = (accountId: number) => {
    router.post(route('payments-hub.payout.primary', accountId));
};

const syncAccount = (accountId: number) => {
    router.post(route('payments-hub.payout.sync', accountId));
};

const deleteAccount = (accountId: number) => {
    if (confirm(t('payments_hub.collection.deactivate_confirm'))) {
        router.delete(route('payments-hub.payout.destroy', accountId));
    }
};

const statusIcon = (status: string) => {
    if (status === 'verified') return CheckCircleIcon;
    if (status === 'pending') return ClockIcon;
    return ExclamationCircleIcon;
};
</script>

<template>
    <div class="space-y-8">
        <!-- Billing info banner -->
        <div
            v-if="billingSettings"
            class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-4"
        >
            <div class="flex items-start gap-3">
                <BanknotesIcon class="w-6 h-6 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                <div>
                    <h3 class="font-medium text-blue-900 dark:text-blue-200">{{ t('payments_hub.collection.platform_fees_title') }}</h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        {{ t('payments_hub.collection.billing_model') }} <strong>{{ billingSettings.billing_model }}</strong>
                    </p>
                    <p v-if="billingSettings.billing_model !== 'subscription'" class="text-sm text-blue-700 dark:text-blue-300">
                        {{ t('payments_hub.collection.fee_label') }}: <strong>{{ billingSettings.transaction_fee_percentage }}%</strong> {{ t('payments_hub.collection.per_transaction') }}
                        ({{ t('payments_hub.collection.min_label') }} {{ formatMoney(billingSettings.minimum_fee) }})
                    </p>
                </div>
            </div>
        </div>

        <!-- Gateway credentials + accepted methods form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.collection.accepted_methods_title') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ t('payments_hub.collection.accepted_methods_subtitle') }}
                </p>
            </div>

            <form class="p-6 space-y-6" @submit.prevent="submitCredForm">
                <!-- Method checkboxes -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label
                        v-for="method in methodEntries"
                        :key="method.value"
                        class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                    >
                        <input
                            v-model="credForm.accepted_payment_methods"
                            type="checkbox"
                            :value="method.value"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div class="flex items-center gap-2">
                            <BuildingLibraryIcon v-if="method.value === 'bank_transfer'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <DevicePhoneMobileIcon v-else-if="method.value === 'mobile_money'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <CreditCardIcon v-else-if="method.value === 'paystack'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <GlobeAltIcon v-else-if="method.value === 'intasend_mpesa'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <BanknotesIcon v-else class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ method.label }}</span>
                        </div>
                    </label>
                </div>
                <p v-if="credForm.errors.accepted_payment_methods" class="text-sm text-red-600">
                    {{ credForm.errors.accepted_payment_methods }}
                </p>

                <!-- Bank transfer details -->
                <div v-if="wantsBankTransfer" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <BuildingLibraryIcon class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.bank_transfer_details') }}</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="cred_bank_name" :value="t('payments_hub.collection.bank_name')" />
                            <TextInput id="cred_bank_name" v-model="credForm.bank_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.bank_name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="cred_bank_branch" :value="t('payments_hub.collection.branch')" />
                            <TextInput id="cred_bank_branch" v-model="credForm.bank_branch" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.bank_branch" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="cred_bank_account_name" :value="t('payments_hub.collection.account_name')" />
                            <TextInput id="cred_bank_account_name" v-model="credForm.bank_account_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.bank_account_name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="cred_bank_account_number" :value="t('payments_hub.collection.account_number')" />
                            <TextInput id="cred_bank_account_number" v-model="credForm.bank_account_number" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.bank_account_number" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- M-Pesa details + API credentials -->
                <div v-if="wantsMobileMoney" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <DevicePhoneMobileIcon class="w-5 h-5 text-green-600 dark:text-green-400" />
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.mpesa_details') }}</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="cred_mpesa_paybill" :value="t('payments_hub.collection.mpesa_paybill')" />
                            <TextInput id="cred_mpesa_paybill" v-model="credForm.mpesa_paybill" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.mpesa_paybill" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="cred_mpesa_account_name" :value="t('payments_hub.collection.mpesa_account_name')" />
                            <TextInput id="cred_mpesa_account_name" v-model="credForm.mpesa_account_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="credForm.errors.mpesa_account_name" class="mt-1" />
                        </div>
                    </div>

                    <!-- STK Push API credentials -->
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600 space-y-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.mpesa_stk_heading') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cred_mpesa_consumer_key">
                                    {{ t('payments_hub.collection.mpesa_consumer_key') }}
                                    <span v-if="props.paymentConfig?.mpesa_consumer_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_consumer_key_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_mpesa_consumer_key"
                                    v-model="credForm.mpesa_consumer_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasMpesaConsumerKey ? '••••••••••••' : t('payments_hub.collection.mpesa_consumer_key_placeholder')"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.mpesa_consumer_key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_mpesa_consumer_secret">
                                    {{ t('payments_hub.collection.mpesa_consumer_secret') }}
                                    <span v-if="props.paymentConfig?.mpesa_consumer_secret_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_consumer_secret_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_mpesa_consumer_secret"
                                    v-model="credForm.mpesa_consumer_secret"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasMpesaConsumerKey ? '••••••••••••' : t('payments_hub.collection.mpesa_consumer_secret_placeholder')"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.mpesa_consumer_secret" class="mt-1" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', hasMpesaConsumerKey || (credForm.mpesa_consumer_key && credForm.mpesa_consumer_secret) ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ hasMpesaConsumerKey || (credForm.mpesa_consumer_key && credForm.mpesa_consumer_secret) ? t('payments_hub.collection.mpesa_stk_enabled') : t('payments_hub.collection.mpesa_stk_disabled') }}
                            </span>
                        </div>
                    </div>

                    <!-- B2C credentials -->
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600 space-y-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.mpesa_b2c_heading') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cred_mpesa_b2c_shortcode" :value="t('payments_hub.collection.mpesa_b2c_shortcode')" />
                                <TextInput id="cred_mpesa_b2c_shortcode" v-model="credForm.mpesa_b2c_shortcode" type="text" class="mt-1 block w-full" />
                                <InputError :message="credForm.errors.mpesa_b2c_shortcode" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_mpesa_b2c_initiator" :value="t('payments_hub.collection.mpesa_b2c_initiator')" />
                                <TextInput id="cred_mpesa_b2c_initiator" v-model="credForm.mpesa_b2c_initiator" type="text" class="mt-1 block w-full" />
                                <InputError :message="credForm.errors.mpesa_b2c_initiator" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_mpesa_b2c_password">
                                    {{ t('payments_hub.collection.mpesa_b2c_password') }}
                                    <span v-if="props.paymentConfig?.mpesa_b2c_password_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_b2c_password_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_mpesa_b2c_password"
                                    v-model="credForm.mpesa_b2c_password"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasMpesaB2CPassword ? '••••••••••••' : t('payments_hub.collection.mpesa_b2c_password_placeholder')"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.mpesa_b2c_password" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_mpesa_b2c_security_credential">
                                    {{ t('payments_hub.collection.mpesa_b2c_security') }}
                                    <span v-if="props.paymentConfig?.mpesa_b2c_security_credential_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.mpesa_b2c_security_credential_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_mpesa_b2c_security_credential"
                                    v-model="credForm.mpesa_b2c_security_credential"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasMpesaB2CCredential ? '••••••••••••' : t('payments_hub.collection.mpesa_b2c_security_placeholder')"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.mpesa_b2c_security_credential" class="mt-1" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', (hasMpesaB2CPassword && hasMpesaB2CCredential) || (credForm.mpesa_b2c_password && credForm.mpesa_b2c_security_credential) ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ (hasMpesaB2CPassword && hasMpesaB2CCredential) || (credForm.mpesa_b2c_password && credForm.mpesa_b2c_security_credential) ? t('payments_hub.collection.mpesa_b2c_enabled') : t('payments_hub.collection.mpesa_b2c_disabled') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Paystack credentials -->
                <div v-if="wantsPaystack" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <CreditCardIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.paystack_heading') }}</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="credForm.paystack_enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ credForm.paystack_enabled ? t('payments_hub.collection.toggle_enabled') : t('payments_hub.collection.toggle_disabled') }}
                            </span>
                        </label>
                    </div>
                    <div v-if="credForm.paystack_enabled" class="space-y-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cred_paystack_public_key" :value="t('payments_hub.collection.paystack_public_key')" />
                                <TextInput id="cred_paystack_public_key" v-model="credForm.paystack_public_key" type="text" class="mt-1 block w-full font-mono text-sm" placeholder="pk_live_... or pk_test_..." />
                                <InputError :message="credForm.errors.paystack_public_key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_paystack_secret_key">
                                    {{ t('payments_hub.collection.paystack_secret_key') }}
                                    <span v-if="props.paymentConfig?.paystack_secret_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.paystack_secret_key_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_paystack_secret_key"
                                    v-model="credForm.paystack_secret_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasPaystackSecretKey ? '••••••••••••' : 'sk_live_... or sk_test_...'"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.paystack_secret_key" class="mt-1" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', credForm.paystack_public_key && (hasPaystackSecretKey || credForm.paystack_secret_key) ? 'bg-green-500' : 'bg-yellow-500']"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ credForm.paystack_public_key && (hasPaystackSecretKey || credForm.paystack_secret_key) ? t('payments_hub.collection.paystack_ready') : t('payments_hub.collection.paystack_pending') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- IntaSend credentials -->
                <div v-if="wantsIntasend" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <GlobeAltIcon class="w-5 h-5 text-green-600 dark:text-green-400" />
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.intasend_heading') }}</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="credForm.intasend_enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ credForm.intasend_enabled ? t('payments_hub.collection.toggle_enabled') : t('payments_hub.collection.toggle_disabled') }}
                            </span>
                        </label>
                    </div>
                    <div v-if="credForm.intasend_enabled" class="space-y-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <div>
                            <InputLabel for="cred_intasend_environment" :value="t('payments_hub.collection.intasend_environment')" />
                            <select id="cred_intasend_environment" v-model="credForm.intasend_environment" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="sandbox">{{ t('payments_hub.collection.environment_sandbox') }}</option>
                                <option value="production">{{ t('payments_hub.collection.environment_production') }}</option>
                            </select>
                            <InputError :message="credForm.errors.intasend_environment" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cred_intasend_publishable_key" :value="t('payments_hub.collection.intasend_publishable_key')" />
                                <TextInput id="cred_intasend_publishable_key" v-model="credForm.intasend_publishable_key" type="text" class="mt-1 block w-full font-mono text-sm" placeholder="ISPubKey_..." />
                                <InputError :message="credForm.errors.intasend_publishable_key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="cred_intasend_secret_key">
                                    {{ t('payments_hub.collection.intasend_secret_key') }}
                                    <span v-if="props.paymentConfig?.intasend_secret_key_last4" class="ms-2 text-xs text-green-600">({{ props.paymentConfig.intasend_secret_key_last4 }})</span>
                                </InputLabel>
                                <TextInput
                                    id="cred_intasend_secret_key"
                                    v-model="credForm.intasend_secret_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    :placeholder="hasIntasendSecretKey ? '••••••••••••' : 'ISSecretKey_...'"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.credential_keep_hint') }}</p>
                                <InputError :message="credForm.errors.intasend_secret_key" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="cred_intasend_webhook_challenge" :value="t('payments_hub.collection.intasend_webhook')" />
                            <TextInput id="cred_intasend_webhook_challenge" v-model="credForm.intasend_webhook_challenge" type="text" class="mt-1 block w-full" :placeholder="t('payments_hub.collection.intasend_webhook_placeholder')" />
                            <InputError :message="credForm.errors.intasend_webhook_challenge" class="mt-1" />
                        </div>
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', credForm.intasend_publishable_key && (hasIntasendSecretKey || credForm.intasend_secret_key) ? 'bg-green-500' : 'bg-yellow-500']"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ credForm.intasend_publishable_key && (hasIntasendSecretKey || credForm.intasend_secret_key) ? t('payments_hub.collection.intasend_ready') : t('payments_hub.collection.intasend_pending') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="credForm.processing"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ credForm.processing ? t('payments_hub.collection.saving') : t('payments_hub.collection.save_methods') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Payout accounts card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.collection.payout_accounts_title') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ t('payments_hub.collection.payout_accounts_subtitle') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    @click="openAddModal"
                >
                    <PlusIcon class="w-4 h-4" />
                    {{ t('payments_hub.collection.add_account') }}
                </button>
            </div>

            <div v-if="payoutAccounts.length > 0">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="account in payoutAccounts" :key="account.id" class="px-6 py-5">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="w-11 h-11 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center shrink-0">
                                    <BanknotesIcon class="w-6 h-6 text-gray-500 dark:text-gray-400" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ account.business_name }}
                                        </p>
                                        <span
                                            v-if="account.is_primary"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300"
                                        >
                                            <StarIcon class="w-3 h-3" />
                                            {{ t('payments_hub.collection.primary_badge') }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ account.bank_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500">
                                        {{ account.account_name }} &middot; {{ account.masked_account_number }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 shrink-0">
                                <div class="flex items-center gap-1" :class="account.status_color">
                                    <component :is="statusIcon(account.verification_status)" class="w-4 h-4" />
                                    <span class="text-sm">{{ account.status_label }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="!account.is_primary && account.can_receive_payments"
                                        type="button"
                                        class="p-2 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                        :title="t('payments_hub.collection.set_primary_title')"
                                        @click="setPrimary(account.id)"
                                    >
                                        <StarIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                        :title="t('payments_hub.collection.sync_status_title')"
                                        @click="syncAccount(account.id)"
                                    >
                                        <ArrowPathIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="!account.is_primary"
                                        type="button"
                                        class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                        :title="t('payments_hub.collection.deactivate_title')"
                                        @click="deleteAccount(account.id)"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-else>
                <EmptyState
                    :icon="BanknotesIcon"
                    :title="t('payments_hub.collection.no_payout_title')"
                    :description="t('payments_hub.collection.no_payout_desc')"
                    :action-label="t('payments_hub.collection.add_payout_action')"
                    @action="openAddModal"
                />
            </div>
        </div>
    </div>

    <!-- Add payout account modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <button
                type="button"
                class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70 z-40 w-full cursor-default"
                :aria-label="t('payments_hub.collection.cancel')"
                @click="closeAddModal"
                @keydown.escape="closeAddModal"
            />

            <div class="relative z-50 bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-5">{{ t('payments_hub.collection.modal_title') }}</h3>

                <form class="space-y-4" @submit.prevent="submitAddForm">
                    <div>
                        <label for="add_business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.business_name') }}</label>
                        <input
                            id="add_business_name"
                            v-model="addForm.business_name"
                            type="text"
                            :placeholder="t('payments_hub.collection.business_name_placeholder')"
                            required
                            class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="addForm.errors.business_name" class="mt-1 text-xs text-red-600">{{ addForm.errors.business_name }}</p>
                    </div>

                    <div>
                        <label for="add_bank_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.bank_label') }}</label>
                        <select
                            id="add_bank_code"
                            v-model="addForm.bank_code"
                            required
                            class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            @change="onBankSelected"
                        >
                            <option value="">{{ t('payments_hub.collection.select_bank') }}</option>
                            <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                                {{ bank.name }}
                            </option>
                        </select>
                        <p v-if="loadingBanks" class="mt-1 text-xs text-gray-500">{{ t('payments_hub.collection.loading_banks') }}</p>
                        <p v-if="addForm.errors.bank_code" class="mt-1 text-xs text-red-600">{{ addForm.errors.bank_code }}</p>
                    </div>

                    <div>
                        <label for="add_account_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.account_number_label') }}</label>
                        <div class="flex">
                            <input
                                id="add_account_number"
                                v-model="addForm.account_number"
                                type="text"
                                :placeholder="t('payments_hub.collection.account_number_placeholder')"
                                required
                                class="block w-full rounded-s-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <button
                                type="button"
                                :disabled="!addForm.account_number || !addForm.bank_code || verifyingAccount"
                                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-s-0 border-gray-300 dark:border-gray-600 rounded-e-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors"
                                @click="verifyAccount"
                            >
                                {{ verifyingAccount ? t('payments_hub.collection.verifying') : t('payments_hub.collection.verify') }}
                            </button>
                        </div>
                        <p v-if="addForm.errors.account_number" class="mt-1 text-xs text-red-600">{{ addForm.errors.account_number }}</p>
                    </div>

                    <div
                        v-if="verifiedAccountName"
                        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3"
                    >
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon class="w-5 h-5 text-green-600 dark:text-green-400" />
                            <div>
                                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ t('payments_hub.collection.account_verified') }}</p>
                                <p class="text-sm text-green-700 dark:text-green-300">{{ verifiedAccountName }}</p>
                            </div>
                        </div>
                    </div>

                    <p v-if="addForm.errors.error" class="text-sm text-red-600">{{ addForm.errors.error }}</p>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            @click="closeAddModal"
                        >
                            {{ t('payments_hub.collection.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing || !verifiedAccountName"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ addForm.processing ? t('payments_hub.collection.adding') : t('payments_hub.collection.add_account') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
