<script setup lang="ts">
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { TenantDocument } from '@/types';

const props = defineProps<{
    documents?: TenantDocument[];
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();

const documentTypeLabel = (type) => {
    return t(`tenant_profile_documents_tab.document_type.${type}`, type ?? '');
};

const documentTypeClass = (type) => {
    const classes = {
        'lease_agreement': 'bg-purple-100 text-purple-800',
        'tenant_id': 'bg-blue-100 text-blue-800',
        'tenant_passport': 'bg-indigo-100 text-indigo-800',
        'bank_statement': 'bg-green-100 text-green-800',
        'payslip': 'bg-yellow-100 text-yellow-800',
        'reference_letter': 'bg-pink-100 text-pink-800',
        'utility_bill': 'bg-orange-100 text-orange-800',
        'other': 'bg-gray-100 text-gray-800'
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getFileIcon = (mimeType) => {
    if (mimeType === 'application/pdf') {
        return 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z';
    }
    if (mimeType?.startsWith('image/')) {
        return 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z';
    }
    return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
};

const attachmentLabel = (doc) => {
    if (doc.documentable_type?.includes('Lease')) return t('tenant_profile_documents_tab.attachment.lease');
    if (doc.documentable_type?.includes('User')) return t('tenant_profile_documents_tab.attachment.tenant');
    return t('tenant_profile_documents_tab.attachment.other');
};
</script>

<template>
    <div class="space-y-4">
        <div v-if="!documents?.length" class="text-center py-8 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-2 text-sm">{{ t('tenant_profile_documents_tab.no_documents') }}</p>
        </div>

        <ul v-else class="divide-y border rounded-lg overflow-hidden">
            <li v-for="doc in documents" :key="doc.id" class="bg-white p-4 hover:bg-gray-50">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded bg-gray-100 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getFileIcon(doc.mime_type)" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ doc.title || doc.file_name }}</p>
                            <span :class="[documentTypeClass(doc.document_type), 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium']">
                                {{ documentTypeLabel(doc.document_type) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                {{ attachmentLabel(doc) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ doc.file_size_formatted || t('tenant_profile_documents_tab.unknown_size') }} &middot; {{ formatDate(doc.created_at) }}
                        </p>
                        <p v-if="doc.description" class="text-xs text-gray-600 mt-1">{{ doc.description }}</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <a
                            :href="`/documents/${doc.id}/view`"
                            target="_blank"
                            class="text-sm text-blue-600 hover:text-blue-800"
                        >
                            {{ t('tenant_profile_documents_tab.view') }}
                        </a>
                        <a
                            :href="`/documents/${doc.id}/download`"
                            class="text-sm text-gray-600 hover:text-gray-800"
                        >
                            {{ t('tenant_profile_documents_tab.download') }}
                        </a>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
