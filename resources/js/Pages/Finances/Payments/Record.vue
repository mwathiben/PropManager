<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { useFormatters, useErrorHandler, useCurrency, usePaymentForm } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { PaymentMethodSelector } from '@/Components/Finances';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    BanknotesIcon,
    MagnifyingGlassIcon,
    UserIcon,
    DocumentTextIcon,
    CheckIcon,
    ExclamationTriangleIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';
import type { PaymentsRecordPageProps } from '@/types';
import type { PaymentMethodOption } from '@/types/finances';

const props = withDefaults(defineProps<PaymentsRecordPageProps>(), {
    paymentMethods: () => [],
    buildings: () => [],
});

const { t } = useI18n();
const { formatMoney, todayAsISODate } = useFormatters();
const { logError } = useErrorHandler();
const { currencySymbol } = useCurrency();
const { form, errors, isSubmitting, isSuccess: success, validate } = usePaymentForm();

const isUnallocated = ref(false);

const normalizedMethods = computed<PaymentMethodOption[]>(() =>
    props.paymentMethods.map(m => ({ id: m.value, label: m.label }))
);

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);

const selectedTenant = ref(null);
const tenantInvoices = ref([]);
const totalOutstanding = ref(0);
const isLoadingInvoices = ref(false);

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
            logError(err, { component: 'PaymentsRecord', action: 'searchTenants' });
            searchResults.value = [];
        } finally {
            isSearching.value = false;
        }
    }, 300);
});

const selectTenant = async (tenant) => {
    selectedTenant.value = tenant;
    form.value.invoice_id = null;
    isUnallocated.value = false;
    searchQuery.value = '';
    showSearchResults.value = false;

    isLoadingInvoices.value = true;
    try {
        const response = await fetch(route('tenants.outstanding-invoices', tenant.id));
        const data = await response.json();
        tenantInvoices.value = data.data || [];
        totalOutstanding.value = data.total_outstanding || 0;

        if (tenantInvoices.value.length === 1) {
            form.value.invoice_id = tenantInvoices.value[0].id;
            form.value.amount = tenantInvoices.value[0].balance;
        }
    } catch (err) {
        logError(err, { component: 'PaymentsRecord', action: 'loadInvoices' });
        tenantInvoices.value = [];
        totalOutstanding.value = 0;
    } finally {
        isLoadingInvoices.value = false;
    }
};

const clearTenant = () => {
    selectedTenant.value = null;
    form.value.invoice_id = null;
    form.value.amount = '';
    tenantInvoices.value = [];
    totalOutstanding.value = 0;
};

const selectedInvoice = computed(() => {
    return tenantInvoices.value.find(i => i.id === form.value.invoice_id);
});

const maxAmount = computed(() => {
    if (isUnallocated.value) return null;
    return selectedInvoice.value?.balance || 0;
});

const remainingAfterPayment = computed(() => {
    if (!selectedInvoice.value || !form.value.amount) return null;
    return Math.max(0, selectedInvoice.value.balance - Number(form.value.amount));
});

const isOverpayment = computed(() => {
    if (isUnallocated.value || !selectedInvoice.value) return false;
    return Number(form.value.amount) > selectedInvoice.value.balance;
});

watch(() => form.value.invoice_id, (newVal) => {
    if (newVal && !isUnallocated.value) {
        const invoice = tenantInvoices.value.find(i => i.id === newVal);
        if (invoice) {
            form.value.amount = invoice.balance;
        }
    }
});

watch(isUnallocated, (newVal) => {
    if (newVal) {
        form.value.invoice_id = null;
    }
});

const setFullAmount = () => {
    if (selectedInvoice.value) {
        form.value.amount = selectedInvoice.value.balance;
    }
};

const handleValidate = () => {
    return validate(() => {
        const extra: Record<string, string> = {};
        if (!selectedTenant.value && !form.value.invoice_id) {
            extra.tenant = t('finances_payment_record.tenant.required');
        }
        if (!isUnallocated.value && !form.value.invoice_id && tenantInvoices.value.length > 0) {
            extra.invoice = t('finances_payment_record.invoice.required');
        }
        return extra;
    });
};

const handleSubmit = () => {
    if (!handleValidate()) return;

    isSubmitting.value = true;

    router.post(route('finances.payments.store-manual'), {
        tenant_id: selectedTenant.value?.id,
        invoice_id: isUnallocated.value ? null : form.value.invoice_id,
        amount: form.value.amount,
        payment_method: form.value.payment_method,
        payment_date: form.value.payment_date,
        reference: form.value.reference,
        notes: form.value.notes,
        is_unallocated: isUnallocated.value,
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
</script>

<template>
    <Head :title="t('finances_payment_record.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <Link
                        :href="route('finances.payments')"
                        class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        {{ t('finances_payment_record.back') }}
                    </Link>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-emerald-100 rounded-lg">
                                <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                            </div>
                            <div>
                                <h1 class="text-lg font-semibold text-gray-900">{{ t('finances_payment_record.heading') }}</h1>
                                <p class="text-sm text-gray-500">{{ t('finances_payment_record.subheading') }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="success" class="p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                            <CheckIcon class="w-8 h-8 text-emerald-600" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_payment_record.success.title') }}</h3>
                        <p class="text-sm text-gray-500 mt-2">{{ t('finances_payment_record.success.body') }}</p>
                        <div class="mt-6">
                            <Link
                                :href="route('finances.payments')"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                            >
                                {{ t('finances_payment_record.success.view_payments') }}
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
                                {{ t('finances_payment_record.tenant.section') }}
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
                                        {{ t('finances_payment_record.tenant.change') }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="relative">
                                <div class="relative">
                                    <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                    <input
                                        v-model="searchQuery"
                                        id="tenant-search"
                                        type="text"
                                        class="w-full ps-10 pe-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        :placeholder="t('finances_payment_record.tenant.search_placeholder')"
                                        :aria-label="t('finances_payment_record.tenant.search_placeholder')"
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
                                            {{ tenant.unit?.unit_number || t('finances_payment_record.tenant.no_unit') }}
                                            <span v-if="tenant.unit?.building_name">· {{ tenant.unit.building_name }}</span>
                                        </p>
                                    </button>
                                </div>

                                <p v-if="errors.tenant" class="mt-1 text-sm text-red-600">{{ errors.tenant }}</p>
                            </div>
                        </div>

                        <div v-if="selectedTenant" class="space-y-4">
                            <h3 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                                {{ t('finances_payment_record.invoice.section') }}
                            </h3>

                            <div v-if="isLoadingInvoices" class="text-center py-4">
                                <svg class="animate-spin h-6 w-6 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-sm text-gray-500 mt-2">{{ t('finances_payment_record.invoice.loading') }}</p>
                            </div>

                            <template v-else>
                                <div class="flex items-center gap-2 mb-3">
                                    <input
                                        v-model="isUnallocated"
                                        type="checkbox"
                                        id="is_unallocated"
                                        class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <label for="is_unallocated" class="text-sm text-gray-700">
                                        {{ t('finances_payment_record.invoice.unallocated') }}
                                    </label>
                                </div>

                                <div v-if="!isUnallocated">
                                    <div v-if="tenantInvoices.length === 0" class="p-4 bg-gray-50 rounded-lg text-center">
                                        <p class="text-sm text-gray-500">{{ t('finances_payment_record.invoice.none') }}</p>
                                    </div>

                                    <div v-else class="space-y-2">
                                        <div
                                            v-for="invoice in tenantInvoices"
                                            :key="invoice.id"
                                            role="button"
                                            tabindex="0"
                                            @click="form.invoice_id = invoice.id"
                                            @keydown.enter="form.invoice_id = invoice.id"
                                            @keydown.space.prevent="form.invoice_id = invoice.id"
                                            :class="['p-3 border rounded-lg cursor-pointer transition-colors', form.invoice_id === invoice.id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300']"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                                    <p class="text-sm text-gray-500">{{ invoice.description }}</p>
                                                </div>
                                                <div class="text-end">
                                                    <p class="font-semibold text-gray-900">{{ formatMoney(invoice.balance) }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        {{ t('finances_payment_record.invoice.due', { date: invoice.due_date || t('finances_payment_record.invoice.due_na') }) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <p v-if="totalOutstanding > 0" class="text-sm text-gray-600 pt-2">
                                            {{ t('finances_payment_record.invoice.total_outstanding') }} <span class="font-semibold">{{ formatMoney(totalOutstanding) }}</span>
                                        </p>
                                    </div>
                                </div>

                                <p v-if="errors.invoice" class="mt-1 text-sm text-red-600">{{ errors.invoice }}</p>
                            </template>
                        </div>

                        <div v-if="selectedTenant" class="space-y-4 pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <BanknotesIcon class="w-4 h-4 text-gray-400" />
                                {{ t('finances_payment_record.details.section') }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="pay-amount" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_payment_record.details.amount') }}</label>
                                    <div class="relative">
                                        <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">{{ currencySymbol }}</span>
                                        <input
                                            id="pay-amount"
                                            v-model.number="form.amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            :class="[
                                                'w-full ps-12 pe-20 py-2.5 text-sm border rounded-lg transition-colors',
                                                errors.amount
                                                    ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                    : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                            ]"
                                            placeholder="0.00"
                                        />
                                        <button
                                            v-if="selectedInvoice && !isUnallocated"
                                            type="button"
                                            @click="setFullAmount"
                                            class="absolute end-2 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        >
                                            {{ t('finances_payment_record.details.full') }}
                                        </button>
                                    </div>
                                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                                </div>

                                <div>
                                    <label for="pay-method" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_payment_record.details.method') }}</label>
                                    <PaymentMethodSelector
                                        v-model="form.payment_method"
                                        :methods="normalizedMethods"
                                        :error="errors.payment_method"
                                    />
                                </div>

                                <div>
                                    <label for="pay-date" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_payment_record.details.date') }}</label>
                                    <input
                                        id="pay-date"
                                        v-model="form.payment_date"
                                        type="date"
                                        :max="todayAsISODate()"
                                        :class="[
                                            'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                                            errors.payment_date
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500'
                                        ]"
                                    />
                                    <p v-if="errors.payment_date" class="mt-1 text-sm text-red-600">{{ errors.payment_date }}</p>
                                </div>

                                <div>
                                    <label for="pay-reference" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_payment_record.details.reference') }}</label>
                                    <input
                                        id="pay-reference"
                                        v-model="form.reference"
                                        type="text"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        :placeholder="t('finances_payment_record.details.reference_placeholder')"
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="pay-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ t('finances_payment_record.details.notes') }}</label>
                                <textarea
                                    id="pay-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 transition-colors resize-none"
                                    :placeholder="t('finances_payment_record.details.notes_placeholder')"
                                />
                            </div>

                            <div v-if="isOverpayment" class="p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
                                <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-amber-800">{{ t('finances_payment_record.overpayment.title') }}</p>
                                    <p class="text-sm text-amber-700">
                                        {{ t('finances_payment_record.overpayment.body', { amount: formatMoney(Number(form.amount) - selectedInvoice.balance) }) }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="selectedInvoice && remainingAfterPayment !== null && !isUnallocated" class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ t('finances_payment_record.summary.invoice_balance') }}</span>
                                    <span class="font-medium">{{ formatMoney(selectedInvoice.balance) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-600">{{ t('finances_payment_record.summary.payment_amount') }}</span>
                                    <span class="font-medium text-emerald-600">- {{ formatMoney(form.amount || 0) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-2 pt-2 border-t border-gray-200">
                                    <span class="text-gray-700 font-medium">{{ t('finances_payment_record.summary.remaining') }}</span>
                                    <span class="font-semibold">{{ formatMoney(remainingAfterPayment) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            <Link
                                :href="route('finances.payments')"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-center text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                {{ t('finances_payment_record.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="isSubmitting || !selectedTenant"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ isSubmitting ? t('finances_payment_record.submitting') : t('finances_payment_record.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
