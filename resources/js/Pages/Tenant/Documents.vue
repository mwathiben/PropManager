<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    DocumentTextIcon,
    ReceiptPercentIcon,
    IdentificationIcon,
    ArrowDownTrayIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';

interface DocumentRow {
    id: number;
    type: string;
    title: string;
    document_type: string;
    size: string;
    mime: string;
    date: string | null;
    expires_at: string | null;
    download_url: string;
}

interface ReceiptRow {
    id: number;
    type: 'receipt';
    title: string;
    description: string;
    amount: number;
    date: string | null;
    download_url: string;
}

const props = defineProps<{
    leaseDocuments: DocumentRow[];
    receipts: ReceiptRow[];
    kycDocuments: DocumentRow[];
}>();

type Category = 'all' | 'lease' | 'receipts' | 'kyc';
const activeCategory = ref<Category>('all');

const tabs: { key: Category; label: string; icon: any; count: number }[] = [
    { key: 'all', label: 'All', icon: DocumentTextIcon, count: props.leaseDocuments.length + props.receipts.length + props.kycDocuments.length },
    { key: 'lease', label: 'Lease', icon: DocumentTextIcon, count: props.leaseDocuments.length },
    { key: 'receipts', label: 'Receipts', icon: ReceiptPercentIcon, count: props.receipts.length },
    { key: 'kyc', label: 'KYC', icon: IdentificationIcon, count: props.kycDocuments.length },
];

const documentsByCategory = computed(() => {
    const lease = props.leaseDocuments;
    const kyc = props.kycDocuments;
    const receipts = props.receipts;

    if (activeCategory.value === 'lease') return { docs: lease, receipts: [] };
    if (activeCategory.value === 'kyc') return { docs: kyc, receipts: [] };
    if (activeCategory.value === 'receipts') return { docs: [], receipts };
    return { docs: [...lease, ...kyc], receipts };
});

const formatMoney = (value: number) =>
    value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="My Documents" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">My Documents</h1>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <nav class="flex gap-2 border-b border-gray-200" aria-label="Document categories">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        @click="activeCategory = tab.key"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 text-sm font-medium border-b-2 -mb-px',
                            activeCategory === tab.key
                                ? 'border-indigo-500 text-indigo-700'
                                : 'border-transparent text-gray-600 hover:text-gray-900',
                        ]"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.label }}
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-xs">{{ tab.count }}</span>
                    </button>
                </nav>

                <ul v-if="documentsByCategory.docs.length || documentsByCategory.receipts.length" class="space-y-2">
                    <li
                        v-for="doc in documentsByCategory.docs"
                        :key="`doc-${doc.id}`"
                        class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-4"
                    >
                        <div class="p-2 bg-indigo-50 rounded-lg">
                            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ doc.title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ doc.document_type }} &middot; {{ doc.size }}
                                <span v-if="doc.date"> &middot; uploaded {{ doc.date }}</span>
                                <span
                                    v-if="doc.expires_at"
                                    class="ms-2 inline-flex items-center gap-1 text-amber-700"
                                >
                                    <CalendarDaysIcon class="w-3 h-3" />
                                    expires {{ doc.expires_at }}
                                </span>
                            </p>
                        </div>
                        <a
                            :href="doc.download_url"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Download
                        </a>
                    </li>

                    <li
                        v-for="r in documentsByCategory.receipts"
                        :key="`r-${r.id}`"
                        class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-4"
                    >
                        <div class="p-2 bg-emerald-50 rounded-lg">
                            <ReceiptPercentIcon class="w-6 h-6 text-emerald-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ r.title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ r.description }} &middot; KES {{ formatMoney(r.amount) }}
                                <span v-if="r.date"> &middot; {{ r.date }}</span>
                            </p>
                        </div>
                        <a
                            :href="r.download_url"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md text-emerald-700 bg-emerald-50 hover:bg-emerald-100"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Receipt PDF
                        </a>
                    </li>
                </ul>

                <p v-else class="text-center text-sm text-gray-500 py-12">
                    No documents in this category yet.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
