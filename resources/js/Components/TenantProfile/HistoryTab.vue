<script setup lang="ts">
import { useFormatters } from '@/composables';
import type { TenantHistoryTabProps } from '@/types';

const props = defineProps<TenantHistoryTabProps>();
const { formatDate, formatMoney: formatCurrency } = useFormatters();

const paymentMethodLabel = (method) => {
    const labels = {
        'cash': 'Cash',
        'bank_transfer': 'Bank Transfer',
        'mobile_money': 'M-Pesa',
        'mpesa': 'M-Pesa',
        'paystack': 'Card (Paystack)',
        'stripe': 'Card (Stripe)'
    };
    return labels[method] || method;
};

const invoiceStatusClass = (status) => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800',
        'sent': 'bg-blue-100 text-blue-800',
        'partial': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-green-100 text-green-800',
        'overdue': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-900">Payment History</h3>
                <span class="text-xs text-gray-500">{{ payments?.length || 0 }} payments</span>
            </div>
            <div v-if="!payments?.length" class="p-8 text-center text-gray-500">
                <p class="text-sm">No payment records found.</p>
            </div>
            <ul v-else class="divide-y max-h-64 overflow-y-auto">
                <li v-for="payment in payments" :key="payment.id" class="p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                            <p class="text-xs text-gray-500">
                                {{ paymentMethodLabel(payment.payment_method) }}
                                <span v-if="payment.invoice?.invoice_number">
                                    &middot; {{ payment.invoice.invoice_number }}
                                </span>
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-xs text-gray-500">{{ formatDate(payment.created_at) }}</p>
                            <p v-if="payment.reference" class="text-xs text-gray-400 font-mono">{{ payment.reference }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-900">Invoice History</h3>
                <span class="text-xs text-gray-500">{{ invoices?.length || 0 }} invoices</span>
            </div>
            <div v-if="!invoices?.length" class="p-8 text-center text-gray-500">
                <p class="text-sm">No invoices found.</p>
            </div>
            <ul v-else class="divide-y max-h-64 overflow-y-auto">
                <li v-for="invoice in invoices" :key="invoice.id" class="p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                            <p class="text-xs text-gray-500">
                                Due: {{ formatDate(invoice.due_date) }}
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-medium text-gray-900">{{ formatCurrency(invoice.total_amount) }}</p>
                            <span :class="[invoiceStatusClass(invoice.status), 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize']">
                                {{ invoice.status }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="pastLeases?.length" class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3">
                <h3 class="text-sm font-medium text-gray-900">Previous Leases</h3>
            </div>
            <ul class="divide-y">
                <li v-for="lease in pastLeases" :key="lease.id" class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ lease.unit?.unit_number || 'Unknown' }}
                                <span class="text-gray-500 font-normal">
                                    at {{ lease.unit?.building?.name || 'Unknown' }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ formatDate(lease.start_date) }} - {{ formatDate(lease.end_date) }}
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-medium">{{ formatCurrency(lease.rent_amount) }}/mo</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
