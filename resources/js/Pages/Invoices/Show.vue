<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import {
    DocumentTextIcon,
    ArrowLeftIcon,
    CreditCardIcon,
    EyeIcon,
    ArrowDownTrayIcon,
    EnvelopeIcon,
    XCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    invoice: Object,
});

const showPaymentModal = ref(false);
const showVoidModal = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference: '',
    notes: '',
});

const voidForm = useForm({
    reason: '',
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
    }).format(amount || 0);
};

const statusColor = (status) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        partial: 'bg-yellow-100 text-yellow-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
        voided: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const remainingBalance = computed(() => {
    return (props.invoice?.total_due || 0) - (props.invoice?.amount_paid || 0);
});

const paymentProgress = computed(() => {
    if (!props.invoice || props.invoice.total_due === 0) return 0;
    return Math.round((props.invoice.amount_paid / props.invoice.total_due) * 100);
});

const canVoid = computed(() => {
    return ['draft', 'sent'].includes(props.invoice?.status) && (props.invoice?.amount_paid || 0) === 0;
});

const isVoided = computed(() => props.invoice?.status === 'voided');

const submitPayment = () => {
    paymentForm.post(route('invoices.recordPayment', props.invoice.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        },
    });
};

const updateStatus = (newStatus) => {
    router.put(route('invoices.updateStatus', props.invoice.id), {
        status: newStatus,
    });
};

const previewInvoice = () => {
    window.open(route('invoices.preview', props.invoice.id), '_blank');
};

const downloadInvoice = () => {
    window.open(route('invoices.download', props.invoice.id), '_blank');
};

const sendReminder = () => {
    router.post(route('invoices.send-reminder', props.invoice.id));
};

const submitVoid = () => {
    voidForm.post(route('invoices.void', props.invoice.id), {
        onSuccess: () => {
            showVoidModal.value = false;
            voidForm.reset();
        },
    });
};

const reissueInvoice = () => {
    router.post(route('invoices.reissue', props.invoice.id));
};
</script>

<template>
    <Head :title="`Invoice ${invoice?.invoice_number || ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('invoices.index')" class="text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Invoice {{ invoice?.invoice_number }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ invoice?.invoice_number }}</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ invoice?.lease?.tenant?.name }} - Unit {{ invoice?.lease?.unit?.unit_number }}
                                </p>
                            </div>
                            <span :class="[statusColor(invoice?.status), 'px-3 py-1 text-sm font-medium rounded-full']">
                                {{ invoice?.status }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Total Due</h4>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(invoice?.total_due) }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Amount Paid</h4>
                            <p class="text-2xl font-bold text-emerald-600">{{ formatCurrency(invoice?.amount_paid) }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Remaining Balance</h4>
                            <p class="text-2xl font-bold" :class="remainingBalance > 0 ? 'text-red-600' : 'text-gray-900'">
                                {{ formatCurrency(remainingBalance) }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Due Date</h4>
                            <p class="text-lg text-gray-900">{{ invoice?.due_date }}</p>
                        </div>
                        <div v-if="invoice?.billing_period_start">
                            <h4 class="text-sm font-medium text-gray-500">Billing Period</h4>
                            <p class="text-lg text-gray-900">
                                {{ invoice.billing_period_start }} - {{ invoice.billing_period_end }}
                            </p>
                        </div>
                    </div>

                    <div v-if="invoice?.total_due > 0" class="p-6 border-t border-gray-200">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Payment Progress</span>
                                <span class="font-medium text-gray-900">{{ paymentProgress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                    :style="{ width: `${paymentProgress}%` }"
                                />
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Paid: {{ formatCurrency(invoice.amount_paid) }}</span>
                                <span>Total: {{ formatCurrency(invoice.total_due) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">Line Items</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Rent</span>
                                <span>{{ formatCurrency(invoice?.rent_amount) }}</span>
                            </div>
                            <div v-if="invoice?.water_charges > 0" class="flex justify-between text-sm">
                                <span>Water Charges</span>
                                <span>{{ formatCurrency(invoice?.water_charges) }}</span>
                            </div>
                            <div v-if="invoice?.previous_arrears > 0" class="flex justify-between text-sm text-red-600">
                                <span>Previous Arrears</span>
                                <span>{{ formatCurrency(invoice?.previous_arrears) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoice?.payments?.length" class="p-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">Payment History</h4>
                        <div class="space-y-3">
                            <div
                                v-for="payment in invoice.payments"
                                :key="payment.id"
                                class="flex justify-between items-center p-3 bg-gray-50 rounded-lg"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ payment.payment_method }} - {{ payment.payment_date }}
                                    </p>
                                </div>
                                <span v-if="payment.reference" class="text-sm text-gray-500">
                                    Ref: {{ payment.reference }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-200">
                        <div class="flex flex-wrap gap-3">
                            <button
                                @click="previewInvoice"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                <EyeIcon class="w-5 h-5 mr-2" />
                                Preview PDF
                            </button>

                            <button
                                @click="downloadInvoice"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                                Download PDF
                            </button>

                            <button
                                v-if="invoice?.status === 'draft'"
                                @click="updateStatus('sent')"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <EnvelopeIcon class="w-5 h-5 mr-2" />
                                Mark as Sent
                            </button>

                            <button
                                v-if="['sent', 'partial', 'overdue'].includes(invoice?.status)"
                                @click="sendReminder"
                                class="inline-flex items-center px-4 py-2 border border-blue-300 text-blue-700 rounded-md hover:bg-blue-50"
                            >
                                <EnvelopeIcon class="w-5 h-5 mr-2" />
                                Send Reminder
                            </button>

                            <button
                                v-if="['sent', 'partial', 'overdue'].includes(invoice?.status)"
                                @click="showPaymentModal = true"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700"
                            >
                                <CreditCardIcon class="w-5 h-5 mr-2" />
                                Record Payment
                            </button>

                            <button
                                v-if="canVoid"
                                @click="showVoidModal = true"
                                class="inline-flex items-center px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50"
                            >
                                <XCircleIcon class="w-5 h-5 mr-2" />
                                Void Invoice
                            </button>

                            <button
                                v-if="isVoided"
                                @click="reissueInvoice"
                                class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700"
                            >
                                <ArrowPathIcon class="w-5 h-5 mr-2" />
                                Reissue Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-30" @click="showPaymentModal = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Record Payment</h3>

                        <form @submit.prevent="submitPayment">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Amount</label>
                                    <input
                                        v-model="paymentForm.amount"
                                        type="number"
                                        step="0.01"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                        :placeholder="remainingBalance"
                                    />
                                    <p v-if="paymentForm.errors.amount" class="mt-1 text-sm text-red-600">
                                        {{ paymentForm.errors.amount }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                                    <select
                                        v-model="paymentForm.payment_method"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                    <p v-if="paymentForm.errors.payment_method" class="mt-1 text-sm text-red-600">
                                        {{ paymentForm.errors.payment_method }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Reference (Optional)</label>
                                    <input
                                        v-model="paymentForm.reference"
                                        type="text"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="showPaymentModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="paymentForm.processing"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    Record Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="showVoidModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-30" @click="showVoidModal = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Void Invoice</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Are you sure you want to void this invoice? This action cannot be undone.
                        </p>

                        <form @submit.prevent="submitVoid">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Reason for voiding</label>
                                <textarea
                                    v-model="voidForm.reason"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                    placeholder="Enter reason..."
                                    required
                                ></textarea>
                                <p v-if="voidForm.errors.reason" class="mt-1 text-sm text-red-600">
                                    {{ voidForm.errors.reason }}
                                </p>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="showVoidModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="voidForm.processing"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                                >
                                    Void Invoice
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
