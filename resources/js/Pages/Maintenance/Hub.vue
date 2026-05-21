<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import {
    WrenchScrewdriverIcon,
    TicketIcon,
    ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    caretakers?: unknown[];
    counts?: Record<string, number>;
    tickets?: Record<string, unknown>;
    stats?: Record<string, unknown>;
}

const props = defineProps<Props>();

const TicketsTab = defineAsyncComponent({
    loader: () => import('./tabs/TicketsTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const tabs = [
    { id: 'tickets', name: 'Maintenance', icon: TicketIcon, badgeKey: 'tickets' },
    { id: 'complaints', name: 'Complaints', icon: ChatBubbleLeftRightIcon, badgeKey: 'complaints' },
];

const currentTab = computed(() => props.activeTab || 'tickets');

const navigateToTab = (tab: { id: string }) => {
    router.get(route('maintenance.hub', { tab: tab.id }), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const prefetchTab = (tab: { id: string }) => {
    if (tab.id === currentTab.value) return;
    router.prefetch(route('maintenance.hub', { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};

const pageTitle = computed(() => {
    const tabName = tabs.find((t) => t.id === currentTab.value)?.name || 'Maintenance';
    return `Maintenance - ${tabName}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Maintenance', href: route('maintenance.hub') },
    { label: tabs.find((t) => t.id === currentTab.value)?.name || 'Maintenance' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <WrenchScrewdriverIcon class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Maintenance</h1>
                    <p class="text-sm text-gray-500">Tickets and complaints across your properties</p>
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
                                        ? 'border-orange-500 text-orange-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component
                                    :is="tab.icon"
                                    :class="['w-5 h-5', currentTab === tab.id ? 'text-orange-500' : 'text-gray-400']"
                                />
                                {{ tab.name }}
                                <span
                                    v-if="counts && counts[tab.badgeKey] > 0"
                                    class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800"
                                >
                                    {{ counts[tab.badgeKey] > 99 ? '99+' : counts[tab.badgeKey] }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <TicketsTab
                            :key="currentTab"
                            :tickets="tickets"
                            :stats="stats"
                            :filters="filters"
                            :buildings="buildings"
                            :caretakers="caretakers"
                            :counts="counts"
                            :active-tab="currentTab"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
