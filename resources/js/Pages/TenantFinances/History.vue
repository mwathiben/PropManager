<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { TenantFinancesHistoryPageProps } from '@/types';
import {
    DataTable,
    InvoiceStatusBadge,
    PaymentMethodBadge,
    AmountDisplay,
    EmptyState,
} from '@/Components/Finances';
import {
    BanknotesIcon,
    DocumentTextIcon,
    ChevronLeftIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantFinancesHistoryPageProps>();

const { formatDate } = useFormatters();

const activeTab = ref('payments');

const paymentColumns = [
    { key: 'payment_date', label: 'Date', sortable: true },
    { key: 'amount', label: 'Amount', align: 'right', sortable: true },
    { key: 'payment_method', label: 'Method', sortable: false },
    { key: 'reference', label: 'Reference', sortable: false },
    { key: 'actions', label: '', align: 'right' },
];

const invoiceColumns = [
    { key: 'created_at', label: 'Date', sortable: true },
    { key: 'invoice_number', label: 'Invoice #', sortable: false },
    { key: 'total_due', label: 'Amount', align: 'right', sortable: true },
    { key: 'amount_paid', label: 'Paid', align: 'right', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const downloadReceipt = (payment) => {
    window.open(route('payments.downloadReceipt', payment.id), '_blank');
};
</script>

<template>
    <Head title="Payment History" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('tenant.finances.index')"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ChevronLeftIcon class="w-5 h-5" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Payment History</h1>
                    <p class="text-sm text-gray-500">View all your payments and invoices</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                @click="activeTab = 'payments'"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'payments'
                                        ? 'border-emerald-500 text-emerald-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <BanknotesIcon :class="['w-5 h-5', activeTab === 'payments' ? 'text-emerald-500' : 'text-gray-400']" />
                                Payments
                            </button>
                            <button
                                @click="activeTab = 'invoices'"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'invoices'
                                        ? 'border-emerald-500 text-emerald-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <DocumentTextIcon :class="['w-5 h-5', activeTab === 'invoices' ? 'text-emerald-500' : 'text-gray-400']" />
                                Invoices
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <div v-if="activeTab === 'payments'">
                            <DataTable
                                :columns="paymentColumns"
                                :data="payments?.data || []"
                                :loading="false"
                                row-key="id"
                                :empty-icon="BanknotesIcon"
                                empty-title="No payments yet"
                                empty-description="Your payment history will appear here"
                            >
                                <template #cell-payment_date="{ row }">
                                    <span class="text-sm text-gray-900">{{ formatDate(row.payment_date) }}</span>
                                </template>

                                <template #cell-amount="{ row }">
                                    <AmountDisplay :amount="row.amount" size="sm" />
                                </template>

                                <template #cell-payment_method="{ row }">
                                    <PaymentMethodBadge :method="row.payment_method" size="sm" />
                                </template>

                                <template #cell-reference="{ row }">
                                    <span class="text-sm text-gray-600">{{ row.reference || row.invoice_number || '-' }}</span>
                                </template>

                                <template #cell-actions="{ row }">
                                    <button
                                        @click.stop="downloadReceipt(row)"
                                        class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded"
                                        title="Download Receipt"
                                    >
                                        <DocumentArrowDownIcon class="h-4 w-4" />
                                    </button>
                                </template>
                            </DataTable>

                            <div v-if="payments?.links?.length > 3" class="mt-4 flex justify-center">
                                <nav class="flex items-center gap-1">
                                    <template v-for="link in payments.links" :key="link.label">
                                        <button
                                            v-if="link.url"
                                            @click="router.visit(link.url)"
                                            :class="[
                                                'px-3 py-1.5 text-sm rounded-lg transition-colors',
                                                link.active
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'text-gray-600 hover:bg-gray-100'
                                            ]"
                                            v-html="link.label"
                                        />
                                        <span
                                            v-else
                                            class="px-3 py-1.5 text-sm text-gray-400"
                                            v-html="link.label"
                                        />
                                    </template>
                                </nav>
                            </div>
                        </div>

                        <div v-if="activeTab === 'invoices'">
                            <DataTable
                                :columns="invoiceColumns"
                                :data="invoices?.data || []"
                                :loading="false"
                                row-key="id"
                                :empty-icon="DocumentTextIcon"
                                empty-title="No invoices yet"
                                empty-description="Your invoices will appear here"
                            >
                                <template #cell-created_at="{ row }">
                                    <span class="text-sm text-gray-900">{{ formatDate(row.created_at) }}</span>
                                </template>

                                <template #cell-total_due="{ row }">
                                    <AmountDisplay :amount="row.total_due" size="sm" />
                                </template>

                                <template #cell-amount_paid="{ row }">
                                    <AmountDisplay :amount="row.amount_paid" size="sm" :colorize="row.amount_paid > 0" />
                                </template>

                                <template #cell-status="{ row }">
                                    <InvoiceStatusBadge :status="row.status" size="sm" />
                                </template>
                            </DataTable>

                            <div v-if="invoices?.links?.length > 3" class="mt-4 flex justify-center">
                                <nav class="flex items-center gap-1">
                                    <template v-for="link in invoices.links" :key="link.label">
                                        <button
                                            v-if="link.url"
                                            @click="router.visit(link.url)"
                                            :class="[
                                                'px-3 py-1.5 text-sm rounded-lg transition-colors',
                                                link.active
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'text-gray-600 hover:bg-gray-100'
                                            ]"
                                            v-html="link.label"
                                        />
                                        <span
                                            v-else
                                            class="px-3 py-1.5 text-sm text-gray-400"
                                            v-html="link.label"
                                        />
                                    </template>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
