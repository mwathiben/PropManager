<script setup lang="ts">
import { computed, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import type { ReceiptTemplateEditPageProps } from '@/types/templates';
import {
    ReceiptPercentIcon,
    ArrowLeftIcon,
    SwatchIcon,
    EyeIcon,
    Cog6ToothIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ReceiptTemplateEditPageProps>();

const { formatMoney: formatCurrency } = useFormatters();

const isEditing = computed(() => !!props.template);

const breadcrumbItems = computed(() => [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Templates', href: route('finances.templates') },
    { label: 'Receipts', href: route('finances.templates.receipts') },
    { label: isEditing.value ? props.template.name : 'Create Template' },
]);

const previewState = reactive({
    name: props.template?.name || 'New Receipt Template',
    design: props.template?.design || 'classic',
    is_default: props.template?.is_default || false,
    show_logo: props.template?.show_logo ?? true,
    show_receipt_number: props.template?.show_receipt_number ?? true,
    show_payment_date: props.template?.show_payment_date ?? true,
    show_payment_method: props.template?.show_payment_method ?? true,
    show_transaction_reference: props.template?.show_transaction_reference ?? true,
    show_amount_breakdown: props.template?.show_amount_breakdown ?? false,
    show_tenant_name: props.template?.show_tenant_name ?? true,
    show_tenant_email: props.template?.show_tenant_email ?? true,
    show_tenant_phone: props.template?.show_tenant_phone ?? false,
    show_unit_details: props.template?.show_unit_details ?? true,
    show_building_name: props.template?.show_building_name ?? true,
    show_invoice_details: props.template?.show_invoice_details ?? true,
    show_invoice_breakdown: props.template?.show_invoice_breakdown ?? false,
    show_balance_after_payment: props.template?.show_balance_after_payment ?? true,
    show_thank_you_message: props.template?.show_thank_you_message ?? true,
    show_qr_code: props.template?.show_qr_code ?? false,
    show_footer: props.template?.show_footer ?? true,
    custom_header: props.template?.custom_header || '',
    custom_footer: props.template?.custom_footer || '',
    thank_you_message: props.template?.thank_you_message || 'Thank you for your payment!',
    primary_color: props.template?.primary_color || '#059669',
    secondary_color: props.template?.secondary_color || '#10B981',
});

const form = useForm({
    name: previewState.name,
    design: previewState.design,
    is_default: previewState.is_default,
    show_logo: previewState.show_logo,
    show_receipt_number: previewState.show_receipt_number,
    show_payment_date: previewState.show_payment_date,
    show_payment_method: previewState.show_payment_method,
    show_transaction_reference: previewState.show_transaction_reference,
    show_amount_breakdown: previewState.show_amount_breakdown,
    show_tenant_name: previewState.show_tenant_name,
    show_tenant_email: previewState.show_tenant_email,
    show_tenant_phone: previewState.show_tenant_phone,
    show_unit_details: previewState.show_unit_details,
    show_building_name: previewState.show_building_name,
    show_invoice_details: previewState.show_invoice_details,
    show_invoice_breakdown: previewState.show_invoice_breakdown,
    show_balance_after_payment: previewState.show_balance_after_payment,
    show_thank_you_message: previewState.show_thank_you_message,
    show_qr_code: previewState.show_qr_code,
    show_footer: previewState.show_footer,
    custom_header: previewState.custom_header,
    custom_footer: previewState.custom_footer,
    thank_you_message: previewState.thank_you_message,
    primary_color: previewState.primary_color,
    secondary_color: previewState.secondary_color,
});

const updateField = (key, value) => {
    previewState[key] = value;
    form[key] = value;
};

const toggleField = (key) => {
    const newValue = !previewState[key];
    previewState[key] = newValue;
    form[key] = newValue;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('receipt-templates.update', props.template.id));
    } else {
        form.post(route('receipt-templates.store'));
    }
};

const getLogoUrl = () => {
    if (props.settings?.logo_path) {
        return `/storage/${props.settings.logo_path}`;
    }
    return null;
};

const designStyles = computed(() => {
    const design = previewState.design;

    return {
        container: {
            classic: 'border-2 border-gray-400 rounded-none shadow-md',
            modern: 'rounded-3xl shadow-2xl border-0',
            minimal: 'shadow-none border border-gray-200 rounded-lg',
            professional: 'rounded-none shadow-xl border-t-4 border-t-slate-800 border border-slate-300',
        }[design] || '',

        header: {
            classic: 'border-b-2 border-gray-300 bg-gray-100',
            modern: 'bg-gradient-to-br from-gray-50 via-white to-gray-50',
            minimal: 'bg-white',
            professional: 'bg-slate-800 text-white',
        }[design] || '',

        headerTextColor: {
            classic: 'text-gray-900',
            modern: 'text-gray-800',
            minimal: 'text-gray-600',
            professional: 'text-white font-serif',
        }[design] || 'text-gray-900',

        headerSubTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-500',
            minimal: 'text-gray-400',
            professional: 'text-slate-300',
        }[design] || 'text-gray-600',

        paymentBox: {
            classic: 'border-2 rounded-none shadow-inner',
            modern: 'rounded-2xl shadow-lg',
            minimal: 'rounded-lg border border-gray-100',
            professional: 'rounded-none border-l-4 border-l-slate-800 shadow-md bg-slate-50',
        }[design] || 'rounded-lg',

        sectionBg: {
            classic: 'bg-gray-100 border-y-2 border-gray-200',
            modern: 'bg-gradient-to-r from-gray-50 to-white rounded-xl mx-2 my-1',
            minimal: 'bg-white border-b border-gray-50',
            professional: 'bg-slate-50 border-l-2 border-l-amber-600',
        }[design] || 'bg-gray-50',

        sectionTextColor: {
            classic: 'text-gray-900',
            modern: 'text-gray-800',
            minimal: 'text-gray-700',
            professional: 'text-slate-800',
        }[design] || 'text-gray-900',

        sectionSubTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-500',
            minimal: 'text-gray-500',
            professional: 'text-slate-600',
        }[design] || 'text-gray-600',

        sectionTitle: {
            classic: 'text-sm font-bold uppercase tracking-wide border-b-2 border-gray-300 pb-1 text-gray-700',
            modern: 'text-xs font-medium uppercase text-gray-400 tracking-wider',
            minimal: 'text-xs text-gray-400 font-light',
            professional: 'text-xs font-serif font-semibold uppercase tracking-wide text-slate-800',
        }[design] || 'text-xs font-medium text-gray-500 uppercase',

        thankYouStyle: {
            classic: 'border-t-2 border-b-2 border-gray-300 py-4 bg-gray-50',
            modern: 'rounded-2xl py-5 mx-4 my-2',
            minimal: 'py-3',
            professional: 'py-4 bg-slate-50 border-y border-slate-200',
        }[design] || '',

        footer: {
            classic: 'border-t-2 border-gray-300 bg-gray-100',
            modern: 'bg-gradient-to-r from-gray-50 to-white rounded-b-3xl',
            minimal: 'border-t border-gray-100',
            professional: 'bg-slate-800 text-white',
        }[design] || 'border-t border-gray-200',

        footerTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-500',
            minimal: 'text-gray-400',
            professional: 'text-slate-300',
        }[design] || 'text-gray-500',
    };
});

const toggleGroups = props.toggleGroups || [
    {
        title: 'Header Elements',
        toggles: [
            { key: 'show_logo', label: 'Show Logo' },
            { key: 'show_receipt_number', label: 'Show Receipt Number' },
            { key: 'show_payment_date', label: 'Show Payment Date' },
        ],
    },
    {
        title: 'Payment Information',
        toggles: [
            { key: 'show_payment_method', label: 'Show Payment Method' },
            { key: 'show_transaction_reference', label: 'Show Transaction Reference' },
            { key: 'show_amount_breakdown', label: 'Show Amount Breakdown' },
        ],
    },
    {
        title: 'Tenant Information',
        toggles: [
            { key: 'show_tenant_name', label: 'Show Tenant Name' },
            { key: 'show_tenant_email', label: 'Show Tenant Email' },
            { key: 'show_tenant_phone', label: 'Show Tenant Phone' },
        ],
    },
    {
        title: 'Property Information',
        toggles: [
            { key: 'show_unit_details', label: 'Show Unit Details' },
            { key: 'show_building_name', label: 'Show Building Name' },
        ],
    },
    {
        title: 'Invoice Information',
        toggles: [
            { key: 'show_invoice_details', label: 'Show Invoice Details' },
            { key: 'show_invoice_breakdown', label: 'Show Invoice Breakdown' },
            { key: 'show_balance_after_payment', label: 'Show Balance After Payment' },
        ],
    },
    {
        title: 'Footer Elements',
        toggles: [
            { key: 'show_thank_you_message', label: 'Show Thank You Message' },
            { key: 'show_qr_code', label: 'Show QR Code' },
            { key: 'show_footer', label: 'Show Footer Note' },
        ],
    },
];
</script>

<template>
    <Head :title="isEditing ? 'Edit Receipt Template' : 'Create Receipt Template'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('finances.templates.receipts')"
                    class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <ReceiptPercentIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Receipt Template' : 'Create Receipt Template' }}
                    </h1>
                    <p class="text-sm text-gray-500">Customize your payment receipt appearance with live preview</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Settings Panel -->
                        <div class="space-y-6">
                            <!-- Basic Info -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <div class="flex items-center gap-2">
                                        <Cog6ToothIcon class="w-5 h-5 text-gray-500" />
                                        <h2 class="text-base font-medium text-gray-900">Template Settings</h2>
                                    </div>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                                        <input
                                            :value="previewState.name"
                                            @input="updateField('name', $event.target.value)"
                                            type="text"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            placeholder="e.g., Standard Receipt"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Design Style</label>
                                        <select
                                            :value="previewState.design"
                                            @change="updateField('design', $event.target.value)"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option v-for="(label, value) in designOptions" :key="value" :value="value">
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <input
                                            :checked="previewState.is_default"
                                            @change="updateField('is_default', $event.target.checked)"
                                            type="checkbox"
                                            id="is_default"
                                            class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                        />
                                        <label for="is_default" class="text-sm text-gray-700">Set as default template</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Colors -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <div class="flex items-center gap-2">
                                        <SwatchIcon class="w-5 h-5 text-gray-500" />
                                        <h2 class="text-base font-medium text-gray-900">Colors</h2>
                                    </div>
                                </div>
                                <div class="p-6">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    :value="previewState.primary_color"
                                                    @input="updateField('primary_color', $event.target.value)"
                                                    type="color"
                                                    class="w-10 h-10 rounded border border-gray-300 cursor-pointer"
                                                />
                                                <input
                                                    :value="previewState.primary_color"
                                                    @input="updateField('primary_color', $event.target.value)"
                                                    type="text"
                                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                                    placeholder="#059669"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Color</label>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    :value="previewState.secondary_color"
                                                    @input="updateField('secondary_color', $event.target.value)"
                                                    type="color"
                                                    class="w-10 h-10 rounded border border-gray-300 cursor-pointer"
                                                />
                                                <input
                                                    :value="previewState.secondary_color"
                                                    @input="updateField('secondary_color', $event.target.value)"
                                                    type="text"
                                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                                    placeholder="#10B981"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Toggle Groups -->
                            <div
                                v-for="group in toggleGroups"
                                :key="group.title"
                                class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                            >
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <h2 class="text-base font-medium text-gray-900">{{ group.title }}</h2>
                                </div>
                                <div class="p-6 space-y-3">
                                    <div v-for="toggle in group.toggles" :key="toggle.key" class="flex items-center justify-between">
                                        <label :for="toggle.key" class="text-sm text-gray-700">{{ toggle.label }}</label>
                                        <button
                                            type="button"
                                            @click="toggleField(toggle.key)"
                                            :class="[
                                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                                                previewState[toggle.key] ? 'bg-emerald-600' : 'bg-gray-200'
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                    previewState[toggle.key] ? 'translate-x-5' : 'translate-x-0'
                                                ]"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Content -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <h2 class="text-base font-medium text-gray-900">Custom Content</h2>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Header</label>
                                        <textarea
                                            :value="previewState.custom_header"
                                            @input="updateField('custom_header', $event.target.value)"
                                            rows="2"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                            placeholder="Additional text to display in the header..."
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Thank You Message</label>
                                        <textarea
                                            :value="previewState.thank_you_message"
                                            @input="updateField('thank_you_message', $event.target.value)"
                                            rows="2"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                            placeholder="Thank you for your payment!"
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Footer</label>
                                        <textarea
                                            :value="previewState.custom_footer"
                                            @input="updateField('custom_footer', $event.target.value)"
                                            rows="2"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                            placeholder="Additional text to display in the footer..."
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center gap-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 px-6 py-2.5 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing">Saving...</span>
                                    <span v-else>{{ isEditing ? 'Update Template' : 'Create Template' }}</span>
                                </button>
                                <Link
                                    :href="route('finances.templates.receipts')"
                                    class="px-6 py-2.5 bg-white text-gray-700 font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </div>

                        <!-- Live Preview Panel -->
                        <div class="lg:sticky lg:top-8 h-fit">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <div class="flex items-center gap-2">
                                        <EyeIcon class="w-5 h-5 text-gray-500" />
                                        <h2 class="text-base font-medium text-gray-900">Live Preview</h2>
                                    </div>
                                </div>
                                <div class="p-4 bg-gray-100">
                                    <!-- Receipt Preview -->
                                    <div
                                        :class="['bg-white overflow-hidden transform scale-[0.85] origin-top transition-all duration-300', designStyles.container]"
                                        :style="previewState.design === 'professional' ? { borderLeftColor: previewState.primary_color } : {}"
                                    >
                                        <!-- Header -->
                                        <div
                                            :class="['p-6', designStyles.header]"
                                            :style="previewState.design !== 'professional' ? { borderTop: `4px solid ${previewState.primary_color}` } : {}"
                                        >
                                            <div :class="previewState.design === 'minimal' ? 'text-center' : 'flex justify-between items-start'">
                                                <div :class="previewState.design === 'minimal' ? 'mb-4' : ''">
                                                    <div v-if="previewState.show_logo && getLogoUrl()" :class="previewState.design === 'minimal' ? 'mb-4 flex justify-center' : 'mb-3'">
                                                        <img :src="getLogoUrl()" alt="Logo" class="h-12 object-contain" />
                                                    </div>
                                                    <div v-else-if="previewState.show_logo" :class="['w-24 h-12 rounded flex items-center justify-center text-xs', previewState.design === 'professional' ? 'bg-stone-200 text-stone-500 border border-stone-300' : 'bg-gray-200 text-gray-400', previewState.design === 'minimal' ? 'mx-auto mb-4' : 'mb-3']">
                                                        Logo
                                                    </div>
                                                    <h3 :class="['text-lg', designStyles.headerTextColor]">{{ sampleReceipt.business.name }}</h3>
                                                    <p :class="['text-sm mt-1', designStyles.headerSubTextColor]">{{ sampleReceipt.business.address }}</p>
                                                    <p :class="['text-sm', designStyles.headerSubTextColor]">{{ sampleReceipt.business.phone }}</p>
                                                </div>
                                                <div :class="previewState.design === 'minimal' ? 'mt-4' : 'text-right'">
                                                    <h2
                                                        :class="previewState.design === 'professional' ? 'text-2xl font-bold tracking-wide' : 'text-2xl font-bold'"
                                                        :style="{ color: previewState.primary_color }"
                                                    >
                                                        RECEIPT
                                                    </h2>
                                                    <p v-if="previewState.show_receipt_number" :class="['text-sm mt-1', designStyles.headerSubTextColor]">#{{ sampleReceipt.receipt_number }}</p>
                                                    <p v-if="previewState.show_payment_date" :class="['text-sm', designStyles.headerSubTextColor]">{{ sampleReceipt.payment_date }}</p>
                                                    <p v-if="previewState.show_payment_date" :class="['text-sm', designStyles.headerSubTextColor, 'opacity-70']">{{ sampleReceipt.payment_time }}</p>
                                                </div>
                                            </div>
                                            <p v-if="previewState.custom_header" :class="['mt-4 text-sm italic', designStyles.headerSubTextColor]">{{ previewState.custom_header }}</p>
                                        </div>

                                        <!-- Payment Confirmation -->
                                        <div
                                            :class="['mx-6 p-4', designStyles.paymentBox]"
                                            :style="{
                                                backgroundColor: `${previewState.secondary_color}15`,
                                                borderColor: previewState.design === 'professional' ? previewState.primary_color : previewState.design === 'classic' ? previewState.secondary_color : 'transparent'
                                            }"
                                        >
                                            <div :class="previewState.design === 'minimal' ? 'text-center' : 'flex items-center gap-3'">
                                                <CheckCircleIcon v-if="previewState.design !== 'minimal'" class="w-8 h-8" :style="{ color: previewState.primary_color }" />
                                                <div>
                                                    <p class="text-sm font-medium text-gray-600">Payment Received</p>
                                                    <p :class="previewState.design === 'minimal' ? 'text-3xl font-light' : 'text-2xl font-bold'" :style="{ color: previewState.primary_color }">
                                                        {{ formatCurrency(sampleReceipt.payment.amount) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Details -->
                                        <div v-if="previewState.show_payment_method || previewState.show_transaction_reference" class="px-6 py-4">
                                            <p :class="['mb-2', designStyles.sectionTitle]">Payment Details</p>
                                            <div :class="previewState.design === 'minimal' ? 'space-y-2 text-center' : 'grid grid-cols-2 gap-4 text-sm'">
                                                <div v-if="previewState.show_payment_method">
                                                    <p class="text-gray-500">Method</p>
                                                    <p class="font-medium text-gray-900">{{ sampleReceipt.payment_method }}</p>
                                                </div>
                                                <div v-if="previewState.show_transaction_reference">
                                                    <p class="text-gray-500">Reference</p>
                                                    <p class="font-medium text-gray-900">{{ sampleReceipt.transaction_reference }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tenant & Property -->
                                        <div
                                            :class="['px-6 py-4', designStyles.sectionBg]"
                                            :style="previewState.design === 'professional' ? { borderLeftColor: previewState.primary_color } : {}"
                                        >
                                            <div :class="previewState.design === 'minimal' ? 'space-y-4 text-center' : 'grid grid-cols-2 gap-6'">
                                                <div v-if="previewState.show_tenant_name || previewState.show_tenant_email || previewState.show_tenant_phone">
                                                    <p :class="['mb-1', designStyles.sectionTitle]">Received From</p>
                                                    <p v-if="previewState.show_tenant_name" :class="['font-medium', designStyles.sectionTextColor]">{{ sampleReceipt.tenant.name }}</p>
                                                    <p v-if="previewState.show_tenant_email" :class="['text-sm', designStyles.sectionSubTextColor]">{{ sampleReceipt.tenant.email }}</p>
                                                    <p v-if="previewState.show_tenant_phone" :class="['text-sm', designStyles.sectionSubTextColor]">{{ sampleReceipt.tenant.phone }}</p>
                                                </div>
                                                <div v-if="previewState.show_unit_details || previewState.show_building_name">
                                                    <p :class="['mb-1', designStyles.sectionTitle]">Property</p>
                                                    <p v-if="previewState.show_unit_details" :class="['font-medium', designStyles.sectionTextColor]">{{ sampleReceipt.unit.name }}</p>
                                                    <p v-if="previewState.show_building_name" :class="['text-sm', designStyles.sectionSubTextColor]">{{ sampleReceipt.unit.building }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Invoice Details -->
                                        <div v-if="previewState.show_invoice_details" class="px-6 py-4">
                                            <p :class="['mb-2', designStyles.sectionTitle]">For Invoice</p>
                                            <div class="flex justify-between items-center text-sm">
                                                <span class="text-gray-600">Invoice #{{ sampleReceipt.invoice.number }}</span>
                                                <span class="font-medium text-gray-900">{{ formatCurrency(sampleReceipt.invoice.total_amount) }}</span>
                                            </div>

                                            <!-- Invoice Breakdown -->
                                            <div v-if="previewState.show_invoice_breakdown" :class="['mt-3 pt-3', previewState.design === 'classic' ? 'border-t-2 border-gray-200' : 'border-t border-gray-100']">
                                                <div v-for="item in sampleReceipt.invoice.items" :key="item.description" class="flex justify-between text-sm text-gray-600 py-1">
                                                    <span>{{ item.description }}</span>
                                                    <span>{{ formatCurrency(item.amount) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Amount Breakdown -->
                                        <div v-if="previewState.show_amount_breakdown || previewState.show_balance_after_payment" :class="['px-6 py-4', previewState.design === 'classic' ? 'border-t-2 border-gray-200' : 'border-t border-gray-100']">
                                            <table class="w-full text-sm">
                                                <tbody>
                                                    <tr v-if="previewState.show_amount_breakdown">
                                                        <td class="py-1 text-gray-600">Previous Balance</td>
                                                        <td class="py-1 text-right text-gray-900">{{ formatCurrency(sampleReceipt.payment.previous_balance) }}</td>
                                                    </tr>
                                                    <tr v-if="previewState.show_amount_breakdown">
                                                        <td class="py-1 text-gray-600">Amount Paid</td>
                                                        <td class="py-1 text-right font-medium" :style="{ color: previewState.primary_color }">-{{ formatCurrency(sampleReceipt.payment.amount) }}</td>
                                                    </tr>
                                                    <tr v-if="previewState.show_balance_after_payment" :class="previewState.design === 'professional' ? 'font-bold text-lg' : 'font-medium'">
                                                        <td class="py-2 text-gray-900">Balance Due</td>
                                                        <td class="py-2 text-right text-gray-900">{{ formatCurrency(sampleReceipt.payment.new_balance) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- QR Code -->
                                        <div v-if="previewState.show_qr_code" class="px-6 py-4 flex justify-center">
                                            <div v-if="sampleReceipt.qr_code" :class="['p-2', previewState.design === 'modern' ? 'bg-gray-50 rounded-xl' : previewState.design === 'professional' ? 'bg-stone-50 border border-stone-200' : 'bg-white rounded']">
                                                <img :src="sampleReceipt.qr_code" alt="QR Code" class="w-24 h-24" />
                                            </div>
                                            <div v-else :class="['w-24 h-24 flex items-center justify-center text-xs text-gray-400', previewState.design === 'modern' ? 'bg-gray-100 rounded-xl' : previewState.design === 'professional' ? 'bg-stone-100 text-stone-500 border border-stone-300' : 'bg-gray-200 rounded']">
                                                QR Code
                                            </div>
                                        </div>

                                        <!-- Thank You Message -->
                                        <div
                                            v-if="previewState.show_thank_you_message && previewState.thank_you_message"
                                            :class="['px-6 text-center', designStyles.thankYouStyle]"
                                            :style="{ backgroundColor: `${previewState.secondary_color}10`, borderLeftColor: previewState.design === 'professional' ? previewState.primary_color : 'transparent', borderColor: previewState.design === 'classic' ? previewState.secondary_color : 'transparent' }"
                                        >
                                            <p :class="previewState.design === 'professional' ? 'text-base font-semibold' : 'text-sm font-medium'" :style="{ color: previewState.primary_color }">{{ previewState.thank_you_message }}</p>
                                        </div>

                                        <!-- Footer -->
                                        <div v-if="previewState.show_footer || previewState.custom_footer" :class="['px-6 py-4', designStyles.footer]">
                                            <p v-if="previewState.custom_footer" :class="['text-sm text-center', designStyles.footerTextColor]">{{ previewState.custom_footer }}</p>
                                            <p v-if="previewState.show_footer" :class="['text-xs text-center mt-2', designStyles.footerTextColor, 'opacity-70']">This is an official receipt for payment received.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
