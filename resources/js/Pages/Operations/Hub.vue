<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import HubOverview from '@/Components/Hub/HubOverview.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import type { OperationsHubPageProps } from '@/types';
import {
    CpuChipIcon,
    BellIcon,
    ArrowUpTrayIcon,
    UserGroupIcon,
    DocumentArrowDownIcon,
    InboxIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline';

const props = defineProps<OperationsHubPageProps>();

const NotificationsTab = defineAsyncComponent({ loader: () => import('./tabs/NotificationsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const BulkTab = defineAsyncComponent({ loader: () => import('./tabs/BulkTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const TeamTab = defineAsyncComponent({ loader: () => import('./tabs/TeamTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const ImportsTab = defineAsyncComponent({ loader: () => import('./tabs/ImportsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const InboxTab = defineAsyncComponent({ loader: () => import('./tabs/InboxTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = computed(() => [
    { id: 'overview', name: 'Overview', icon: Squares2X2Icon },
    { id: 'notifications', name: 'Notifications', icon: BellIcon },
    { id: 'inbox', name: 'Inbox', icon: InboxIcon, badge: props.inboxUnreadCount },
    { id: 'bulk', name: 'Bulk Operations', icon: ArrowUpTrayIcon },
    { id: 'team', name: 'Team', icon: UserGroupIcon },
    { id: 'imports', name: 'Imports', icon: DocumentArrowDownIcon },
]);

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    notifications: NotificationsTab,
    inbox: InboxTab,
    bulk: BulkTab,
    team: TeamTab,
    imports: ImportsTab,
};

const currentTab = computed(() => props.activeTab || 'overview');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || NotificationsTab);

const quickLinks = computed(() => tabs.value
    .filter((t) => t.id !== 'overview')
    .map((t) => ({ label: t.name, href: route('operations.hub', { tab: t.id }), icon: t.icon, badge: t.badge })));
</script>

<template>
    <HubShell
        title="Operations"
        subtitle="Notifications, bulk operations, team, and imports"
        :icon="CpuChipIcon"
        accent="purple"
        route-name="operations.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
        <HubOverview v-if="currentTab === 'overview'" :stats="overviewStats" :links="quickLinks" />
        <component
            v-else
            :is="currentTabComponent"
            :key="currentTab"
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
    </HubShell>
</template>
