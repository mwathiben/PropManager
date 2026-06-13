<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import {
    BellIcon,
    DocumentTextIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

interface Preferences {
    default_payment_terms_days: number;
    auto_send_invoices: boolean;
    invoice_footer: string;
}

interface InvoiceSettings {
    include_water_charges: boolean;
    include_arrears: boolean;
    auto_generate_monthly: boolean;
}

interface ReminderSettings {
    reminder_days_before_due: number;
    overdue_reminder_frequency: string;
    reminder_channels: string[];
}

interface Props {
    preferences?: Preferences;
    invoiceSettings?: InvoiceSettings;
    reminderSettings?: ReminderSettings;
}

const props = withDefaults(defineProps<Props>(), {});

const { t } = useI18n();

const statusBadgeBase = 'inline-block px-2.5 py-0.5 rounded-full text-xs font-medium';

const prefsForm = useForm({
    default_payment_terms_days: props.preferences?.default_payment_terms_days ?? 7,
    auto_send_invoices: props.preferences?.auto_send_invoices ?? true,
    invoice_footer: props.preferences?.invoice_footer ?? '',
    reminder_days_before_due: props.reminderSettings?.reminder_days_before_due ?? 3,
    overdue_reminder_frequency: props.reminderSettings?.overdue_reminder_frequency ?? 'weekly',
});

const submitPreferences = () => {
    prefsForm.post(route('payments-hub.preferences.update'));
};

const frequencyOptions = [
    { value: 'none', label: 'Never' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'daily', label: 'Daily' },
];
</script>

<template>
    <div class="max-w-2xl space-y-8">
        <!-- Payment preferences -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                <Cog6ToothIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.settings.prefs_title') }}</h2>
            </div>

            <form class="p-6 space-y-5" @submit.prevent="submitPreferences">
                <!-- Payment terms -->
                <div>
                    <label for="payment_terms_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('payments_hub.settings.payment_terms_label') }}
                    </label>
                    <input
                        id="payment_terms_days"
                        v-model.number="prefsForm.default_payment_terms_days"
                        type="number"
                        min="1"
                        max="90"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('payments_hub.settings.payment_terms_help') }}
                    </p>
                    <p v-if="prefsForm.errors.default_payment_terms_days" class="mt-1 text-xs text-red-600">
                        {{ prefsForm.errors.default_payment_terms_days }}
                    </p>
                </div>

                <!-- Auto-send invoices -->
                <div class="flex items-start gap-3">
                    <input
                        id="auto_send_invoices"
                        v-model="prefsForm.auto_send_invoices"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <label for="auto_send_invoices" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                            {{ t('payments_hub.settings.auto_send_label') }}
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ t('payments_hub.settings.auto_send_help') }}
                        </p>
                    </div>
                </div>

                <!-- Invoice footer -->
                <div>
                    <label for="invoice_footer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('payments_hub.settings.footer_note_label') }}
                    </label>
                    <textarea
                        id="invoice_footer"
                        v-model="prefsForm.invoice_footer"
                        rows="3"
                        maxlength="500"
                        :placeholder="t('payments_hub.settings.footer_note_placeholder')"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                    />
                    <p v-if="prefsForm.errors.invoice_footer" class="mt-1 text-xs text-red-600">
                        {{ prefsForm.errors.invoice_footer }}
                    </p>
                </div>

                <hr class="border-gray-200 dark:border-gray-700" />

                <!-- Reminders section -->
                <div class="flex items-center gap-3 pb-1">
                    <BellIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.settings.reminders_title') }}</h3>
                </div>

                <div>
                    <label for="reminder_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('payments_hub.settings.reminder_days_label') }}
                    </label>
                    <input
                        id="reminder_days"
                        v-model.number="prefsForm.reminder_days_before_due"
                        type="number"
                        min="1"
                        max="30"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p v-if="prefsForm.errors.reminder_days_before_due" class="mt-1 text-xs text-red-600">
                        {{ prefsForm.errors.reminder_days_before_due }}
                    </p>
                </div>

                <div>
                    <label for="overdue_freq" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('payments_hub.settings.overdue_freq_label') }}
                    </label>
                    <select
                        id="overdue_freq"
                        v-model="prefsForm.overdue_reminder_frequency"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option v-for="opt in frequencyOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="prefsForm.errors.overdue_reminder_frequency" class="mt-1 text-xs text-red-600">
                        {{ prefsForm.errors.overdue_reminder_frequency }}
                    </p>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="prefsForm.processing"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ prefsForm.processing ? t('payments_hub.settings.saving') : t('payments_hub.settings.save_settings') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Invoice settings (read-only display) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                <DocumentTextIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.settings.invoice_gen_title') }}</h2>
            </div>
            <div class="p-6 space-y-4">
                <div v-if="invoiceSettings" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('payments_hub.settings.include_water_label') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.settings.include_water_help') }}</p>
                        </div>
                        <span
                            :class="[
                                statusBadgeBase,
                                invoiceSettings.include_water_charges
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                            ]"
                        >
                            {{ invoiceSettings.include_water_charges ? t('payments_hub.settings.enabled') : t('payments_hub.settings.disabled') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('payments_hub.settings.rollover_arrears_label') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.settings.rollover_arrears_help') }}</p>
                        </div>
                        <span
                            :class="[
                                statusBadgeBase,
                                invoiceSettings.include_arrears
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                            ]"
                        >
                            {{ invoiceSettings.include_arrears ? t('payments_hub.settings.enabled') : t('payments_hub.settings.disabled') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('payments_hub.settings.auto_generate_label') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('payments_hub.settings.auto_generate_help') }}</p>
                        </div>
                        <span
                            :class="[
                                statusBadgeBase,
                                invoiceSettings.auto_generate_monthly
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                            ]"
                        >
                            {{ invoiceSettings.auto_generate_monthly ? t('payments_hub.settings.enabled') : t('payments_hub.settings.disabled') }}
                        </span>
                    </div>
                </div>

                <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('payments_hub.settings.not_configured') }}
                </div>
            </div>
        </div>
    </div>
</template>
