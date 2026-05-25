<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps<{
    invoice: {
        id: number;
        invoice_number: string;
        total_due: number;
        amount_paid: number;
        balance: number;
        status: string;
        due_date: string | null;
        currency?: string;
        currency_symbol?: string;
    };
    tenant: {
        name: string | null;
        unit: string | null;
        building: string | null;
    };
    landlord: {
        name: string | null;
        business_name: string | null;
    };
    token: string;
    loginUrl: string;
}>();

const { formatMoney: formatCurrency, formatDate } = useFormatters({ currency: props.invoice.currency ?? 'KES' });

const isOverdue = computed(() => {
    if (!props.invoice.due_date) return false;
    const parts = props.invoice.due_date.substring(0, 10).split('-');
    const dueDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    dueDate.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today > dueDate;
});

const landlordDisplay = computed(() => {
    return props.landlord.business_name || props.landlord.name || t('payment_link.landlord_fallback');
});
</script>

<template>
    <GuestLayout>
        <Head :title="t('payment_link.page_title', { number: invoice.invoice_number })" />

        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-linear-to-r from-emerald-600 to-emerald-700 px-6 py-8 text-center text-white">
                    <h1 class="text-lg font-medium opacity-90 mb-1">{{ t('payment_link.amount_due') }}</h1>
                    <p class="text-4xl font-bold">{{ formatCurrency(invoice.balance) }}</p>
                    <p class="mt-2 text-sm opacity-80">
                        {{ t('payment_link.invoice_number', { number: invoice.invoice_number }) }}
                    </p>
                </div>

                <div class="px-6 py-6 space-y-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.billed_to') }}</span>
                        <span class="font-medium text-gray-900">{{ tenant.name || t('payment_link.tenant_fallback') }}</span>
                    </div>

                    <div v-if="tenant.unit" class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.unit') }}</span>
                        <span class="font-medium text-gray-900">
                            {{ tenant.unit }}
                            <span v-if="tenant.building" class="text-gray-500">
                                · {{ tenant.building }}
                            </span>
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.due_date') }}</span>
                        <span
                            class="font-medium"
                            :class="isOverdue ? 'text-red-600' : 'text-gray-900'"
                        >
                            {{ formatDate(invoice.due_date) }}
                            <span v-if="isOverdue" class="text-xs">{{ t('payment_link.overdue') }}</span>
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.from') }}</span>
                        <span class="font-medium text-gray-900">{{ landlordDisplay }}</span>
                    </div>

                    <hr class="border-gray-100" />

                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.total_invoiced') }}</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(invoice.total_due) }}</span>
                    </div>

                    <div v-if="invoice.amount_paid > 0" class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ t('payment_link.already_paid') }}</span>
                        <span class="font-medium text-emerald-600">-{{ formatCurrency(invoice.amount_paid) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-base font-semibold">
                        <span class="text-gray-900">{{ t('payment_link.balance_due') }}</span>
                        <span class="text-emerald-700">{{ formatCurrency(invoice.balance) }}</span>
                    </div>
                </div>

                <div class="px-6 pb-6 space-y-3">
                    <Link
                        :href="loginUrl"
                        class="flex items-center justify-center w-full px-4 py-3 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <svg class="w-5 h-5 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        {{ t('payment_link.sign_in_to_pay') }}
                    </Link>

                    <p class="text-xs text-center text-gray-500">
                        {{ t('payment_link.sign_in_hint') }}
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">
                {{ t('payment_link.powered_by', { app: 'PropManager' }) }}
            </p>
        </div>
    </GuestLayout>
</template>
