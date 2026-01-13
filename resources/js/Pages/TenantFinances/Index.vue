<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

const props = defineProps({
    hasLease: Boolean,
    lease: Object,
    balance: Number,
    pendingInvoices: Array,
    recentPayments: Array,
});

const { formatMoney, formatDate } = useFormatters();

const hasArrears = computed(() => props.balance > 0);

const balanceColor = computed(() => {
    if (props.balance > 0) return 'red';
    if (props.balance < 0) return 'emerald';
    return 'gray';
});

const nextDueInvoice = computed(() => {
    if (!props.pendingInvoices?.length) return null;
    return props.pendingInvoices[0];
});
</script>

<template>
    <Head title="My Finances" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <BanknotesIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">My Finances</h1>
                    <p class="text-sm text-gray-500">View and pay your rent</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="!hasLease" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <EmptyState
                        :icon="HomeIcon"
                        title="No Active Lease"
                        description="You don't have an active lease. Please contact your landlord if you believe this is an error."
                    />
                </div>

                <div v-else class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Current Balance</p>
                                <p :class="[
                                    'text-3xl font-bold mt-1',
                                    balance > 0 ? 'text-red-600' : balance < 0 ? 'text-emerald-600' : 'text-gray-900'
                                ]">
                                    {{ formatMoney(Math.abs(balance)) }}
                                    <span v-if="balance !== 0" class="text-lg font-normal">
                                        {{ balance > 0 ? 'Due' : 'Credit' }}
                                    </span>
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ lease.unit }} - {{ lease.building }}
                                </p>
                            </div>

                            <div v-if="nextDueInvoice" class="flex-shrink-0">
                                <Link
                                    :href="route('tenant.finances.pay', nextDueInvoice.id)"
                                    class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-200"
                                >
                                    <CreditCardIcon class="h-5 w-5" />
                                    Pay Now
                                </Link>
                            </div>
                        </div>

                        <div v-if="hasArrears" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <ExclamationTriangleIcon class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-red-800">You have an outstanding balance</p>
                                    <p class="text-sm text-red-700">Please pay as soon as possible to avoid late fees.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="pendingInvoices?.length" class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Pending Invoices</h2>
                            <span class="text-xs text-gray-500">{{ pendingInvoices.length }} pending</span>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <Link
                                v-for="invoice in pendingInvoices"
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
                                        <p class="text-xs text-gray-500">Due {{ formatDate(invoice.due_date) }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <AmountDisplay :amount="invoice.balance" size="sm" class="text-gray-900" />
                                    <InvoiceStatusBadge :status="invoice.status" size="sm" class="mt-1" />
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Recent Payments</h2>
                            <Link
                                :href="route('tenant.finances.history')"
                                class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                            >
                                View all
                            </Link>
                        </div>
                        <div v-if="recentPayments?.length" class="divide-y divide-gray-200">
                            <div
                                v-for="payment in recentPayments"
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
                                <div class="text-right">
                                    <AmountDisplay :amount="payment.amount" size="sm" />
                                    <PaymentMethodBadge :method="payment.payment_method" size="sm" :show-icon="false" class="mt-1" />
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-5 py-8">
                            <EmptyState
                                :icon="BanknotesIcon"
                                title="No payments yet"
                                description="Your payment history will appear here"
                                size="sm"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
