<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
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
} from '@heroicons/vue/24/outline';

interface PaymentConfig {
    accepted_payment_methods: string[];
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_branch: string | null;
    mpesa_paybill: string | null;
    mpesa_account_name: string | null;
    paystack_enabled: boolean;
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

// ── Payment methods form ──────────────────────────────────────────────────────

const methodsForm = useForm({
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods ?? [],
    bank_name: props.paymentConfig?.bank_name ?? '',
    bank_account_name: props.paymentConfig?.bank_account_name ?? '',
    bank_account_number: props.paymentConfig?.bank_account_number ?? '',
    bank_branch: props.paymentConfig?.bank_branch ?? '',
    mpesa_paybill: props.paymentConfig?.mpesa_paybill ?? '',
    mpesa_account_name: props.paymentConfig?.mpesa_account_name ?? '',
});

const methodEntries = computed(() =>
    Object.entries(props.paymentMethods).map(([value, label]) => ({ value, label }))
);

const wantsBankTransfer = computed(() =>
    methodsForm.accepted_payment_methods.includes('bank_transfer')
);

const wantsMobileMoney = computed(() =>
    methodsForm.accepted_payment_methods.includes('mobile_money')
);

const wantsPaystack = computed(() =>
    methodsForm.accepted_payment_methods.includes('paystack')
);

const submitPaymentMethods = () => {
    methodsForm.post(route('payments-hub.payment-methods.update'));
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
                        Fee: <strong>{{ billingSettings.transaction_fee_percentage }}%</strong> {{ t('payments_hub.collection.per_transaction') }}
                        (min {{ formatMoney(billingSettings.minimum_fee) }})
                    </p>
                </div>
            </div>
        </div>

        <!-- Payment methods card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.collection.accepted_methods_title') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ t('payments_hub.collection.accepted_methods_subtitle') }}
                </p>
            </div>

            <form class="p-6 space-y-5" @submit.prevent="submitPaymentMethods">
                <!-- Method checkboxes -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label
                        v-for="method in methodEntries"
                        :key="method.value"
                        class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                    >
                        <input
                            v-model="methodsForm.accepted_payment_methods"
                            type="checkbox"
                            :value="method.value"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div class="flex items-center gap-2">
                            <BuildingLibraryIcon v-if="method.value === 'bank_transfer'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <DevicePhoneMobileIcon v-else-if="method.value === 'mobile_money'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <CreditCardIcon v-else class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ method.label }}</span>
                        </div>
                    </label>
                </div>
                <p v-if="methodsForm.errors.accepted_payment_methods" class="text-sm text-red-600">
                    {{ methodsForm.errors.accepted_payment_methods }}
                </p>

                <!-- Bank transfer details -->
                <div v-if="wantsBankTransfer" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.bank_transfer_details') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="bank_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.bank_name') }}</label>
                            <input
                                id="bank_name"
                                v-model="methodsForm.bank_name"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="methodsForm.errors.bank_name" class="mt-1 text-xs text-red-600">{{ methodsForm.errors.bank_name }}</p>
                        </div>
                        <div>
                            <label for="bank_account_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.account_name') }}</label>
                            <input
                                id="bank_account_name"
                                v-model="methodsForm.bank_account_name"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="bank_account_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.account_number') }}</label>
                            <input
                                id="bank_account_number"
                                v-model="methodsForm.bank_account_number"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="bank_branch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.branch') }}</label>
                            <input
                                id="bank_branch"
                                v-model="methodsForm.bank_branch"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- M-Pesa details -->
                <div v-if="wantsMobileMoney" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('payments_hub.collection.mpesa_details') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="mpesa_paybill" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.mpesa_paybill') }}</label>
                            <input
                                id="mpesa_paybill"
                                v-model="methodsForm.mpesa_paybill"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="mpesa_account_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('payments_hub.collection.mpesa_account_name') }}</label>
                            <input
                                id="mpesa_account_name"
                                v-model="methodsForm.mpesa_account_name"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Paystack notice -->
                <div
                    v-if="wantsPaystack"
                    class="flex items-start gap-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 p-4"
                >
                    <CreditCardIcon class="w-5 h-5 text-purple-600 dark:text-purple-400 mt-0.5 shrink-0" />
                    <p class="text-sm text-purple-700 dark:text-purple-300">
                        {{ t('payments_hub.collection.paystack_notice') }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="methodsForm.processing"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ methodsForm.processing ? t('payments_hub.collection.saving') : t('payments_hub.collection.save_methods') }}
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
                                <!-- Status badge -->
                                <div class="flex items-center gap-1" :class="account.status_color">
                                    <component :is="statusIcon(account.verification_status)" class="w-4 h-4" />
                                    <span class="text-sm">{{ account.status_label }}</span>
                                </div>

                                <!-- Actions -->
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
