<script setup lang="ts">
/**
 * Phase-99 WATER-CLIENT-PAYMENTS-ONLINE: checkout page for one outstanding
 * water-client invoice. Pays through the supplier's gateway-agnostic checkout
 * (payments.checkout.initialize), the same endpoint a tenant uses — the invoice
 * is a real invoice, authorized for the water client via InvoicePolicy::pay.
 */
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios, { isAxiosError } from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

interface Invoice {
    id: number;
    invoice_number: string;
    total_due: number;
    amount_paid: number;
    balance: number;
    status: string;
    due_date: string | null;
    billing_period_start: string | null;
    currency: string;
    currency_symbol: string;
}

const props = withDefaults(
    defineProps<{
        invoice: Invoice;
        line?: { identifier: string | null };
        onlinePayEnabled?: boolean;
        supplierName?: string | null;
    }>(),
    { line: () => ({ identifier: null }), onlinePayEnabled: false, supplierName: null },
);

const { t } = useI18n();
const { formatMoney } = useFormatters();

const processing = ref(false);
const error = ref<string | null>(null);

async function payNow(): Promise<void> {
    if (processing.value) return;
    processing.value = true;
    error.value = null;

    try {
        const { data } = await axios.post(route('payments.checkout.initialize', props.invoice.id), {
            amount: props.invoice.balance,
        });

        const url = data?.data?.authorization_url;
        if (url) {
            window.location.href = url;
            return;
        }
        error.value = t('water.client_pay.no_redirect');
    } catch (e) {
        error.value = isAxiosError(e) ? (e.response?.data?.message ?? t('water.client_pay.failed')) : t('water.client_pay.failed');
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('water.client_pay.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-cyan-100 p-2"><BanknotesIcon class="h-6 w-6 text-cyan-600" /></div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('water.client_pay.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ line.identifier }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-lg space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-testid="water-client-pay">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">{{ t('water.client_pay.invoice') }}</span>
                    <span class="font-medium text-gray-900">{{ invoice.invoice_number }}</span>
                </div>
                <div v-if="invoice.due_date" class="mt-2 flex items-center justify-between">
                    <span class="text-sm text-gray-500">{{ t('water.client_pay.due_date') }}</span>
                    <span class="text-sm text-gray-700">{{ invoice.due_date }}</span>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-500">{{ t('water.client_pay.amount_due') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ formatMoney(invoice.balance) }}</p>
                </div>
            </div>

            <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-testid="pay-error">
                {{ error }}
            </div>

            <button
                v-if="onlinePayEnabled"
                type="button"
                :disabled="processing"
                data-testid="pay-now-button"
                class="w-full rounded-lg bg-cyan-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
                @click="payNow"
            >
                {{ processing ? t('water.client_pay.processing') : t('water.client_pay.pay_button', { amount: formatMoney(invoice.balance) }) }}
            </button>

            <p v-else class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ supplierName ? t('water.client_finances.how_to_pay', { supplier: supplierName }) : t('water.client_finances.how_to_pay_generic') }}
            </p>

            <Link :href="route('water-client.finances')" class="block text-center text-sm text-gray-500 hover:text-gray-700">
                {{ t('water.client_pay.back') }}
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
