<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
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
    ClockIcon,
    UserPlusIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import type { PaymentsBulkImportPageProps } from '@/types';

const props = withDefaults(defineProps<PaymentsBulkImportPageProps>(), {
    buildings: () => [],
});

const { formatMoney } = useFormatters();

const step = ref(1);
const file = ref(null);
const fileName = ref('');
const isValidating = ref(false);
const isProcessing = ref(false);
const validationError = ref('');
const importMode = ref('current');
const selectedBuildingId = ref(null);

const previewData = ref({
    total_rows: 0,
    valid_rows: 0,
    invalid_rows: 0,
    valid: [],
    invalid: [],
    mode: 'current',
});

const resultData = ref({
    success_count: 0,
    failed_count: 0,
    total_amount: 0,
    archived_tenants_created: 0,
    errors: [],
});

const showValidRows = ref(false);

const breadcrumbItems = [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Payments', href: route('finances.payments') },
    { label: 'Bulk Import' },
];

const canProceed = computed(() => {
    return file.value && selectedBuildingId.value;
});

const templateUrl = computed(() => {
    return route('finances.payments.bulk-import.template', { mode: importMode.value });
});

const currentModeInstructions = [
    { field: 'Unit Number', desc: 'Required. The unit identifier (e.g., A101).' },
    { field: 'Tenant Name', desc: 'Optional. Used for display purposes.' },
    { field: 'Tenant Email', desc: 'Required. Must match an active tenant.' },
    { field: 'Invoice Number', desc: 'Optional. Leave empty for auto-allocation (FIFO).' },
    { field: 'Payment Date', desc: 'Required. Format: YYYY-MM-DD' },
    { field: 'Amount', desc: 'Required. Payment amount (numbers only).' },
    { field: 'Payment Method', desc: 'Required. One of: cash, bank_transfer, mobile_money, paystack' },
    { field: 'Reference', desc: 'Optional. Transaction reference number.' },
];

const historicalModeInstructions = [
    { field: 'Unit Number', desc: 'Required. The unit identifier (e.g., A101).' },
    { field: 'Tenant Name', desc: 'Required. Name of the historical tenant.' },
    { field: 'Tenant Email', desc: 'Optional. Will generate placeholder if empty.' },
    { field: 'Payment Date', desc: 'Required. Format: YYYY-MM-DD (can be in the past).' },
    { field: 'Amount', desc: 'Required. Payment amount (numbers only).' },
    { field: 'Payment Method', desc: 'Required. One of: cash, bank_transfer, mobile_money, paystack' },
    { field: 'Reference', desc: 'Optional. Transaction reference number.' },
];

const instructions = computed(() => {
    return importMode.value === 'historical' ? historicalModeInstructions : currentModeInstructions;
});

const handleFileChange = (event) => {
    const selectedFile = event.target.files[0];
    if (selectedFile) {
        file.value = selectedFile;
        fileName.value = selectedFile.name;
        validationError.value = '';
    }
};

const handleDragOver = (event) => {
    event.currentTarget.classList.add('border-emerald-400', 'bg-emerald-50');
};

const handleDragLeave = (event) => {
    event.currentTarget.classList.remove('border-emerald-400', 'bg-emerald-50');
};

const handleDrop = (event) => {
    event.currentTarget.classList.remove('border-emerald-400', 'bg-emerald-50');
    const droppedFile = event.dataTransfer.files[0];
    if (droppedFile) {
        file.value = droppedFile;
        fileName.value = droppedFile.name;
        validationError.value = '';
        const fileInput = document.getElementById('csv-file');
        if (fileInput) {
            const dt = new DataTransfer();
            dt.items.add(droppedFile);
            fileInput.files = dt.files;
        }
    }
};

const validateFile = async () => {
    if (!file.value) {
        validationError.value = 'Please select a CSV file';
        return;
    }
    if (!selectedBuildingId.value) {
        validationError.value = 'Please select a building';
        return;
    }

    isValidating.value = true;
    validationError.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file.value);
        formData.append('building_id', selectedBuildingId.value);
        formData.append('mode', importMode.value);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta?.getAttribute('content');

        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(route('finances.payments.bulk-import.validate'), {
            method: 'POST',
            headers,
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
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta?.getAttribute('content');

        const headers = {
            'Content-Type': 'application/json',
        };
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(route('finances.payments.bulk-import.process'), {
            method: 'POST',
            headers,
            body: JSON.stringify({
                payments: previewData.value.valid,
                mode: previewData.value.mode,
                building_id: selectedBuildingId.value,
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
        mode: 'current',
    };
    resultData.value = {
        success_count: 0,
        failed_count: 0,
        total_amount: 0,
        archived_tenants_created: 0,
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
            <!-- Header with Finance Hub styling -->
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <ArrowUpTrayIcon class="h-6 w-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Bulk Import Payments</h1>
                    <p class="text-sm text-gray-500">Upload CSV to record multiple payments</p>
                </div>
            </div>

            <!-- Breadcrumb -->
            <div class="mb-6">
                <Breadcrumb :items="breadcrumbItems" />
            </div>

            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center">
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 1 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">1</span>
                        <span class="ms-2 font-medium" :class="step >= 1 ? 'text-gray-900' : 'text-gray-500'">Upload</span>
                    </div>
                    <div class="flex-1 mx-4 h-0.5" :class="step >= 2 ? 'bg-emerald-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 2 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">2</span>
                        <span class="ms-2 font-medium" :class="step >= 2 ? 'text-gray-900' : 'text-gray-500'">Preview</span>
                    </div>
                    <div class="flex-1 mx-4 h-0.5" :class="step >= 3 ? 'bg-emerald-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-sm">
                        <span :class="[
                            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
                            step >= 3 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'
                        ]">3</span>
                        <span class="ms-2 font-medium" :class="step >= 3 ? 'text-gray-900' : 'text-gray-500'">Results</span>
                    </div>
                </div>
            </div>

            <!-- Step 1: Upload -->
            <div v-if="step === 1" class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 space-y-6">
                    <!-- Import Mode Toggle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Import Mode</label>
                        <div class="flex gap-2 p-1 bg-gray-100 rounded-lg w-fit">
                            <button
                                @click="importMode = 'current'"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-md transition-colors flex items-center gap-2',
                                    importMode === 'current'
                                        ? 'bg-white text-emerald-700 shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900'
                                ]"
                            >
                                <BanknotesIcon class="h-4 w-4" />
                                Current Tenants
                            </button>
                            <button
                                @click="importMode = 'historical'"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-md transition-colors flex items-center gap-2',
                                    importMode === 'historical'
                                        ? 'bg-white text-amber-700 shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900'
                                ]"
                            >
                                <ClockIcon class="h-4 w-4" />
                                Historical Data
                            </button>
                        </div>
                    </div>

                    <!-- Historical Mode Warning -->
                    <div v-if="importMode === 'historical'" class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex gap-3">
                            <InformationCircleIcon class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                            <div class="text-sm text-amber-800">
                                <p class="font-medium mb-1">Historical Import Mode</p>
                                <ul class="space-y-1 text-amber-700">
                                    <li>• Creates archived tenant records for past tenants</li>
                                    <li>• Does NOT affect current tenant balances</li>
                                    <li>• Useful for onboarding landlords with existing buildings</li>
                                    <li>• Historical payments will appear in unit history reports</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Building Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Building *</label>
                        <select
                            v-model="selectedBuildingId"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option :value="null">-- Select Building --</option>
                            <option v-for="building in buildings" :key="building.id" :value="building.id">
                                {{ building.display_name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Unit numbers in the CSV must match units in this building</p>
                    </div>

                    <!-- CSV Format Instructions -->
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-medium text-blue-900 mb-2">CSV Format Instructions</h3>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li v-for="inst in instructions" :key="inst.field">
                                <strong>{{ inst.field }}</strong> - {{ inst.desc }}
                            </li>
                        </ul>
                    </div>

                    <!-- Download Template -->
                    <div>
                        <a
                            :href="templateUrl"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-colors"
                        >
                            <DocumentArrowDownIcon class="h-5 w-5" />
                            Download {{ importMode === 'historical' ? 'Historical' : 'Current' }} Template
                        </a>
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File *</label>
                        <label
                            class="flex items-center justify-center px-6 py-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-emerald-400 hover:bg-gray-50 transition-colors"
                            @dragover.prevent="handleDragOver"
                            @dragleave.prevent="handleDragLeave"
                            @drop.prevent="handleDrop"
                        >
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

                    <!-- Error Message -->
                    <p v-if="validationError" class="text-sm text-red-600">{{ validationError }}</p>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link
                            :href="route('finances.payments')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            @click="validateFile"
                            :disabled="!canProceed || isValidating"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <EyeIcon v-if="!isValidating" class="h-4 w-4" />
                            <svg v-else class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isValidating ? 'Validating...' : 'Validate & Preview →' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Preview -->
            <div v-if="step === 2" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ previewData.total_rows }}</p>
                        <p class="text-sm text-gray-500">Total Rows</p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-4 text-center">
                        <p class="text-3xl font-bold text-emerald-600">{{ previewData.valid_rows }}</p>
                        <p class="text-sm text-gray-500">Valid</p>
                    </div>
                    <div class="bg-red-50 rounded-xl border border-red-200 p-4 text-center">
                        <p class="text-3xl font-bold text-red-600">{{ previewData.invalid_rows }}</p>
                        <p class="text-sm text-gray-500">Invalid</p>
                    </div>
                </div>

                <!-- Historical Mode Indicator -->
                <div v-if="previewData.mode === 'historical'" class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex items-center gap-2 text-amber-800">
                        <ClockIcon class="h-5 w-5" />
                        <span class="font-medium">Historical Import Mode</span>
                        <span class="text-sm text-amber-700">- Archived tenant records will be created</span>
                    </div>
                </div>

                <!-- Invalid Rows -->
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
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Row</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Unit</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Errors</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="row in previewData.invalid" :key="row.row" class="bg-red-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.row }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.unit_number || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <div>{{ row.tenant_name || row.tenant_email || '-' }}</div>
                                        <div v-if="row.tenant_name && row.tenant_email" class="text-xs text-gray-500">{{ row.tenant_email }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.amount || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-red-700">
                                        <ul class="list-disc list-inside">
                                            <li v-for="(error, idx) in row.errors" :key="`${row.row}-${idx}`">{{ error }}</li>
                                        </ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Valid Rows -->
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
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Row</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Unit</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                        {{ previewData.mode === 'historical' ? 'Status' : 'Allocation' }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="row in previewData.valid" :key="row.row">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ row.row }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ row.unit_number }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ row.tenant_name }}</p>
                                                <p v-if="row.tenant_email" class="text-gray-500 text-xs">{{ row.tenant_email }}</p>
                                            </div>
                                            <span v-if="row.is_historical && row.will_create_tenant" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 rounded-full">
                                                <UserPlusIcon class="h-3 w-3" />
                                                New
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ formatMoney(row.amount) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <template v-if="row.is_historical">
                                            <span class="text-amber-600 text-xs">Historical record (no invoice)</span>
                                        </template>
                                        <template v-else>
                                            <div v-for="alloc in row.allocations" :key="alloc.invoice_number" class="text-xs">
                                                {{ alloc.invoice_number }}: {{ formatMoney(alloc.amount) }}
                                            </div>
                                            <div v-if="row.allocations.length === 0" class="text-xs text-gray-500">
                                                No outstanding invoices
                                            </div>
                                            <div v-if="row.wallet_credit > 0" class="text-xs text-blue-600 mt-1">
                                                Wallet Credit: {{ formatMoney(row.wallet_credit) }}
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Error Message -->
                <p v-if="validationError" class="text-sm text-red-600">{{ validationError }}</p>

                <!-- Actions -->
                <div class="flex justify-between">
                    <button
                        @click="step = 1"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        <ArrowLeftIcon class="h-4 w-4" />
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
                        {{ isProcessing ? 'Processing...' : `Process ${previewData.valid_rows} Payments →` }}
                    </button>
                </div>
            </div>

            <!-- Step 3: Results -->
            <div v-if="step === 3" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
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
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-3xl font-bold text-blue-600">{{ formatMoney(resultData.total_amount) }}</p>
                        <p class="text-sm text-gray-600">Total Amount</p>
                    </div>
                </div>

                <!-- Historical Import Stats -->
                <div v-if="resultData.archived_tenants_created > 0" class="max-w-md mx-auto mb-6">
                    <div class="bg-amber-50 rounded-lg p-4 flex items-center justify-center gap-3">
                        <UserPlusIcon class="h-6 w-6 text-amber-600" />
                        <div class="text-start">
                            <p class="text-lg font-bold text-amber-700">{{ resultData.archived_tenants_created }}</p>
                            <p class="text-sm text-amber-600">Archived Tenants Created</p>
                        </div>
                    </div>
                </div>

                <div v-if="resultData.failed_count > 0" class="max-w-md mx-auto mb-6">
                    <div class="bg-red-50 rounded-lg p-4">
                        <p class="text-3xl font-bold text-red-600">{{ resultData.failed_count }}</p>
                        <p class="text-sm text-gray-600">Failed</p>
                    </div>
                </div>

                <div v-if="resultData.errors && resultData.errors.length > 0" class="mb-6 text-start max-w-md mx-auto">
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
                        View Payments →
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
