<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useFormatters } from '@/composables';
import type { PaymentRequiredPageProps } from '@/types';
import {
    BanknotesIcon,
    DocumentArrowUpIcon,
    CreditCardIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    BuildingOffice2Icon,
    HomeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<PaymentRequiredPageProps>();

const { formatMoney: formatCurrency, formatFileSize } = useFormatters();

const form = useForm({
    documents: [],
});

const fileInput = ref(null);
const selectedFiles = ref([]);
const dragOver = ref(false);

const statusConfig = computed(() => {
    switch (props.verification.status) {
        case 'pending_payment':
            return {
                icon: ClockIcon,
                color: 'yellow',
                title: 'Payment Required',
                message: 'Please upload proof of payment or pay online to continue.',
            };
        case 'payment_submitted':
            return {
                icon: ClockIcon,
                color: 'blue',
                title: 'Verification Pending',
                message: 'Your payment proof has been submitted and is awaiting verification by your landlord.',
            };
        case 'rejected':
            return {
                icon: XCircleIcon,
                color: 'red',
                title: 'Verification Rejected',
                message: props.verification.rejection_reason || 'Your payment proof was rejected. Please resubmit.',
            };
        default:
            return {
                icon: CheckCircleIcon,
                color: 'green',
                title: 'Payment Verified',
                message: 'Your payment has been verified.',
            };
    }
});

const selectFiles = () => {
    fileInput.value.click();
};

const handleFileSelect = (event) => {
    addFiles(event.target.files);
};

const handleDrop = (event) => {
    event.preventDefault();
    dragOver.value = false;
    addFiles(event.dataTransfer.files);
};

const addFiles = (files) => {
    const newFiles = Array.from(files).filter(file => {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (!validTypes.includes(file.type)) {
            form.errors.documents = 'Only PDF, JPG, and PNG files are allowed.';
            return false;
        }

        if (file.size > maxSize) {
            form.errors.documents = 'Each file must not exceed 10MB.';
            return false;
        }

        return true;
    });

    selectedFiles.value = [...selectedFiles.value, ...newFiles];
    form.documents = selectedFiles.value;
    form.errors.documents = null;
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
    form.documents = selectedFiles.value;
};

const submitProof = () => {
    form.post(route('tenant.payment.submit'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedFiles.value = [];
        },
    });
};

const payOnline = () => {
    router.post(route('tenant.payment.pay-online'), {
        amount: props.verification.total_required - props.verification.amount_paid,
    });
};

const canSubmit = computed(() => {
    return props.verification.status !== 'payment_submitted' && selectedFiles.value.length > 0;
});

const canPayOnline = computed(() => {
    return props.verification.status !== 'payment_submitted';
});
</script>

<template>
    <Head title="Payment Required" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <BanknotesIcon class="w-6 h-6 text-yellow-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Initial Payment Required</h1>
                    <p class="text-sm text-gray-500">Complete your payment to access the tenant portal</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Status Banner -->
                <div
                    :class="[
                        'rounded-xl p-4 mb-6 border',
                        {
                            'bg-yellow-50 border-yellow-200': statusConfig.color === 'yellow',
                            'bg-blue-50 border-blue-200': statusConfig.color === 'blue',
                            'bg-red-50 border-red-200': statusConfig.color === 'red',
                            'bg-green-50 border-green-200': statusConfig.color === 'green',
                        }
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <component
                            :is="statusConfig.icon"
                            :class="[
                                'w-6 h-6 shrink-0',
                                {
                                    'text-yellow-600': statusConfig.color === 'yellow',
                                    'text-blue-600': statusConfig.color === 'blue',
                                    'text-red-600': statusConfig.color === 'red',
                                    'text-green-600': statusConfig.color === 'green',
                                }
                            ]"
                        />
                        <div>
                            <h3 :class="[
                                'font-medium',
                                {
                                    'text-yellow-800': statusConfig.color === 'yellow',
                                    'text-blue-800': statusConfig.color === 'blue',
                                    'text-red-800': statusConfig.color === 'red',
                                    'text-green-800': statusConfig.color === 'green',
                                }
                            ]">
                                {{ statusConfig.title }}
                            </h3>
                            <p :class="[
                                'text-sm mt-1',
                                {
                                    'text-yellow-700': statusConfig.color === 'yellow',
                                    'text-blue-700': statusConfig.color === 'blue',
                                    'text-red-700': statusConfig.color === 'red',
                                    'text-green-700': statusConfig.color === 'green',
                                }
                            ]">
                                {{ statusConfig.message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Unit Info Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <BuildingOffice2Icon class="w-5 h-5 text-gray-400" />
                        <h2 class="text-sm font-medium text-gray-900">Your Unit</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Building</p>
                            <p class="text-sm font-medium text-gray-900">{{ lease.unit?.building?.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Unit</p>
                            <p class="text-sm font-medium text-gray-900">{{ lease.unit?.unit_number }}</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Breakdown Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <BanknotesIcon class="w-5 h-5 text-gray-400" />
                        <h2 class="text-sm font-medium text-gray-900">Payment Required</h2>
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
                            <span class="text-sm font-medium text-green-600">- {{ formatCurrency(verification.amount_paid) }}</span>
                        </div>
                        <div v-if="verification.amount_paid > 0" class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-semibold text-gray-900">Balance Due</span>
                            <span class="text-lg font-bold text-red-600">{{ formatCurrency(verification.total_required - verification.amount_paid) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Options -->
                <div v-if="verification.status !== 'payment_submitted'" class="space-y-6">
                    <!-- Pay Online Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <CreditCardIcon class="w-5 h-5 text-indigo-500" />
                            <h2 class="text-sm font-medium text-gray-900">Pay Online</h2>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Pay securely with your card or mobile money. Your payment will be verified automatically.
                        </p>
                        <PrimaryButton
                            @click="payOnline"
                            :disabled="!canPayOnline"
                            class="w-full justify-center"
                        >
                            <CreditCardIcon class="w-5 h-5 mr-2" />
                            Pay {{ formatCurrency(verification.total_required - verification.amount_paid) }} Now
                        </PrimaryButton>
                    </div>

                    <!-- Or Divider -->
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-gray-50 text-gray-500">or upload proof of payment</span>
                        </div>
                    </div>

                    <!-- Upload Proof Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <DocumentArrowUpIcon class="w-5 h-5 text-gray-400" />
                            <h2 class="text-sm font-medium text-gray-900">Upload Payment Proof</h2>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            If you've already made a bank transfer or mobile money payment, upload your proof here.
                        </p>

                        <form @submit.prevent="submitProof">
                            <!-- Drop Zone -->
                            <div
                                @click="selectFiles"
                                @dragover.prevent="dragOver = true"
                                @dragleave="dragOver = false"
                                @drop="handleDrop"
                                :class="[
                                    'border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors',
                                    dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'
                                ]"
                            >
                                <input
                                    ref="fileInput"
                                    type="file"
                                    class="hidden"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    multiple
                                    @change="handleFileSelect"
                                />
                                <DocumentArrowUpIcon class="w-10 h-10 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium text-indigo-600">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG up to 10MB each</p>
                            </div>

                            <InputError :message="form.errors.documents" class="mt-2" />

                            <!-- Selected Files List -->
                            <div v-if="selectedFiles.length > 0" class="mt-4 space-y-2">
                                <div
                                    v-for="(file, index) in selectedFiles"
                                    :key="index"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <div class="flex items-center gap-3">
                                        <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 truncate max-w-[200px]">{{ file.name }}</p>
                                            <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeFile(index)"
                                        class="text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        <XCircleIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <PrimaryButton
                                v-if="selectedFiles.length > 0"
                                type="submit"
                                :disabled="!canSubmit || form.processing"
                                class="w-full justify-center mt-4"
                            >
                                <span v-if="form.processing">Uploading...</span>
                                <span v-else>Submit for Verification</span>
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <!-- Uploaded Documents (if submitted) -->
                <div v-if="verification.status === 'payment_submitted' && verification.documents?.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                        <h2 class="text-sm font-medium text-gray-900">Submitted Documents</h2>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="doc in verification.documents"
                            :key="doc.id"
                            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg"
                        >
                            <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                            <span class="text-sm text-gray-700">{{ doc.title }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Need help?</h3>
                            <p class="mt-1 text-sm text-blue-700">
                                If you have questions about your payment or need assistance, please contact your property manager.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
