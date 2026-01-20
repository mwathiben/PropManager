<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DocumentDuplicateIcon from '@heroicons/vue/24/outline/DocumentDuplicateIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import ReceiptPercentIcon from '@heroicons/vue/24/outline/ReceiptPercentIcon';
import CreditCardIcon from '@heroicons/vue/24/outline/CreditCardIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import PencilIcon from '@heroicons/vue/24/outline/PencilIcon';
import StarIcon from '@heroicons/vue/24/outline/StarIcon';
import StarIconSolid from '@heroicons/vue/24/solid/StarIcon';
import EmptyState from '@/Components/EmptyState.vue';

interface Template {
    id: number;
    name: string;
    design: string;
    is_default: boolean;
    primary_color: string;
    secondary_color: string;
    show_logo?: boolean;
    show_bank_details?: boolean;
    show_qr_code?: boolean;
    show_water_details?: boolean;
    show_arrears_breakdown?: boolean;
    show_receipt_number?: boolean;
    show_payment_method?: boolean;
    show_tenant_name?: boolean;
}

interface Props {
    templates?: Template[];
    receiptTemplates?: Template[];
    designOptions?: Record<string, string>;
    activeSubtab?: string;
}

const props = withDefaults(defineProps<Props>(), {
    templates: () => [],
    receiptTemplates: () => [],
    designOptions: () => ({
        classic: 'Classic',
        modern: 'Modern',
        minimal: 'Minimal',
        professional: 'Professional',
    }),
    activeSubtab: 'template-invoices',
});

const currentType = computed(() => props.activeSubtab || 'template-invoices');

const getDesignLabel = (design: string) => {
    return props.designOptions[design] || design;
};

const getInvoiceToggleSummary = (template: Template) => {
    const features = [];
    if (template.show_logo) features.push('Logo');
    if (template.show_bank_details) features.push('Bank');
    if (template.show_qr_code) features.push('QR');
    if (template.show_water_details) features.push('Water');
    if (template.show_arrears_breakdown) features.push('Arrears');
    return features.join(' • ') || 'No extras';
};

const getReceiptToggleSummary = (template: Template) => {
    const features = [];
    if (template.show_logo) features.push('Logo');
    if (template.show_receipt_number) features.push('Receipt #');
    if (template.show_payment_method) features.push('Method');
    if (template.show_qr_code) features.push('QR');
    if (template.show_tenant_name) features.push('Tenant');
    return features.join(' • ') || 'No extras';
};

const setDefaultInvoiceTemplate = (templateId: number) => {
    router.post(route('invoice-templates.set-default', templateId), {}, {
        preserveScroll: true,
    });
};

const setDefaultReceiptTemplate = (templateId: number) => {
    router.post(route('receipt-templates.set-default', templateId), {}, {
        preserveScroll: true,
    });
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
                        <p class="text-xs text-gray-500 mt-1">{{ getInvoiceToggleSummary(template) }}</p>

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
                                @click="setDefaultInvoiceTemplate(template.id)"
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
            <div v-else class="bg-white rounded-xl border border-gray-200">
                <EmptyState
                    :icon="DocumentDuplicateIcon"
                    title="No invoice templates yet"
                    description="Create your first template to customize how your invoices look."
                    action-label="Create Template"
                    :action-href="route('invoice-templates.create')"
                />
            </div>
        </div>

        <!-- Receipt Templates -->
        <div v-else-if="currentType === 'template-receipts'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Receipt Templates</h2>
                    <p class="text-sm text-gray-600 mt-1">Customize how payment receipts appear to tenants</p>
                </div>
                <Link
                    :href="route('receipt-templates.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-5 h-5" />
                    New Template
                </Link>
            </div>

            <!-- Templates Grid -->
            <div v-if="receiptTemplates.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in receiptTemplates"
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
                            <ReceiptPercentIcon class="w-12 h-12 text-white/30" />
                        </div>
                    </div>

                    <!-- Template Info -->
                    <div class="p-4">
                        <h3 class="font-medium text-gray-900">{{ template.name }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ getReceiptToggleSummary(template) }}</p>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 mt-4">
                            <Link
                                :href="route('receipt-templates.edit', template.id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                <PencilIcon class="w-4 h-4" />
                                Edit
                            </Link>
                            <button
                                v-if="!template.is_default"
                                type="button"
                                @click="setDefaultReceiptTemplate(template.id)"
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
            <div v-else class="bg-white rounded-xl border border-gray-200">
                <EmptyState
                    :icon="ReceiptPercentIcon"
                    title="No receipt templates yet"
                    description="Create your first template to customize how your payment receipts look."
                    action-label="Create Template"
                    :action-href="route('receipt-templates.create')"
                />
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
                                <Link
                                    v-if="templates.find(t => t.is_default)"
                                    :href="route('invoice-templates.edit', templates.find(t => t.is_default)?.id)"
                                    class="inline-flex items-center gap-1 mt-3 text-sm text-emerald-600 hover:text-emerald-700"
                                >
                                    <PencilIcon class="w-4 h-4" />
                                    Edit Invoice Template
                                </Link>
                            </div>
                            <div v-else class="mt-4 p-4 bg-amber-50 rounded-lg">
                                <p class="text-sm text-amber-700">
                                    No invoice template found. Create an invoice template first to use with credit notes.
                                </p>
                                <Link
                                    :href="route('invoice-templates.create')"
                                    class="inline-flex items-center gap-1 mt-3 text-sm text-amber-700 hover:text-amber-800"
                                >
                                    <PlusIcon class="w-4 h-4" />
                                    Create Invoice Template
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
