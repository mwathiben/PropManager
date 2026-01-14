<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    CreditCardIcon,
    BanknotesIcon,
    DevicePhoneMobileIcon,
    Cog6ToothIcon,
    BellIcon,
    DocumentTextIcon,
    ArrowTopRightOnSquareIcon,
    ReceiptPercentIcon,
    EyeIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';

interface PaymentConfig {
    accepted_payment_methods?: string[];
    bank_name?: string;
    bank_account_name?: string;
    bank_account_number?: string;
    bank_branch?: string;
    mpesa_shortcode_type?: 'paybill' | 'till';
    mpesa_shortcode?: string;
    mpesa_account_name?: string;
}

interface InvoiceSettings {
    include_water_charges?: boolean;
    include_arrears?: boolean;
    auto_generate_monthly?: boolean;
}

interface ReminderSettings {
    reminder_days_before_due?: number;
    overdue_reminder_frequency?: string;
    reminder_channels?: string[];
}

interface ReceiptSettings {
    auto_email_receipt?: boolean;
    receipt_show_logo?: boolean;
    receipt_show_tenant_details?: boolean;
    receipt_show_invoice_details?: boolean;
    receipt_show_payment_method?: boolean;
    receipt_header_text?: string;
    receipt_footer_text?: string;
    receipt_thank_you_message?: string;
}

interface FiscalYearSettings {
    fiscal_year_type?: 'calendar' | 'custom';
    fiscal_year_start_month?: number;
}

interface Props {
    paymentConfig?: PaymentConfig;
    paymentMethods?: Record<string, boolean>;
    invoiceSettings?: InvoiceSettings;
    reminderSettings?: ReminderSettings;
    receiptSettings?: ReceiptSettings;
    fiscalYearSettings?: FiscalYearSettings;
}

const props = withDefaults(defineProps<Props>(), {
    paymentConfig: () => ({}),
    paymentMethods: () => ({}),
    invoiceSettings: () => ({}),
    reminderSettings: () => ({}),
    receiptSettings: () => ({}),
    fiscalYearSettings: () => ({}),
});

const activeSection = ref('payment-methods');

const paymentMethodsForm = useForm({
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods || [],
    bank_name: props.paymentConfig?.bank_name || '',
    bank_account_name: props.paymentConfig?.bank_account_name || '',
    bank_account_number: props.paymentConfig?.bank_account_number || '',
    bank_branch: props.paymentConfig?.bank_branch || '',
    mpesa_shortcode_type: props.paymentConfig?.mpesa_shortcode_type || 'paybill',
    mpesa_shortcode: props.paymentConfig?.mpesa_shortcode || '',
    mpesa_account_name: props.paymentConfig?.mpesa_account_name || '',
    mpesa_passkey: '',
});

const invoiceForm = useForm({
    include_water_charges: props.invoiceSettings?.include_water_charges ?? true,
    include_arrears: props.invoiceSettings?.include_arrears ?? true,
    auto_generate_monthly: props.invoiceSettings?.auto_generate_monthly ?? false,
});

const reminderForm = useForm({
    reminder_days_before_due: props.reminderSettings?.reminder_days_before_due || 3,
    overdue_reminder_frequency: props.reminderSettings?.overdue_reminder_frequency || 'weekly',
    reminder_channels: props.reminderSettings?.reminder_channels || ['email'],
});

const receiptForm = useForm({
    auto_email_receipt: props.receiptSettings?.auto_email_receipt ?? true,
    receipt_show_logo: props.receiptSettings?.receipt_show_logo ?? true,
    receipt_show_tenant_details: props.receiptSettings?.receipt_show_tenant_details ?? true,
    receipt_show_invoice_details: props.receiptSettings?.receipt_show_invoice_details ?? true,
    receipt_show_payment_method: props.receiptSettings?.receipt_show_payment_method ?? true,
    receipt_header_text: props.receiptSettings?.receipt_header_text || '',
    receipt_footer_text: props.receiptSettings?.receipt_footer_text || '',
    receipt_thank_you_message: props.receiptSettings?.receipt_thank_you_message || '',
});

const fiscalYearForm = useForm({
    fiscal_year_type: props.fiscalYearSettings?.fiscal_year_type || 'calendar',
    fiscal_year_start_month: props.fiscalYearSettings?.fiscal_year_start_month || 1,
});

const monthOptions = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const togglePaymentMethod = (method) => {
    const index = paymentMethodsForm.accepted_payment_methods.indexOf(method);
    if (index > -1) {
        paymentMethodsForm.accepted_payment_methods.splice(index, 1);
    } else {
        paymentMethodsForm.accepted_payment_methods.push(method);
    }
};

const isMethodEnabled = (method) => {
    return paymentMethodsForm.accepted_payment_methods.includes(method);
};

const savePaymentMethods = () => {
    paymentMethodsForm.post(route('finances.settings.payment-methods'), {
        preserveScroll: true,
    });
};

const saveInvoiceSettings = () => {
    invoiceForm.post(route('finances.settings.invoice'), {
        preserveScroll: true,
    });
};

const saveReminderSettings = () => {
    reminderForm.post(route('finances.settings.reminder'), {
        preserveScroll: true,
    });
};

const saveReceiptSettings = () => {
    receiptForm.post(route('finances.settings.receipt'), {
        preserveScroll: true,
    });
};

const saveFiscalYearSettings = () => {
    fiscalYearForm.post(route('finances.settings.fiscal-year'), {
        preserveScroll: true,
    });
};

const previewReceipt = () => {
    window.open(route('finances.settings.receipt.preview'), '_blank');
};

const toggleReminderChannel = (channel) => {
    const index = reminderForm.reminder_channels.indexOf(channel);
    if (index > -1) {
        reminderForm.reminder_channels.splice(index, 1);
    } else {
        reminderForm.reminder_channels.push(channel);
    }
};

const isChannelEnabled = (channel) => {
    return reminderForm.reminder_channels.includes(channel);
};

const methodIcons = {
    cash: BanknotesIcon,
    bank_transfer: CreditCardIcon,
    mobile_money: DevicePhoneMobileIcon,
    paystack: CreditCardIcon,
};

const sections = [
    { id: 'payment-methods', name: 'Payment Methods', icon: CreditCardIcon },
    { id: 'invoice-settings', name: 'Invoice Settings', icon: DocumentTextIcon },
    { id: 'receipt-settings', name: 'Receipt Settings', icon: ReceiptPercentIcon },
    { id: 'fiscal-year', name: 'Fiscal Year', icon: CalendarDaysIcon },
    { id: 'reminders', name: 'Reminders', icon: BellIcon },
];
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">
            <nav class="space-y-1">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    @click="activeSection = section.id"
                    :class="[
                        'w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                        activeSection === section.id
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'text-gray-600 hover:bg-gray-50'
                    ]"
                >
                    <component :is="section.icon" class="h-5 w-5" />
                    {{ section.name }}
                </button>
            </nav>
        </div>

        <div class="lg:col-span-3">
            <div v-if="activeSection === 'payment-methods'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Accepted Payment Methods</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            v-for="(label, method) in paymentMethods"
                            :key="method"
                            @click="togglePaymentMethod(method)"
                            :class="[
                                'flex items-center gap-3 p-4 rounded-lg border-2 transition-colors text-left',
                                isMethodEnabled(method)
                                    ? 'border-emerald-500 bg-emerald-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]"
                        >
                            <div :class="['p-2 rounded-lg', isMethodEnabled(method) ? 'bg-emerald-100' : 'bg-gray-100']">
                                <component
                                    :is="methodIcons[method] || CreditCardIcon"
                                    :class="['h-5 w-5', isMethodEnabled(method) ? 'text-emerald-600' : 'text-gray-400']"
                                />
                            </div>
                            <div>
                                <p :class="['text-sm font-medium', isMethodEnabled(method) ? 'text-emerald-900' : 'text-gray-900']">
                                    {{ label }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <div v-if="isMethodEnabled('bank_transfer')" class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Bank Transfer Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Bank Name</label>
                            <input
                                v-model="paymentMethodsForm.bank_name"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Account Name</label>
                            <input
                                v-model="paymentMethodsForm.bank_account_name"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Account Number</label>
                            <input
                                v-model="paymentMethodsForm.bank_account_number"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Branch</label>
                            <input
                                v-model="paymentMethodsForm.bank_branch"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="isMethodEnabled('mobile_money')" class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">M-Pesa Details</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">Shortcode Type</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="paymentMethodsForm.mpesa_shortcode_type"
                                        type="radio"
                                        value="paybill"
                                        class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-gray-700">Paybill</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="paymentMethodsForm.mpesa_shortcode_type"
                                        type="radio"
                                        value="till"
                                        class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-gray-700">Till (Buy Goods)</span>
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    {{ paymentMethodsForm.mpesa_shortcode_type === 'till' ? 'Till Number' : 'Paybill Number' }}
                                </label>
                                <input
                                    v-model="paymentMethodsForm.mpesa_shortcode"
                                    type="text"
                                    :placeholder="paymentMethodsForm.mpesa_shortcode_type === 'till' ? 'e.g. 5123456' : 'e.g. 123456'"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Account Name</label>
                                <input
                                    v-model="paymentMethodsForm.mpesa_account_name"
                                    type="text"
                                    placeholder="Displayed to tenants"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">M-Pesa Passkey</label>
                            <input
                                v-model="paymentMethodsForm.mpesa_passkey"
                                type="password"
                                placeholder="Get this from Safaricom Daraja portal"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">Required for STK Push payments. Leave blank to keep existing.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="savePaymentMethods"
                        :disabled="paymentMethodsForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ paymentMethodsForm.processing ? 'Saving...' : 'Save Payment Methods' }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'invoice-settings'" class="space-y-6">
                <Link
                    :href="route('invoice-settings.edit')"
                    class="block bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-200 p-6 hover:border-indigo-300 transition-colors group"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-indigo-100 rounded-lg group-hover:bg-indigo-200 transition-colors">
                                <Cog6ToothIcon class="h-6 w-6 text-indigo-600" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Comprehensive Invoice Settings</h3>
                                <p class="text-sm text-gray-600 mt-0.5">
                                    Business details, bank info, document numbering, terms, and first invoice settings
                                </p>
                            </div>
                        </div>
                        <ArrowTopRightOnSquareIcon class="h-5 w-5 text-indigo-400 group-hover:text-indigo-600 transition-colors" />
                    </div>
                </Link>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Quick Settings</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.include_water_charges"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Include water charges in invoices</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.include_arrears"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Include previous arrears in invoices</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.auto_generate_monthly"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Automatically generate invoices monthly</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="saveInvoiceSettings"
                        :disabled="invoiceForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ invoiceForm.processing ? 'Saving...' : 'Save Invoice Settings' }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'receipt-settings'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Auto-Send Settings</h3>
                    <label class="flex items-center gap-3">
                        <input
                            v-model="receiptForm.auto_email_receipt"
                            type="checkbox"
                            class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                        />
                        <div>
                            <span class="text-sm text-gray-700">Automatically email receipt to tenant after payment</span>
                            <p class="text-xs text-gray-500">Receipts will be sent immediately when a payment is recorded</p>
                        </div>
                    </label>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Receipt Content</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_logo"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Show business logo on receipt</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_tenant_details"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Show tenant details (name, email, unit)</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_invoice_details"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Show invoice details table</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_payment_method"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Show payment method</span>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Custom Text</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Header Text</label>
                            <input
                                v-model="receiptForm.receipt_header_text"
                                type="text"
                                maxlength="255"
                                placeholder="e.g., Official Payment Receipt"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">Custom text displayed below the receipt title</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Thank You Message</label>
                            <input
                                v-model="receiptForm.receipt_thank_you_message"
                                type="text"
                                maxlength="500"
                                placeholder="e.g., Thank you for your payment!"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Footer Text</label>
                            <textarea
                                v-model="receiptForm.receipt_footer_text"
                                rows="3"
                                placeholder="e.g., For any inquiries, please contact us at..."
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button
                        @click="previewReceipt"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        <EyeIcon class="h-4 w-4" />
                        Preview Receipt
                    </button>
                    <button
                        @click="saveReceiptSettings"
                        :disabled="receiptForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ receiptForm.processing ? 'Saving...' : 'Save Receipt Settings' }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'fiscal-year'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Fiscal Year Configuration</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Configure your fiscal year for financial reporting. This affects how "Year to Date" and fiscal year reports are calculated.
                    </p>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">Fiscal Year Type</label>
                            <div class="flex gap-4">
                                <button
                                    type="button"
                                    @click="fiscalYearForm.fiscal_year_type = 'calendar'"
                                    :class="[
                                        'flex-1 px-4 py-3 text-sm rounded-lg border-2 transition-colors text-left',
                                        fiscalYearForm.fiscal_year_type === 'calendar'
                                            ? 'border-emerald-500 bg-emerald-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <p :class="['font-medium', fiscalYearForm.fiscal_year_type === 'calendar' ? 'text-emerald-900' : 'text-gray-900']">
                                        Calendar Year
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">January 1 - December 31</p>
                                </button>
                                <button
                                    type="button"
                                    @click="fiscalYearForm.fiscal_year_type = 'custom'"
                                    :class="[
                                        'flex-1 px-4 py-3 text-sm rounded-lg border-2 transition-colors text-left',
                                        fiscalYearForm.fiscal_year_type === 'custom'
                                            ? 'border-emerald-500 bg-emerald-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <p :class="['font-medium', fiscalYearForm.fiscal_year_type === 'custom' ? 'text-emerald-900' : 'text-gray-900']">
                                        Custom Fiscal Year
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">Choose your start month</p>
                                </button>
                            </div>
                        </div>

                        <div v-if="fiscalYearForm.fiscal_year_type === 'custom'">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fiscal Year Start Month</label>
                            <select
                                v-model.number="fiscalYearForm.fiscal_year_start_month"
                                class="w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">
                                Your fiscal year will run from {{ monthOptions.find(m => m.value === fiscalYearForm.fiscal_year_start_month)?.label }}
                                to {{ monthOptions.find(m => m.value === (fiscalYearForm.fiscal_year_start_month === 1 ? 12 : fiscalYearForm.fiscal_year_start_month - 1))?.label }}.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl border border-blue-200 p-4">
                    <div class="flex gap-3">
                        <CalendarDaysIcon class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <h4 class="text-sm font-medium text-blue-900">How this affects your reports</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                The "Year to Date" filter in reports will use your fiscal year start date.
                                You'll also have access to "This Fiscal Year" and "Last Fiscal Year" filter options.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="saveFiscalYearSettings"
                        :disabled="fiscalYearForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ fiscalYearForm.processing ? 'Saving...' : 'Save Fiscal Year Settings' }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'reminders'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Reminder Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Days before due date</label>
                            <input
                                v-model.number="reminderForm.reminder_days_before_due"
                                type="number"
                                min="1"
                                max="30"
                                class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Overdue reminder frequency</label>
                            <select
                                v-model="reminderForm.overdue_reminder_frequency"
                                class="w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">Notification Channels</label>
                            <div class="flex gap-4">
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('email')"
                                    :class="[
                                        'px-4 py-2 text-sm rounded-lg border-2 transition-colors',
                                        isChannelEnabled('email')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    Email
                                </button>
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('sms')"
                                    :class="[
                                        'px-4 py-2 text-sm rounded-lg border-2 transition-colors',
                                        isChannelEnabled('sms')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    SMS
                                </button>
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('push')"
                                    :class="[
                                        'px-4 py-2 text-sm rounded-lg border-2 transition-colors',
                                        isChannelEnabled('push')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    Push
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="saveReminderSettings"
                        :disabled="reminderForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ reminderForm.processing ? 'Saving...' : 'Save Reminder Settings' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
