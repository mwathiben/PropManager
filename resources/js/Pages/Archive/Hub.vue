<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import {
    ArchiveBoxIcon,
    DocumentTextIcon,
    DocumentDuplicateIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    buildingsWithWings?: unknown[];
    documents?: Record<string, unknown>;
    documentTypes?: unknown[];
    leases?: Record<string, unknown>;
    activities?: Record<string, unknown>;
    activityTypes?: unknown[];
    stats?: Record<string, unknown>;
}

const props = defineProps<Props>();

const DocumentsTab = defineAsyncComponent({ loader: () => import('./tabs/DocumentsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const LeasesTab = defineAsyncComponent({ loader: () => import('./tabs/LeasesTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const ActivityTab = defineAsyncComponent({ loader: () => import('./tabs/ActivityTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = [
    { id: 'documents', name: 'Documents', icon: DocumentTextIcon },
    { id: 'leases', name: 'Leases', icon: DocumentDuplicateIcon },
    { id: 'activity', name: 'Activity', icon: ClockIcon },
];

const tabComponents: Record<string, unknown> = {
    documents: DocumentsTab,
    leases: LeasesTab,
    activity: ActivityTab,
};

const currentTab = computed(() => props.activeTab || 'documents');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DocumentsTab);

const navigateToTab = (tab: { id: string }) => {
    router.get(route('archive.hub', { tab: tab.id }), {}, { preserveState: true, preserveScroll: true });
};

const prefetchTab = (tab: { id: string }) => {
    if (tab.id === currentTab.value) return;
    router.prefetch(route('archive.hub', { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};

const pageTitle = computed(() => `Archive - ${tabs.find((t) => t.id === currentTab.value)?.name || 'Documents'}`);
const breadcrumbItems = computed(() => [
    { label: 'Archive', href: route('archive.hub') },
    { label: tabs.find((t) => t.id === currentTab.value)?.name || 'Documents' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gray-100 rounded-lg">
                    <ArchiveBoxIcon class="w-6 h-6 text-gray-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Archive</h1>
                    <p class="text-sm text-gray-500">Documents, leases, and tenant activity</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="navigateToTab(tab)"
                                @mouseenter="prefetchTab(tab)"
                                :class="[
                                    'flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    currentTab === tab.id
                                        ? 'border-gray-800 text-gray-900'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component :is="tab.icon" :class="['w-5 h-5', currentTab === tab.id ? 'text-gray-700' : 'text-gray-400']" />
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <component
                            :is="currentTabComponent"
                            :key="currentTab"
                            :documents="documents"
                            :document-types="documentTypes"
                            :leases="leases"
                            :activities="activities"
                            :activity-types="activityTypes"
                            :stats="stats"
                            :buildings="buildings"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
