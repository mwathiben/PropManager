<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { InvoicesShowPageProps } from '@/types';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';
import HoldCreateModal from '@/Components/LegalHold/HoldCreateModal.vue';
import {
    DocumentTextIcon,
    ArrowLeftIcon,
    CreditCardIcon,
    EyeIcon,
    ArrowDownTrayIcon,
    EnvelopeIcon,
    XCircleIcon,
    ArrowPathIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<InvoicesShowPageProps>();
const { formatMoney: formatCurrency } = useFormatters();
const { t } = useI18n();

const showPaymentModal = ref(false);
const showVoidModal = ref(false);
const legalHoldModal = ref<InstanceType<typeof HoldCreateModal> | null>(null);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference: '',
    notes: '',
});

const voidForm = useForm({
    reason: '',
});

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

// Phase-21 DEFER-AUTHZ-3: server-resolved per-record gates. Each computed
// mirrors a Policy method outcome from props.invoice.abilities so the UI
// never advertises an action the InvoicePolicy will deny on click.
const canUpdateInvoice = computed(() => props.invoice?.abilities?.update ?? false);
const canDeleteInvoice = computed(() => props.invoice?.abilities?.delete ?? false);
const canSendInvoice = computed(() => props.invoice?.abilities?.send ?? false);
const canRecordPayment = computed(() => props.invoice?.abilities?.recordPayment ?? false);

const canVoid = computed(() => {
    // canVoid composes the update ability with business-state constraints
    // (status + amount_paid). The ability gate prevents cross-landlord +
    // restricted-user button rendering; the status/amount checks encode
    // the operator workflow (voided is reachable only from draft/sent
    // with no payments).
    return canUpdateInvoice.value
        && ['draft', 'sent'].includes(props.invoice?.status)
        && (props.invoice?.amount_paid || 0) === 0;
});

const isVoided = computed(() => props.invoice?.status === 'voided');

const pdfReady = computed(() => !!props.invoice?.pdf_path);
const pdfGenerating = computed(() => !props.invoice?.pdf_path && !props.invoice?.pdf_generated_at);

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
    <Head :title="t('invoices_show.head_title', { number: invoice?.invoice_number || '' })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('invoices.index')" class="text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ t('invoices_show.page_title', { number: invoice?.invoice_number ?? '' }) }}
                </h1>
                <PendingSyncBadge route-family="invoices" :resource-id="invoice?.id" />
                <button
                    v-if="invoice?.id"
                    type="button"
                    @click="legalHoldModal?.open()"
                    class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg"
                    data-testid="open-legal-hold"
                >
                    <ScaleIcon class="h-4 w-4" />
                    {{ t('invoices_show.legal_hold') }}
                </button>
                <Link
                    v-if="invoice?.id"
                    :href="route('legal-holds.history', { subject_type: 'App\\Models\\Invoice', subject_id: invoice.id })"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900"
                    data-testid="hold-history-link"
                >
                    {{ t('invoices_show.hold_history') }}
                </Link>
            </div>
        </template>

        <HoldCreateModal
            v-if="invoice?.id"
            ref="legalHoldModal"
            subject-type="App\\Models\\Invoice"
            :subject-id="invoice.id"
            :subject-label="t('invoices_show.head_title', { number: invoice.invoice_number })"
        />

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ invoice?.invoice_number }}</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ t('invoices_show.tenant_unit', { tenant: invoice?.lease?.tenant?.name ?? '', unit: invoice?.lease?.unit?.unit_number ?? '' }) }}
                                </p>
                            </div>
                            <span :class="[statusColor(invoice?.status), 'px-3 py-1 text-sm font-medium rounded-full']">
                                {{ t(`invoices_show.status.${invoice?.status}`, invoice?.status ?? '') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">{{ t('invoices_show.total_due') }}</h4>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(invoice?.total_due) }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">{{ t('invoices_show.amount_paid') }}</h4>
                            <p class="text-2xl font-bold text-emerald-600">{{ formatCurrency(invoice?.amount_paid) }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">{{ t('invoices_show.remaining_balance') }}</h4>
                            <p class="text-2xl font-bold" :class="remainingBalance > 0 ? 'text-red-600' : 'text-gray-900'">
                                {{ formatCurrency(remainingBalance) }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">{{ t('invoices_show.due_date') }}</h4>
                            <p class="text-lg text-gray-900">{{ invoice?.due_date }}</p>
                        </div>
                        <div v-if="invoice?.billing_period_start">
                            <h4 class="text-sm font-medium text-gray-500">{{ t('invoices_show.billing_period') }}</h4>
                            <p class="text-lg text-gray-900">
                                {{ t('invoices_show.billing_period_range', { start: invoice.billing_period_start, end: invoice.billing_period_end }) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="invoice?.total_due > 0" class="p-6 border-t border-gray-200">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ t('invoices_show.payment_progress') }}</span>
                                <span class="font-medium text-gray-900">{{ paymentProgress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                    :style="{ width: `${paymentProgress}%` }"
                                />
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>{{ t('invoices_show.paid_amount', { amount: formatCurrency(invoice.amount_paid) }) }}</span>
                                <span>{{ t('invoices_show.total_amount', { amount: formatCurrency(invoice.total_due) }) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">{{ t('invoices_show.line_items') }}</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>{{ t('invoices_show.rent') }}</span>
                                <span>{{ formatCurrency(invoice?.rent_amount) }}</span>
                            </div>
                            <div v-if="invoice?.water_charges > 0" class="flex justify-between text-sm">
                                <span>{{ t('invoices_show.water_charges') }}</span>
                                <span>{{ formatCurrency(invoice?.water_charges) }}</span>
                            </div>
                            <div v-if="invoice?.previous_arrears > 0" class="flex justify-between text-sm text-red-600">
                                <span>{{ t('invoices_show.previous_arrears') }}</span>
                                <span>{{ formatCurrency(invoice?.previous_arrears) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoice?.payments?.length" class="p-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">{{ t('invoices_show.payment_history') }}</h4>
                        <div class="space-y-3">
                            <div
                                v-for="payment in invoice.payments"
                                :key="payment.id"
                                class="flex justify-between items-center p-3 bg-gray-50 rounded-lg"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ t('invoices_show.payment_meta', { method: t(`invoices_show.payment_methods.${payment.payment_method}`, payment.payment_method ?? ''), date: payment.payment_date }) }}
                                    </p>
                                </div>
                                <span v-if="payment.reference" class="text-sm text-gray-500">
                                    {{ t('invoices_show.reference', { reference: payment.reference }) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-200">
                        <div v-if="pdfGenerating" class="mb-4 flex items-center gap-2 text-sm text-amber-600">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ t('invoices_show.generating_pdf') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button
                                @click="previewInvoice"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                <EyeIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.preview_pdf') }}
                            </button>

                            <button
                                @click="downloadInvoice"
                                :disabled="pdfGenerating"
                                :class="[
                                    'inline-flex items-center px-4 py-2 border rounded-md',
                                    pdfGenerating
                                        ? 'border-gray-200 text-gray-400 cursor-not-allowed'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                                ]"
                            >
                                <ArrowDownTrayIcon class="w-5 h-5 me-2" />
                                {{ pdfGenerating ? t('invoices_show.actions.downloading') : t('invoices_show.actions.download_pdf') }}
                            </button>

                            <button
                                v-if="canSendInvoice && invoice?.status === 'draft'"
                                @click="updateStatus('sent')"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <EnvelopeIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.mark_sent') }}
                            </button>

                            <button
                                v-if="canUpdateInvoice && ['sent', 'partial', 'overdue'].includes(invoice?.status)"
                                @click="sendReminder"
                                class="inline-flex items-center px-4 py-2 border border-blue-300 text-blue-700 rounded-md hover:bg-blue-50"
                            >
                                <EnvelopeIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.send_reminder') }}
                            </button>

                            <button
                                v-if="canRecordPayment && ['sent', 'partial', 'overdue'].includes(invoice?.status)"
                                @click="showPaymentModal = true"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700"
                            >
                                <CreditCardIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.record_payment') }}
                            </button>

                            <button
                                v-if="canVoid"
                                @click="showVoidModal = true"
                                class="inline-flex items-center px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50"
                            >
                                <XCircleIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.void_invoice') }}
                            </button>

                            <button
                                v-if="canUpdateInvoice && isVoided"
                                @click="reissueInvoice"
                                class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700"
                            >
                                <ArrowPathIcon class="w-5 h-5 me-2" />
                                {{ t('invoices_show.actions.reissue_invoice') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showPaymentModal = false"></div>
                    <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('invoices_show.payment_modal.title') }}</h3>

                        <form @submit.prevent="submitPayment">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ t('invoices_show.payment_modal.amount') }}</label>
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
                                    <label class="block text-sm font-medium text-gray-700">{{ t('invoices_show.payment_modal.payment_method') }}</label>
                                    <select
                                        v-model="paymentForm.payment_method"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="cash">{{ t('invoices_show.payment_methods.cash') }}</option>
                                        <option value="bank_transfer">{{ t('invoices_show.payment_methods.bank_transfer') }}</option>
                                        <option value="mobile_money">{{ t('invoices_show.payment_methods.mobile_money') }}</option>
                                    </select>
                                    <p v-if="paymentForm.errors.payment_method" class="mt-1 text-sm text-red-600">
                                        {{ paymentForm.errors.payment_method }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ t('invoices_show.payment_modal.reference_optional') }}</label>
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
                                    {{ t('invoices_show.payment_modal.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="paymentForm.processing"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {{ t('invoices_show.payment_modal.submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="showVoidModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showVoidModal = false"></div>
                    <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('invoices_show.void_modal.title') }}</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ t('invoices_show.void_modal.warning') }}
                        </p>

                        <form @submit.prevent="submitVoid">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ t('invoices_show.void_modal.reason_label') }}</label>
                                <textarea
                                    v-model="voidForm.reason"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                    :placeholder="t('invoices_show.void_modal.reason_placeholder')"
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
                                    {{ t('invoices_show.void_modal.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="voidForm.processing"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                                >
                                    {{ t('invoices_show.void_modal.submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
