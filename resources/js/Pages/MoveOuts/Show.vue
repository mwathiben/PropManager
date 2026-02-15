<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters, useCurrency } from '@/composables';
import type { MoveOutShowPageProps } from '@/types/finances';
import {
    ArrowLeftIcon,
    ArrowRightOnRectangleIcon,
    HomeIcon,
    CalendarDaysIcon,
    BanknotesIcon,
    ClipboardDocumentCheckIcon,
    PlusIcon,
    TrashIcon,
    PencilIcon,
    XMarkIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    PhotoIcon,
    CurrencyDollarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<MoveOutShowPageProps>();
const { formatMoney: formatCurrency, formatDate, todayAsISODate } = useFormatters();
const { currencyCode } = useCurrency();

const lease = props.moveOut.lease;
const tenant = lease.tenant;
const unit = lease.unit;

// Modal states
const showDeductionModal = ref(false);
const showSettlementModal = ref(false);
const editingDeduction = ref(null);

// Forms
const inspectionForm = useForm({
    actual_move_out_date: props.moveOut.actual_move_out_date || todayAsISODate(),
});

const deductionForm = useForm({
    category_id: '' as number | '',
    description: '',
    amount: '',
    notes: '',
    photo: null,
});

const inspectionNotesForm = useForm({
    inspection_notes: props.moveOut.inspection_notes || '',
});

const settlementForm = useForm({
    settlement_method: 'bank_transfer',
    settlement_reference: '',
});

// Computed
const isNoticeGiven = computed(() => props.moveOut.status === 'notice_given');
const isInspectionPending = computed(() => props.moveOut.status === 'inspection_pending');
const isSettlementPending = computed(() => props.moveOut.status === 'settlement_pending');
const isCompleted = computed(() => props.moveOut.status === 'completed');
const isCancelled = computed(() => props.moveOut.status === 'cancelled');

const canAddDeductions = computed(() => ['inspection_pending', 'inspection_complete'].includes(props.moveOut.status));
const canComplete = computed(() => props.moveOut.status === 'settlement_pending');

const totalDeductions = computed(() => {
    return props.moveOut.deductions?.reduce((sum, d) => sum + parseFloat(d.amount), 0) || 0;
});

const estimatedRefund = computed(() => {
    const deposit = parseFloat(props.moveOut.deposit_held) || 0;
    const arrears = parseFloat(props.moveOut.arrears_balance) || 0;
    return Math.max(0, deposit - totalDeductions.value - arrears);
});

const getStatusInfo = () => {
    switch (props.moveOut.status) {
        case 'notice_given':
            return { color: 'bg-blue-100 text-blue-800 border-blue-200', label: 'Notice Given', step: 1 };
        case 'inspection_pending':
            return { color: 'bg-yellow-100 text-yellow-800 border-yellow-200', label: 'Inspection In Progress', step: 2 };
        case 'inspection_complete':
            return { color: 'bg-purple-100 text-purple-800 border-purple-200', label: 'Inspection Complete', step: 3 };
        case 'settlement_pending':
            return { color: 'bg-orange-100 text-orange-800 border-orange-200', label: 'Settlement Pending', step: 4 };
        case 'completed':
            return { color: 'bg-green-100 text-green-800 border-green-200', label: 'Completed', step: 5 };
        case 'cancelled':
            return { color: 'bg-gray-100 text-gray-800 border-gray-200', label: 'Cancelled', step: 0 };
        default:
            return { color: 'bg-gray-100 text-gray-800 border-gray-200', label: props.moveOut.status, step: 0 };
    }
};

// Actions
const startInspection = () => {
    inspectionForm.post(route('move-outs.start-inspection', props.moveOut.id));
};

const openDeductionModal = (deduction = null) => {
    editingDeduction.value = deduction;
    if (deduction) {
        deductionForm.category_id = deduction.category_id || '';
        deductionForm.description = deduction.description;
        deductionForm.amount = deduction.amount;
        deductionForm.notes = deduction.notes || '';
        deductionForm.photo = null;
    } else {
        deductionForm.reset();
    }
    showDeductionModal.value = true;
};

const onCategorySelected = () => {
    if (deductionForm.category_id) {
        const category = props.categories.find(c => c.id === deductionForm.category_id);
        if (category) {
            deductionForm.description = category.name;
            deductionForm.amount = String(category.default_amount);
        }
    }
};

const saveDeduction = () => {
    if (editingDeduction.value) {
        deductionForm.put(route('move-outs.deductions.update', editingDeduction.value.id), {
            onSuccess: () => {
                showDeductionModal.value = false;
                editingDeduction.value = null;
                deductionForm.reset();
            },
        });
    } else {
        deductionForm.post(route('move-outs.deductions.store', props.moveOut.id), {
            onSuccess: () => {
                showDeductionModal.value = false;
                deductionForm.reset();
            },
        });
    }
};

const deleteDeduction = (deductionId) => {
    if (confirm('Are you sure you want to remove this deduction?')) {
        router.delete(route('move-outs.deductions.destroy', deductionId));
    }
};

const completeInspection = () => {
    inspectionNotesForm.post(route('move-outs.complete-inspection', props.moveOut.id));
};

const completeMoveOut = () => {
    settlementForm.post(route('move-outs.complete', props.moveOut.id), {
        onSuccess: () => {
            showSettlementModal.value = false;
        },
    });
};

const cancelMoveOut = () => {
    if (confirm('Are you sure you want to cancel this move-out? The tenant will remain in the unit.')) {
        router.post(route('move-outs.cancel', props.moveOut.id));
    }
};

const statusInfo = computed(() => getStatusInfo());
</script>

<template>
    <Head :title="`Move-Out: ${tenant?.name}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <Link :href="route('move-outs.index')" class="text-gray-400 hover:text-gray-600">
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Move-Out Process</h1>
                            <p class="text-sm text-gray-500">{{ tenant?.name }} - Unit {{ unit?.unit_number }}</p>
                        </div>
                    </div>
                    <span
                        :class="statusInfo.color"
                        class="self-start px-4 py-2 rounded-full text-sm font-medium border"
                    >
                        {{ statusInfo.label }}
                    </span>
                </div>

                <!-- Progress Steps -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div v-for="(step, index) in ['Notice', 'Move Out', 'Inspection', 'Settlement', 'Complete']" :key="index" class="flex-1 flex items-center">
                            <div class="flex flex-col items-center flex-1">
                                <div
                                    :class="statusInfo.step > index ? 'bg-green-500 text-white' : statusInfo.step === index + 1 ? 'bg-indigo-500 text-white' : 'bg-gray-200 text-gray-500'"
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium"
                                >
                                    <CheckCircleIcon v-if="statusInfo.step > index" class="w-5 h-5" />
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <span class="mt-2 text-xs text-gray-500 hidden sm:block">{{ step }}</span>
                            </div>
                            <div v-if="index < 4" class="h-0.5 flex-1 mx-2" :class="statusInfo.step > index + 1 ? 'bg-green-500' : 'bg-gray-200'"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Step 1: Notice Given - Start Inspection -->
                        <div v-if="isNoticeGiven" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <CalendarDaysIcon class="w-5 h-5" />
                                Start Inspection
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                When the tenant has vacated the unit, enter the actual move-out date to begin the inspection process.
                            </p>
                            <div class="flex items-end gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Actual Move-Out Date</label>
                                    <input
                                        v-model="inspectionForm.actual_move_out_date"
                                        type="date"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <button
                                    @click="startInspection"
                                    :disabled="inspectionForm.processing"
                                    data-testid="start-inspection-button"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ inspectionForm.processing ? 'Starting...' : 'Start Inspection' }}
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Inspection - Deductions -->
                        <div v-if="isInspectionPending || isSettlementPending || isCompleted" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <ClipboardDocumentCheckIcon class="w-5 h-5" />
                                    Inspection & Deductions
                                </h3>
                                <button
                                    v-if="canAddDeductions"
                                    @click="openDeductionModal()"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 rounded-lg"
                                >
                                    <PlusIcon class="w-4 h-4" />
                                    Add Deduction
                                </button>
                            </div>

                            <!-- Deductions List -->
                            <div v-if="moveOut.deductions?.length" class="divide-y divide-gray-200">
                                <div v-for="deduction in moveOut.deductions" :key="deduction.id" class="p-4 flex items-center justify-between" :data-deduction="deduction.description">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                            <CurrencyDollarIcon class="w-5 h-5 text-red-600" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">
                                                {{ deduction.description }}
                                                <span v-if="deduction.auto_applied" class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                    Auto
                                                </span>
                                                <span v-else-if="deduction.category" class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                    {{ deduction.category.name }}
                                                </span>
                                            </p>
                                            <p v-if="deduction.notes" class="text-sm text-gray-500">{{ deduction.notes }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-red-600">-{{ formatCurrency(deduction.amount) }}</span>
                                        <div v-if="canAddDeductions" class="flex items-center gap-1">
                                            <button @click="openDeductionModal(deduction)" class="p-1 text-gray-400 hover:text-gray-600">
                                                <PencilIcon class="w-4 h-4" />
                                            </button>
                                            <button @click="deleteDeduction(deduction.id)" class="p-1 text-red-400 hover:text-red-600">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-8 text-center text-gray-500">
                                <p>No deductions recorded</p>
                            </div>

                            <!-- Total Deductions -->
                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700">Total Deductions</span>
                                <span class="text-lg font-bold text-red-600">{{ formatCurrency(totalDeductions) }}</span>
                            </div>
                        </div>

                        <!-- Inspection Notes -->
                        <div v-if="isInspectionPending" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Inspection Notes</h3>
                            <textarea
                                v-model="inspectionNotesForm.inspection_notes"
                                rows="4"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Record any notes from the inspection..."
                            ></textarea>
                            <div class="mt-4 flex justify-end">
                                <button
                                    @click="completeInspection"
                                    :disabled="inspectionNotesForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ inspectionNotesForm.processing ? 'Completing...' : 'Complete Inspection' }}
                                </button>
                            </div>
                        </div>

                        <!-- Settlement -->
                        <div v-if="isSettlementPending" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <BanknotesIcon class="w-5 h-5" />
                                Ready for Settlement
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Inspection is complete. Review the financial summary and settle the deposit.
                            </p>
                            <button
                                @click="showSettlementModal = true"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                            >
                                <CheckCircleIcon class="w-5 h-5" />
                                Settle Deposit & Complete
                            </button>
                        </div>

                        <!-- Completed Summary -->
                        <div v-if="isCompleted" class="bg-green-50 border border-green-200 rounded-xl p-6">
                            <div class="flex items-start gap-4">
                                <CheckCircleIcon class="w-8 h-8 text-green-600" />
                                <div>
                                    <h3 class="text-lg font-semibold text-green-800">Move-Out Completed</h3>
                                    <p class="text-sm text-green-700 mt-1">
                                        Settled on {{ formatDate(moveOut.settled_at) }} via {{ moveOut.settlement_method?.replace(/_/g, ' ') }}
                                    </p>
                                    <p v-if="moveOut.settlement_reference" class="text-sm text-green-600 mt-1">
                                        Reference: {{ moveOut.settlement_reference }}
                                    </p>
                                    <p class="text-sm text-green-700 mt-2">
                                        Processed by: {{ moveOut.processor?.name }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Financial Summary -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">Financial Summary</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Deposit Held</span>
                                    <span class="text-sm font-medium text-gray-900">{{ formatCurrency(moveOut.deposit_held) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Arrears Balance</span>
                                    <span class="text-sm font-medium text-red-600">-{{ formatCurrency(moveOut.arrears_balance) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Total Deductions</span>
                                    <span class="text-sm font-medium text-red-600">-{{ formatCurrency(totalDeductions) }}</span>
                                </div>
                                <div class="border-t pt-3 flex justify-between">
                                    <span class="text-sm font-semibold text-gray-900">Refund Amount</span>
                                    <span class="text-lg font-bold" :class="estimatedRefund > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(estimatedRefund) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">Details</h3>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="text-gray-500">Notice Date</span>
                                    <p class="font-medium text-gray-900">{{ formatDate(moveOut.notice_date) }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Intended Move-Out</span>
                                    <p class="font-medium text-gray-900">{{ formatDate(moveOut.intended_move_out_date) }}</p>
                                </div>
                                <div v-if="moveOut.actual_move_out_date">
                                    <span class="text-gray-500">Actual Move-Out</span>
                                    <p class="font-medium text-gray-900">{{ formatDate(moveOut.actual_move_out_date) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Inspection Notes (if exists) -->
                        <div v-if="moveOut.inspection_notes && !isInspectionPending" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Inspection Notes</h3>
                            <p class="text-sm text-gray-600">{{ moveOut.inspection_notes }}</p>
                        </div>

                        <!-- Cancel Button -->
                        <div v-if="!isCompleted && !isCancelled" class="text-center">
                            <button
                                @click="cancelMoveOut"
                                class="text-sm text-red-600 hover:text-red-800"
                            >
                                Cancel Move-Out Process
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deduction Modal -->
        <div v-if="showDeductionModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showDeductionModal = false"></div>

                <div class="relative z-50 inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ editingDeduction ? 'Edit Deduction' : 'Add Deduction' }}
                        </h3>
                        <button @click="showDeductionModal = false" class="text-gray-400 hover:text-gray-500">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <form @submit.prevent="saveDeduction" class="p-6 space-y-4">
                        <div v-if="categories?.length > 0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category (Optional)</label>
                            <select
                                v-model="deductionForm.category_id"
                                @change="onCategorySelected"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Custom Deduction</option>
                                <option v-for="cat in (categories ?? [])" :key="cat.id" :value="cat.id">
                                    {{ cat.name }} ({{ formatCurrency(cat.default_amount) }})
                                </option>
                            </select>
                            <p v-if="deductionForm.category_id" class="mt-1 text-xs text-gray-500">
                                {{ categories?.find(c => c.id === deductionForm.category_id)?.description }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                            <input
                                v-model="deductionForm.description"
                                type="text"
                                required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g., Wall damage repair"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount ({{ currencyCode }}) *</label>
                            <input
                                v-model="deductionForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                            <textarea
                                v-model="deductionForm.notes"
                                rows="2"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showDeductionModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="deductionForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ deductionForm.processing ? 'Saving...' : editingDeduction ? 'Update' : 'Add Deduction' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Settlement Modal -->
        <div v-if="showSettlementModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showSettlementModal = false"></div>

                <div class="relative z-50 inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Complete Settlement</h3>
                    </div>

                    <form @submit.prevent="completeMoveOut" class="p-6 space-y-4">
                        <!-- Summary -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-green-800">Refund to Tenant</span>
                                <span class="text-2xl font-bold text-green-700">{{ formatCurrency(estimatedRefund) }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Settlement Method *</label>
                            <select
                                v-model="settlementForm.settlement_method"
                                required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money (M-Pesa)</option>
                                <option value="offset">Offset Against Arrears</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number (Optional)</label>
                            <input
                                v-model="settlementForm.settlement_reference"
                                type="text"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Transaction ID or receipt number"
                            />
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-sm text-yellow-800">
                                <ExclamationCircleIcon class="w-4 h-4 inline mr-1" />
                                This action will end the lease and mark the unit as vacant.
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showSettlementModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="settlementForm.processing"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <CheckCircleIcon class="w-5 h-5" />
                                {{ settlementForm.processing ? 'Processing...' : 'Complete Move-Out' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
