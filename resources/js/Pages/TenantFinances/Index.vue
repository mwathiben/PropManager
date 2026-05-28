<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useFormatters, useEcho } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { TenantFinancesIndexPageProps } from '@/types';
import {
    MetricCard,
    InvoiceStatusBadge,
    PaymentMethodBadge,
    AmountDisplay,
    EmptyState,
} from '@/Components/Finances';
import {
    BanknotesIcon,
    CreditCardIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    HomeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantFinancesIndexPageProps>();

const { t } = useI18n();
const { formatMoney, formatDate, todayAsISODate } = useFormatters();

// --- REAL-TIME UPDATES ---
const { subscribePrivate, unsubscribe } = useEcho();
const localBalance = ref(props.balance);
const localPendingInvoices = ref([...(props.pendingInvoices || [])]);
const localRecentPayments = ref([...(props.recentPayments || [])]);

const hasArrears = computed(() => localBalance.value > 0);

const balanceColor = computed(() => {
    if (localBalance.value > 0) return 'red';
    if (localBalance.value < 0) return 'emerald';
    return 'gray';
});

const nextDueInvoice = computed(() => {
    if (!localPendingInvoices.value?.length) return null;
    return localPendingInvoices.value[0];
});

const userId = computed(() => window.__auth?.user?.id);

onMounted(() => {
    if (userId.value) {
        subscribePrivate(`tenant.${userId.value}`, 'PaymentReceived', (data) => {
            // Update balance
            localBalance.value = data.remaining_balance;

            // Update invoice status if it matches
            const invoiceIdx = localPendingInvoices.value.findIndex(inv => inv.id === data.invoice_id);
            if (invoiceIdx !== -1) {
                localPendingInvoices.value[invoiceIdx].status = data.invoice_status;
                localPendingInvoices.value[invoiceIdx].balance = data.remaining_balance;

                // Remove if fully paid
                if (data.invoice_status === 'paid') {
                    localPendingInvoices.value.splice(invoiceIdx, 1);
                }
            }

            // Add to recent payments
            localRecentPayments.value.unshift({
                id: data.payment_id,
                amount: data.amount,
                reference: data.reference,
                payment_method: data.payment_method,
                payment_date: todayAsISODate(),
            });

            // Keep only last 5 payments
            if (localRecentPayments.value.length > 5) {
                localRecentPayments.value.pop();
            }
        });
    }
});

onUnmounted(() => {
    if (userId.value) {
        unsubscribe(`tenant.${userId.value}`);
    }
});
</script>

<template>
    <Head :title="t('tenant_finances_index.page_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <BanknotesIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('tenant_finances_index.header_title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('tenant_finances_index.header_subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="!hasLease" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <EmptyState
                        :icon="HomeIcon"
                        :title="t('tenant_finances_index.no_active_lease_title')"
                        :description="t('tenant_finances_index.no_active_lease_description')"
                    />
                </div>

                <div v-else class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-500">{{ t('tenant_finances_index.current_balance') }}</p>
                                <p :class="[
                                    'text-3xl font-bold mt-1',
                                    localBalance > 0 ? 'text-red-600' : localBalance < 0 ? 'text-emerald-600' : 'text-gray-900'
                                ]">
                                    {{ formatMoney(Math.abs(localBalance)) }}
                                    <span v-if="localBalance !== 0" class="text-lg font-normal">
                                        {{ localBalance > 0 ? t('tenant_finances_index.balance_due') : t('tenant_finances_index.balance_credit') }}
                                    </span>
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ lease.unit }} - {{ lease.building }}
                                </p>
                            </div>

                            <div v-if="nextDueInvoice" class="shrink-0">
                                <Link
                                    :href="route('tenant.finances.pay', nextDueInvoice.id)"
                                    class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-200"
                                >
                                    <CreditCardIcon class="h-5 w-5" />
                                    {{ t('tenant_finances_index.pay_now') }}
                                </Link>
                            </div>
                        </div>

                        <div v-if="hasArrears" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <ExclamationTriangleIcon class="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-red-800">{{ t('tenant_finances_index.outstanding_balance_title') }}</p>
                                    <p class="text-sm text-red-700">{{ t('tenant_finances_index.outstanding_balance_description') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="localPendingInvoices?.length" class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">{{ t('tenant_finances_index.pending_invoices') }}</h2>
                            <span class="text-xs text-gray-500">{{ t('tenant_finances_index.pending_count', { count: localPendingInvoices.length }) }}</span>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <Link
                                v-for="invoice in localPendingInvoices"
                                :key="invoice.id"
                                :href="route('tenant.finances.pay', invoice.id)"
                                class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition-colors"
                            >
                                <div class="flex items-center gap-4">
                                    <div :class="[
                                        'p-2 rounded-lg',
                                        invoice.is_overdue ? 'bg-red-100' : 'bg-blue-100'
                                    ]">
                                        <DocumentTextIcon :class="[
                                            'h-5 w-5',
                                            invoice.is_overdue ? 'text-red-600' : 'text-blue-600'
                                        ]" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                        <p class="text-xs text-gray-500">{{ t('tenant_finances_index.due_label', { date: formatDate(invoice.due_date) }) }}</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <AmountDisplay :amount="invoice.balance" size="sm" class="text-gray-900" />
                                    <InvoiceStatusBadge :status="invoice.status" size="sm" class="mt-1" />
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">{{ t('tenant_finances_index.recent_payments') }}</h2>
                            <Link
                                :href="route('tenant.finances.history')"
                                class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                            >
                                {{ t('tenant_finances_index.view_all') }}
                            </Link>
                        </div>
                        <div v-if="localRecentPayments?.length" class="divide-y divide-gray-200">
                            <div
                                v-for="payment in localRecentPayments"
                                :key="payment.id"
                                class="flex items-center justify-between px-5 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <BanknotesIcon class="h-4 w-4 text-emerald-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ formatDate(payment.payment_date) }}</p>
                                        <p class="text-xs text-gray-500">{{ payment.reference }}</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <AmountDisplay :amount="payment.amount" size="sm" />
                                    <PaymentMethodBadge :method="payment.payment_method" size="sm" :show-icon="false" class="mt-1" />
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-5 py-8">
                            <EmptyState
                                :icon="BanknotesIcon"
                                :title="t('tenant_finances_index.no_payments_title')"
                                :description="t('tenant_finances_index.no_payments_description')"
                                size="sm"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
