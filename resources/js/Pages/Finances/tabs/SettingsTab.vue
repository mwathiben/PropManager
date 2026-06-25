<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import {
    CreditCardIcon,
    Cog6ToothIcon,
    BellIcon,
    DocumentTextIcon,
    ArrowTopRightOnSquareIcon,
    ReceiptPercentIcon,
    EyeIcon,
    CalendarDaysIcon,
    CurrencyDollarIcon,
} from '@heroicons/vue/24/outline';

interface CurrencyOption {
    value: string;
    label: string;
}

interface PaymentConfig {
    default_currency?: string;
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
    currencyOptions?: CurrencyOption[];
    invoiceSettings?: InvoiceSettings;
    reminderSettings?: ReminderSettings;
    receiptSettings?: ReceiptSettings;
    fiscalYearSettings?: FiscalYearSettings;
}

const props = withDefaults(defineProps<Props>(), {
    paymentConfig: () => ({}),
    currencyOptions: () => [],
    invoiceSettings: () => ({}),
    reminderSettings: () => ({}),
    receiptSettings: () => ({}),
    fiscalYearSettings: () => ({}),
});

const { t } = useI18n();

const activeSection = ref('currency');

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

const currencyForm = useForm({
    default_currency: props.paymentConfig?.default_currency || 'KES',
});

const monthOptions = [
    { value: 1, label: t('finances_settings.months.january') },
    { value: 2, label: t('finances_settings.months.february') },
    { value: 3, label: t('finances_settings.months.march') },
    { value: 4, label: t('finances_settings.months.april') },
    { value: 5, label: t('finances_settings.months.may') },
    { value: 6, label: t('finances_settings.months.june') },
    { value: 7, label: t('finances_settings.months.july') },
    { value: 8, label: t('finances_settings.months.august') },
    { value: 9, label: t('finances_settings.months.september') },
    { value: 10, label: t('finances_settings.months.october') },
    { value: 11, label: t('finances_settings.months.november') },
    { value: 12, label: t('finances_settings.months.december') },
];

const saveCurrencySettings = () => {
    currencyForm.post(route('finances.settings.default-currency'), {
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

const sections = [
    { id: 'currency', name: t('finances_settings.sections.currency'), icon: CurrencyDollarIcon },
    { id: 'invoice-settings', name: t('finances_settings.sections.invoice_settings'), icon: DocumentTextIcon },
    { id: 'receipt-settings', name: t('finances_settings.sections.receipt_settings'), icon: ReceiptPercentIcon },
    { id: 'fiscal-year', name: t('finances_settings.sections.fiscal_year'), icon: CalendarDaysIcon },
    { id: 'reminders', name: t('finances_settings.sections.reminders'), icon: BellIcon },
];

const navButtonBaseClass = 'w-full flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors';
const fiscalTypeButtonBaseClass = 'flex-1 px-4 py-3 text-sm rounded-lg border-2 transition-colors text-start';
const channelButtonBaseClass = 'px-4 py-2 text-sm rounded-lg border-2 transition-colors';
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
                        navButtonBaseClass,
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
            <!-- Payment Methods & gateway credentials live in Payments Hub -->
            <div class="mb-6 flex items-center gap-4 p-4 bg-indigo-50 rounded-xl border border-indigo-200">
                <CreditCardIcon class="w-6 h-6 text-indigo-600 shrink-0" />
                <p class="flex-1 text-sm text-indigo-800">{{ t('finances_settings.payment_hub_notice.text') }}</p>
                <Link
                    :href="route('payments-hub.collection')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors whitespace-nowrap"
                >
                    {{ t('finances_settings.payment_hub_notice.button') }}
                </Link>
            </div>

            <div v-if="activeSection === 'currency'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.currency.heading') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('finances_settings.currency.description') }}
                    </p>
                    <div>
                        <label for="currency-default" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.currency.label') }}</label>
                        <select
                            id="currency-default"
                            v-model="currencyForm.default_currency"
                            class="w-64 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option v-for="option in currencyOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="saveCurrencySettings"
                        :disabled="currencyForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ currencyForm.processing ? t('finances_settings.saving') : t('finances_settings.currency.save') }}
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
                                <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_settings.invoice.comprehensive_heading') }}</h3>
                                <p class="text-sm text-gray-600 mt-0.5">
                                    {{ t('finances_settings.invoice.comprehensive_description') }}
                                </p>
                            </div>
                        </div>
                        <ArrowTopRightOnSquareIcon class="h-5 w-5 text-indigo-400 group-hover:text-indigo-600 transition-colors" />
                    </div>
                </Link>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.invoice.quick_heading') }}</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.include_water_charges"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.invoice.include_water') }}</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.include_arrears"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.invoice.include_arrears') }}</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="invoiceForm.auto_generate_monthly"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.invoice.auto_generate') }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="saveInvoiceSettings"
                        :disabled="invoiceForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ invoiceForm.processing ? t('finances_settings.saving') : t('finances_settings.invoice.save') }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'receipt-settings'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.receipt.auto_send_heading') }}</h3>
                    <label class="flex items-center gap-3">
                        <input
                            v-model="receiptForm.auto_email_receipt"
                            type="checkbox"
                            class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                        />
                        <div>
                            <span class="text-sm text-gray-700">{{ t('finances_settings.receipt.auto_email') }}</span>
                            <p class="text-xs text-gray-500">{{ t('finances_settings.receipt.auto_email_help') }}</p>
                        </div>
                    </label>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.receipt.content_heading') }}</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_logo"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.receipt.show_logo') }}</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_tenant_details"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.receipt.show_tenant_details') }}</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_invoice_details"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.receipt.show_invoice_details') }}</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="receiptForm.receipt_show_payment_method"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_settings.receipt.show_payment_method') }}</span>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.receipt.custom_text_heading') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="receipt-header-text" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.receipt.header_text') }}</label>
                            <input
                                id="receipt-header-text"
                                v-model="receiptForm.receipt_header_text"
                                type="text"
                                maxlength="255"
                                :placeholder="t('finances_settings.receipt.header_text_placeholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('finances_settings.receipt.header_text_help') }}</p>
                        </div>
                        <div>
                            <label for="receipt-thank-you" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.receipt.thank_you') }}</label>
                            <input
                                id="receipt-thank-you"
                                v-model="receiptForm.receipt_thank_you_message"
                                type="text"
                                maxlength="500"
                                :placeholder="t('finances_settings.receipt.thank_you_placeholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label for="receipt-footer-text" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.receipt.footer_text') }}</label>
                            <textarea
                                id="receipt-footer-text"
                                v-model="receiptForm.receipt_footer_text"
                                rows="3"
                                :placeholder="t('finances_settings.receipt.footer_text_placeholder')"
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
                        {{ t('finances_settings.receipt.preview') }}
                    </button>
                    <button
                        @click="saveReceiptSettings"
                        :disabled="receiptForm.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                    >
                        {{ receiptForm.processing ? t('finances_settings.saving') : t('finances_settings.receipt.save') }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'fiscal-year'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.fiscal_year.heading') }}</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        {{ t('finances_settings.fiscal_year.description') }}
                    </p>
                    <div class="space-y-6">
                        <div>
                            <span id="fiscal-type-group-label" class="block text-xs font-medium text-gray-700 mb-2">{{ t('finances_settings.fiscal_year.type_label') }}</span>
                            <div class="flex gap-4" role="group" aria-labelledby="fiscal-type-group-label">
                                <button
                                    type="button"
                                    @click="fiscalYearForm.fiscal_year_type = 'calendar'"
                                    :class="[
                                        fiscalTypeButtonBaseClass,
                                        fiscalYearForm.fiscal_year_type === 'calendar'
                                            ? 'border-emerald-500 bg-emerald-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <p :class="['font-medium', fiscalYearForm.fiscal_year_type === 'calendar' ? 'text-emerald-900' : 'text-gray-900']">
                                        {{ t('finances_settings.fiscal_year.calendar_title') }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('finances_settings.fiscal_year.calendar_range') }}</p>
                                </button>
                                <button
                                    type="button"
                                    @click="fiscalYearForm.fiscal_year_type = 'custom'"
                                    :class="[
                                        fiscalTypeButtonBaseClass,
                                        fiscalYearForm.fiscal_year_type === 'custom'
                                            ? 'border-emerald-500 bg-emerald-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <p :class="['font-medium', fiscalYearForm.fiscal_year_type === 'custom' ? 'text-emerald-900' : 'text-gray-900']">
                                        {{ t('finances_settings.fiscal_year.custom_title') }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ t('finances_settings.fiscal_year.custom_subtitle') }}</p>
                                </button>
                            </div>
                        </div>

                        <div v-if="fiscalYearForm.fiscal_year_type === 'custom'">
                            <label for="fiscal-start-month" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.fiscal_year.start_month') }}</label>
                            <select
                                id="fiscal-start-month"
                                v-model.number="fiscalYearForm.fiscal_year_start_month"
                                class="w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">
                                {{ t('finances_settings.fiscal_year.range_hint', {
                                    start: monthOptions.find(m => m.value === fiscalYearForm.fiscal_year_start_month)?.label,
                                    end: monthOptions.find(m => m.value === (fiscalYearForm.fiscal_year_start_month === 1 ? 12 : fiscalYearForm.fiscal_year_start_month - 1))?.label,
                                }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl border border-blue-200 p-4">
                    <div class="flex gap-3">
                        <CalendarDaysIcon class="h-5 w-5 text-blue-600 shrink-0 mt-0.5" />
                        <div>
                            <h4 class="text-sm font-medium text-blue-900">{{ t('finances_settings.fiscal_year.info_heading') }}</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                {{ t('finances_settings.fiscal_year.info_body') }}
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
                        {{ fiscalYearForm.processing ? t('finances_settings.saving') : t('finances_settings.fiscal_year.save') }}
                    </button>
                </div>
            </div>

            <div v-if="activeSection === 'reminders'" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('finances_settings.reminders.heading') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="reminder-days-before" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.reminders.days_before') }}</label>
                            <input
                                id="reminder-days-before"
                                v-model.number="reminderForm.reminder_days_before_due"
                                type="number"
                                min="1"
                                max="30"
                                class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label for="reminder-overdue-freq" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_settings.reminders.overdue_frequency') }}</label>
                            <select
                                id="reminder-overdue-freq"
                                v-model="reminderForm.overdue_reminder_frequency"
                                class="w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="daily">{{ t('finances_settings.reminders.frequency_daily') }}</option>
                                <option value="weekly">{{ t('finances_settings.reminders.frequency_weekly') }}</option>
                                <option value="none">{{ t('finances_settings.reminders.frequency_none') }}</option>
                            </select>
                        </div>
                        <div>
                            <span id="reminder-channels-group-label" class="block text-xs font-medium text-gray-700 mb-2">{{ t('finances_settings.reminders.channels_label') }}</span>
                            <div class="flex gap-4" role="group" aria-labelledby="reminder-channels-group-label">
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('email')"
                                    :class="[
                                        channelButtonBaseClass,
                                        isChannelEnabled('email')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    {{ t('finances_settings.reminders.channel_email') }}
                                </button>
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('sms')"
                                    :class="[
                                        channelButtonBaseClass,
                                        isChannelEnabled('sms')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    {{ t('finances_settings.reminders.channel_sms') }}
                                </button>
                                <button
                                    type="button"
                                    @click="toggleReminderChannel('push')"
                                    :class="[
                                        channelButtonBaseClass,
                                        isChannelEnabled('push')
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    {{ t('finances_settings.reminders.channel_push') }}
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
                        {{ reminderForm.processing ? t('finances_settings.saving') : t('finances_settings.reminders.save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
