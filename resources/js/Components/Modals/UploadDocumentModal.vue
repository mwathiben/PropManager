<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import type { UploadDocumentModalProps } from '@/types';

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
    description: ''
});

// Map backend value 'User' to user-friendly label 'Tenant'
const documentableLabel = computed(() => {
    return form.documentable_type === 'User' ? 'Tenant' : form.documentable_type;
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
            <h3 class="text-lg font-medium text-gray-900">Upload Document</h3>
        </div>

        <form @submit.prevent="submit">
            <div class="px-6 py-4 space-y-4">
                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        File <span class="text-red-500">*</span>
                    </label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        @change="handleFileSelect"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p class="mt-1 text-xs text-gray-500">Max file size: 10MB. Accepted: PDF, JPG, PNG, DOC, DOCX</p>
                    <p v-if="selectedFile" class="mt-1 text-sm text-green-600">
                        Selected: {{ selectedFile.name }} ({{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB)
                    </p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">
                        {{ form.errors.file }}
                    </p>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        placeholder="e.g., John Doe Lease Agreement 2024"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">
                        {{ form.errors.title }}
                    </p>
                </div>

                <!-- Document Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Document Type <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.document_type"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="lease_agreement">Lease Agreement</option>
                        <option value="tenant_id">Tenant ID</option>
                        <option value="tenant_passport">Passport</option>
                        <option value="bank_statement">Bank Statement</option>
                        <option value="payslip">Payslip</option>
                        <option value="reference_letter">Reference Letter</option>
                        <option value="utility_bill">Utility Bill</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Attach To -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Attach To <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.documentable_type"
                            required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="Lease">Lease</option>
                            <option value="User">Tenant</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ documentableLabel }} ID <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.documentable_id"
                            type="number"
                            required
                            placeholder="Enter ID"
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
                        Description (Optional)
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Add notes about this document..."
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
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Uploading...' : 'Upload Document' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
