<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Cog6ToothIcon,
    BuildingOfficeIcon,
    BanknotesIcon,
    DocumentTextIcon,
    ClockIcon,
    PhotoIcon,
    TrashIcon,
    UserPlusIcon,
    ArrowLeftIcon,
    DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: Object,
});

const activeSection = ref('business');

const sections = [
    { id: 'business', name: 'Business Details', icon: BuildingOfficeIcon },
    { id: 'bank', name: 'Bank Account', icon: BanknotesIcon },
    { id: 'numbering', name: 'Document Numbering', icon: DocumentDuplicateIcon },
    { id: 'terms', name: 'Payment Terms', icon: ClockIcon },
    { id: 'conditions', name: 'Terms & Conditions', icon: DocumentTextIcon },
    { id: 'first-invoice', name: 'First Invoice', icon: UserPlusIcon },
];

const form = useForm({
    business_name: props.settings?.business_name || '',
    business_address: props.settings?.business_address || '',
    business_phone: props.settings?.business_phone || '',
    business_email: props.settings?.business_email || '',
    tax_number: props.settings?.tax_number || '',
    bank_name: props.settings?.bank_name || '',
    bank_account_name: props.settings?.bank_account_name || '',
    bank_account_number: props.settings?.bank_account_number || '',
    bank_branch: props.settings?.bank_branch || '',
    bank_swift_code: props.settings?.bank_swift_code || '',
    invoice_prefix: props.settings?.invoice_prefix || 'INV',
    invoice_next_number: props.settings?.invoice_next_number || 1,
    receipt_prefix: props.settings?.receipt_prefix || 'RCT',
    receipt_next_number: props.settings?.receipt_next_number || 1,
    credit_note_prefix: props.settings?.credit_note_prefix || 'CN',
    credit_note_next_number: props.settings?.credit_note_next_number || 1,
    default_due_days: props.settings?.default_due_days || 7,
    late_penalty_percentage: props.settings?.late_penalty_percentage || 0,
    grace_period_days: props.settings?.grace_period_days || 0,
    terms_and_conditions: props.settings?.terms_and_conditions || '',
    footer_note: props.settings?.footer_note || '',
    auto_generate_enabled: props.settings?.auto_generate_enabled || false,
    auto_generate_day: props.settings?.auto_generate_day || 1,
    auto_send_enabled: props.settings?.auto_send_enabled || false,
    prorate_first_month: props.settings?.prorate_first_month ?? true,
    include_last_month_rent: props.settings?.include_last_month_rent || false,
    admin_fee_amount: props.settings?.admin_fee_amount || '',
    key_deposit_amount: props.settings?.key_deposit_amount || '',
    first_invoice_due_days: props.settings?.first_invoice_due_days || 0,
    auto_generate_first_invoice: props.settings?.auto_generate_first_invoice || false,
});

const logoForm = useForm({
    logo: null,
});

const logoInput = ref(null);

const submit = () => {
    form.put(route('invoice-settings.update'), {
        preserveScroll: true,
    });
};

const uploadLogo = () => {
    if (logoForm.logo) {
        logoForm.post(route('invoice-settings.upload-logo'), {
            preserveScroll: true,
            onSuccess: () => {
                logoForm.reset();
                if (logoInput.value) {
                    logoInput.value.value = '';
                }
            },
        });
    }
};

const removeLogo = () => {
    if (confirm('Are you sure you want to remove the logo?')) {
        useForm({}).delete(route('invoice-settings.remove-logo'), {
            preserveScroll: true,
        });
    }
};

const handleLogoChange = (e) => {
    logoForm.logo = e.target.files[0];
    uploadLogo();
};

const getLogoUrl = () => {
    if (props.settings?.logo_path) {
        return `/storage/${props.settings.logo_path}`;
    }
    return null;
};
</script>

<template>
    <Head title="Invoice Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('finances.settings')"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <Cog6ToothIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Invoice Settings</h1>
                        <p class="text-sm text-gray-500">Configure your invoice preferences and business details</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="flex">
                        <nav class="w-64 border-r border-gray-200 bg-gray-50 flex-shrink-0">
                            <div class="p-4 space-y-1">
                                <button
                                    v-for="section in sections"
                                    :key="section.id"
                                    @click="activeSection = section.id"
                                    :class="[
                                        'w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                        activeSection === section.id
                                            ? 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                                            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                    ]"
                                >
                                    <component
                                        :is="section.icon"
                                        :class="[
                                            'w-5 h-5',
                                            activeSection === section.id ? 'text-indigo-500' : 'text-gray-400'
                                        ]"
                                    />
                                    {{ section.name }}
                                </button>
                            </div>
                        </nav>

                        <div class="flex-1 min-h-[600px] flex flex-col">
                            <form @submit.prevent="submit" class="flex-1 flex flex-col">
                                <div class="flex-1 p-6">
                                    <div v-if="activeSection === 'business'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Business Details</h2>
                                            <p class="mt-1 text-sm text-gray-500">Information displayed on your invoices</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Business Logo</label>
                                            <div class="flex items-center gap-4">
                                                <div class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden bg-gray-50">
                                                    <img v-if="getLogoUrl()" :src="getLogoUrl()" alt="Logo" class="w-full h-full object-contain" />
                                                    <PhotoIcon v-else class="w-8 h-8 text-gray-400" />
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                        <input
                                                            ref="logoInput"
                                                            type="file"
                                                            accept="image/*"
                                                            class="hidden"
                                                            @change="handleLogoChange"
                                                        />
                                                        Upload Logo
                                                    </label>
                                                    <button
                                                        v-if="getLogoUrl()"
                                                        type="button"
                                                        @click="removeLogo"
                                                        class="inline-flex items-center text-sm text-red-600 hover:text-red-700"
                                                    >
                                                        <TrashIcon class="w-4 h-4 mr-1" />
                                                        Remove
                                                    </button>
                                                    <p class="text-xs text-gray-500">PNG, JPG up to 2MB</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                                                <input
                                                    v-model="form.business_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Your Business Name"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Tax/VAT Number</label>
                                                <input
                                                    v-model="form.tax_number"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="e.g., P051234567A"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                                <input
                                                    v-model="form.business_phone"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="+254 7XX XXX XXX"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                                <input
                                                    v-model="form.business_email"
                                                    type="email"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="billing@example.com"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                                            <textarea
                                                v-model="form.business_address"
                                                rows="3"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Street address, City, Country"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'bank'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Bank Account Details</h2>
                                            <p class="mt-1 text-sm text-gray-500">Payment information shown on invoices</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                                <input
                                                    v-model="form.bank_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="e.g., Equity Bank"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                                                <input
                                                    v-model="form.bank_account_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Account holder name"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                                                <input
                                                    v-model="form.bank_account_number"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="XXXX XXXX XXXX"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                                                <input
                                                    v-model="form.bank_branch"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Branch name"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">SWIFT Code</label>
                                                <input
                                                    v-model="form.bank_swift_code"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="e.g., EABORBIAX"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'numbering'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Document Numbering</h2>
                                            <p class="mt-1 text-sm text-gray-500">Configure prefixes and starting numbers</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">Invoices</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Prefix</label>
                                                    <input
                                                        v-model="form.invoice_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="INV"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Next Number</label>
                                                    <input
                                                        v-model="form.invoice_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">Preview: {{ form.invoice_prefix }}-202601-{{ String(form.invoice_next_number).padStart(4, '0') }}</p>
                                            </div>

                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">Receipts</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Prefix</label>
                                                    <input
                                                        v-model="form.receipt_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="RCT"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Next Number</label>
                                                    <input
                                                        v-model="form.receipt_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">Preview: {{ form.receipt_prefix }}-202601-{{ String(form.receipt_next_number).padStart(4, '0') }}</p>
                                            </div>

                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">Credit Notes</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Prefix</label>
                                                    <input
                                                        v-model="form.credit_note_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="CN"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">Next Number</label>
                                                    <input
                                                        v-model="form.credit_note_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">Preview: {{ form.credit_note_prefix }}-202601-{{ String(form.credit_note_next_number).padStart(4, '0') }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'terms'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Payment Terms</h2>
                                            <p class="mt-1 text-sm text-gray-500">Payment terms applied to new invoices</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Due Days</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.default_due_days"
                                                        type="number"
                                                        min="1"
                                                        max="90"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">days</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Days until payment is due</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Late Penalty</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.late_penalty_percentage"
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">%</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Applied after due date</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Grace Period</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.grace_period_days"
                                                        type="number"
                                                        min="0"
                                                        max="30"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">days</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Before penalty applies</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'conditions'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Terms & Conditions</h2>
                                            <p class="mt-1 text-sm text-gray-500">Custom text displayed on invoices</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Terms and Conditions</label>
                                            <textarea
                                                v-model="form.terms_and_conditions"
                                                rows="6"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Payment is due within the specified period. Late payments may incur additional charges..."
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Footer Note</label>
                                            <textarea
                                                v-model="form.footer_note"
                                                rows="3"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Thank you for your business!"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'first-invoice'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">First Invoice Settings</h2>
                                            <p class="mt-1 text-sm text-gray-500">Configure charges for new tenant onboarding</p>
                                        </div>

                                        <div class="space-y-4">
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.prorate_first_month"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">Prorate first month rent</span>
                                                    <p class="text-xs text-gray-500">Calculate rent based on move-in date (e.g., 15th = half month)</p>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.include_last_month_rent"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">Include last month rent</span>
                                                    <p class="text-xs text-gray-500">Require advance payment for last month of tenancy</p>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.auto_generate_first_invoice"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">Auto-generate first invoice</span>
                                                    <p class="text-xs text-gray-500">Automatically create invoice when tenant accepts invitation</p>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-gray-200">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin/Processing Fee</label>
                                                <div class="relative">
                                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">KES</span>
                                                    <input
                                                        v-model="form.admin_fee_amount"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-full pl-14 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">One-time fee for new tenants</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Key Deposit</label>
                                                <div class="relative">
                                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">KES</span>
                                                    <input
                                                        v-model="form.key_deposit_amount"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-full pl-14 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Refundable key deposit</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Due Days After Move-in</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.first_invoice_due_days"
                                                        type="number"
                                                        min="0"
                                                        max="30"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">days</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">0 = due immediately</p>
                                            </div>
                                        </div>

                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <p class="text-sm text-blue-800">
                                                <strong>Note:</strong> Security deposit is configured per unit/lease. First invoice will automatically include:
                                                first month rent (prorated if enabled), security deposit, and any fees configured above.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        <span v-if="form.processing">Saving...</span>
                                        <span v-else>Save Settings</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
