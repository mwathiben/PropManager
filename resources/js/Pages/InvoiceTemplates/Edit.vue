<script setup>
import { computed, ref, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    DocumentDuplicateIcon,
    ArrowLeftIcon,
    SwatchIcon,
    EyeIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    template: Object,
    designOptions: Object,
    settings: Object,
    sampleInvoice: Object,
});

const isEditing = computed(() => !!props.template);

const breadcrumbItems = computed(() => [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Templates', href: route('finances.templates') },
    { label: 'Invoices', href: route('finances.templates.invoices') },
    { label: isEditing.value ? props.template.name : 'Create Template' },
]);

// Use reactive object for preview state to ensure real-time updates
const previewState = reactive({
    name: props.template?.name || 'New Template',
    design: props.template?.design || 'classic',
    is_default: props.template?.is_default || false,
    show_logo: props.template?.show_logo ?? true,
    show_tax_number: props.template?.show_tax_number ?? true,
    show_tenant_id: props.template?.show_tenant_id ?? false,
    show_unit_details: props.template?.show_unit_details ?? true,
    show_lease_reference: props.template?.show_lease_reference ?? true,
    show_due_date: props.template?.show_due_date ?? true,
    show_late_warning: props.template?.show_late_warning ?? true,
    show_bank_details: props.template?.show_bank_details ?? true,
    show_footer: props.template?.show_footer ?? true,
    show_qr_code: props.template?.show_qr_code ?? false,
    show_payment_instructions: props.template?.show_payment_instructions ?? true,
    show_arrears_breakdown: props.template?.show_arrears_breakdown ?? true,
    show_water_details: props.template?.show_water_details ?? true,
    custom_header: props.template?.custom_header || '',
    custom_footer: props.template?.custom_footer || '',
    primary_color: props.template?.primary_color || '#4F46E5',
    secondary_color: props.template?.secondary_color || '#6366F1',
});

// Inertia form for submission
const form = useForm({
    name: previewState.name,
    design: previewState.design,
    is_default: previewState.is_default,
    show_logo: previewState.show_logo,
    show_tax_number: previewState.show_tax_number,
    show_tenant_id: previewState.show_tenant_id,
    show_unit_details: previewState.show_unit_details,
    show_lease_reference: previewState.show_lease_reference,
    show_due_date: previewState.show_due_date,
    show_late_warning: previewState.show_late_warning,
    show_bank_details: previewState.show_bank_details,
    show_footer: previewState.show_footer,
    show_qr_code: previewState.show_qr_code,
    show_payment_instructions: previewState.show_payment_instructions,
    show_arrears_breakdown: previewState.show_arrears_breakdown,
    show_water_details: previewState.show_water_details,
    custom_header: previewState.custom_header,
    custom_footer: previewState.custom_footer,
    primary_color: previewState.primary_color,
    secondary_color: previewState.secondary_color,
});

// Helper to update both preview and form state
const updateField = (key, value) => {
    previewState[key] = value;
    form[key] = value;
};

// Toggle helper for boolean fields
const toggleField = (key) => {
    const newValue = !previewState[key];
    previewState[key] = newValue;
    form[key] = newValue;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('invoice-templates.update', props.template.id));
    } else {
        form.post(route('invoice-templates.store'));
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
    }).format(amount);
};

const getLogoUrl = () => {
    if (props.settings?.logo_path) {
        return `/storage/${props.settings.logo_path}`;
    }
    return null;
};

const toggleGroups = [
    {
        title: 'Header Elements',
        toggles: [
            { key: 'show_logo', label: 'Business Logo' },
            { key: 'show_tax_number', label: 'Tax/VAT Number' },
        ],
    },
    {
        title: 'Tenant Information',
        toggles: [
            { key: 'show_tenant_id', label: 'National ID' },
            { key: 'show_unit_details', label: 'Unit Details' },
            { key: 'show_lease_reference', label: 'Lease Reference' },
        ],
    },
    {
        title: 'Invoice Details',
        toggles: [
            { key: 'show_due_date', label: 'Due Date' },
            { key: 'show_late_warning', label: 'Late Payment Warning' },
            { key: 'show_arrears_breakdown', label: 'Arrears Breakdown' },
            { key: 'show_water_details', label: 'Water Details' },
        ],
    },
    {
        title: 'Footer Elements',
        toggles: [
            { key: 'show_bank_details', label: 'Bank Details' },
            { key: 'show_payment_instructions', label: 'Payment Instructions' },
            { key: 'show_qr_code', label: 'QR Code' },
            { key: 'show_footer', label: 'Footer Note' },
        ],
    },
];

const designStyles = computed(() => {
    const design = previewState.design;
    return {
        container: {
            classic: 'border-2 border-gray-300 rounded-none shadow-md',
            modern: 'rounded-2xl shadow-2xl border-0',
            minimal: 'shadow-sm border border-gray-100 rounded-lg',
            professional: 'rounded-none shadow-xl border-t-4 border-t-slate-800 border border-slate-300',
        }[design] || 'rounded-lg shadow-lg',

        header: {
            classic: 'border-b-2 border-gray-300 bg-gray-50',
            modern: 'bg-gradient-to-br from-gray-50 via-white to-gray-50',
            minimal: 'bg-white',
            professional: 'bg-slate-800 text-white',
        }[design] || 'bg-white',

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

        billTo: {
            classic: 'bg-gray-100 border-y-2 border-gray-200',
            modern: 'bg-gradient-to-r from-gray-50 to-white',
            minimal: 'bg-white border-b border-gray-100',
            professional: 'bg-slate-50 border-l-4 border-l-slate-800',
        }[design] || 'bg-gray-50',

        billToTextColor: {
            classic: 'text-gray-900',
            modern: 'text-gray-800',
            minimal: 'text-gray-700',
            professional: 'text-slate-800',
        }[design] || 'text-gray-900',

        billToSubTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-500',
            minimal: 'text-gray-500',
            professional: 'text-slate-600',
        }[design] || 'text-gray-600',

        billToLabelColor: {
            classic: 'text-gray-500',
            modern: 'text-gray-400',
            minimal: 'text-gray-400',
            professional: 'text-slate-800 font-serif tracking-wide',
        }[design] || 'text-gray-500',

        table: {
            classic: 'border-2 border-gray-200',
            modern: '',
            minimal: '',
            professional: '',
        }[design] || '',

        tableHeader: {
            classic: 'border-b-2 border-gray-300 bg-gray-100',
            modern: 'border-b border-gray-200',
            minimal: 'border-b border-gray-100',
            professional: 'bg-slate-800 text-white',
        }[design] || 'border-b border-gray-200',

        lateWarning: {
            classic: 'bg-yellow-100 border-y-2 border-yellow-200',
            modern: 'bg-yellow-50 border-t border-yellow-100 rounded-lg mx-4',
            minimal: 'bg-yellow-50 border-t border-yellow-100',
            professional: 'bg-slate-50 border-l-4 border-l-amber-600',
        }[design] || 'bg-yellow-50',

        lateWarningText: {
            classic: 'text-yellow-800',
            modern: 'text-yellow-700',
            minimal: 'text-yellow-600',
            professional: 'text-slate-700',
        }[design] || 'text-yellow-800',

        bankDetails: {
            classic: 'bg-gray-100 border-t-2 border-gray-200',
            modern: 'bg-gradient-to-r from-gray-50 to-white border-t border-gray-100',
            minimal: 'bg-white border-t border-gray-100',
            professional: 'bg-slate-50 border-l-2 border-l-amber-600',
        }[design] || 'bg-gray-50 border-t',

        bankDetailsTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-600',
            minimal: 'text-gray-500',
            professional: 'text-slate-700',
        }[design] || 'text-gray-600',

        footer: {
            classic: 'border-t-2 border-gray-300 bg-gray-100',
            modern: 'bg-gradient-to-r from-gray-50 to-white',
            minimal: 'border-t border-gray-100',
            professional: 'bg-slate-800 text-white',
        }[design] || 'border-t',

        footerTextColor: {
            classic: 'text-gray-600',
            modern: 'text-gray-500',
            minimal: 'text-gray-400',
            professional: 'text-slate-300',
        }[design] || 'text-gray-500',
    };
});
</script>

<template>
    <Head :title="isEditing ? 'Edit Template' : 'Create Template'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('invoice-templates.index')"
                    class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <DocumentDuplicateIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Template' : 'Create Template' }}
                    </h1>
                    <p class="text-sm text-gray-500">Customize your invoice appearance with live preview</p>
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
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g., Standard Invoice"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Design Style</label>
                                        <select
                                            :value="previewState.design"
                                            @change="updateField('design', $event.target.value)"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
                                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
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
                                                    placeholder="#4F46E5"
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
                                                    placeholder="#6366F1"
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
                                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                                previewState[toggle.key] ? 'bg-indigo-600' : 'bg-gray-200'
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
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                            placeholder="Additional text to display in the header..."
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Footer</label>
                                        <textarea
                                            :value="previewState.custom_footer"
                                            @input="updateField('custom_footer', $event.target.value)"
                                            rows="2"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
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
                                    class="flex-1 px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing">Saving...</span>
                                    <span v-else>{{ isEditing ? 'Update Template' : 'Create Template' }}</span>
                                </button>
                                <Link
                                    :href="route('invoice-templates.index')"
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
                                    <!-- Invoice Preview -->
                                    <div
                                        :class="['bg-white overflow-hidden transform scale-[0.85] origin-top transition-all duration-300', designStyles.container]"
                                        :style="previewState.design === 'professional' ? { borderLeftColor: previewState.primary_color } : {}"
                                    >
                                        <!-- Header -->
                                        <div
                                            :class="['p-6', designStyles.header]"
                                            :style="previewState.design !== 'professional' ? { borderTop: `4px solid ${previewState.primary_color}` } : {}"
                                        >
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div v-if="previewState.show_logo && getLogoUrl()" class="mb-3">
                                                        <img :src="getLogoUrl()" alt="Logo" class="h-12 object-contain" />
                                                    </div>
                                                    <div v-else-if="previewState.show_logo" :class="['w-24 h-12 rounded mb-3 flex items-center justify-center text-xs', previewState.design === 'professional' ? 'bg-stone-200 text-stone-500 border border-stone-300' : 'bg-gray-200 text-gray-400']">
                                                        Logo
                                                    </div>
                                                    <h3 :class="['text-lg', designStyles.headerTextColor]">{{ settings?.business_name || 'Your Business Name' }}</h3>
                                                    <p v-if="settings?.business_address" :class="['text-sm mt-1', designStyles.headerSubTextColor]">{{ settings.business_address }}</p>
                                                    <p v-if="settings?.business_phone" :class="['text-sm', designStyles.headerSubTextColor]">{{ settings.business_phone }}</p>
                                                    <p v-if="previewState.show_tax_number && settings?.tax_number" :class="['text-sm', designStyles.headerSubTextColor]">Tax: {{ settings.tax_number }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <h2
                                                        :class="previewState.design === 'professional' ? 'text-2xl font-bold tracking-wide' : 'text-2xl font-bold'"
                                                        :style="{ color: previewState.primary_color }"
                                                    >
                                                        INVOICE
                                                    </h2>
                                                    <p :class="['text-sm mt-1', designStyles.headerSubTextColor]">#{{ sampleInvoice.invoice_number }}</p>
                                                    <p :class="['text-sm', designStyles.headerSubTextColor]">Date: {{ sampleInvoice.date }}</p>
                                                    <p v-if="previewState.show_due_date" :class="['text-sm font-medium', previewState.design === 'professional' ? 'text-red-600' : 'text-red-600']">Due: {{ sampleInvoice.due_date }}</p>
                                                </div>
                                            </div>
                                            <p v-if="previewState.custom_header" :class="['mt-4 text-sm italic', designStyles.headerSubTextColor]">{{ previewState.custom_header }}</p>
                                        </div>

                                        <!-- Bill To -->
                                        <div
                                            :class="['px-6 py-4', designStyles.billTo]"
                                            :style="previewState.design === 'professional' ? { borderLeftColor: previewState.primary_color } : {}"
                                        >
                                            <div class="grid grid-cols-2 gap-6">
                                                <div>
                                                    <p :class="['text-xs font-medium uppercase mb-1', designStyles.billToLabelColor]">Bill To</p>
                                                    <p :class="['font-medium', designStyles.billToTextColor]">{{ sampleInvoice.tenant.name }}</p>
                                                    <p :class="['text-sm', designStyles.billToSubTextColor]">{{ sampleInvoice.tenant.email }}</p>
                                                    <p v-if="previewState.show_tenant_id" :class="['text-sm', designStyles.billToSubTextColor]">ID: {{ sampleInvoice.tenant.national_id }}</p>
                                                </div>
                                                <div v-if="previewState.show_unit_details">
                                                    <p :class="['text-xs font-medium uppercase mb-1', designStyles.billToLabelColor]">Property</p>
                                                    <p :class="['font-medium', designStyles.billToTextColor]">{{ sampleInvoice.unit.name }}</p>
                                                    <p :class="['text-sm', designStyles.billToSubTextColor]">{{ sampleInvoice.unit.building }}</p>
                                                    <p v-if="previewState.show_lease_reference" :class="['text-sm', designStyles.billToSubTextColor]">Lease: {{ sampleInvoice.lease.reference }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Items Table -->
                                        <div :class="['px-6 py-4', designStyles.table]">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr :class="designStyles.tableHeader">
                                                        <th class="text-left py-2 font-medium text-gray-600">Description</th>
                                                        <th class="text-right py-2 font-medium text-gray-600">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(item, index) in sampleInvoice.items" :key="index" class="border-b border-gray-100">
                                                        <td class="py-2 text-gray-900">{{ item.description }}</td>
                                                        <td class="py-2 text-right text-gray-900">{{ formatCurrency(item.total) }}</td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="font-bold">
                                                        <td class="py-3 text-gray-900">Total Due</td>
                                                        <td class="py-3 text-right" :style="{ color: previewState.primary_color }">{{ formatCurrency(sampleInvoice.total_due) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        <!-- Late Warning -->
                                        <div
                                            v-if="previewState.show_late_warning"
                                            :class="['px-6 py-3', designStyles.lateWarning]"
                                            :style="previewState.design === 'professional' ? { borderLeftColor: previewState.secondary_color } : {}"
                                        >
                                            <p :class="['text-sm', designStyles.lateWarningText]">{{ sampleInvoice.late_warning }}</p>
                                        </div>

                                        <!-- Bank Details -->
                                        <div
                                            v-if="previewState.show_bank_details && settings?.bank_name"
                                            :class="['px-6 py-4', designStyles.bankDetails]"
                                            :style="previewState.design === 'professional' ? { borderLeftColor: previewState.primary_color } : {}"
                                        >
                                            <p :class="['text-xs font-medium uppercase mb-2 tracking-wider', previewState.design === 'professional' ? 'text-stone-500 font-semibold' : 'text-gray-500']">Payment Details</p>
                                            <div :class="['text-sm', designStyles.bankDetailsTextColor]">
                                                <p>Bank: {{ settings.bank_name }}</p>
                                                <p>Account: {{ settings.bank_account_name }}</p>
                                                <p>Number: {{ settings.bank_account_number }}</p>
                                            </div>
                                        </div>

                                        <!-- QR Code Placeholder -->
                                        <div v-if="previewState.show_qr_code" class="px-6 py-4 flex justify-center">
                                            <div :class="['w-24 h-24 flex items-center justify-center text-xs', previewState.design === 'modern' ? 'bg-gray-100 rounded-xl' : previewState.design === 'professional' ? 'bg-stone-100 text-stone-500 border border-stone-200 rounded' : 'bg-gray-200 rounded text-gray-400']">
                                                QR Code
                                            </div>
                                        </div>

                                        <!-- Footer -->
                                        <div v-if="previewState.show_footer || previewState.custom_footer" :class="['px-6 py-4', designStyles.footer]">
                                            <p v-if="previewState.custom_footer" :class="['text-sm mb-2', designStyles.footerTextColor]">{{ previewState.custom_footer }}</p>
                                            <p v-if="previewState.show_footer && settings?.footer_note" :class="['text-sm', designStyles.footerTextColor]">{{ settings.footer_note }}</p>
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
