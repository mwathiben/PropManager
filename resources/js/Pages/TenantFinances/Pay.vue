<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useFormatters, usePayments, useEcho, useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { TenantFinancesPayPageProps } from '@/types';
import { AmountDisplay, InvoiceStatusBadge, PaymentMethodSelector } from '@/Components/Finances';
import {
    CreditCardIcon,
    ChevronLeftIcon,
    CheckCircleIcon,
    ClipboardDocumentIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantFinancesPayPageProps>();

const { t } = useI18n();
const { formatMoney, formatDate } = useFormatters({ currency: props.invoice.currency || 'KES' });
const { initiatePaystackPayment, initiateMpesaPayment, checkMpesaStatus, initiateIntaSendPayment, isProcessing, error } = usePayments();
const { subscribePrivate, unsubscribe, isConnected } = useEcho();
const { logError } = useErrorHandler();

const selectedMethod = ref(null);
const phoneNumber = ref('');
const copied = ref(false);
const mpesaState = ref('idle');
const mpesaMessage = ref('');
const checkoutRequestId = ref(null);
const echoSubscribed = ref(false);
let pollingInterval = null;
const FALLBACK_POLLING_INTERVAL = 30000;

const intasendState = ref('idle');
const intasendMessage = ref('');
const intasendInvoiceId = ref(null);
const intasendEchoSubscribed = ref(false);
let intasendTimeout = null;
const INTASEND_TIMEOUT = 60000;

const selectedMethodData = computed(() => {
    return props.paymentMethods?.find(m => m.id === selectedMethod.value);
});

const canProceed = computed(() => {
    if (!selectedMethod.value) return false;
    if (selectedMethod.value === 'mpesa' || selectedMethod.value === 'mobile_money' || selectedMethod.value === 'intasend_mpesa') {
        return phoneNumber.value.length >= 10;
    }
    return true;
});

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

const unsubscribeEcho = () => {
    if (checkoutRequestId.value && echoSubscribed.value) {
        unsubscribe(`mpesa.${checkoutRequestId.value}`);
        echoSubscribed.value = false;
    }
};

const handleMpesaStatusUpdate = (data) => {
    stopPolling();
    unsubscribeEcho();

    if (data.status === 'success') {
        mpesaState.value = 'success';
        mpesaMessage.value = data.message || t('tenant_finances_pay.messages.payment_received_success');
        setTimeout(() => {
            router.visit(route('tenant.finances.index'), {
                preserveState: false,
            });
        }, 2000);
    } else if (data.status === 'cancelled' || data.status === 'failed') {
        mpesaState.value = 'failed';
        mpesaMessage.value = data.message || (data.status === 'cancelled' ? t('tenant_finances_pay.messages.payment_cancelled') : t('tenant_finances_pay.messages.payment_failed'));
    }
};

const subscribeToMpesaUpdates = () => {
    if (!checkoutRequestId.value || echoSubscribed.value) return;

    subscribePrivate(
        `mpesa.${checkoutRequestId.value}`,
        'MpesaPaymentStatusChanged',
        handleMpesaStatusUpdate
    );
    echoSubscribed.value = true;
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
    subscribeToMpesaUpdates();
    pollingInterval = setInterval(pollMpesaStatus, isConnected.value ? FALLBACK_POLLING_INTERVAL : 3000);
};

const unsubscribeIntaSendEcho = () => {
    if (intasendInvoiceId.value && intasendEchoSubscribed.value) {
        unsubscribe(`intasend.${intasendInvoiceId.value}`);
        intasendEchoSubscribed.value = false;
    }
};

const stopIntaSendTimeout = () => {
    if (intasendTimeout) {
        clearTimeout(intasendTimeout);
        intasendTimeout = null;
    }
};

const handleIntaSendStatusUpdate = (data) => {
    stopIntaSendTimeout();

    if (data.status === 'success' || data.state === 'COMPLETE') {
        unsubscribeIntaSendEcho();
        intasendState.value = 'success';
        intasendMessage.value = data.message || t('tenant_finances_pay.messages.payment_received_success');
        setTimeout(() => {
            router.visit(route('tenant.finances.index'), {
                preserveState: false,
            });
        }, 2000);
    } else if (data.status === 'failed' || data.state === 'FAILED') {
        unsubscribeIntaSendEcho();
        intasendState.value = 'failed';
        intasendMessage.value = data.failure_reason || data.message || t('tenant_finances_pay.messages.payment_failed');
    } else if (data.state === 'PROCESSING') {
        intasendState.value = 'processing';
        intasendMessage.value = t('tenant_finances_pay.messages.payment_processing');
    }
};

const subscribeToIntaSendUpdates = () => {
    if (!intasendInvoiceId.value || intasendEchoSubscribed.value) return;

    subscribePrivate(
        `intasend.${intasendInvoiceId.value}`,
        'IntaSendPaymentStatusChanged',
        handleIntaSendStatusUpdate
    );
    intasendEchoSubscribed.value = true;
};

const startIntaSendTimeout = () => {
    stopIntaSendTimeout();
    intasendTimeout = setTimeout(() => {
        if (intasendState.value === 'waiting' || intasendState.value === 'processing') {
            intasendState.value = 'failed';
            intasendMessage.value = t('tenant_finances_pay.messages.payment_timed_out');
        }
    }, INTASEND_TIMEOUT);
};

const resetIntaSendState = () => {
    stopIntaSendTimeout();
    unsubscribeIntaSendEcho();
    intasendState.value = 'idle';
    intasendMessage.value = '';
    intasendInvoiceId.value = null;
};

const proceedWithPayment = async () => {
    if (!canProceed.value) return;

    try {
        if (selectedMethod.value === 'paystack' || selectedMethod.value === 'stripe') {
            await initiatePaystackPayment(props.invoice.id, props.invoice.balance);
        } else if (selectedMethod.value === 'mpesa' || selectedMethod.value === 'mobile_money') {
            mpesaState.value = 'sending';
            mpesaMessage.value = t('tenant_finances_pay.messages.sending_stk_push');

            const result = await initiateMpesaPayment(props.invoice.id, props.invoice.balance, phoneNumber.value);

            if (result.success && result.checkout_request_id) {
                checkoutRequestId.value = result.checkout_request_id;
                mpesaState.value = 'waiting';
                mpesaMessage.value = t('tenant_finances_pay.messages.enter_mpesa_pin');
                startPolling();
            }
        } else if (selectedMethod.value === 'intasend_mpesa') {
            intasendState.value = 'sending';
            intasendMessage.value = t('tenant_finances_pay.messages.sending_stk_push');

            const result = await initiateIntaSendPayment(props.invoice.id, props.invoice.balance, phoneNumber.value);

            if (result.success && result.intasend_invoice_id) {
                intasendInvoiceId.value = result.intasend_invoice_id;
                intasendState.value = 'waiting';
                intasendMessage.value = t('tenant_finances_pay.messages.enter_mpesa_pin');
                subscribeToIntaSendUpdates();
                startIntaSendTimeout();
            } else if (result.success && !result.intasend_invoice_id) {
                console.warn('IntaSend returned success but no intasend_invoice_id', result);
                intasendState.value = 'failed';
                intasendMessage.value = t('tenant_finances_pay.messages.failed_to_initiate_mpesa');
                intasendInvoiceId.value = null;
            }
        }
    } catch (err) {
        if (selectedMethod.value === 'intasend_mpesa') {
            intasendState.value = 'failed';
            intasendMessage.value = err.message || t('tenant_finances_pay.messages.payment_failed');
        } else {
            mpesaState.value = 'failed';
            mpesaMessage.value = err.message || t('tenant_finances_pay.messages.payment_failed');
        }
        logError(err, { component: 'TenantFinancesPay', action: 'processPayment' });
    }
};

const resetMpesaState = () => {
    stopPolling();
    unsubscribeEcho();
    mpesaState.value = 'idle';
    mpesaMessage.value = '';
    checkoutRequestId.value = null;
};

onUnmounted(() => {
    stopPolling();
    unsubscribeEcho();
    stopIntaSendTimeout();
    unsubscribeIntaSendEcho();
});
</script>

<template>
    <Head :title="t('tenant_finances_pay.page_title', { invoice_number: invoice.invoice_number })" />

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
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('tenant_finances_pay.heading') }}</h1>
                    <p class="text-sm text-gray-500">{{ invoice.invoice_number }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500">{{ t('tenant_finances_pay.amount_due') }}</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                {{ formatMoney(invoice.balance) }}
                            </p>
                        </div>
                        <InvoiceStatusBadge :status="invoice.status" />
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">{{ t('tenant_finances_pay.unit') }}</p>
                                <p class="font-medium text-gray-900">{{ lease.unit }} - {{ lease.building }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">{{ t('tenant_finances_pay.due_date') }}</p>
                                <p class="font-medium text-gray-900">{{ formatDate(invoice.due_date) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoice.rent_amount || invoice.water_charges || invoice.arrears_amount" class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">{{ t('tenant_finances_pay.breakdown') }}</p>
                        <div class="space-y-1 text-sm">
                            <div v-if="invoice.rent_amount" class="flex justify-between">
                                <span class="text-gray-600">{{ t('tenant_finances_pay.rent') }}</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.rent_amount) }}</span>
                            </div>
                            <div v-if="invoice.water_charges" class="flex justify-between">
                                <span class="text-gray-600">{{ t('tenant_finances_pay.water') }}</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.water_charges) }}</span>
                            </div>
                            <div v-if="invoice.arrears_amount" class="flex justify-between">
                                <span class="text-gray-600">{{ t('tenant_finances_pay.arrears') }}</span>
                                <span class="text-gray-900">{{ formatMoney(invoice.arrears_amount) }}</span>
                            </div>
                            <div v-if="invoice.amount_paid > 0" class="flex justify-between text-emerald-600">
                                <span>{{ t('tenant_finances_pay.paid') }}</span>
                                <span>-{{ formatMoney(invoice.amount_paid) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ t('tenant_finances_pay.select_payment_method') }}</h2>

                    <PaymentMethodSelector
                        v-model="selectedMethod"
                        :methods="paymentMethods"
                        mode="card"
                    />

                    <div v-if="selectedMethodData?.details" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <InformationCircleIcon class="h-5 w-5 text-blue-500 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-blue-800 mb-2">{{ t('tenant_finances_pay.payment_details') }}</p>

                                <template v-if="selectedMethod === 'bank_transfer'">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">{{ t('tenant_finances_pay.bank') }}</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.bank_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-blue-700">{{ t('tenant_finances_pay.account_name') }}</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.account_name }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-blue-700">{{ t('tenant_finances_pay.account_number') }}</span>
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
                                            <span class="text-blue-700">{{ t('tenant_finances_pay.paybill') }}</span>
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
                                            <span class="text-blue-700">{{ t('tenant_finances_pay.account') }}</span>
                                            <span class="font-medium text-blue-900">{{ selectedMethodData.details.account_name }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p v-if="copied" class="text-xs text-emerald-600 mt-2">{{ t('tenant_finances_pay.copied_to_clipboard') }}</p>
                    </div>

                    <div v-if="(selectedMethod === 'mpesa' || selectedMethod === 'mobile_money' || selectedMethod === 'intasend_mpesa') && mpesaState === 'idle' && intasendState === 'idle'" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenant_finances_pay.mpesa_phone_number') }}</label>
                        <input
                            v-model="phoneNumber"
                            type="tel"
                            :placeholder="t('tenant_finances_pay.phone_placeholder')"
                            class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <p class="text-xs text-gray-500 mt-1">{{ t('tenant_finances_pay.stk_push_hint') }}</p>
                    </div>

                    <!-- M-Pesa Status -->
                    <div v-if="mpesaState !== 'idle'" class="mt-4">
                        <div v-if="mpesaState === 'sending' || mpesaState === 'waiting' || mpesaState === 'processing'" class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-blue-500 border-t-transparent" />
                                <div>
                                    <p class="text-sm font-medium text-blue-800">{{ mpesaMessage }}</p>
                                    <p v-if="mpesaState === 'waiting'" class="text-xs text-blue-600 mt-1">
                                        {{ t('tenant_finances_pay.check_phone_for_prompt') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="mpesaState === 'success'" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <CheckCircleIcon class="h-5 w-5 text-emerald-500" />
                                <div>
                                    <p class="text-sm font-medium text-emerald-800">{{ mpesaMessage }}</p>
                                    <p class="text-xs text-emerald-600 mt-1">{{ t('tenant_finances_pay.redirecting_to_finances') }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="mpesaState === 'failed'" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <InformationCircleIcon class="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-800">{{ mpesaMessage }}</p>
                                    <button
                                        @click="resetMpesaState"
                                        class="mt-2 text-sm text-red-700 underline hover:text-red-900"
                                    >
                                        {{ t('tenant_finances_pay.try_again') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IntaSend Status -->
                    <div v-if="intasendState !== 'idle'" class="mt-4">
                        <div v-if="intasendState === 'sending' || intasendState === 'waiting' || intasendState === 'processing'" class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-blue-500 border-t-transparent" />
                                <div>
                                    <p class="text-sm font-medium text-blue-800">{{ intasendMessage }}</p>
                                    <p v-if="intasendState === 'waiting'" class="text-xs text-blue-600 mt-1">
                                        {{ t('tenant_finances_pay.check_phone_for_prompt') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="intasendState === 'success'" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <CheckCircleIcon class="h-5 w-5 text-emerald-500" />
                                <div>
                                    <p class="text-sm font-medium text-emerald-800">{{ intasendMessage }}</p>
                                    <p class="text-xs text-emerald-600 mt-1">{{ t('tenant_finances_pay.redirecting_to_finances') }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="intasendState === 'failed'" class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <InformationCircleIcon class="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-800">{{ intasendMessage }}</p>
                                    <button
                                        @click="resetIntaSendState"
                                        class="mt-2 text-sm text-red-700 underline hover:text-red-900"
                                    >
                                        {{ t('tenant_finances_pay.try_again') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="error && mpesaState === 'idle' && intasendState === 'idle'" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">{{ error }}</p>
                    </div>

                    <div v-if="(mpesaState === 'idle' || mpesaState === 'failed') && (intasendState === 'idle' || intasendState === 'failed')" class="mt-6 flex flex-col sm:flex-row gap-3">
                        <button
                            v-if="selectedMethod === 'paystack' || selectedMethod === 'stripe' || ((selectedMethod === 'mpesa' || selectedMethod === 'mobile_money') && mpesaState === 'idle') || (selectedMethod === 'intasend_mpesa' && intasendState === 'idle')"
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
                            <span v-if="isProcessing">{{ t('tenant_finances_pay.processing') }}</span>
                            <span v-else>{{ t('tenant_finances_pay.pay_amount', { amount: formatMoney(invoice.balance) }) }}</span>
                        </button>

                        <div v-else-if="selectedMethod === 'cash' || selectedMethod === 'bank_transfer'" class="flex-1 p-4 bg-gray-50 rounded-xl text-center">
                            <p class="text-sm text-gray-600">
                                {{ selectedMethod === 'cash' ? t('tenant_finances_pay.cash_instruction') : t('tenant_finances_pay.bank_transfer_instruction') }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">{{ t('tenant_finances_pay.recorded_once_confirmed') }}</p>
                        </div>

                        <Link
                            :href="route('tenant.finances.index')"
                            class="px-6 py-3 text-sm font-medium text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors text-center"
                        >
                            {{ t('tenant_finances_pay.cancel') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
