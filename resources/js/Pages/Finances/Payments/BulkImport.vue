<script setup>
import { ref, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowUpTrayIcon,
    DocumentArrowDownIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ArrowLeftIcon,
    EyeIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';

const { formatMoney } = useFormatters();

const step = ref(1);
const file = ref(null);
const fileName = ref('');
const isValidating = ref(false);
const isProcessing = ref(false);
const validationError = ref('');

const previewData = ref({
    total_rows: 0,
    valid_rows: 0,
    invalid_rows: 0,
    valid: [],
    invalid: [],
});

const resultData = ref({
    success_count: 0,
    failed_count: 0,
    total_amount: 0,
    errors: [],
});

const showValidRows = ref(false);

const handleFileChange = (event) => {
    const selectedFile = event.target.files[0];
    if (selectedFile) {
        file.value = selectedFile;
        fileName.value = selectedFile.name;
        validationError.value = '';
    }
};

const validateFile = async () => {
    if (!file.value) {
        validationError.value = 'Please select a CSV file';
        return;
    }

    isValidating.value = true;
    validationError.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file.value);

        const response = await fetch(route('finances.payments.bulk-import.validate'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            validationError.value = data.error || 'Validation failed';
            return;
        }

        previewData.value = data;
        step.value = 2;
    } catch (err) {
        validationError.value = 'Failed to validate file. Please try again.';
    } finally {
        isValidating.value = false;
    }
};

const processPayments = async () => {
    isProcessing.value = true;

    try {
        const response = await fetch(route('finances.payments.bulk-import.process'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                payments: previewData.value.valid,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            validationError.value = data.error || 'Processing failed';
            return;
        }

        resultData.value = data;
        step.value = 3;
    } catch (err) {
        validationError.value = 'Failed to process payments. Please try again.';
    } finally {
        isProcessing.value = false;
    }
};

const resetForm = () => {
    step.value = 1;
    file.value = null;
    fileName.value = '';
    validationError.value = '';
    previewData.value = {
        total_rows: 0,
        valid_rows: 0,
        invalid_rows: 0,
        valid: [],
        invalid: [],
    };
    resultData.value = {
        success_count: 0,
        failed_count: 0,
        total_amount: 0,
        errors: [],
    };

    const fileInput = document.getElementById('csv-file');
    if (fileInput) fileInput.value = '';
};

const totalValidAmount = computed(() => {
    return previewData.value.valid.reduce((sum, row) => sum + row.amount, 0);
});
</script>

<template>
    <Head title="Bulk Import Payments" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6">
            <div class="flex items-center gap-4 mb-6">
                <Link
                    :href="route('finances.payments')"
                    class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Bulk Import Payments</h1>
                    <p class="text-sm text-gray-500">Upload a CSV file to record multiple payments at once</p>
                </div>
            </div>

            <div class="mb-8">
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 1 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">1</span>
                        <span class="ml-2 font-medium" :class="step >= 1 ? 'text-gray-900' : 'text-gray-500'">Upload</span>
                    </div>
                    <div class="flex-1 mx-4 h-0.5" :class="step >= 2 ? 'bg-emerald-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 2 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">2</span>
                        <span class="ml-2 font-medium" :class="step >= 2 ? 'text-gray-900' : 'text-gray-500'">Preview</span>
                    </div>
                    <div class="flex-1 mx-4 h-0.5" :class="step >= 3 ? 'bg-emerald-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 3 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">3</span>
                        <span class="ml-2 font-medium" :class="step >= 3 ? 'text-gray-900' : 'text-gray-500'">Results</span>
                    </div>
                </div>
            </div>

            <div v-if="step === 1" class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-medium text-blue-900 mb-2">CSV Format Instructions</h3>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li><strong>Tenant Email</strong> - Required. The tenant's email address.</li>
                        <li><strong>Invoice Number</strong> - Optional. Leave empty for auto-allocation (FIFO).</li>
                        <li><strong>Payment Date</strong> - Required. Format: YYYY-MM-DD</li>
                        <li><strong>Amount</strong> - Required. Payment amount (numbers only).</li>
                        <li><strong>Payment Method</strong> - Required. One of: cash, mpesa, bank_transfer, cheque</li>
                        <li><strong>Reference</strong> - Optional. Transaction reference number.</li>
                    </ul>
                </div>

                <div class="mb-6">
                    <a
                        :href="route('finances.payments.bulk-import.template')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-colors"
                    >
                        <DocumentArrowDownIcon class="h-5 w-5" />
                        Download CSV Template
                    </a>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <div class="flex items-center gap-4">
                        <label class="flex-1 flex items-center justify-center px-6 py-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-emerald-400 hover:bg-gray-50 transition-colors">
                            <div class="text-center">
                                <ArrowUpTrayIcon class="mx-auto h-10 w-10 text-gray-400" />
                                <p class="mt-2 text-sm text-gray-600">
                                    <span class="font-medium text-emerald-600">Click to upload</span> or drag and drop
                                </p>
                                <p class="mt-1 text-xs text-gray-500">CSV files only (max 5MB)</p>
                                <p v-if="fileName" class="mt-2 text-sm font-medium text-gray-900">{{ fileName }}</p>
                            </div>
                            <input
                                id="csv-file"
                                type="file"
                                accept=".csv,.txt"
                                class="hidden"
                                @change="handleFileChange"
                            />
                        </label>
                    </div>
                </div>

                <p v-if="validationError" class="mb-4 text-sm text-red-600">{{ validationError }}</p>

                <div class="flex justify-end gap-3">
                    <Link
                        :href="route('finances.payments')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </Link>
                    <button
                        @click="validateFile"
                        :disabled="!file || isValidating"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <EyeIcon v-if="!isValidating" class="h-4 w-4" />
                        <svg v-else class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isValidating ? 'Validating...' : 'Validate & Preview' }}
                    </button>
                </div>
            </div>

            <div v-if="step === 2" class="space-y-6">
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ previewData.total_rows }}</p>
                        <p class="text-sm text-gray-500">Total Rows</p>
                    </div>
                    <div class="bg-white rounded-xl border border-emerald-200 p-4 text-center">
                        <p class="text-3xl font-bold text-emerald-600">{{ previewData.valid_rows }}</p>
                        <p class="text-sm text-gray-500">Valid</p>
                    </div>
                    <div class="bg-white rounded-xl border border-red-200 p-4 text-center">
                        <p class="text-3xl font-bold text-red-600">{{ previewData.invalid_rows }}</p>
                        <p class="text-sm text-gray-500">Invalid</p>
                    </div>
                </div>

                <div v-if="previewData.invalid.length > 0" class="bg-white rounded-xl border border-red-200">
                    <div class="px-4 py-3 border-b border-red-200 bg-red-50 rounded-t-xl">
                        <h3 class="font-medium text-red-900 flex items-center gap-2">
                            <ExclamationCircleIcon class="h-5 w-5" />
                            Invalid Rows ({{ previewData.invalid.length }})
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="row in previewData.invalid" :key="row.row" class="bg-red-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.row }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.tenant_email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.amount }}</td>
                                    <td class="px-4 py-3 text-sm text-red-700">
                                        <ul class="list-disc list-inside">
                                            <li v-for="error in row.errors" :key="error">{{ error }}</li>
                                        </ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="previewData.valid.length > 0" class="bg-white rounded-xl border border-emerald-200">
                    <button
                        @click="showValidRows = !showValidRows"
                        class="w-full px-4 py-3 border-b border-emerald-200 bg-emerald-50 rounded-t-xl flex items-center justify-between"
                    >
                        <h3 class="font-medium text-emerald-900 flex items-center gap-2">
                            <CheckCircleIcon class="h-5 w-5" />
                            Valid Rows ({{ previewData.valid.length }}) - {{ formatMoney(totalValidAmount) }}
                        </h3>
                        <ChevronDownIcon v-if="!showValidRows" class="h-5 w-5 text-emerald-700" />
                        <ChevronUpIcon v-else class="h-5 w-5 text-emerald-700" />
                    </button>
                    <div v-if="showValidRows" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Allocation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="row in previewData.valid" :key="row.row">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.row }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <p class="font-medium text-gray-900">{{ row.tenant_name }}</p>
                                        <p class="text-gray-500 text-xs">{{ row.tenant_email }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ formatMoney(row.amount) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <div v-for="alloc in row.allocations" :key="alloc.invoice_number" class="text-xs">
                                            {{ alloc.invoice_number }}: {{ formatMoney(alloc.amount) }}
                                        </div>
                                        <div v-if="row.wallet_credit > 0" class="text-xs text-blue-600 mt-1">
                                            Wallet Credit: {{ formatMoney(row.wallet_credit) }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-if="validationError" class="text-sm text-red-600">{{ validationError }}</p>

                <div class="flex justify-between">
                    <button
                        @click="step = 1"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Back
                    </button>
                    <button
                        @click="processPayments"
                        :disabled="previewData.valid_rows === 0 || isProcessing"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <BanknotesIcon v-if="!isProcessing" class="h-4 w-4" />
                        <svg v-else class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isProcessing ? 'Processing...' : `Process ${previewData.valid_rows} Payments` }}
                    </button>
                </div>
            </div>

            <div v-if="step === 3" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="mb-6">
                    <CheckCircleIcon v-if="resultData.success_count > 0" class="mx-auto h-16 w-16 text-emerald-500" />
                    <ExclamationCircleIcon v-else class="mx-auto h-16 w-16 text-red-500" />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 mb-2">Import Complete</h2>

                <div class="grid grid-cols-2 gap-4 max-w-md mx-auto mb-6">
                    <div class="bg-emerald-50 rounded-lg p-4">
                        <p class="text-3xl font-bold text-emerald-600">{{ resultData.success_count }}</p>
                        <p class="text-sm text-gray-600">Payments Recorded</p>
                    </div>
                    <div v-if="resultData.failed_count > 0" class="bg-red-50 rounded-lg p-4">
                        <p class="text-3xl font-bold text-red-600">{{ resultData.failed_count }}</p>
                        <p class="text-sm text-gray-600">Failed</p>
                    </div>
                    <div v-else class="bg-blue-50 rounded-lg p-4">
                        <p class="text-3xl font-bold text-blue-600">{{ formatMoney(resultData.total_amount) }}</p>
                        <p class="text-sm text-gray-600">Total Amount</p>
                    </div>
                </div>

                <div v-if="resultData.errors && resultData.errors.length > 0" class="mb-6 text-left max-w-md mx-auto">
                    <p class="text-sm font-medium text-red-700 mb-2">Errors:</p>
                    <ul class="text-sm text-red-600 list-disc list-inside">
                        <li v-for="(error, index) in resultData.errors" :key="index">{{ error.error }}</li>
                    </ul>
                </div>

                <div class="flex justify-center gap-4">
                    <button
                        @click="resetForm"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Import More
                    </button>
                    <Link
                        :href="route('finances.payments')"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        View Payments
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
