<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { useFormatters, useCurrency } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import type { CreditNoteShowPageProps } from '@/types/templates';

import {
    DocumentTextIcon,
    CheckCircleIcon,
    XCircleIcon,
    ArrowPathIcon,
    UserIcon,
    BuildingOfficeIcon,
    CalendarIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<CreditNoteShowPageProps>();
const { can } = useAuth();
const { formatMoney, formatDateTime } = useFormatters();
const { currencySymbol } = useCurrency();

const breadcrumbItems = [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Credit Notes', href: route('credit-notes.index') },
    { label: props.creditNote.credit_number },
];

const showApplyModal = ref(false);
const selectedInvoice = ref(null);
const applyAmount = ref('');

const applyForm = useForm({
    invoice_id: null,
    amount: null,
});

const statusBadgeClass = computed(() => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        approved: 'bg-blue-100 text-blue-800 border-blue-200',
        applied: 'bg-green-100 text-green-800 border-green-200',
        voided: 'bg-gray-100 text-gray-500 border-gray-200',
    };
    return classes[props.creditNote.status] || 'bg-gray-100 text-gray-800 border-gray-200';
});

const remainingAmount = computed(() => {
    return (props.creditNote.amount || 0) - (props.creditNote.applied_amount || 0);
});

const canApprove = computed(() => props.creditNote.status === 'pending');
const canApply = computed(() => props.creditNote.status === 'approved' && remainingAmount.value > 0);
const canVoid = computed(() => ['pending', 'approved'].includes(props.creditNote.status));

const approve = () => {
    if (confirm('Approve this credit note?')) {
        router.post(route('credit-notes.approve', props.creditNote.id), {}, {
            preserveScroll: true,
        });
    }
};

const openApplyModal = () => {
    showApplyModal.value = true;
    selectedInvoice.value = null;
    applyAmount.value = '';
};

const selectInvoice = (invoice) => {
    selectedInvoice.value = invoice;
    applyAmount.value = Math.min(remainingAmount.value, invoice.outstanding);
};

const applyToInvoice = () => {
    if (!selectedInvoice.value) return;

    applyForm.invoice_id = selectedInvoice.value.id;
    applyForm.amount = parseFloat(applyAmount.value);

    applyForm.post(route('credit-notes.apply', props.creditNote.id), {
        preserveScroll: true,
        onSuccess: () => {
            showApplyModal.value = false;
        },
    });
};

const voidCredit = () => {
    if (confirm('Void this credit note? This action cannot be undone.')) {
        router.post(route('credit-notes.void', props.creditNote.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="`Credit Note ${creditNote.credit_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <DocumentTextIcon class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ creditNote.credit_number }}</h1>
                    <p class="text-sm text-gray-500">Credit Note Details</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Credit Details Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                                <h2 class="text-lg font-medium text-gray-900">Credit Details</h2>
                                <span :class="['px-3 py-1 text-sm font-medium rounded-full border', statusBadgeClass]">
                                    {{ creditNote.status }}
                                </span>
                            </div>
                            <div class="p-6">
                                <dl class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm text-gray-500">Amount</dt>
                                        <dd class="text-2xl font-semibold text-gray-900">{{ formatMoney(creditNote.amount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm text-gray-500">Applied</dt>
                                        <dd class="text-2xl font-semibold text-green-600">{{ formatMoney(creditNote.applied_amount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm text-gray-500">Remaining</dt>
                                        <dd class="text-xl font-medium text-gray-700">{{ formatMoney(remainingAmount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm text-gray-500">Reason</dt>
                                        <dd class="text-base text-gray-900">{{ reasonOptions[creditNote.reason] || creditNote.reason }}</dd>
                                    </div>
                                </dl>

                                <div v-if="creditNote.notes" class="mt-6 pt-4 border-t border-gray-100">
                                    <dt class="text-sm text-gray-500 mb-1">Notes</dt>
                                    <dd class="text-gray-700">{{ creditNote.notes }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Tenant Info -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Tenant Information</h2>
                            </div>
                            <div class="p-6">
                                <div class="flex items-start gap-4">
                                    <div class="p-3 bg-gray-100 rounded-full">
                                        <UserIcon class="w-6 h-6 text-gray-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ creditNote.tenant?.name }}</p>
                                        <p class="text-sm text-gray-500">{{ creditNote.tenant?.email }}</p>
                                        <p class="text-sm text-gray-500">{{ creditNote.tenant?.phone }}</p>
                                    </div>
                                </div>
                                <div v-if="creditNote.lease?.unit" class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-3">
                                    <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                    <div>
                                        <p class="text-gray-900">{{ creditNote.lease.unit.unit_number }}</p>
                                        <p class="text-sm text-gray-500">{{ creditNote.lease.unit.building?.name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Applied To Invoice -->
                        <div v-if="creditNote.applied_to_invoice" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Applied To</h2>
                            </div>
                            <div class="p-6">
                                <div class="flex items-center gap-3">
                                    <BanknotesIcon class="w-5 h-5 text-green-500" />
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            Invoice {{ creditNote.applied_to_invoice.invoice_number }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Applied {{ formatDateTime(creditNote.applied_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Actions -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-sm font-medium text-gray-500 uppercase mb-4">Actions</h3>
                            <div class="space-y-3">
                                <button
                                    v-if="canApprove"
                                    @click="approve"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                >
                                    <CheckCircleIcon class="w-5 h-5" />
                                    Approve
                                </button>

                                <button
                                    v-if="canApply"
                                    @click="openApplyModal"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                                >
                                    <ArrowPathIcon class="w-5 h-5" />
                                    Apply to Invoice
                                </button>

                                <button
                                    v-if="can('invoices:manage') && canVoid"
                                    @click="voidCredit"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition"
                                >
                                    <XCircleIcon class="w-5 h-5" />
                                    Void Credit Note
                                </button>

                                <Link
                                    :href="route('tenants.ledger', creditNote.tenant_id)"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition"
                                >
                                    View Tenant Ledger
                                </Link>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-sm font-medium text-gray-500 uppercase mb-4">Timeline</h3>
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-gray-400"></div>
                                    <div>
                                        <p class="text-sm text-gray-900">Created</p>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(creditNote.created_at) }}</p>
                                    </div>
                                </div>
                                <div v-if="creditNote.approved_at" class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                                    <div>
                                        <p class="text-sm text-gray-900">Approved</p>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(creditNote.approved_at) }}</p>
                                        <p v-if="creditNote.approved_by_user" class="text-xs text-gray-400">
                                            by {{ creditNote.approved_by_user.name }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="creditNote.applied_at" class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                                    <div>
                                        <p class="text-sm text-gray-900">Applied</p>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(creditNote.applied_at) }}</p>
                                    </div>
                                </div>
                                <div v-if="creditNote.voided_at" class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-red-500"></div>
                                    <div>
                                        <p class="text-sm text-gray-900">Voided</p>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(creditNote.voided_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply Modal -->
        <Teleport to="body">
            <div v-if="showApplyModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showApplyModal = false"></div>

                    <div class="relative z-50 transform overflow-hidden rounded-xl bg-white text-start shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="bg-white px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Apply Credit to Invoice</h3>
                        </div>

                        <div class="bg-white px-6 py-4">
                            <p class="text-sm text-gray-500 mb-4">
                                Available credit: <span class="font-medium text-gray-900">{{ formatMoney(remainingAmount) }}</span>
                            </p>

                            <div v-if="outstandingInvoices.length === 0" class="text-center py-8">
                                <p class="text-gray-500">No outstanding invoices for this tenant.</p>
                            </div>

                            <div v-else class="space-y-2 max-h-60 overflow-y-auto">
                                <button
                                    v-for="invoice in outstandingInvoices"
                                    :key="invoice.id"
                                    type="button"
                                    @click="selectInvoice(invoice)"
                                    :class="[
                                        'w-full p-3 text-start border rounded-lg transition',
                                        selectedInvoice?.id === invoice.id
                                            ? 'border-purple-500 bg-purple-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ invoice.invoice_number }}</p>
                                            <p class="text-sm text-gray-500">Due: {{ invoice.due_date }}</p>
                                        </div>
                                        <p class="text-lg font-semibold text-gray-900">{{ formatMoney(invoice.outstanding) }}</p>
                                    </div>
                                </button>
                            </div>

                            <div v-if="selectedInvoice" class="mt-4 pt-4 border-t border-gray-200">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Amount to Apply
                                </label>
                                <div class="relative">
                                    <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-500">{{ currencySymbol }}</span>
                                    <input
                                        v-model="applyAmount"
                                        type="number"
                                        step="0.01"
                                        :max="Math.min(remainingAmount, selectedInvoice.outstanding)"
                                        class="w-full ps-14 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showApplyModal = false"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="applyToInvoice"
                                :disabled="!selectedInvoice || !applyAmount || applyForm.processing"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50"
                            >
                                {{ applyForm.processing ? 'Applying...' : 'Apply Credit' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
