<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useFormatters, usePayments } from '@/composables';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { AmountDisplay, InvoiceStatusBadge } from '@/Components/Finances';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
    ChevronLeftIcon,
    CheckCircleIcon,
    ClipboardDocumentIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    invoice: Object,
    lease: Object,
    paymentMethods: Array,
    paystackPublicKey: String,
});

const { formatMoney, formatDate } = useFormatters();
const { initiatePaystackPayment, initiateMpesaPayment, checkMpesaStatus, isProcessing, error } = usePayments();

const selectedMethod = ref(null);
const phoneNumber = ref('');
const copied = ref(false);
const mpesaState = ref('idle');
const mpesaMessage = ref('');
const checkoutRequestId = ref(null);
let pollingInterval = null;

const methodIcons = {
    cash: BanknotesIcon,
    bank_transfer: BuildingLibraryIcon,
    mobile_money: DevicePhoneMobileIcon,
    mpesa: DevicePhoneMobileIcon,
    paystack: CreditCardIcon,
    stripe: CreditCardIcon,
};

const selectedMethodData = computed(() => {
    return props.paymentMethods?.find(m => m.id === selectedMethod.value);
});

const canProceed = computed(() => {
    if (!selectedMethod.value) return false;
    if (selectedMethod.value === 'mpesa' || selectedMethod.value === 'mobile_money') {
        return phoneNumber.value.length >= 10;
    }
    return true;
});

const selectMethod = (method) => {
    selectedMethod.value = method.id;
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

const pollMpesaStatus = async () => {
    if (!checkoutRequestId.value) return;

    const result = await checkMpesaStatus(checkoutRequestId.value);

    if (result.status === 'completed') {
        stopPolling();
        mpesaState.value = 'success';
        mpesaMessage.value = result.message;
        setTimeout(() => {
            router.visit(route('tenant.finances.index'), {
                preserveState: false,
            });
        }, 2000);
    } else if (result.status === 'cancelled' || result.status === 'failed') {
        stopPolling();
        mpesaState.value = 'failed';
        mpesaMessage.value = result.message;
    } else if (result.status === 'processing') {
        mpesaState.value = 'processing';
        mpesaMessage.value = result.message;
    }
};

const startPolling = () => {
    stopPolling();
    pollingInterval = setInterval(pollMpesaStatus, 3000);
};

const proceedWithPayment = async () => {
    if (!canProceed.value) return;

    try {
        if (selectedMethod.value === 'paystack' || selectedMethod.value === 'stripe') {
            await initiatePaystackPayment(props.invoice.id, props.invoice.balance);
        } else if (selectedMethod.value === 'mpesa' || selectedMethod.value === 'mobile_money') {
            mpesaState.value = 'sending';
            mpesaMessage.value = 'Sending STK push to your phone...';

            const result = await initiateMpesaPayment(props.invoice.id, props.invoice.balance, phoneNumber.value);

            if (result.success && result.checkout_request_id) {
                checkoutRequestId.value = result.checkout_request_id;
                mpesaState.value = 'waiting';
                mpesaMessage.value = 'Please enter your M-Pesa PIN on your phone';
                startPolling();
            }
        }
    } catch (err) {
        mpesaState.value = 'failed';
        mpesaMessage.value = err.message || 'Payment failed';
        console.error('Payment failed:', err);
    }
};

const resetMpesaState = () => {
    stopPolling();
    mpesaState.value = 'idle';
    mpesaMessage.value = '';
    checkoutRequestId.value = null;
};

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head :title="`Pay Invoice ${invoice.invoice_number}`" />

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
                    <h1 class="text-lg font-semibold text-gray-900">Pay Invoice</h1>
                    <p class="text-sm text-gray-500">{{ invoice.invoice_number }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Amount Due</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                {{ formatMoney(invoice.balance) }}
                            </p>
                        </div>
                        <InvoiceStatusBadge :status="invoice.status" />
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Unit</p>
                                <p class="font-medium text-gray-900">{{ lease.unit }} - {{ lease.building }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Due Date</p>
                                <p class="font-medium text-gray-900">{{ formatDate(invoice.due_date) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoice.rent_amount || invoice.water_charges || invoice.arrears_amount" class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Breakdown</p>
                        <div class="space-y-1 text-sm">
                            <div v-if="invoice.rent_amount" class="flex justify-between">
                                <span class="text-gray-600">Rent</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.rent_amount) }}</span>
                            </div>
                            <div v-if="invoice.water_charges" class="flex justify-between">
                                <span class="text-gray-600">Water</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.water_charges) }}</span>
                            </div>
                            <div v-if="invoice.arrears_amount" class="flex justify-between">
                                <span class="text-gray-600">Arrears</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.arrears_amount) }}</span>
                            </div>
                            <div v-if="invoice.amount_paid > 0" class="flex justify-between text-emerald-600">
                                <span>Paid</span>
                                <span>-{{ formatMoney(invoice.amount_paid) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Select Payment Method</h2>

                    <div class="space-y-3">
                        <button
                            v-for="method in paymentMethods"
                            :key="method.id"
                            @click="selectMethod(method)"
                            :class="[
                                'w-full flex items-start gap-4 p-4 rounded-xl border-2 transition-all text-left',
                                selectedMethod === method.id
                                    ? 'border-emerald-500 bg-emerald-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]"
                        >
                            <div :class="[
                                'p-2.5 rounded-lg',
                                selectedMethod === method.id ? 'bg-emerald-100' : 'bg-gray-100'
                            ]">
                                <component
                                    :is="methodIcons[method.id] || CreditCardIcon"
                                    :class="[
                                        'h-5 w-5',
                                        selectedMethod === method.id ? 'text-emerald-600' : 'text-gray-500'
                                    ]"
                                />
                            </div>
                            <div class="flex-1">
                                <p :class="[
                                    'font-medium',
                                    selectedMethod === method.id ? 'text-emerald-900' : 'text-gray-900'
                                ]">
                                    {{ method.label }}
                                </p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ method.description }}</p>
                            </div>
                            <div v-if="selectedMethod === method.id" class="flex-shrink-0">
                                <CheckCircleIcon class="h-6 w-6 text-emerald-500" />
                            </div>
                        </button>
                    </div>

                    <div v-if="selectedMethodData?.details" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <InformationCircleIcon class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-blue-800 mb-2">Payment Details</p>

                                <template v-if="selectedMethod === 'bank_transfer'">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">Bank:</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.bank_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">Account Name:</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.account_name }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-blue-700">Account Number:</span>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-blue-900">{{ selectedMethodData.details.account_number }}</span>
                                                <button
                                                    @click="copyToClipboard(selectedMethodData.details.account_number)"
                                                    class="p-1 text-blue-600 hover:text-blue-800"
                                                >
                                                    <ClipboardDocumentIcon class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template v-if="selectedMethod === 'mobile_money' || selectedMethod === 'mpesa'">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">Paybill:</span>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-blue-900">{{ selectedMethodData.details.paybill }}</span>
                                                <button
                                                    @click="copyToClipboard(selectedMethodData.details.paybill)"
                                                    class="p-1 text-blue-600 hover:text-blue-800"
                                                >
                                                    <ClipboardDocumentIcon class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">Account:</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.account_name }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p v-if="copied" class="text-xs text-emerald-600 mt-2">Copied to clipboard!</p>
                    </div>

                    <div v-if="(selectedMethod === 'mpesa' || selectedMethod === 'mobile_money') && mpesaState === 'idle'" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">M-Pesa Phone Number</label>
                        <input
                            v-model="phoneNumber"
                            type="tel"
                            placeholder="0712345678"
                            class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <p class="text-xs text-gray-500 mt-1">You'll receive an STK push to this number</p>
                    </div>

                    <!-- M-Pesa Status -->
                    <div v-if="mpesaState !== 'idle'" class="mt-4">
                        <div v-if="mpesaState === 'sending' || mpesaState === 'waiting' || mpesaState === 'processing'" class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-blue-500 border-t-transparent" />
                                <div>
                                    <p class="text-sm font-medium text-blue-800">{{ mpesaMessage }}</p>
                                    <p v-if="mpesaState === 'waiting'" class="text-xs text-blue-600 mt-1">
                                        Check your phone for the M-Pesa prompt
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="mpesaState === 'success'" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <CheckCircleIcon class="h-5 w-5 text-emerald-500" />
                                <div>
                                    <p class="text-sm font-medium text-emerald-800">{{ mpesaMessage }}</p>
                                    <p class="text-xs text-emerald-600 mt-1">Redirecting to your finances...</p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="mpesaState === 'failed'" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <InformationCircleIcon class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-800">{{ mpesaMessage }}</p>
                                    <button
                                        @click="resetMpesaState"
                                        class="mt-2 text-sm text-red-700 underline hover:text-red-900"
                                    >
                                        Try again
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="error && mpesaState === 'idle'" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">{{ error }}</p>
                    </div>

                    <div v-if="mpesaState === 'idle' || mpesaState === 'failed'" class="mt-6 flex flex-col sm:flex-row gap-3">
                        <button
                            v-if="selectedMethod === 'paystack' || selectedMethod === 'stripe' || ((selectedMethod === 'mpesa' || selectedMethod === 'mobile_money') && mpesaState === 'idle')"
                            @click="proceedWithPayment"
                            :disabled="!canProceed || isProcessing"
                            :class="[
                                'flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold rounded-xl transition-colors',
                                canProceed && !isProcessing
                                    ? 'text-white bg-emerald-600 hover:bg-emerald-700'
                                    : 'text-gray-400 bg-gray-100 cursor-not-allowed'
                            ]"
                        >
                            <CreditCardIcon v-if="!isProcessing" class="h-5 w-5" />
                            <span v-if="isProcessing">Processing...</span>
                            <span v-else>Pay {{ formatMoney(invoice.balance) }}</span>
                        </button>

                        <div v-else-if="selectedMethod === 'cash' || selectedMethod === 'bank_transfer'" class="flex-1 p-4 bg-gray-50 rounded-xl text-center">
                            <p class="text-sm text-gray-600">
                                {{ selectedMethod === 'cash' ? 'Pay cash to your landlord or caretaker' : 'Transfer the amount to the account above' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Your payment will be recorded once confirmed</p>
                        </div>

                        <Link
                            :href="route('tenant.finances.index')"
                            class="px-6 py-3 text-sm font-medium text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors text-center"
                        >
                            Cancel
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
