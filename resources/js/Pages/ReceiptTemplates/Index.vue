<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    DocumentDuplicateIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    StarIcon,
    CheckBadgeIcon,
    ReceiptPercentIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    templates: Array,
    designOptions: Object,
});

const breadcrumbItems = [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Templates', href: route('finances.templates') },
    { label: 'Receipts' },
];

const setDefault = (template) => {
    router.post(route('receipt-templates.set-default', template.id), {}, {
        preserveScroll: true,
    });
};

const deleteTemplate = (template) => {
    if (confirm('Are you sure you want to delete this template?')) {
        router.delete(route('receipt-templates.destroy', template.id), {
            preserveScroll: true,
        });
    }
};

const getDesignBadgeClass = (design) => {
    const classes = {
        classic: 'bg-blue-100 text-blue-700',
        modern: 'bg-purple-100 text-purple-700',
        minimal: 'bg-gray-100 text-gray-700',
        professional: 'bg-green-100 text-green-700',
    };
    return classes[design] || 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Receipt Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 rounded-lg">
                        <ReceiptPercentIcon class="w-6 h-6 text-emerald-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Receipt Templates</h1>
                        <p class="text-sm text-gray-500">Customize how your payment receipts look</p>
                    </div>
                </div>
                <Link
                    :href="route('receipt-templates.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-5 h-5" />
                    New Template
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <!-- Empty State -->
                <div v-if="templates.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <ReceiptPercentIcon class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No templates yet</h3>
                    <p class="text-gray-500 mb-6">Create your first receipt template to customize how your payment receipts look.</p>
                    <Link
                        :href="route('receipt-templates.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-5 h-5" />
                        Create Template
                    </Link>
                </div>

                <!-- Template Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="template in templates"
                        :key="template.id"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
                    >
                        <!-- Template Preview Header -->
                        <div class="h-32 relative" :style="{ backgroundColor: template.primary_color || '#059669' }">
                            <div class="absolute inset-0 bg-black/10"></div>
                            <div class="absolute top-3 left-3">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full', getDesignBadgeClass(template.design)]">
                                    {{ designOptions[template.design] }}
                                </span>
                            </div>
                            <div v-if="template.is_default" class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-medium rounded-full">
                                    <StarIconSolid class="w-3 h-3" />
                                    Default
                                </span>
                            </div>
                            <div class="absolute bottom-4 left-4 right-4">
                                <div class="bg-white/90 backdrop-blur rounded-lg p-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <div class="h-2 w-20 bg-gray-300 rounded"></div>
                                    </div>
                                    <div class="h-1.5 w-12 bg-gray-200 rounded"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Template Info -->
                        <div class="p-4">
                            <h3 class="font-medium text-gray-900 mb-2">{{ template.name }}</h3>

                            <!-- Toggle Summary -->
                            <div class="flex flex-wrap gap-1 mb-4">
                                <span v-if="template.show_logo" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">Logo</span>
                                <span v-if="template.show_payment_method" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">Method</span>
                                <span v-if="template.show_qr_code" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">QR</span>
                                <span v-if="template.show_invoice_details" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">Invoice</span>
                                <span v-if="template.show_balance_after_payment" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">Balance</span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                <button
                                    v-if="!template.is_default"
                                    @click="setDefault(template)"
                                    class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-emerald-600"
                                >
                                    <StarIcon class="w-4 h-4" />
                                    Set Default
                                </button>
                                <span v-else class="inline-flex items-center gap-1 text-sm text-yellow-600">
                                    <CheckBadgeIcon class="w-4 h-4" />
                                    Default
                                </span>

                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="route('receipt-templates.edit', template.id)"
                                        class="p-2 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                    >
                                        <PencilIcon class="w-4 h-4" />
                                    </Link>
                                    <button
                                        v-if="!template.is_default"
                                        @click="deleteTemplate(template)"
                                        class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
