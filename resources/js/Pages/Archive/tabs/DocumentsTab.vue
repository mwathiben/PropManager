<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import Modal from '@/Components/Modal.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import {
    MagnifyingGlassIcon,
    DocumentTextIcon,
    EyeIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

type ExpiryStatus = 'expired' | 'expiring_soon' | 'valid' | 'none';

interface DocRow {
    id: number;
    title: string | null;
    file_name: string | null;
    document_type: string | null;
    document_type_label: string | null;
    documentable_type?: string | null;
    expires_at: string | null;
    expiry_status: ExpiryStatus;
    is_renewable: boolean;
    uploaded_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
}

const props = defineProps<{
    documents?: Paginator<DocRow>;
    documentTypes?: Option[];
    expiryFilters?: Option[];
    buildings?: { id: number; name: string }[];
    filters?: { search?: string; type?: string; expiry?: string };
}>();

const { formatDate } = useFormatters();

const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');
const expiry = ref(props.filters?.expiry || '');

const applyFilters = () => {
    router.get(route('archive.hub', { tab: 'documents' }), {
        search: search.value || undefined,
        type: type.value || undefined,
        expiry: expiry.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    type.value = '';
    expiry.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => !!(search.value || type.value || expiry.value));

const expiryChipClass = (status: ExpiryStatus): string => {
    switch (status) {
        case 'expired':
            return 'bg-red-100 text-red-800';
        case 'expiring_soon':
            return 'bg-amber-100 text-amber-800';
        case 'valid':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-600';
    }
};

const canRenew = (doc: DocRow): boolean =>
    doc.is_renewable && (doc.expiry_status === 'expired' || doc.expiry_status === 'expiring_soon');

const renewDoc = ref<DocRow | null>(null);
const renewForm = useForm({
    file: null as File | null,
    expires_at: '',
});

const openRenew = (doc: DocRow) => {
    renewDoc.value = doc;
    renewForm.reset();
    renewForm.clearErrors();
};

const handleRenewFile = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    renewForm.file = file;
};

const submitRenew = () => {
    if (!renewDoc.value) {
        return;
    }
    renewForm.post(route('documents.renew', renewDoc.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            renewDoc.value = null;
            renewForm.reset();
        },
    });
};
</script>

<template>
    <div>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            :placeholder="$t('document.search')"
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                        />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select
                    v-model="type"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                >
                    <option value="">{{ $t('document.all_types') }}</option>
                    <option v-for="t in documentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <select
                    v-model="expiry"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                >
                    <option v-for="f in expiryFilters" :key="f.value" :value="f.value">{{ f.label }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ $t('document.clear_filters') }}</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="documents?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('document.label') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('document.type') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('document.expiry.column') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('document.uploaded') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('document.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ doc.title || doc.file_name || $t('document.label') }}</div>
                            <div v-if="doc.file_name && doc.title" class="text-xs text-gray-500">{{ doc.file_name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ doc.document_type_label || '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', expiryChipClass(doc.expiry_status)]">
                                    {{ $t('document.expiry.' + doc.expiry_status) }}
                                </span>
                                <span v-if="doc.expires_at" class="text-gray-500">{{ formatDate(doc.expires_at) }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ doc.uploaded_at ? formatDate(doc.uploaded_at) : '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-end">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    v-if="canRenew(doc)"
                                    type="button"
                                    @click="openRenew(doc)"
                                    :title="$t('document.renewal.renew')"
                                    class="text-amber-600 hover:text-amber-800"
                                >
                                    <ArrowPathIcon class="w-5 h-5" />
                                </button>
                                <a :href="route('documents.view', doc.id)" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900">
                                    <EyeIcon class="w-5 h-5" />
                                </a>
                                <a :href="route('documents.download', doc.id)" class="text-gray-600 hover:text-gray-900">
                                    <ArrowDownTrayIcon class="w-5 h-5" />
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                :icon="DocumentTextIcon"
                :title="$t('document.empty.title')"
                :description="hasActiveFilters ? $t('document.empty.filtered') : $t('document.empty.description')"
            />

            <div v-if="documents?.data?.length && documents.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ $t('document.showing', { from: documents.from, to: documents.to, total: documents.total }) }}</div>
                    <div class="flex space-x-2">
                        <Link
                            v-for="link in documents.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']"
                        >
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="renewDoc !== null" max-width="lg" @close="renewDoc = null">
            <form @submit.prevent="submitRenew">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ $t('document.renewal.title') }}</h3>
                    <p v-if="renewDoc" class="mt-1 text-sm text-gray-500">{{ renewDoc.title || renewDoc.file_name }}</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $t('document.label') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="file"
                            @change="handleRenewFile"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500"
                        />
                        <p v-if="renewForm.errors.file" class="mt-1 text-sm text-red-600">{{ renewForm.errors.file }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $t('document.renewal.new_expiry') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="renewForm.expires_at"
                            type="date"
                            required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500"
                        />
                        <p v-if="renewForm.errors.expires_at" class="mt-1 text-sm text-red-600">{{ renewForm.errors.expires_at }}</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                    <button type="button" @click="renewDoc = null" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        {{ $t('document.cancel') }}
                    </button>
                    <button type="submit" :disabled="renewForm.processing" class="px-4 py-2 bg-gray-900 text-white rounded-md hover:bg-gray-800 disabled:opacity-50">
                        {{ $t('document.renewal.submit') }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
