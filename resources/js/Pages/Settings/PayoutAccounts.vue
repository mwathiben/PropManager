<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useErrorHandler } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import {
    BanknotesIcon,
    PlusIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ClockIcon,
    StarIcon,
    TrashIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import type { PayoutAccountsPageProps } from '@/types';

const { can } = useAuth();

const props = withDefaults(defineProps<PayoutAccountsPageProps>(), {
    accounts: () => [],
    hasPrimaryAccount: false,
    hasVerifiedAccount: false,
    currentFeePercentage: 0,
    billingModel: '',
});

const { logError } = useErrorHandler();
const showAddModal = ref(false);
const banks = ref([]);
const loadingBanks = ref(false);
const verifyingAccount = ref(false);
const verifiedAccountName = ref('');

const form = useForm({
    business_name: '',
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_name: '',
});

const selectedBank = computed(() => {
    return banks.value.find(b => b.code === form.bank_code);
});

const openAddModal = async () => {
    showAddModal.value = true;
    await loadBanks();
};

const closeAddModal = () => {
    showAddModal.value = false;
    form.reset();
    verifiedAccountName.value = '';
};

const loadBanks = async () => {
    if (banks.value.length > 0) return;

    loadingBanks.value = true;
    try {
        const response = await fetch(route('api.banks'));
        const data = await response.json();
        banks.value = data.banks || [];
    } catch (error) {
        logError(error, { component: 'PayoutAccounts', action: 'loadBanks' });
    } finally {
        loadingBanks.value = false;
    }
};

const onBankSelected = () => {
    if (selectedBank.value) {
        form.bank_name = selectedBank.value.name;
    }
    verifiedAccountName.value = '';
};

const verifyAccount = async () => {
    if (!form.account_number || !form.bank_code) return;

    verifyingAccount.value = true;
    verifiedAccountName.value = '';

    try {
        const response = await fetch(route('api.verify-account'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                account_number: form.account_number,
                bank_code: form.bank_code,
            }),
        });

        const data = await response.json();

        if (data.status === 'success') {
            verifiedAccountName.value = data.account_name;
            form.account_name = data.account_name;
        } else {
            alert(data.message || 'Could not verify account');
        }
    } catch (error) {
        logError(error, { component: 'PayoutAccounts', action: 'verifyAccount' });
        alert('Account verification failed');
    } finally {
        verifyingAccount.value = false;
    }
};

const submitForm = () => {
    form.post(route('settings.payout.store'), {
        onSuccess: () => {
            closeAddModal();
        },
    });
};

const setPrimary = (accountId) => {
    router.post(route('settings.payout.primary', accountId));
};

const syncAccount = (accountId) => {
    router.post(route('settings.payout.sync', accountId));
};

const deleteAccount = (accountId) => {
    if (confirm('Are you sure you want to deactivate this payout account?')) {
        router.delete(route('settings.payout.destroy', accountId));
    }
};

const getStatusIcon = (status) => {
    switch (status) {
        case 'verified': return CheckCircleIcon;
        case 'pending': return ClockIcon;
        case 'rejected': return ExclamationCircleIcon;
        default: return ExclamationCircleIcon;
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'verified': return 'text-green-600';
        case 'pending': return 'text-yellow-600';
        case 'rejected': return 'text-red-600';
        default: return 'text-gray-600';
    }
};
</script>

<template>
    <Head title="Payout Accounts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Payout Accounts
                </h2>
                <button
                    @click="openAddModal"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Add Account
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Fee Info Banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <BanknotesIcon class="w-6 h-6 text-blue-600 mr-3 mt-0.5" />
                        <div>
                            <h3 class="font-medium text-blue-900">Platform Fee Information</h3>
                            <p class="text-sm text-blue-700 mt-1">
                                Current billing model: <strong>{{ billingModel === 'transaction_fee' ? 'Transaction Fee' : billingModel === 'subscription' ? 'Subscription' : 'Hybrid' }}</strong>
                            </p>
                            <p v-if="billingModel !== 'subscription'" class="text-sm text-blue-700">
                                Platform fee: <strong>{{ currentFeePercentage }}%</strong> per transaction
                            </p>
                            <p class="text-sm text-blue-600 mt-2">
                                Connect your bank account to receive payments directly. The platform fee will be automatically deducted.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Alert if no verified account -->
                <div v-if="!hasVerifiedAccount" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <ExclamationCircleIcon class="w-6 h-6 text-yellow-600 mr-3 mt-0.5" />
                        <div>
                            <h3 class="font-medium text-yellow-900">Payout Account Required</h3>
                            <p class="text-sm text-yellow-700 mt-1">
                                You need to connect a verified payout account before tenants can make online payments.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Accounts List -->
                <div v-if="accounts.length > 0" class="bg-white shadow rounded-lg overflow-hidden">
                    <ul class="divide-y divide-gray-200">
                        <li v-for="account in accounts" :key="account.id" class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="shrink-0">
                                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <BanknotesIcon class="w-6 h-6 text-gray-600" />
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-900">{{ account.business_name }}</p>
                                            <span v-if="account.is_primary" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                <StarIcon class="w-3 h-3 mr-1" />
                                                Primary
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">{{ account.bank_name }}</p>
                                        <p class="text-sm text-gray-500">{{ account.account_name }} - {{ account.masked_account_number }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center" :class="getStatusColor(account.verification_status)">
                                        <component :is="getStatusIcon(account.verification_status)" class="w-5 h-5 mr-1" />
                                        <span class="text-sm">{{ account.status_label }}</span>
                                    </div>
                                    <div class="flex space-x-2">
                                        <IconButton
                                            v-if="!account.is_primary && account.can_receive_payments"
                                            :icon="StarIcon"
                                            tone="primary"
                                            aria-label="Set as primary payout account"
                                            @click="setPrimary(account.id)"
                                        />
                                        <IconButton
                                            :icon="ArrowPathIcon"
                                            aria-label="Sync account status"
                                            @click="syncAccount(account.id)"
                                        />
                                        <IconButton
                                            v-if="can('settings:manage') && !account.is_primary"
                                            :icon="TrashIcon"
                                            tone="danger"
                                            aria-label="Deactivate payout account"
                                            @click="deleteAccount(account.id)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white shadow rounded-lg">
                    <EmptyState
                        :icon="BanknotesIcon"
                        title="No payout accounts"
                        description="Get started by connecting your bank account."
                        action-label="Add Account"
                        @action="openAddModal"
                    />
                </div>
            </div>
        </div>

        <!-- Add Account Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="closeAddModal"></div>

                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add Payout Account</h3>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Business Name</label>
                            <input
                                v-model="form.business_name"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Your business or property name"
                                required
                            />
                            <p v-if="form.errors.business_name" class="mt-1 text-sm text-red-600">{{ form.errors.business_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Bank</label>
                            <select
                                v-model="form.bank_code"
                                @change="onBankSelected"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="">Select a bank</option>
                                <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                                    {{ bank.name }}
                                </option>
                            </select>
                            <p v-if="loadingBanks" class="mt-1 text-sm text-gray-500">Loading banks...</p>
                            <p v-if="form.errors.bank_code" class="mt-1 text-sm text-red-600">{{ form.errors.bank_code }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account Number</label>
                            <div class="mt-1 flex">
                                <input
                                    v-model="form.account_number"
                                    type="text"
                                    class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Enter account number"
                                    required
                                />
                                <button
                                    type="button"
                                    @click="verifyAccount"
                                    :disabled="!form.account_number || !form.bank_code || verifyingAccount"
                                    class="px-4 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                                >
                                    {{ verifyingAccount ? 'Verifying...' : 'Verify' }}
                                </button>
                            </div>
                            <p v-if="form.errors.account_number" class="mt-1 text-sm text-red-600">{{ form.errors.account_number }}</p>
                        </div>

                        <div v-if="verifiedAccountName" class="bg-green-50 border border-green-200 rounded-md p-3">
                            <div class="flex items-center">
                                <CheckCircleIcon class="w-5 h-5 text-green-600 mr-2" />
                                <div>
                                    <p class="text-sm font-medium text-green-800">Account Verified</p>
                                    <p class="text-sm text-green-700">{{ verifiedAccountName }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                @click="closeAddModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing || !verifiedAccountName"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Adding...' : 'Add Account' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
