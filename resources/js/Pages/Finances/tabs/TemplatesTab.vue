<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    DocumentDuplicateIcon,
    DocumentTextIcon,
    ReceiptPercentIcon,
    CreditCardIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    StarIcon,
    SwatchIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid';

interface InvoiceTemplate {
    id: number;
    name: string;
    design: string;
    is_default: boolean;
    primary_color: string;
    secondary_color: string;
    show_logo: boolean;
    show_bank_details: boolean;
    show_qr_code: boolean;
    show_water_details: boolean;
    show_arrears_breakdown: boolean;
}

interface Props {
    templates?: InvoiceTemplate[];
    receiptSettings?: {
        receipt_show_logo?: boolean;
        receipt_show_tenant_details?: boolean;
        receipt_show_invoice_details?: boolean;
        receipt_show_payment_method?: boolean;
        receipt_header_text?: string;
        receipt_footer_text?: string;
    };
    designOptions?: Record<string, string>;
    activeSubtab?: string;
}

const props = withDefaults(defineProps<Props>(), {
    templates: () => [],
    receiptSettings: () => ({}),
    designOptions: () => ({
        classic: 'Classic',
        modern: 'Modern',
        minimal: 'Minimal',
        professional: 'Professional',
    }),
    activeSubtab: 'template-invoices',
});

const templateTypeLabels = {
    'template-invoices': 'Invoice',
    'template-receipts': 'Receipt',
    'template-credit-notes': 'Credit Note',
};

const currentType = computed(() => props.activeSubtab || 'template-invoices');

const getDesignLabel = (design: string) => {
    return props.designOptions[design] || design;
};

const getToggleSummary = (template: InvoiceTemplate) => {
    const features = [];
    if (template.show_logo) features.push('Logo');
    if (template.show_bank_details) features.push('Bank');
    if (template.show_qr_code) features.push('QR');
    if (template.show_water_details) features.push('Water');
    if (template.show_arrears_breakdown) features.push('Arrears');
    return features.join(' • ') || 'No extras';
};
</script>

<template>
    <div class="space-y-6">
        <!-- Invoice Templates -->
        <div v-if="currentType === 'template-invoices'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Invoice Templates</h2>
                    <p class="text-sm text-gray-600 mt-1">Customize how your invoices look when sent to tenants</p>
                </div>
                <Link
                    :href="route('invoice-templates.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-5 h-5" />
                    New Template
                </Link>
            </div>

            <!-- Templates Grid -->
            <div v-if="templates.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow"
                >
                    <!-- Template Preview Header -->
                    <div
                        class="h-24 relative"
                        :style="{ background: `linear-gradient(135deg, ${template.primary_color} 0%, ${template.secondary_color} 100%)` }"
                    >
                        <div class="absolute top-3 left-3">
                            <span class="px-2 py-1 text-xs font-medium rounded bg-white/90 text-gray-700">
                                {{ getDesignLabel(template.design) }}
                            </span>
                        </div>
                        <div v-if="template.is_default" class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded bg-amber-100 text-amber-700">
                                <StarIconSolid class="w-3 h-3" />
                                Default
                            </span>
                        </div>
                        <div class="absolute bottom-3 right-3">
                            <DocumentTextIcon class="w-12 h-12 text-white/30" />
                        </div>
                    </div>

                    <!-- Template Info -->
                    <div class="p-4">
                        <h3 class="font-medium text-gray-900">{{ template.name }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ getToggleSummary(template) }}</p>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 mt-4">
                            <Link
                                :href="route('invoice-templates.edit', template.id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                <PencilIcon class="w-4 h-4" />
                                Edit
                            </Link>
                            <button
                                v-if="!template.is_default"
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                            >
                                <StarIcon class="w-4 h-4" />
                                Set Default
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <DocumentDuplicateIcon class="w-12 h-12 text-gray-400 mx-auto" />
                <h3 class="mt-4 text-lg font-medium text-gray-900">No invoice templates yet</h3>
                <p class="mt-2 text-sm text-gray-600">Create your first template to customize how your invoices look.</p>
                <Link
                    :href="route('invoice-templates.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 mt-6 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-5 h-5" />
                    Create Template
                </Link>
            </div>
        </div>

        <!-- Receipt Templates -->
        <div v-else-if="currentType === 'template-receipts'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Receipt Templates</h2>
                    <p class="text-sm text-gray-600 mt-1">Customize how payment receipts appear to tenants</p>
                </div>
            </div>

            <!-- Current Receipt Configuration -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-base font-medium text-gray-900">Current Receipt Settings</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-700">Display Options</h4>
                            <ul class="space-y-2">
                                <li class="flex items-center gap-2 text-sm">
                                    <span :class="receiptSettings.receipt_show_logo ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ receiptSettings.receipt_show_logo ? '✓' : '✗' }}
                                    </span>
                                    <span :class="receiptSettings.receipt_show_logo ? 'text-gray-900' : 'text-gray-500'">Show Logo</span>
                                </li>
                                <li class="flex items-center gap-2 text-sm">
                                    <span :class="receiptSettings.receipt_show_tenant_details ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ receiptSettings.receipt_show_tenant_details ? '✓' : '✗' }}
                                    </span>
                                    <span :class="receiptSettings.receipt_show_tenant_details ? 'text-gray-900' : 'text-gray-500'">Show Tenant Details</span>
                                </li>
                                <li class="flex items-center gap-2 text-sm">
                                    <span :class="receiptSettings.receipt_show_invoice_details ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ receiptSettings.receipt_show_invoice_details ? '✓' : '✗' }}
                                    </span>
                                    <span :class="receiptSettings.receipt_show_invoice_details ? 'text-gray-900' : 'text-gray-500'">Show Invoice Details</span>
                                </li>
                                <li class="flex items-center gap-2 text-sm">
                                    <span :class="receiptSettings.receipt_show_payment_method ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ receiptSettings.receipt_show_payment_method ? '✓' : '✗' }}
                                    </span>
                                    <span :class="receiptSettings.receipt_show_payment_method ? 'text-gray-900' : 'text-gray-500'">Show Payment Method</span>
                                </li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-700">Custom Text</h4>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="text-gray-500">Header:</span>
                                    <p class="text-gray-900 mt-0.5">{{ receiptSettings.receipt_header_text || 'Not set' }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Footer:</span>
                                    <p class="text-gray-900 mt-0.5">{{ receiptSettings.receipt_footer_text || 'Not set' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="bg-amber-50 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <SwatchIcon class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <h4 class="text-sm font-medium text-amber-800">Coming Soon: Full Receipt Template Editor</h4>
                                    <p class="text-sm text-amber-700 mt-1">
                                        A complete receipt template editor with live preview, color customization, and design styles is coming soon.
                                        For now, you can customize basic settings in Finance Hub → Settings → Receipt Settings.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credit Note Templates -->
        <div v-else-if="currentType === 'template-credit-notes'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Credit Note Templates</h2>
                    <p class="text-sm text-gray-600 mt-1">Credit notes use your invoice template with modifications</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-base font-medium text-gray-900">Template Inheritance</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <CreditCardIcon class="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Using Invoice Template</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Credit notes automatically inherit your default invoice template settings.
                                The title is changed to "CREDIT NOTE" and amounts are displayed as negative values.
                            </p>

                            <div v-if="templates.length > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    <strong>Current Default Template:</strong>
                                    {{ templates.find(t => t.is_default)?.name || 'None selected' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
