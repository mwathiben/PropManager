<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useAuth } from '@/composables/useAuth';
import {
    PhotoIcon,
    DocumentTextIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import type { BrandingSettings, InvoiceNumberFormats } from '@/types';

const props = withDefaults(defineProps<{
    brandingSettings?: BrandingSettings;
    invoiceNumberFormats?: InvoiceNumberFormats;
}>(), {
    brandingSettings: () => ({} as BrandingSettings),
    invoiceNumberFormats: () => ({} as InvoiceNumberFormats),
});

const { can } = useAuth();

const form = useForm({
    invoice_number_format: props.brandingSettings?.invoice_number_format || 'INV-{YYYY}{MM}-{NNNN}',
    invoice_footer_text: props.brandingSettings?.invoice_footer_text || '',
    receipt_footer_text: props.brandingSettings?.receipt_footer_text || '',
});

const logoForm = useForm({
    logo: null,
});

const logoPreview = ref(props.brandingSettings?.business_logo_url || null);
const fileInput = ref(null);

const hasLogo = computed(() => !!props.brandingSettings?.business_logo_path);

const selectLogo = () => {
    fileInput.value?.click();
};

const onLogoSelected = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('Logo file must be less than 2MB');
            return;
        }

        // Preview
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);

        // Upload
        logoForm.logo = file;
        logoForm.post(route('settings.branding.logo'), {
            preserveScroll: true,
            onSuccess: () => {
                logoForm.reset();
            },
        });
    }
};

const deleteLogo = () => {
    if (confirm('Are you sure you want to delete your business logo?')) {
        router.delete(route('settings.branding.logo.delete'), {
            preserveScroll: true,
            onSuccess: () => {
                logoPreview.value = null;
            },
        });
    }
};

const submit = () => {
    form.post(route('settings.branding.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Branding</h3>
            <p class="mt-1 text-sm text-gray-600">
                Customize how your invoices and receipts look to tenants.
            </p>
        </div>

        <!-- Logo Upload -->
        <div class="bg-gray-50 rounded-xl p-6 space-y-4">
            <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Business Logo</h4>
            <p class="text-sm text-gray-500">Your logo will appear on invoices and receipts. Recommended size: 200x80 pixels.</p>

            <div class="flex items-start gap-6">
                <!-- Logo Preview -->
                <div class="shrink-0">
                    <div
                        v-if="logoPreview"
                        class="relative w-48 h-24 bg-white border border-gray-200 rounded-lg overflow-hidden"
                    >
                        <img
                            :src="logoPreview"
                            alt="Business logo"
                            loading="lazy"
                            decoding="async"
                            class="w-full h-full object-contain p-2"
                        >
                        <button
                            v-if="can('settings:manage')"
                            @click="deleteLogo"
                            class="absolute top-1 end-1 p-1 bg-red-100 text-red-600 rounded-full hover:bg-red-200 transition-colors"
                            title="Delete logo"
                        >
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                    <div
                        v-else
                        @click="selectLogo"
                        class="w-48 h-24 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                    >
                        <PhotoIcon class="w-8 h-8 text-gray-400" />
                        <span class="mt-1 text-xs text-gray-500">Click to upload</span>
                    </div>
                </div>

                <!-- Upload Instructions -->
                <div class="flex-1">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/svg+xml"
                        class="hidden"
                        @change="onLogoSelected"
                    >
                    <button
                        @click="selectLogo"
                        type="button"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        :disabled="logoForm.processing"
                    >
                        {{ logoForm.processing ? 'Uploading...' : (hasLogo ? 'Change Logo' : 'Upload Logo') }}
                    </button>
                    <p class="mt-2 text-xs text-gray-500">
                        Accepted formats: JPEG, PNG, GIF, SVG. Max size: 2MB.
                    </p>
                    <InputError :message="logoForm.errors.logo" class="mt-2" />
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Invoice Number Format -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Invoice Numbering</h4>

                <div>
                    <InputLabel for="invoice_number_format" value="Invoice Number Format" />
                    <select
                        id="invoice_number_format"
                        v-model="form.invoice_number_format"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option
                            v-for="(example, format) in invoiceNumberFormats"
                            :key="format"
                            :value="format"
                        >
                            {{ format }} (e.g., {{ example }})
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        {YYYY} = Year, {MM} = Month, {NNNN} = Sequential number
                    </p>
                    <InputError :message="form.errors.invoice_number_format" class="mt-2" />
                </div>
            </div>

            <!-- Footer Texts -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-6">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Document Footers</h4>

                <div>
                    <InputLabel for="invoice_footer_text" value="Invoice Footer Text" />
                    <textarea
                        id="invoice_footer_text"
                        v-model="form.invoice_footer_text"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g., Thank you for your business. Payment is due within 7 days."
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        This text appears at the bottom of all invoices (max 500 characters)
                    </p>
                    <InputError :message="form.errors.invoice_footer_text" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="receipt_footer_text" value="Receipt Footer Text" />
                    <textarea
                        id="receipt_footer_text"
                        v-model="form.receipt_footer_text"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g., Thank you for your payment. This receipt is auto-generated."
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        This text appears at the bottom of all payment receipts (max 500 characters)
                    </p>
                    <InputError :message="form.errors.receipt_footer_text" class="mt-2" />
                </div>
            </div>

            <!-- Preview Section -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider mb-4">Invoice Preview</h4>

                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <!-- Mini Invoice Preview -->
                    <div class="space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <div v-if="logoPreview" class="w-24 h-12 bg-white rounded border border-gray-200 overflow-hidden mb-2">
                                    <img :src="logoPreview" alt="Logo" loading="lazy" decoding="async" class="w-full h-full object-contain p-1">
                                </div>
                                <div v-else class="w-24 h-12 bg-gray-200 rounded flex items-center justify-center mb-2">
                                    <span class="text-xs text-gray-400">No Logo</span>
                                </div>
                                <p class="text-xs text-gray-500">Your Company Name</p>
                            </div>
                            <div class="text-end">
                                <p class="text-lg font-bold text-gray-900">INVOICE</p>
                                <p class="text-sm text-gray-600">{{ invoiceNumberFormats[form.invoice_number_format] || 'INV-202501-0001' }}</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <div class="h-8 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-8 bg-gray-200 rounded w-3/4"></div>
                        </div>

                        <div v-if="form.invoice_footer_text" class="border-t border-gray-200 pt-4">
                            <p class="text-xs text-gray-500 italic">{{ form.invoice_footer_text }}</p>
                        </div>
                        <div v-else class="border-t border-gray-200 pt-4">
                            <p class="text-xs text-gray-400 italic">Your invoice footer text will appear here</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                >
                    {{ form.processing ? 'Saving...' : 'Save Branding Settings' }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
