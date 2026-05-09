<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useFormatters } from '@/composables';
import type { PaymentVerificationsShowPageProps } from '@/types/tenants';
import {
    ShieldCheckIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentTextIcon,
    UserIcon,
    HomeIcon,
    BanknotesIcon,
    CalendarIcon,
    ArrowLeftIcon,
    EyeIcon,
    ArrowDownTrayIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<PaymentVerificationsShowPageProps>();

const { formatMoney: formatCurrency, formatDateTime } = useFormatters();

const showApproveModal = ref(false);
const showRejectModal = ref(false);
const processing = ref(false);

const rejectForm = useForm({
    reason: '',
});

const getStatusBadge = (status) => {
    const badges = {
        pending_payment: { class: 'bg-yellow-100 text-yellow-800', icon: ClockIcon, label: 'Pending Payment' },
        payment_submitted: { class: 'bg-blue-100 text-blue-800', icon: ClockIcon, label: 'Awaiting Review' },
        payment_verified: { class: 'bg-green-100 text-green-800', icon: CheckCircleIcon, label: 'Verified' },
        rejected: { class: 'bg-red-100 text-red-800', icon: XCircleIcon, label: 'Rejected' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-800', icon: ClockIcon, label: status };
};

const approve = () => {
    processing.value = true;
    router.post(route('payment-verifications.approve', props.verification.id), {}, {
        onFinish: () => {
            processing.value = false;
            showApproveModal.value = false;
        },
    });
};

const reject = () => {
    rejectForm.post(route('payment-verifications.reject', props.verification.id), {
        onSuccess: () => {
            showRejectModal.value = false;
        },
    });
};

const viewDocument = (doc) => {
    window.open(route('documents.view', doc.id), '_blank');
};

const downloadDocument = (doc) => {
    window.location.href = route('documents.download', doc.id);
};

const canApproveOrReject = props.verification.status === 'payment_submitted' || props.verification.status === 'rejected';
</script>

<template>
    <Head title="Payment Verification Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('payment-verifications.index')"
                    class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
                </Link>
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <ShieldCheckIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Payment Verification</h1>
                        <p class="text-sm text-gray-500">{{ verification.lease?.tenant?.name }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Status Banner -->
                <div
                    :class="[
                        'rounded-xl p-4 mb-6 border',
                        {
                            'bg-yellow-50 border-yellow-200': verification.status === 'pending_payment',
                            'bg-blue-50 border-blue-200': verification.status === 'payment_submitted',
                            'bg-green-50 border-green-200': verification.status === 'payment_verified',
                            'bg-red-50 border-red-200': verification.status === 'rejected',
                        }
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <component
                                :is="getStatusBadge(verification.status).icon"
                                :class="[
                                    'w-6 h-6',
                                    {
                                        'text-yellow-600': verification.status === 'pending_payment',
                                        'text-blue-600': verification.status === 'payment_submitted',
                                        'text-green-600': verification.status === 'payment_verified',
                                        'text-red-600': verification.status === 'rejected',
                                    }
                                ]"
                            />
                            <div>
                                <span
                                    :class="[
                                        'font-medium',
                                        {
                                            'text-yellow-800': verification.status === 'pending_payment',
                                            'text-blue-800': verification.status === 'payment_submitted',
                                            'text-green-800': verification.status === 'payment_verified',
                                            'text-red-800': verification.status === 'rejected',
                                        }
                                    ]"
                                >
                                    {{ getStatusBadge(verification.status).label }}
                                </span>
                                <p v-if="verification.verified_at" class="text-sm text-gray-600 mt-1">
                                    Verified on {{ formatDateTime(verification.verified_at) }}
                                    <span v-if="verification.verified_by">by {{ verification.verified_by.name }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div v-if="canApproveOrReject" class="flex items-center gap-2">
                            <DangerButton @click="showRejectModal = true" class="!py-2">
                                <XCircleIcon class="w-4 h-4 mr-1" />
                                Reject
                            </DangerButton>
                            <PrimaryButton @click="showApproveModal = true" class="!py-2">
                                <CheckCircleIcon class="w-4 h-4 mr-1" />
                                Approve
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- Rejection Reason -->
                    <div v-if="verification.status === 'rejected' && verification.rejection_reason" class="mt-3 p-3 bg-red-100 rounded-lg">
                        <p class="text-sm text-red-800">
                            <strong>Rejection Reason:</strong> {{ verification.rejection_reason }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Tenant Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <UserIcon class="w-5 h-5 text-gray-400" />
                            <h2 class="text-sm font-medium text-gray-900">Tenant Information</h2>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">Name</p>
                                <p class="text-sm font-medium text-gray-900">{{ verification.lease?.tenant?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Email</p>
                                <p class="text-sm text-gray-900">{{ verification.lease?.tenant?.email }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Phone</p>
                                <p class="text-sm text-gray-900">{{ verification.lease?.tenant?.mobile_number || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Unit Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <HomeIcon class="w-5 h-5 text-gray-400" />
                            <h2 class="text-sm font-medium text-gray-900">Unit Information</h2>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">Building</p>
                                <p class="text-sm font-medium text-gray-900">{{ verification.lease?.unit?.building?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Unit</p>
                                <p class="text-sm text-gray-900">{{ verification.lease?.unit?.unit_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Monthly Rent</p>
                                <p class="text-sm text-gray-900">{{ formatCurrency(verification.lease?.rent_amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Breakdown -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                    <div class="flex items-center gap-3 mb-4">
                        <BanknotesIcon class="w-5 h-5 text-gray-400" />
                        <h2 class="text-sm font-medium text-gray-900">Payment Details</h2>
                    </div>

                    <div class="space-y-3">
                        <div v-if="verification.deposit_required > 0" class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Security Deposit</span>
                            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(verification.deposit_required) }}</span>
                        </div>
                        <div v-if="verification.first_rent_required > 0" class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">First Month Rent</span>
                            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(verification.first_rent_required) }}</span>
                        </div>
                        <div v-if="verification.other_charges > 0" class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">{{ verification.other_charges_description || 'Other Charges' }}</span>
                            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(verification.other_charges) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t-2 border-gray-200">
                            <span class="text-sm font-semibold text-gray-900">Total Required</span>
                            <span class="text-lg font-bold text-indigo-600">{{ formatCurrency(verification.total_required) }}</span>
                        </div>
                        <div v-if="verification.amount_paid > 0" class="flex justify-between items-center py-2">
                            <span class="text-sm text-green-600">Amount Paid</span>
                            <span class="text-sm font-medium text-green-600">{{ formatCurrency(verification.amount_paid) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Created</p>
                            <p class="text-gray-900">{{ formatDateTime(verification.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Submitted</p>
                            <p class="text-gray-900">{{ formatDateTime(verification.submitted_at) || 'Not yet submitted' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Uploaded Documents -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                    <div class="flex items-center gap-3 mb-4">
                        <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                        <h2 class="text-sm font-medium text-gray-900">Payment Proof Documents</h2>
                    </div>

                    <div v-if="verification.documents?.length > 0" class="space-y-3">
                        <div
                            v-for="doc in verification.documents"
                            :key="doc.id"
                            class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-white rounded-lg border border-gray-200">
                                    <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ doc.title }}</p>
                                    <p class="text-xs text-gray-500">
                                        Uploaded {{ formatDateTime(doc.uploaded_at) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="viewDocument(doc)"
                                    class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                    title="View"
                                >
                                    <EyeIcon class="w-5 h-5" />
                                </button>
                                <button
                                    @click="downloadDocument(doc)"
                                    class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                    title="Download"
                                >
                                    <ArrowDownTrayIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">No documents uploaded yet</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <Modal :show="showApproveModal" @close="showApproveModal = false">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-green-100 rounded-full">
                        <CheckCircleIcon class="w-6 h-6 text-green-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Approve Payment Verification</h3>
                </div>

                <p class="text-gray-600 mb-6">
                    Are you sure you want to approve this payment verification? The tenant will gain full access to the portal.
                </p>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Verified</span>
                        <span class="font-semibold text-gray-900">{{ formatCurrency(verification.total_required) }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showApproveModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="approve" :disabled="processing">
                        <span v-if="processing">Processing...</span>
                        <span v-else>Confirm Approval</span>
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Reject Modal -->
        <Modal :show="showRejectModal" @close="showRejectModal = false">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-red-100 rounded-full">
                        <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Reject Payment Verification</h3>
                </div>

                <p class="text-gray-600 mb-4">
                    Please provide a reason for rejecting this payment. The tenant will be notified and can resubmit their proof of payment.
                </p>

                <form @submit.prevent="reject">
                    <div class="mb-6">
                        <InputLabel for="reason" value="Rejection Reason" />
                        <textarea
                            id="reason"
                            v-model="rejectForm.reason"
                            rows="4"
                            class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., Payment proof is illegible, amount doesn't match, etc."
                            required
                        ></textarea>
                        <InputError :message="rejectForm.errors.reason" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showRejectModal = false">
                            Cancel
                        </SecondaryButton>
                        <DangerButton type="submit" :disabled="rejectForm.processing">
                            <span v-if="rejectForm.processing">Processing...</span>
                            <span v-else>Reject Verification</span>
                        </DangerButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
