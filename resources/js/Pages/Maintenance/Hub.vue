<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Link } from '@inertiajs/vue3';
import HubShell from '@/Components/Hub/HubShell.vue';
import HubOverview from '@/Components/Hub/HubOverview.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { WrenchScrewdriverIcon, TicketIcon, ChatBubbleLeftRightIcon, ChartBarIcon, CubeIcon, PhotoIcon, UserGroupIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    caretakers?: unknown[];
    counts?: Record<string, number>;
    tickets?: Record<string, unknown>;
    stats?: Record<string, unknown>;
    overviewStats?: Array<{ label: string; value: string | number; tone?: string }>;
}

const props = defineProps<Props>();

const TicketsTab = defineAsyncComponent({
    loader: () => import('./tabs/TicketsTab.vue'),
    loadingComponent: TabLoadingPlaceholder,
    delay: 100,
});

const currentTab = computed(() => props.activeTab || 'overview');

const tabs = computed(() => [
    { id: 'overview', name: 'Overview', icon: Squares2X2Icon },
    { id: 'tickets', name: 'Maintenance', icon: TicketIcon, badge: props.counts?.tickets },
    { id: 'complaints', name: 'Complaints', icon: ChatBubbleLeftRightIcon, badge: props.counts?.complaints },
]);

const quickLinks = computed(() => tabs.value
    .filter((t) => t.id !== 'overview')
    .map((t) => ({ label: t.name, href: route('maintenance.hub', { tab: t.id }), icon: t.icon, badge: t.badge })));
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
        <template #actions>
            <Link
                :href="route('maintenance.vendor-performance')"
                class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
            >
                <ChartBarIcon class="h-4 w-4" /> {{ $t('vendors.performance.title') }}
            </Link>
            <Link
                :href="route('maintenance.caretaker-performance')"
                class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
            >
                <UserGroupIcon class="h-4 w-4" /> {{ $t('maintenance.caretaker_perf.title') }}
            </Link>
            <Link
                :href="route('parts.pricing')"
                class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
            >
                <CubeIcon class="h-4 w-4" /> {{ $t('parts.pricing.title') }}
            </Link>
            <Link
                :href="route('maintenance.photos')"
                class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
            >
                <PhotoIcon class="h-4 w-4" /> {{ $t('maintenance.photos.title') }}
            </Link>
        </template>

        <HubOverview v-if="currentTab === 'overview'" :stats="overviewStats" :links="quickLinks" />
        <TicketsTab
            v-else
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
