<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import {
    BeakerIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    counts?: Record<string, number>;
    buildingsData?: unknown[];
    readings?: unknown;
    buildingsList?: unknown[];
    settings?: Record<string, unknown>;
}

const props = defineProps<Props>();

const ReadingsTab = defineAsyncComponent({ loader: () => import('./tabs/ReadingsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const HistoryTab = defineAsyncComponent({ loader: () => import('./tabs/HistoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const SettingsTab = defineAsyncComponent({ loader: () => import('./tabs/SettingsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = [
    { id: 'readings', name: 'Readings', icon: ClipboardDocumentListIcon, badgeKey: 'pendingReadings' },
    { id: 'history', name: 'History', icon: ClockIcon },
    { id: 'settings', name: 'Settings', icon: Cog6ToothIcon },
];

const tabComponents: Record<string, unknown> = {
    readings: ReadingsTab,
    history: HistoryTab,
    settings: SettingsTab,
};

const currentTab = computed(() => props.activeTab || 'readings');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || ReadingsTab);

const navigateToTab = (tab: { id: string }) => {
    router.get(route('water.hub', { tab: tab.id }), {}, { preserveState: true, preserveScroll: true });
};

const prefetchTab = (tab: { id: string }) => {
    if (tab.id === currentTab.value) return;
    router.prefetch(route('water.hub', { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};

const pageTitle = computed(() => `Water - ${tabs.find((t) => t.id === currentTab.value)?.name || 'Readings'}`);
const breadcrumbItems = computed(() => [
    { label: 'Water', href: route('water.hub') },
    { label: tabs.find((t) => t.id === currentTab.value)?.name || 'Readings' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-100 rounded-lg">
                    <BeakerIcon class="w-6 h-6 text-cyan-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Water</h1>
                    <p class="text-sm text-gray-500">Meter readings, history, and billing settings</p>
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
                                        ? 'border-cyan-500 text-cyan-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component :is="tab.icon" :class="['w-5 h-5', currentTab === tab.id ? 'text-cyan-500' : 'text-gray-400']" />
                                {{ tab.name }}
                                <span
                                    v-if="tab.badgeKey && counts && counts[tab.badgeKey] > 0"
                                    class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800"
                                >
                                    {{ counts[tab.badgeKey] > 99 ? '99+' : counts[tab.badgeKey] }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <component
                            :is="currentTabComponent"
                            :key="currentTab"
                            :buildings-data="buildingsData"
                            :readings="readings"
                            :buildings-list="buildingsList"
                            :settings="settings"
                            :buildings="buildings"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
