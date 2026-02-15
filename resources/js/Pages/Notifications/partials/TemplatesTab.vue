<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    DocumentDuplicateIcon,
    EyeIcon,
    XMarkIcon,
    CheckIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';
import type { NotificationsTemplatesTabProps } from '@/types';

const { formatDate, formatMoney } = useFormatters();

const props = withDefaults(defineProps<NotificationsTemplatesTabProps>(), {
    templates: () => [],
});

const showCreateModal = ref(false);
const showPreviewModal = ref(false);
const editingTemplate = ref(null);
const previewTemplate = ref(null);
const previewData = ref({});

const notificationTypes = [
    { value: 'rent_reminder', label: 'Rent Reminder', placeholders: ['tenant_name', 'unit_name', 'rent_amount', 'due_date', 'landlord_name', 'property_name'] },
    { value: 'arrears_notice', label: 'Arrears Notice', placeholders: ['tenant_name', 'unit_name', 'arrears_amount', 'days_overdue', 'landlord_name', 'property_name'] },
    { value: 'invoice', label: 'Invoice', placeholders: ['tenant_name', 'unit_name', 'invoice_number', 'total_amount', 'due_date', 'landlord_name'] },
    { value: 'receipt', label: 'Receipt', placeholders: ['tenant_name', 'payment_amount', 'payment_date', 'payment_method', 'invoice_number', 'landlord_name'] },
    { value: 'rent_hike', label: 'Rent Hike', placeholders: ['tenant_name', 'unit_name', 'old_rent', 'new_rent', 'effective_date', 'landlord_name'] },
    { value: 'lease_expiry', label: 'Lease Expiry', placeholders: ['tenant_name', 'unit_name', 'expiry_date', 'days_remaining', 'landlord_name', 'property_name'] },
    { value: 'general', label: 'General', placeholders: ['tenant_name', 'unit_name', 'landlord_name', 'property_name', 'current_date'] },
];

const form = useForm({
    id: null,
    name: '',
    type: 'general',
    subject: '',
    body: '',
    is_active: true,
});

const selectedTypePlaceholders = computed(() => {
    const type = notificationTypes.find(t => t.value === form.type);
    return type ? type.placeholders : [];
});

const openCreateModal = () => {
    form.reset();
    form.id = null;
    editingTemplate.value = null;
    showCreateModal.value = true;
};

const openEditModal = (template) => {
    editingTemplate.value = template;
    form.id = template.id;
    form.name = template.name;
    form.type = template.type;
    form.subject = template.subject;
    form.body = template.body;
    form.is_active = template.is_active;
    showCreateModal.value = true;
};

const closeModal = () => {
    showCreateModal.value = false;
    editingTemplate.value = null;
    form.reset();
};

const saveTemplate = () => {
    if (form.id) {
        form.put(route('notifications.templates.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('notifications.templates.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteTemplate = (template) => {
    if (confirm(`Are you sure you want to delete "${template.name}"?`)) {
        useForm({}).delete(route('notifications.templates.destroy', template.id));
    }
};

const duplicateTemplate = (template) => {
    form.reset();
    form.id = null;
    form.name = template.name + ' (Copy)';
    form.type = template.type;
    form.subject = template.subject;
    form.body = template.body;
    form.is_active = true;
    editingTemplate.value = null;
    showCreateModal.value = true;
};

const insertPlaceholder = (placeholder, field) => {
    const tag = `{{${placeholder}}}`;
    if (field === 'subject') {
        form.subject += tag;
    } else {
        form.body += tag;
    }
};

const openPreview = (template) => {
    previewTemplate.value = template;
    previewData.value = {
        tenant_name: 'John Doe',
        unit_name: 'Unit A1',
        rent_amount: formatMoney(25000),
        due_date: '5th January 2025',
        arrears_amount: formatMoney(50000),
        days_overdue: '15',
        invoice_number: 'INV-202501-0001',
        total_amount: formatMoney(27500),
        payment_amount: formatMoney(25000),
        payment_date: '3rd January 2025',
        payment_method: 'M-Pesa',
        old_rent: formatMoney(23000),
        new_rent: formatMoney(25000),
        effective_date: '1st February 2025',
        expiry_date: '31st March 2025',
        days_remaining: '90',
        landlord_name: 'Property Manager',
        property_name: 'Sunrise Apartments',
        current_date: formatDate(new Date(), 'long'),
    };
    showPreviewModal.value = true;
};

const renderPreview = (text) => {
    if (!text) return '';
    let rendered = text;
    Object.keys(previewData.value).forEach(key => {
        rendered = rendered.replace(new RegExp(`{{${key}}}`, 'g'), previewData.value[key]);
    });
    return rendered;
};

const getTypeLabel = (type) => {
    const found = notificationTypes.find(t => t.value === type);
    return found ? found.label : type;
};

const getTypeColor = (type) => {
    const colors = {
        rent_reminder: 'bg-blue-100 text-blue-700',
        arrears_notice: 'bg-red-100 text-red-700',
        invoice: 'bg-purple-100 text-purple-700',
        receipt: 'bg-green-100 text-green-700',
        rent_hike: 'bg-orange-100 text-orange-700',
        lease_expiry: 'bg-yellow-100 text-yellow-700',
        general: 'bg-gray-100 text-gray-700',
    };
    return colors[type] || 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Notification Templates</h2>
                <p class="text-sm text-gray-500">Create reusable templates for different notification types</p>
            </div>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                Create Template
            </button>
        </div>

        <!-- Templates Grid -->
        <div v-if="templates.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="template in templates"
                :key="template.id"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 truncate">{{ template.name }}</h3>
                        <span :class="['inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full', getTypeColor(template.type)]">
                            {{ getTypeLabel(template.type) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1 ml-2">
                        <span
                            v-if="template.is_default"
                            class="px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full"
                        >
                            Default
                        </span>
                        <span
                            :class="[
                                'w-2 h-2 rounded-full',
                                template.is_active ? 'bg-green-500' : 'bg-gray-300'
                            ]"
                            :title="template.is_active ? 'Active' : 'Inactive'"
                        ></span>
                    </div>
                </div>

                <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ template.subject }}</p>

                <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                    <button
                        @click="openPreview(template)"
                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                        title="Preview"
                    >
                        <EyeIcon class="w-4 h-4" />
                    </button>
                    <button
                        @click="openEditModal(template)"
                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        title="Edit"
                    >
                        <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button
                        @click="duplicateTemplate(template)"
                        class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                        title="Duplicate"
                    >
                        <DocumentDuplicateIcon class="w-4 h-4" />
                    </button>
                    <button
                        v-if="!template.is_default"
                        @click="deleteTemplate(template)"
                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        title="Delete"
                    >
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="p-4 bg-indigo-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <DocumentDuplicateIcon class="w-8 h-8 text-indigo-600" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Templates Yet</h3>
            <p class="text-gray-500 mb-4">Create your first notification template to get started</p>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                Create Template
            </button>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="closeModal"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ editingTemplate ? 'Edit Template' : 'Create Template' }}
                                </h3>
                                <button @click="closeModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="saveTemplate" class="p-6 space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="e.g., Monthly Rent Reminder"
                                        required
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select
                                        v-model="form.type"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option v-for="type in notificationTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Placeholders Help -->
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <div class="flex items-start gap-3">
                                    <InformationCircleIcon class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                                    <div>
                                        <p class="text-sm font-medium text-blue-900">Available Placeholders</p>
                                        <p class="text-xs text-blue-700 mt-1">Click to insert into subject or body</p>
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            <button
                                                v-for="placeholder in selectedTypePlaceholders"
                                                :key="placeholder"
                                                type="button"
                                                @click="insertPlaceholder(placeholder, 'body')"
                                                class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors font-mono"
                                            >
                                                {{placeholder}}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                <input
                                    v-model="form.subject"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="e.g., Rent Due Reminder for {{unit_name}}"
                                    required
                                />
                                <p v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Message Body</label>
                                <textarea
                                    v-model="form.body"
                                    rows="8"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                    placeholder="Dear {{tenant_name}},&#10;&#10;This is a reminder that your rent of {{rent_amount}} is due on {{due_date}}.&#10;&#10;Best regards,&#10;{{landlord_name}}"
                                    required
                                ></textarea>
                                <p v-if="form.errors.body" class="text-sm text-red-600 mt-1">{{ form.errors.body }}</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                                <span class="text-sm text-gray-700">Template is active</span>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                >
                                    <CheckIcon class="w-4 h-4" />
                                    {{ editingTemplate ? 'Update Template' : 'Create Template' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreviewModal && previewTemplate" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showPreviewModal = false"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-lg w-full">
                        <div class="border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Template Preview</h3>
                                <button @click="showPreviewModal = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Subject</p>
                                <p class="font-medium text-gray-900">{{ renderPreview(previewTemplate.subject) }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Message</p>
                                <p class="text-gray-700 whitespace-pre-wrap text-sm">{{ renderPreview(previewTemplate.body) }}</p>
                            </div>

                            <p class="text-xs text-gray-400 mt-4 text-center">
                                Preview uses sample data. Actual values will be replaced when sending.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
