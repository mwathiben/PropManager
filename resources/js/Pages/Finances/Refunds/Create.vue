<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { useFormatters, useErrorHandler, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowUturnLeftIcon,
    MagnifyingGlassIcon,
    UserIcon,
    BanknotesIcon,
    CheckIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';
import type { RefundsCreatePageProps } from '@/types';

const props = withDefaults(defineProps<RefundsCreatePageProps>(), {
    refundMethods: () => [],
    refundReasons: () => [],
});

const { t } = useI18n();
const { formatMoney, formatDate } = useFormatters();
const { logError } = useErrorHandler();
const { currencySymbol } = useCurrency();

const form = ref({
    tenant_id: null,
    payment_id: null,
    amount: '',
    reason: '',
    custom_reason: '',
    refund_method: 'original_method',
    notes: '',
});

const errors = ref({});
const isSubmitting = ref(false);
const success = ref(false);

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);

const selectedTenant = ref(null);
const tenantPayments = ref([]);
const isLoadingPayments = ref(false);

let searchTimeout = null;

watch(searchQuery, (newVal) => {
    if (searchTimeout) clearTimeout(searchTimeout);

    if (newVal.length < 2) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }

    searchTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            const response = await fetch(route('tenants.search') + `?q=${encodeURIComponent(newVal)}`);
            const data = await response.json();
            searchResults.value = data.data || [];
            showSearchResults.value = true;
        } catch (err) {
            logError(err, { component: 'RefundsCreate', action: 'searchTenants' });
            searchResults.value = [];
        } finally {
            isSearching.value = false;
        }
    }, 300);
});

const selectTenant = async (tenant) => {
    selectedTenant.value = tenant;
    form.value.tenant_id = tenant.id;
    form.value.payment_id = null;
    form.value.amount = '';
    searchQuery.value = '';
    showSearchResults.value = false;

    isLoadingPayments.value = true;
    try {
        const response = await fetch(route('tenants.refundable-payments', tenant.id));
        const data = await response.json();
        tenantPayments.value = data.data || [];
    } catch (err) {
        logError(err, { component: 'RefundsCreate', action: 'loadPayments' });
        tenantPayments.value = [];
    } finally {
        isLoadingPayments.value = false;
    }
};

const clearTenant = () => {
    selectedTenant.value = null;
    form.value.tenant_id = null;
    form.value.payment_id = null;
    form.value.amount = '';
    tenantPayments.value = [];
};

const selectedPayment = computed(() => {
    return tenantPayments.value.find(p => p.id === form.value.payment_id);
});

const maxAmount = computed(() => {
    return selectedPayment.value?.refundable_amount || 0;
});

watch(() => form.value.payment_id, (newVal) => {
    if (newVal) {
        const payment = tenantPayments.value.find(p => p.id === newVal);
        if (payment) {
            form.value.amount = payment.refundable_amount;
        }
    }
});

const setFullAmount = () => {
    if (selectedPayment.value) {
        form.value.amount = selectedPayment.value.refundable_amount;
    }
};

const actualReason = computed(() => {
    if (form.value.reason === 'Other') {
        return form.value.custom_reason;
    }
    return form.value.reason;
});

const validate = () => {
    errors.value = {};

    if (!selectedTenant.value) {
        errors.value.tenant = t('finances_refunds_create.errors.select_tenant');
    }

    if (!form.value.payment_id) {
        errors.value.payment = t('finances_refunds_create.errors.select_payment');
    }

    if (!form.value.amount || Number(form.value.amount) <= 0) {
        errors.value.amount = t('finances_refunds_create.errors.valid_amount');
    }

    if (Number(form.value.amount) > maxAmount.value) {
        errors.value.amount = t('finances_refunds_create.errors.amount_exceeds', { amount: formatMoney(maxAmount.value) });
    }

    if (!form.value.reason) {
        errors.value.reason = t('finances_refunds_create.errors.select_reason');
    }

    if (form.value.reason === 'Other' && !form.value.custom_reason) {
        errors.value.custom_reason = t('finances_refunds_create.errors.specify_reason');
    }

    if (!form.value.refund_method) {
        errors.value.refund_method = t('finances_refunds_create.errors.select_method');
    }

    return Object.keys(errors.value).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;

    isSubmitting.value = true;

    router.post(route('finances.refunds.store'), {
        payment_id: form.value.payment_id,
        amount: form.value.amount,
        reason: actualReason.value,
        refund_method: form.value.refund_method,
        notes: form.value.notes,
    }, {
        onSuccess: () => {
            success.value = true;
        },
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const paymentMethodLabels = computed(() => ({
    cash: t('finances_refunds_create.payment_methods.cash'),
    bank_transfer: t('finances_refunds_create.payment_methods.bank_transfer'),
    mobile_money: t('finances_refunds_create.payment_methods.mobile_money'),
    paystack: t('finances_refunds_create.payment_methods.paystack'),
}));
</script>

<template>
    <Head :title="t('finances_refunds_create.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <Link
                        :href="route('finances.refunds')"
                        class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        {{ t('finances_refunds_create.back_to_refunds') }}
                    </Link>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-amber-100 rounded-lg">
                                <ArrowUturnLeftIcon class="w-5 h-5 text-amber-600" />
                            </div>
                            <div>
                                <h1 class="text-lg font-semibold text-gray-900">{{ t('finances_refunds_create.heading') }}</h1>
                                <p class="text-sm text-gray-500">{{ t('finances_refunds_create.subheading') }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="success" class="p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                            <CheckIcon class="w-8 h-8 text-emerald-600" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_refunds_create.success.title') }}</h3>
                        <p class="text-sm text-gray-500 mt-2">{{ t('finances_refunds_create.success.body') }}</p>
                        <div class="mt-6">
                            <Link
                                :href="route('finances.refunds')"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                            >
                                {{ t('finances_refunds_create.success.view_refunds') }}
                            </Link>
                        </div>
                    </div>

                    <form v-else @submit.prevent="handleSubmit" class="p-6 space-y-6">
                        <div v-if="errors.general" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                            {{ errors.general }}
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <UserIcon class="w-4 h-4 text-gray-400" />
                                {{ t('finances_refunds_create.tenant_selection') }}
                            </h3>

                            <div v-if="selectedTenant" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ selectedTenant.name }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ selectedTenant.unit?.unit_number }}
                                            <span v-if="selectedTenant.unit?.building_name" class="text-gray-400">
                                                · {{ selectedTenant.unit.building_name }}
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">{{ selectedTenant.email }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearTenant"
                                        class="text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        {{ t('finances_refunds_create.change') }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="relative">
                                <div class="relative">
                                    <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        class="w-full ps-10 pe-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        :placeholder="t('finances_refunds_create.search_placeholder')"
                                        @focus="showSearchResults = searchResults.length > 0"
                                    />
                                    <div v-if="isSearching" class="absolute end-3 top-1/2 -translate-y-1/2">
                                        <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </div>

                                <div
                                    v-if="showSearchResults && searchResults.length > 0"
                                    class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                >
                                    <button
                                        v-for="tenant in searchResults"
                                        :key="tenant.id"
                                        type="button"
                                        @click="selectTenant(tenant)"
                                        class="w-full px-4 py-3 text-start hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                    >
                                        <p class="font-medium text-gray-900">{{ tenant.name }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ tenant.unit?.unit_number || t('finances_refunds_create.no_unit') }}
                                            <span v-if="tenant.unit?.building_name">· {{ tenant.unit.building_name }}</span>
                                        </p>
                                    </button>
                                </div>

                                <p v-if="errors.tenant" class="mt-1 text-sm text-red-600">{{ errors.tenant }}</p>
                            </div>
                        </div>

                        <div v-if="selectedTenant" class="space-y-4">
                            <h3 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <BanknotesIcon class="w-4 h-4 text-gray-400" />
                                {{ t('finances_refunds_create.payment_selection') }}
                            </h3>

                            <div v-if="isLoadingPayments" class="text-center py-4">
                                <svg class="animate-spin h-6 w-6 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-sm text-gray-500 mt-2">{{ t('finances_refunds_create.loading_payments') }}</p>
                            </div>

                            <template v-else>
                                <div v-if="tenantPayments.length === 0" class="p-4 bg-gray-50 rounded-lg text-center">
                                    <p class="text-sm text-gray-500">{{ t('finances_refunds_create.no_refundable_payments') }}</p>
                                </div>

                                <div v-else class="space-y-2">
                                    <div
                                        v-for="payment in tenantPayments"
                                        :key="payment.id"
                                        @click="form.payment_id = payment.id"
                                        :class="[
                                            /* i18n-ignore */ 'p-3 border rounded-lg cursor-pointer transition-colors',
                                            form.payment_id === payment.id
                                                ? 'border-emerald-500 bg-emerald-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ payment.reference }}</p>
                                                <p class="text-sm text-gray-500">
                                                    {{ paymentMethodLabels[payment.payment_method] || payment.payment_method }}
                                                    · {{ payment.payment_date }}
                                                </p>
                                                <p v-if="payment.invoice_number" class="text-xs text-gray-400">
                                                    {{ t('finances_refunds_create.invoice_prefix') }} {{ payment.invoice_number }}
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <p class="font-semibold text-gray-900">{{ formatMoney(payment.refundable_amount) }}</p>
                                                <p class="text-xs text-gray-500">
                                                    {{ t('finances_refunds_create.of_amount', { amount: formatMoney(payment.amount) }) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="errors.payment" class="mt-1 text-sm text-red-600">{{ errors.payment }}</p>
                            </template>
                        </div>

                        <div v-if="selectedPayment" class="space-y-4 pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <ArrowUturnLeftIcon class="w-4 h-4 text-gray-400" />
                                {{ t('finances_refunds_create.refund_details') }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="refund-amount" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_refunds_create.amount_label') }}</label>
                                    <div class="relative">
                                        <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">{{ currencySymbol }}</span>
                                        <input
                                            id="refund-amount"
                                            v-model.number="form.amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            :max="maxAmount"
                                            :class="[
                                                'w-full ps-12 pe-20 py-2.5 text-sm border rounded-lg transition-colors',
                                                errors.amount
                                                    ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                    : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                            ]"
                                            :placeholder="t('finances_refunds_create.amount_placeholder')"
                                        />
                                        <button
                                            type="button"
                                            @click="setFullAmount"
                                            class="absolute end-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            {{ t('finances_refunds_create.max') }}
                                        </button>
                                    </div>
                                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('finances_refunds_create.max_refundable', { amount: formatMoney(maxAmount) }) }}</p>
                                </div>

                                <div>
                                    <label for="refund-method" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_refunds_create.refund_method_label') }}</label>
                                    <select
                                        id="refund-method"
                                        v-model="form.refund_method"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.refund_method
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option v-for="method in refundMethods" :key="method.value" :value="method.value">
                                            {{ method.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.refund_method" class="mt-1 text-sm text-red-600">{{ errors.refund_method }}</p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="refund-reason" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_refunds_create.reason_label') }}</label>
                                    <select
                                        id="refund-reason"
                                        v-model="form.reason"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.reason
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    >
                                        <option value="">{{ t('finances_refunds_create.select_reason') }}</option>
                                        <option v-for="reason in refundReasons" :key="reason.value" :value="reason.value">
                                            {{ reason.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.reason" class="mt-1 text-sm text-red-600">{{ errors.reason }}</p>
                                </div>

                                <div v-if="form.reason === 'Other'" class="sm:col-span-2">
                                    <label for="refund-custom-reason" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_refunds_create.specify_reason_label') }}</label>
                                    <input
                                        id="refund-custom-reason"
                                        v-model="form.custom_reason"
                                        type="text"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.custom_reason
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                        :placeholder="t('finances_refunds_create.custom_reason_placeholder')"
                                    />
                                    <p v-if="errors.custom_reason" class="mt-1 text-sm text-red-600">{{ errors.custom_reason }}</p>
                                </div>
                            </div>

                            <div>
                                <label for="refund-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_refunds_create.notes_label') }}</label>
                                <textarea
                                    id="refund-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors resize-none"
                                    :placeholder="t('finances_refunds_create.notes_placeholder')"
                                />
                            </div>

                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ t('finances_refunds_create.original_payment') }}</span>
                                    <span class="font-medium">{{ formatMoney(selectedPayment.amount) }}</span>
                                </div>
                                <div v-if="selectedPayment.refunded_amount > 0" class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-600">{{ t('finances_refunds_create.already_refunded') }}</span>
                                    <span class="font-medium text-red-600">- {{ formatMoney(selectedPayment.refunded_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-2 pt-2 border-t border-amber-200">
                                    <span class="text-gray-700 font-medium">{{ t('finances_refunds_create.this_refund') }}</span>
                                    <span class="font-semibold text-amber-600">{{ formatMoney(form.amount || 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            <Link
                                :href="route('finances.refunds')"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-center text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                {{ t('finances_refunds_create.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="isSubmitting || !selectedPayment"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ isSubmitting ? t('finances_refunds_create.processing') : t('finances_refunds_create.create_refund') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
