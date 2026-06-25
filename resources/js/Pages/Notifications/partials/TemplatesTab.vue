<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useAuth } from '@/composables/useAuth';
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
const { t } = useI18n();
const { can } = useAuth();

const props = withDefaults(defineProps<NotificationsTemplatesTabProps>(), {
    templates: () => [],
});

const showCreateModal = ref(false);
const showPreviewModal = ref(false);
const editingTemplate = ref(null);
const previewTemplate = ref(null);
const previewData = ref({});

const notificationTypes = computed(() => [
    { value: 'rent_reminder', label: t('notifications_templates.types.rent_reminder'), placeholders: ['tenant_name', 'unit_name', 'rent_amount', 'due_date', 'landlord_name', 'property_name'] },
    { value: 'arrears_notice', label: t('notifications_templates.types.arrears_notice'), placeholders: ['tenant_name', 'unit_name', 'arrears_amount', 'days_overdue', 'landlord_name', 'property_name'] },
    { value: 'invoice', label: t('notifications_templates.types.invoice'), placeholders: ['tenant_name', 'unit_name', 'invoice_number', 'total_amount', 'due_date', 'landlord_name'] },
    { value: 'receipt', label: t('notifications_templates.types.receipt'), placeholders: ['tenant_name', 'payment_amount', 'payment_date', 'payment_method', 'invoice_number', 'landlord_name'] },
    { value: 'rent_hike', label: t('notifications_templates.types.rent_hike'), placeholders: ['tenant_name', 'unit_name', 'old_rent', 'new_rent', 'effective_date', 'landlord_name'] },
    { value: 'lease_expiry', label: t('notifications_templates.types.lease_expiry'), placeholders: ['tenant_name', 'unit_name', 'expiry_date', 'days_remaining', 'landlord_name', 'property_name'] },
    { value: 'general', label: t('notifications_templates.types.general'), placeholders: ['tenant_name', 'unit_name', 'landlord_name', 'property_name', 'current_date'] },
]);

const form = useForm({
    id: null,
    name: '',
    type: 'general',
    subject: '',
    body: '',
    is_active: true,
});

const selectedTypePlaceholders = computed(() => {
    const type = notificationTypes.value.find(nt => nt.value === form.type);
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
    if (confirm(t('notifications_templates.confirm_delete', { name: template.name }))) {
        useForm({}).delete(route('notifications.templates.destroy', template.id));
    }
};

const duplicateTemplate = (template) => {
    form.reset();
    form.id = null;
    form.name = template.name + t('notifications_templates.copy_suffix');
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
        tenant_name: t('notifications_templates.sample.tenant_name'),
        unit_name: t('notifications_templates.sample.unit_name'),
        rent_amount: formatMoney(25000),
        due_date: '5th January 2025',
        arrears_amount: formatMoney(50000),
        days_overdue: '15',
        invoice_number: 'INV-202501-0001',
        total_amount: formatMoney(27500),
        payment_amount: formatMoney(25000),
        payment_date: '3rd January 2025',
        payment_method: t('notifications_templates.sample.payment_method'),
        old_rent: formatMoney(23000),
        new_rent: formatMoney(25000),
        effective_date: '1st February 2025',
        expiry_date: '31st March 2025',
        days_remaining: '90',
        landlord_name: t('notifications_templates.sample.landlord_name'),
        property_name: t('notifications_templates.sample.property_name'),
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
    const found = notificationTypes.value.find(nt => nt.value === type);
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
                <h2 class="text-lg font-semibold text-gray-900">{{ t('notifications_templates.heading') }}</h2>
                <p class="text-sm text-gray-500">{{ t('notifications_templates.subheading') }}</p>
            </div>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                {{ t('notifications_templates.create') }}
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
                    <div class="flex items-center gap-1 ms-2">
                        <span
                            v-if="template.is_default"
                            class="px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full"
                        >
                            {{ t('notifications_templates.default_badge') }}
                        </span>
                        <span
                            :class="[
                                'w-2 h-2 rounded-full',
                                template.is_active ? 'bg-green-500' : 'bg-gray-300'
                            ]"
                            :title="template.is_active ? t('notifications_templates.status.active') : t('notifications_templates.status.inactive')"
                        ></span>
                    </div>
                </div>

                <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ template.subject }}</p>

                <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                    <button
                        @click="openPreview(template)"
                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                        :title="t('notifications_templates.actions.preview')"
                    >
                        <EyeIcon class="w-4 h-4" />
                    </button>
                    <button
                        @click="openEditModal(template)"
                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        :title="t('notifications_templates.actions.edit')"
                    >
                        <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button
                        @click="duplicateTemplate(template)"
                        class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                        :title="t('notifications_templates.actions.duplicate')"
                    >
                        <DocumentDuplicateIcon class="w-4 h-4" />
                    </button>
                    <button
                        v-if="can('templates:manage') && !template.is_default"
                        @click="deleteTemplate(template)"
                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        :title="t('notifications_templates.actions.delete')"
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
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('notifications_templates.empty.title') }}</h3>
            <p class="text-gray-500 mb-4">{{ t('notifications_templates.empty.body') }}</p>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                {{ t('notifications_templates.create') }}
            </button>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" role="button" tabindex="0" @click="closeModal" @keydown.enter="closeModal" @keydown.space.prevent="closeModal"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ editingTemplate ? t('notifications_templates.modal.edit_title') : t('notifications_templates.modal.create_title') }}
                                </h3>
                                <button @click="closeModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="saveTemplate" class="p-6 space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="tpl-name" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_templates.modal.name_label') }}</label>
                                    <input
                                        id="tpl-name"
                                        v-model="form.name"
                                        type="text"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        :placeholder="t('notifications_templates.modal.name_placeholder')"
                                        required
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                                </div>

                                <div>
                                    <label for="tpl-type" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_templates.modal.type_label') }}</label>
                                    <select
                                        id="tpl-type"
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
                                        <p class="text-sm font-medium text-blue-900">{{ t('notifications_templates.modal.placeholders_title') }}</p>
                                        <p class="text-xs text-blue-700 mt-1">{{ t('notifications_templates.modal.placeholders_hint') }}</p>
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
                                <label for="tpl-subject" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_templates.modal.subject_label') }}</label>
                                <input
                                    id="tpl-subject"
                                    v-model="form.subject"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    :placeholder="t('notifications_templates.modal.subject_placeholder')"
                                    required
                                />
                                <p v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</p>
                            </div>

                            <div>
                                <label for="tpl-body" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_templates.modal.body_label') }}</label>
                                <textarea
                                    id="tpl-body"
                                    v-model="form.body"
                                    rows="8"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                    :placeholder="t('notifications_templates.modal.body_placeholder')"
                                    required
                                ></textarea>
                                <p v-if="form.errors.body" class="text-sm text-red-600 mt-1">{{ form.errors.body }}</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                                <span class="text-sm text-gray-700">{{ t('notifications_templates.modal.is_active') }}</span>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    {{ t('notifications_templates.actions.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                >
                                    <CheckIcon class="w-4 h-4" />
                                    {{ editingTemplate ? t('notifications_templates.modal.update_submit') : t('notifications_templates.modal.create_submit') }}
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
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" role="button" tabindex="0" @click="showPreviewModal = false" @keydown.enter="showPreviewModal = false" @keydown.space.prevent="showPreviewModal = false"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-lg w-full">
                        <div class="border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">{{ t('notifications_templates.preview.title') }}</h3>
                                <button @click="showPreviewModal = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ t('notifications_templates.preview.subject') }}</p>
                                <p class="font-medium text-gray-900">{{ renderPreview(previewTemplate.subject) }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ t('notifications_templates.preview.message') }}</p>
                                <p class="text-gray-700 whitespace-pre-wrap text-sm">{{ renderPreview(previewTemplate.body) }}</p>
                            </div>

                            <p class="text-xs text-gray-400 mt-4 text-center">
                                {{ t('notifications_templates.preview.note') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
