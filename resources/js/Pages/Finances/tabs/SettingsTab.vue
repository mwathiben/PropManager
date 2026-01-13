<script setup>
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
} from '@heroicons/vue/24/outline';

const props = defineProps({
    paymentConfig: Object,
    paymentMethods: Object,
    invoiceSettings: Object,
    reminderSettings: Object,
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
