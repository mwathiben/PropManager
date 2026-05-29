<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import HubOverview from '@/Components/Hub/HubOverview.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { ArchiveBoxIcon, DocumentTextIcon, DocumentDuplicateIcon, ClockIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

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
    overviewStats?: Array<{ label: string; value: string | number; tone?: string }>;
}

const props = defineProps<Props>();
const { t } = useI18n();

const DocumentsTab = defineAsyncComponent({ loader: () => import('./tabs/DocumentsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const LeasesTab = defineAsyncComponent({ loader: () => import('./tabs/LeasesTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const ActivityTab = defineAsyncComponent({ loader: () => import('./tabs/ActivityTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = [
    { id: 'overview', name: 'Overview', icon: Squares2X2Icon },
    { id: 'documents', name: 'Documents', icon: DocumentTextIcon },
    { id: 'leases', name: 'Leases', icon: DocumentDuplicateIcon },
    { id: 'activity', name: 'Activity', icon: ClockIcon },
];

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    documents: DocumentsTab,
    leases: LeasesTab,
    activity: ActivityTab,
};

const currentTab = computed(() => props.activeTab || 'overview');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DocumentsTab);

const quickLinks = computed(() => tabs
    .filter((tab) => tab.id !== 'overview')
    .map((tab) => ({ label: tab.name, href: route('archive.hub', { tab: tab.id }), icon: tab.icon })));
</script>

<template>
    <HubShell
        title="Archive"
        :subtitle="t('archive_hub.subtitle')"
        :icon="ArchiveBoxIcon"
        accent="gray"
        route-name="archive.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
        <HubOverview v-if="currentTab === 'overview'" :stats="overviewStats" :links="quickLinks" />
        <component
            v-else
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
