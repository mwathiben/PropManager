<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { ArchiveBoxIcon, DocumentTextIcon, DocumentDuplicateIcon, ClockIcon } from '@heroicons/vue/24/outline';

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

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    documents: DocumentsTab,
    leases: LeasesTab,
    activity: ActivityTab,
};

const currentTab = computed(() => props.activeTab || 'documents');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DocumentsTab);
</script>

<template>
    <HubShell
        title="Archive"
        subtitle="Documents, leases, and tenant activity"
        :icon="ArchiveBoxIcon"
        accent="gray"
        route-name="archive.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
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
    </HubShell>
</template>
