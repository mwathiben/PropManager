<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ClipboardDocumentCheckIcon from '@heroicons/vue/24/outline/ClipboardDocumentCheckIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import PencilIcon from '@heroicons/vue/24/outline/PencilIcon';
import TrashIcon from '@heroicons/vue/24/outline/TrashIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import CheckIcon from '@heroicons/vue/24/outline/CheckIcon';
import StarIcon from '@heroicons/vue/24/outline/StarIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import Bars3Icon from '@heroicons/vue/24/outline/Bars3Icon';
import ExclamationCircleIcon from '@heroicons/vue/24/outline/ExclamationCircleIcon';
import StarIconSolid from '@heroicons/vue/24/solid/StarIcon';
import EmptyState from '@/Components/EmptyState.vue';
import { useAuth } from '@/composables/useAuth';
import { useI18n } from '@/composables/useI18n';
import type { VerificationTemplatesPageProps } from '@/types';

const props = withDefaults(defineProps<VerificationTemplatesPageProps>(), {
    templates: () => [],
    properties: () => [],
});

const { can } = useAuth();
const { t } = useI18n();

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingTemplate = ref(null);

// Create form
const createForm = useForm({
    name: '',
    property_id: '',
    is_default: false,
    items: [
        { name: '', document_type: '', description: '', is_required: true },
    ],
});

// Edit form
const editForm = useForm({
    name: '',
    property_id: '',
    is_default: false,
    items: [],
});

// Document type suggestions
const documentTypes = [
    'tenant_id',
    'tenant_passport',
    'bank_statement',
    'payslip',
    'reference_letter',
    'utility_bill',
    'employment_letter',
    'other',
];

// Helpers
const addItem = (form) => {
    form.items.push({ name: '', document_type: '', description: '', is_required: true });
};

const removeItem = (form, index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const moveItem = (form, index, direction) => {
    const newIndex = index + direction;
    if (newIndex >= 0 && newIndex < form.items.length) {
        const item = form.items.splice(index, 1)[0];
        form.items.splice(newIndex, 0, item);
    }
};

const openEditModal = (template) => {
    editingTemplate.value = template;
    editForm.name = template.name;
    editForm.property_id = template.property_id || '';
    editForm.is_default = template.is_default;
    editForm.items = template.items.map(item => ({
        id: item.id,
        name: item.name,
        document_type: item.document_type || '',
        description: item.description || '',
        is_required: item.is_required,
    }));
    showEditModal.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    editingTemplate.value = null;
    createForm.reset();
    editForm.reset();
};

// Actions
const createTemplate = () => {
    createForm.post(route('verifications.templates.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModals();
        },
    });
};

const updateTemplate = () => {
    if (!editingTemplate.value) return;
    editForm.put(route('verifications.templates.update', editingTemplate.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModals();
        },
    });
};

const deleteTemplate = (templateId) => {
    if (confirm(t('verifications_templates.confirm.delete'))) {
        router.delete(route('verifications.templates.destroy', templateId), { preserveScroll: true });
    }
};

// Stats
const totalTemplates = computed(() => props.templates.length);
const defaultTemplate = computed(() => props.templates.find(tpl => tpl.is_default));
</script>

<template>
    <Head :title="t('verifications_templates.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <Link :href="route('tenants.index')" class="text-gray-400 hover:text-gray-600">
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ t('verifications_templates.title') }}</h1>
                            <p class="text-sm text-gray-500">{{ t('verifications_templates.subtitle') }}</p>
                        </div>
                    </div>
                    <button
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <PlusIcon class="w-5 h-5" />
                        {{ t('verifications_templates.new_template') }}
                    </button>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="text-2xl font-bold text-gray-900">{{ totalTemplates }}</div>
                        <div class="text-sm text-gray-500">{{ t('verifications_templates.stats.total') }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="text-2xl font-bold text-indigo-600">{{ defaultTemplate?.name || t('verifications_templates.none') }}</div>
                        <div class="text-sm text-gray-500">{{ t('verifications_templates.stats.default') }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ defaultTemplate?.items_count || 0 }}
                        </div>
                        <div class="text-sm text-gray-500">{{ t('verifications_templates.stats.items_in_default') }}</div>
                    </div>
                </div>

                <!-- Templates List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        <div v-for="template in templates" :key="template.id" class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center">
                                        <ClipboardDocumentCheckIcon class="w-6 h-6 text-indigo-600" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-lg font-semibold text-gray-900">{{ template.name }}</h3>
                                            <span v-if="template.is_default" class="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">
                                                <StarIconSolid class="w-3 h-3" />
                                                {{ t('verifications_templates.default_badge') }}
                                            </span>
                                        </div>
                                        <p v-if="template.property" class="text-sm text-gray-500 mt-1">
                                            {{ t('verifications_templates.property_label', { name: template.property.name }) }}
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ t('verifications_templates.items_count', { count: template.items_count }) }}
                                        </p>

                                        <!-- Items Preview -->
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="item in template.items.slice(0, 5)"
                                                :key="item.id"
                                                class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-lg"
                                            >
                                                <DocumentTextIcon class="w-3 h-3" />
                                                {{ item.name }}
                                                <ExclamationCircleIcon v-if="item.is_required" class="w-3 h-3 text-red-500" :title="t('verifications_templates.actions.required')" />
                                            </span>
                                            <span v-if="template.items.length > 5" class="text-xs text-gray-500 px-2 py-1">
                                                {{ t('verifications_templates.more', { count: template.items.length - 5 }) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="openEditModal(template)"
                                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg"
                                        :title="t('verifications_templates.actions.edit')"
                                    >
                                        <PencilIcon class="w-5 h-5" />
                                    </button>
                                    <button
                                        v-if="can('templates:manage')"
                                        @click="deleteTemplate(template.id)"
                                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg"
                                        :title="t('verifications_templates.actions.delete')"
                                    >
                                        <TrashIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <EmptyState
                        v-if="!templates.length"
                        :icon="ClipboardDocumentCheckIcon"
                        :title="t('verifications_templates.empty.title')"
                        :description="t('verifications_templates.empty.description')"
                        :action-label="t('verifications_templates.new_template')"
                        @action="showCreateModal = true"
                    />
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" role="button" tabindex="0" @click="closeModals" @keydown.enter="closeModals" @keydown.space.prevent="closeModals"></div>

                <div class="relative z-50 inline-block w-full max-w-3xl my-8 overflow-hidden text-start align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('verifications_templates.create.title') }}</h3>
                        <button @click="closeModals" class="text-gray-400 hover:text-gray-500">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <form @submit.prevent="createTemplate" class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                        <!-- Template Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="create-template-name" class="block text-sm font-medium text-gray-700 mb-1">{{ t('verifications_templates.form.name') }}</label>
                                <input
                                    id="create-template-name"
                                    v-model="createForm.name"
                                    type="text"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    :placeholder="t('verifications_templates.form.name_placeholder')"
                                />
                            </div>
                            <div>
                                <label for="create-template-property" class="block text-sm font-medium text-gray-700 mb-1">{{ t('verifications_templates.form.property') }}</label>
                                <select
                                    id="create-template-property"
                                    v-model="createForm.property_id"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">{{ t('verifications_templates.form.all_properties') }}</option>
                                    <option v-for="property in properties" :key="property.id" :value="property.id">
                                        {{ property.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model="createForm.is_default"
                                type="checkbox"
                                id="create_is_default"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                            <label for="create_is_default" class="text-sm text-gray-700">{{ t('verifications_templates.form.set_default') }}</label>
                        </div>

                        <!-- Items -->
                        <div class="border-t pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-900">{{ t('verifications_templates.form.items_heading') }}</h4>
                                <button
                                    type="button"
                                    @click="addItem(createForm)"
                                    class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    <PlusIcon class="w-4 h-4" />
                                    {{ t('verifications_templates.actions.add_item') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(item, index) in createForm.items"
                                    :key="index"
                                    class="p-4 bg-gray-50 rounded-lg border border-gray-200"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex flex-col gap-1">
                                            <button
                                                type="button"
                                                @click="moveItem(createForm, index, -1)"
                                                :disabled="index === 0"
                                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="moveItem(createForm, index, 1)"
                                                :disabled="index === createForm.items.length - 1"
                                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    required
                                                    :id="`create-item-name-${index}`"
                                                    :aria-label="t('verifications_templates.form.item_name_placeholder')"
                                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                    :placeholder="t('verifications_templates.form.item_name_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <select
                                                    v-model="item.document_type"
                                                    :id="`create-item-doctype-${index}`"
                                                    :aria-label="t('verifications_templates.form.document_type')"
                                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                >
                                                    <option value="">{{ t('verifications_templates.form.document_type') }}</option>
                                                    <option v-for="type in documentTypes" :key="type" :value="type">
                                                        {{ type.replace(/_/g, ' ') }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    v-model="item.is_required"
                                                    type="checkbox"
                                                    :id="`create_item_required_${index}`"
                                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                />
                                                <label :for="`create_item_required_${index}`" class="text-sm text-gray-600">{{ t('verifications_templates.actions.required') }}</label>
                                            </div>
                                        </div>
                                        <button
                                            v-if="createForm.items.length > 1"
                                            type="button"
                                            @click="removeItem(createForm, index)"
                                            class="p-1 text-red-500 hover:text-red-700"
                                        >
                                            <XMarkIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <div class="mt-2 ms-10">
                                        <input
                                            v-model="item.description"
                                            type="text"
                                            :id="`create-item-desc-${index}`"
                                            :aria-label="t('verifications_templates.form.description_placeholder')"
                                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                            :placeholder="t('verifications_templates.form.description_placeholder')"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="closeModals"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                {{ t('verifications_templates.actions.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <CheckIcon class="w-5 h-5" />
                                {{ createForm.processing ? t('verifications_templates.create.creating') : t('verifications_templates.create.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal && editingTemplate" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" role="button" tabindex="0" @click="closeModals" @keydown.enter="closeModals" @keydown.space.prevent="closeModals"></div>

                <div class="relative z-50 inline-block w-full max-w-3xl my-8 overflow-hidden text-start align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('verifications_templates.edit.title', { name: editingTemplate.name }) }}</h3>
                        <button @click="closeModals" class="text-gray-400 hover:text-gray-500">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <form @submit.prevent="updateTemplate" class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                        <!-- Template Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="edit-template-name" class="block text-sm font-medium text-gray-700 mb-1">{{ t('verifications_templates.form.name') }}</label>
                                <input
                                    id="edit-template-name"
                                    v-model="editForm.name"
                                    type="text"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label for="edit-template-property" class="block text-sm font-medium text-gray-700 mb-1">{{ t('verifications_templates.form.property') }}</label>
                                <select
                                    id="edit-template-property"
                                    v-model="editForm.property_id"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">{{ t('verifications_templates.form.all_properties') }}</option>
                                    <option v-for="property in properties" :key="property.id" :value="property.id">
                                        {{ property.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model="editForm.is_default"
                                type="checkbox"
                                id="edit_is_default"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                            <label for="edit_is_default" class="text-sm text-gray-700">{{ t('verifications_templates.form.set_default') }}</label>
                        </div>

                        <!-- Items -->
                        <div class="border-t pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-900">{{ t('verifications_templates.form.items_heading') }}</h4>
                                <button
                                    type="button"
                                    @click="addItem(editForm)"
                                    class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    <PlusIcon class="w-4 h-4" />
                                    {{ t('verifications_templates.actions.add_item') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(item, index) in editForm.items"
                                    :key="index"
                                    class="p-4 bg-gray-50 rounded-lg border border-gray-200"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex flex-col gap-1">
                                            <button
                                                type="button"
                                                @click="moveItem(editForm, index, -1)"
                                                :disabled="index === 0"
                                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="moveItem(editForm, index, 1)"
                                                :disabled="index === editForm.items.length - 1"
                                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    required
                                                    :id="`edit-item-name-${index}`"
                                                    :aria-label="t('verifications_templates.form.item_name_placeholder')"
                                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                    :placeholder="t('verifications_templates.form.item_name_placeholder')"
                                                />
                                            </div>
                                            <div>
                                                <select
                                                    v-model="item.document_type"
                                                    :id="`edit-item-doctype-${index}`"
                                                    :aria-label="t('verifications_templates.form.document_type')"
                                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                >
                                                    <option value="">{{ t('verifications_templates.form.document_type') }}</option>
                                                    <option v-for="type in documentTypes" :key="type" :value="type">
                                                        {{ type.replace(/_/g, ' ') }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    v-model="item.is_required"
                                                    type="checkbox"
                                                    :id="`edit_item_required_${index}`"
                                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                />
                                                <label :for="`edit_item_required_${index}`" class="text-sm text-gray-600">{{ t('verifications_templates.actions.required') }}</label>
                                            </div>
                                        </div>
                                        <button
                                            v-if="editForm.items.length > 1"
                                            type="button"
                                            @click="removeItem(editForm, index)"
                                            class="p-1 text-red-500 hover:text-red-700"
                                        >
                                            <XMarkIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <div class="mt-2 ms-10">
                                        <input
                                            v-model="item.description"
                                            type="text"
                                            :id="`edit-item-desc-${index}`"
                                            :aria-label="t('verifications_templates.form.description_placeholder')"
                                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                            :placeholder="t('verifications_templates.form.description_placeholder')"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="closeModals"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                {{ t('verifications_templates.actions.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <CheckIcon class="w-5 h-5" />
                                {{ editForm.processing ? t('verifications_templates.edit.saving') : t('verifications_templates.edit.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
