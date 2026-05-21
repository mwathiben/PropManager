<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { WrenchScrewdriverIcon, TicketIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

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

const currentTab = computed(() => props.activeTab || 'tickets');

const tabs = computed(() => [
    { id: 'tickets', name: 'Maintenance', icon: TicketIcon, badge: props.counts?.tickets },
    { id: 'complaints', name: 'Complaints', icon: ChatBubbleLeftRightIcon, badge: props.counts?.complaints },
]);
</script>

<template>
    <HubShell
        title="Maintenance"
        subtitle="Tickets and complaints across your properties"
        :icon="WrenchScrewdriverIcon"
        accent="orange"
        route-name="maintenance.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
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
    </HubShell>
</template>
