<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { InvoiceSettingsEditPageProps } from '@/types/templates';
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

const props = defineProps<InvoiceSettingsEditPageProps>();

const { t } = useI18n();
const { currencySymbol } = useCurrency();

const activeSection = ref('business');

const sections = [
    { id: 'business', name: t('invoice_settings_edit.sections.business'), icon: BuildingOfficeIcon },
    { id: 'bank', name: t('invoice_settings_edit.sections.bank'), icon: BanknotesIcon },
    { id: 'numbering', name: t('invoice_settings_edit.sections.numbering'), icon: DocumentDuplicateIcon },
    { id: 'terms', name: t('invoice_settings_edit.sections.terms'), icon: ClockIcon },
    { id: 'conditions', name: t('invoice_settings_edit.sections.conditions'), icon: DocumentTextIcon },
    { id: 'first-invoice', name: t('invoice_settings_edit.sections.first_invoice'), icon: UserPlusIcon },
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
    if (confirm(t('invoice_settings_edit.confirm_remove_logo'))) {
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
    <Head :title="t('invoice_settings_edit.page_title')" />

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
                        <h1 class="text-lg font-semibold text-gray-900">{{ t('invoice_settings_edit.header.title') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('invoice_settings_edit.header.subtitle') }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="flex">
                        <nav class="w-64 border-r border-gray-200 bg-gray-50 shrink-0">
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
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.business.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.business.subheading') }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('invoice_settings_edit.business.logo_label') }}</label>
                                            <div class="flex items-center gap-4">
                                                <div class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden bg-gray-50">
                                                    <img v-if="getLogoUrl()" :src="getLogoUrl()" :alt="t('invoice_settings_edit.business.logo_alt')" loading="lazy" decoding="async" class="w-full h-full object-contain" />
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
                                                        {{ t('invoice_settings_edit.business.upload_logo') }}
                                                    </label>
                                                    <button
                                                        v-if="getLogoUrl()"
                                                        type="button"
                                                        @click="removeLogo"
                                                        class="inline-flex items-center text-sm text-red-600 hover:text-red-700"
                                                    >
                                                        <TrashIcon class="w-4 h-4 me-1" />
                                                        {{ t('invoice_settings_edit.business.remove') }}
                                                    </button>
                                                    <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.business.logo_hint') }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.business.business_name') }}</label>
                                                <input
                                                    v-model="form.business_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.business.business_name_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.business.tax_number') }}</label>
                                                <input
                                                    v-model="form.tax_number"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.business.tax_number_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.business.phone') }}</label>
                                                <input
                                                    v-model="form.business_phone"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.business.phone_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.business.email') }}</label>
                                                <input
                                                    v-model="form.business_email"
                                                    type="email"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.business.email_placeholder')"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.business.address') }}</label>
                                            <textarea
                                                v-model="form.business_address"
                                                rows="3"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                :placeholder="t('invoice_settings_edit.business.address_placeholder')"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'bank'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.bank.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.bank.subheading') }}</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.bank.bank_name') }}</label>
                                                <input
                                                    v-model="form.bank_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.bank.bank_name_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.bank.account_name') }}</label>
                                                <input
                                                    v-model="form.bank_account_name"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.bank.account_name_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.bank.account_number') }}</label>
                                                <input
                                                    v-model="form.bank_account_number"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.bank.account_number_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.bank.branch') }}</label>
                                                <input
                                                    v-model="form.bank_branch"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.bank.branch_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.bank.swift_code') }}</label>
                                                <input
                                                    v-model="form.bank_swift_code"
                                                    type="text"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    :placeholder="t('invoice_settings_edit.bank.swift_code_placeholder')"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'numbering'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.numbering.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.numbering.subheading') }}</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">{{ t('invoice_settings_edit.numbering.invoices') }}</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.prefix') }}</label>
                                                    <input
                                                        v-model="form.invoice_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="INV"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.next_number') }}</label>
                                                    <input
                                                        v-model="form.invoice_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.numbering.preview', { sample: `${form.invoice_prefix}-202601-${String(form.invoice_next_number).padStart(4, '0')}` }) }}</p>
                                            </div>

                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">{{ t('invoice_settings_edit.numbering.receipts') }}</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.prefix') }}</label>
                                                    <input
                                                        v-model="form.receipt_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="RCT"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.next_number') }}</label>
                                                    <input
                                                        v-model="form.receipt_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.numbering.preview', { sample: `${form.receipt_prefix}-202601-${String(form.receipt_next_number).padStart(4, '0')}` }) }}</p>
                                            </div>

                                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
                                                <h3 class="text-sm font-semibold text-gray-900">{{ t('invoice_settings_edit.numbering.credit_notes') }}</h3>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.prefix') }}</label>
                                                    <input
                                                        v-model="form.credit_note_prefix"
                                                        type="text"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="CN"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-sm text-gray-600 mb-1">{{ t('invoice_settings_edit.numbering.next_number') }}</label>
                                                    <input
                                                        v-model="form.credit_note_next_number"
                                                        type="number"
                                                        min="1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                </div>
                                                <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.numbering.preview', { sample: `${form.credit_note_prefix}-202601-${String(form.credit_note_next_number).padStart(4, '0')}` }) }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'terms'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.terms.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.terms.subheading') }}</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.terms.due_days') }}</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.default_due_days"
                                                        type="number"
                                                        min="1"
                                                        max="90"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">{{ t('invoice_settings_edit.terms.days_unit') }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.terms.due_days_hint') }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.terms.late_penalty') }}</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.late_penalty_percentage"
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.1"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">%</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.terms.late_penalty_hint') }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.terms.grace_period') }}</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.grace_period_days"
                                                        type="number"
                                                        min="0"
                                                        max="30"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">{{ t('invoice_settings_edit.terms.days_unit') }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.terms.grace_period_hint') }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'conditions'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.conditions.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.conditions.subheading') }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.conditions.terms_label') }}</label>
                                            <textarea
                                                v-model="form.terms_and_conditions"
                                                rows="6"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                :placeholder="t('invoice_settings_edit.conditions.terms_placeholder')"
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.conditions.footer_label') }}</label>
                                            <textarea
                                                v-model="form.footer_note"
                                                rows="3"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                :placeholder="t('invoice_settings_edit.conditions.footer_placeholder')"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div v-if="activeSection === 'first-invoice'" class="space-y-6">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">{{ t('invoice_settings_edit.first_invoice.heading') }}</h2>
                                            <p class="mt-1 text-sm text-gray-500">{{ t('invoice_settings_edit.first_invoice.subheading') }}</p>
                                        </div>

                                        <div class="space-y-4">
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.prorate_first_month"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">{{ t('invoice_settings_edit.first_invoice.prorate_label') }}</span>
                                                    <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.prorate_hint') }}</p>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.include_last_month_rent"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">{{ t('invoice_settings_edit.first_invoice.include_last_month_label') }}</span>
                                                    <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.include_last_month_hint') }}</p>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                                <input
                                                    v-model="form.auto_generate_first_invoice"
                                                    type="checkbox"
                                                    class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">{{ t('invoice_settings_edit.first_invoice.auto_generate_label') }}</span>
                                                    <p class="text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.auto_generate_hint') }}</p>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-gray-200">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.first_invoice.admin_fee') }}</label>
                                                <div class="relative">
                                                    <span class="absolute start-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">{{ currencySymbol }}</span>
                                                    <input
                                                        v-model="form.admin_fee_amount"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-full ps-14 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.admin_fee_hint') }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.first_invoice.key_deposit') }}</label>
                                                <div class="relative">
                                                    <span class="absolute start-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">{{ currencySymbol }}</span>
                                                    <input
                                                        v-model="form.key_deposit_amount"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-full ps-14 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.key_deposit_hint') }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('invoice_settings_edit.first_invoice.due_days_after_movein') }}</label>
                                                <div class="relative">
                                                    <input
                                                        v-model="form.first_invoice_due_days"
                                                        type="number"
                                                        min="0"
                                                        max="30"
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    />
                                                    <span class="absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">{{ t('invoice_settings_edit.terms.days_unit') }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ t('invoice_settings_edit.first_invoice.due_days_after_movein_hint') }}</p>
                                            </div>
                                        </div>

                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <p class="text-sm text-blue-800">
                                                <strong>{{ t('invoice_settings_edit.first_invoice.note_label') }}</strong> {{ t('invoice_settings_edit.first_invoice.note_body') }}
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
                                        <span v-if="form.processing">{{ t('invoice_settings_edit.actions.saving') }}</span>
                                        <span v-else>{{ t('invoice_settings_edit.actions.save') }}</span>
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
