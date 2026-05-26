<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/composables/useI18n';
import type { UploadDocumentModalProps } from '@/types';

const { t } = useI18n();

const props = defineProps<UploadDocumentModalProps>();

const emit = defineEmits(['close']);

const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const form = useForm({
    file: null as File | null,
    title: '',
    document_type: 'other',
    documentable_type: 'Lease',
    documentable_id: '',
    description: '',
    // Phase-82 DOC-META-2: document lifecycle fields.
    issue_date: '',
    expires_at: '',
    is_renewable: false as boolean,
    reminder_days: '' as number | string,
});

// Map backend value 'User' to user-friendly label 'Tenant'
const documentableLabel = computed(() => {
    return form.documentable_type === 'User'
        ? t('upload_document_modal.option_tenant')
        : t('upload_document_modal.option_lease');
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        selectedFile.value = file;
        form.file = file;

        // Auto-fill title from filename if empty
        if (!form.title) {
            form.title = file.name.replace(/\.[^/.]+$/, '');
        }
    }
};

const submit = () => {
    form.post(route('documents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedFile.value = null;
            emit('close');
        }
    });
};

const close = () => {
    form.reset();
    selectedFile.value = null;
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">{{ t('upload_document_modal.title') }}</h3>
        </div>

        <form @submit.prevent="submit">
            <div class="px-6 py-4 space-y-4">
                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('upload_document_modal.file_label') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        @change="handleFileSelect"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('upload_document_modal.file_hint') }}</p>
                    <p v-if="selectedFile" class="mt-1 text-sm text-green-600">
                        {{ t('upload_document_modal.selected_file', { name: selectedFile.name, size: (selectedFile.size / 1024 / 1024).toFixed(2) }) }}
                    </p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">
                        {{ form.errors.file }}
                    </p>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('upload_document_modal.title_label') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        :placeholder="t('upload_document_modal.title_placeholder')"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">
                        {{ form.errors.title }}
                    </p>
                </div>

                <!-- Document Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('upload_document_modal.document_type_label') }} <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.document_type"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="lease_agreement">{{ $t('document.types.lease_agreement') }}</option>
                        <option value="tenant_id">{{ $t('document.types.tenant_id') }}</option>
                        <option value="tenant_passport">{{ $t('document.types.tenant_passport') }}</option>
                        <option value="bank_statement">{{ $t('document.types.bank_statement') }}</option>
                        <option value="payslip">{{ $t('document.types.payslip') }}</option>
                        <option value="reference_letter">{{ $t('document.types.reference_letter') }}</option>
                        <option value="utility_bill">{{ $t('document.types.utility_bill') }}</option>
                        <option value="insurance">{{ $t('document.types.insurance') }}</option>
                        <option value="compliance_cert">{{ $t('document.types.compliance_cert') }}</option>
                        <option value="title_deed">{{ $t('document.types.title_deed') }}</option>
                        <option value="inspection_report">{{ $t('document.types.inspection_report') }}</option>
                        <option value="other">{{ $t('document.types.other') }}</option>
                    </select>
                </div>

                <!-- Phase-82 DOC-META-2: lifecycle (expiry / issue date / renewal). -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.fields.issue_date') }}</label>
                        <input
                            v-model="form.issue_date"
                            type="date"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.issue_date" class="mt-1 text-sm text-red-600">{{ form.errors.issue_date }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.fields.expires_at') }}</label>
                        <input
                            v-model="form.expires_at"
                            type="date"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600">{{ form.errors.expires_at }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input id="doc-renewable" v-model="form.is_renewable" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="doc-renewable" class="text-sm text-gray-700">{{ $t('document.fields.renewable') }}</label>
                </div>

                <div v-if="form.is_renewable">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.fields.reminder_days') }}</label>
                    <input
                        v-model="form.reminder_days"
                        type="number"
                        min="1"
                        max="365"
                        :placeholder="$t('document.fields.reminder_days_hint')"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p v-if="form.errors.reminder_days" class="mt-1 text-sm text-red-600">{{ form.errors.reminder_days }}</p>
                </div>

                <!-- Attach To -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('upload_document_modal.attach_to_label') }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.documentable_type"
                            required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="Lease">{{ t('upload_document_modal.option_lease') }}</option>
                            <option value="User">{{ t('upload_document_modal.option_tenant') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('upload_document_modal.documentable_id_label', { label: documentableLabel }) }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.documentable_id"
                            type="number"
                            required
                            :placeholder="t('upload_document_modal.documentable_id_placeholder')"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.documentable_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.documentable_id }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('upload_document_modal.description_label') }}
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        :placeholder="t('upload_document_modal.description_placeholder')"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                    {{ t('upload_document_modal.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ form.processing ? t('upload_document_modal.uploading') : t('upload_document_modal.submit') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
