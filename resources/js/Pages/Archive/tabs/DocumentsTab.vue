<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import {
    MagnifyingGlassIcon,
    DocumentTextIcon,
    EyeIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

interface DocRow {
    id: number;
    name: string | null;
    original_name: string | null;
    type: string | null;
    documentable_type?: string | null;
    created_at: string;
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
    documentTypes?: { value: string; label: string }[];
    buildings?: { id: number; name: string }[];
    filters?: { search?: string; type?: string };
}>();

const { formatDate } = useFormatters();

const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');

const applyFilters = () => {
    router.get(route('archive.hub', { tab: 'documents' }), {
        search: search.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    type.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => !!(search.value || type.value));

const labelFor = (value: string | null): string =>
    props.documentTypes?.find((t) => t.value === value)?.label || value || '—';
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
                            placeholder="Search documents..."
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
                    <option value="">All Types</option>
                    <option v-for="t in documentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">Clear filters</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="documents?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ doc.name || doc.original_name || 'Document' }}</div>
                            <div v-if="doc.original_name && doc.name" class="text-xs text-gray-500">{{ doc.original_name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ labelFor(doc.type) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(doc.created_at) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-end">
                            <div class="flex items-center justify-end gap-2">
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
                title="No documents found"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'Uploaded documents will appear here.'"
            />

            <div v-if="documents?.data?.length && documents.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">Showing {{ documents.from }} to {{ documents.to }} of {{ documents.total }} results</div>
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
    </div>
</template>
