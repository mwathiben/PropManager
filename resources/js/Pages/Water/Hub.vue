<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { BeakerIcon, ClipboardDocumentListIcon, ClockIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline';

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

const tabs = computed(() => [
    { id: 'readings', name: 'Readings', icon: ClipboardDocumentListIcon, badge: props.counts?.pendingReadings },
    { id: 'history', name: 'History', icon: ClockIcon },
    { id: 'settings', name: 'Settings', icon: Cog6ToothIcon },
]);

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    readings: ReadingsTab,
    history: HistoryTab,
    settings: SettingsTab,
};

const currentTab = computed(() => props.activeTab || 'readings');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || ReadingsTab);
</script>

<template>
    <HubShell
        title="Water"
        subtitle="Meter readings, history, and billing settings"
        :icon="BeakerIcon"
        accent="cyan"
        route-name="water.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
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
    </HubShell>
</template>
