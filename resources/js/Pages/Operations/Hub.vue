<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import type { OperationsHubPageProps } from '@/types';
import {
    CpuChipIcon,
    BellIcon,
    ArrowUpTrayIcon,
    UserGroupIcon,
    DocumentArrowDownIcon,
    InboxIcon,
} from '@heroicons/vue/24/outline';

const NotificationsTab = defineAsyncComponent({
    loader: () => import('./tabs/NotificationsTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const BulkTab = defineAsyncComponent({
    loader: () => import('./tabs/BulkTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const TeamTab = defineAsyncComponent({
    loader: () => import('./tabs/TeamTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const ImportsTab = defineAsyncComponent({
    loader: () => import('./tabs/ImportsTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const InboxTab = defineAsyncComponent({
    loader: () => import('./tabs/InboxTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const props = defineProps<OperationsHubPageProps>();

const tabs = [
    { id: 'notifications', name: 'Notifications', icon: BellIcon, route: 'operations.hub' },
    { id: 'inbox', name: 'Inbox', icon: InboxIcon, route: 'operations.hub', badgeKey: 'inbox' },
    { id: 'bulk', name: 'Bulk Operations', icon: ArrowUpTrayIcon, route: 'operations.hub' },
    { id: 'team', name: 'Team', icon: UserGroupIcon, route: 'operations.hub' },
    { id: 'imports', name: 'Imports', icon: DocumentArrowDownIcon, route: 'operations.hub' },
];

const tabComponents = {
    notifications: NotificationsTab,
    inbox: InboxTab,
    bulk: BulkTab,
    team: TeamTab,
    imports: ImportsTab,
};

const currentTab = computed(() => props.activeTab || 'notifications');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || NotificationsTab);

const navigateToTab = (tab) => {
    router.get(route(tab.route, { tab: tab.id }), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const prefetchTab = (tab) => {
    if (tab.id === currentTab.value) return;
    router.prefetch(route(tab.route, { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};

const pageTitle = computed(() => {
    const tabName = tabs.find(t => t.id === currentTab.value)?.name || 'Notifications';
    return `Operations - ${tabName}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Operations', href: route('operations.hub') },
    { label: tabs.find(t => t.id === currentTab.value)?.name || 'Notifications' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <CpuChipIcon class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Operations</h1>
                    <p class="text-sm text-gray-500">Notifications, bulk operations, team, and imports</p>
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
                                        ? 'border-purple-500 text-purple-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component
                                    :is="tab.icon"
                                    :class="[
                                        'w-5 h-5',
                                        currentTab === tab.id ? 'text-purple-500' : 'text-gray-400'
                                    ]"
                                />
                                {{ tab.name }}
                                <span
                                    v-if="tab.badgeKey === 'inbox' && inboxUnreadCount > 0"
                                    class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                >
                                    {{ inboxUnreadCount > 99 ? '99+' : inboxUnreadCount }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <component
                            :is="currentTabComponent"
                            :stats="stats"
                            :recent-notifications="recentNotifications"
                            :channel-stats="channelStats"
                            :tenants="tenants"
                            :templates="templates"
                            :scheduled="scheduled"
                            :setup-complete="setupComplete"
                            :buildings-with-counts="buildingsWithCounts"
                            :active-tenant-count="activeTenantCount"
                            :bulk-operations="bulkOperations"
                            :buildings="buildings"
                            :invitations="invitations"
                            :caretakers="caretakers"
                            :imports="imports"
                            :import-templates="importTemplates"
                            :inbox="inbox"
                            :inbox-unread-count="inboxUnreadCount"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
