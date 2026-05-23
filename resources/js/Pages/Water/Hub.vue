<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import HubOverview from '@/Components/Hub/HubOverview.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon, ClipboardDocumentListIcon, ClockIcon, Cog6ToothIcon, CheckBadgeIcon, Squares2X2Icon, ArrowUpTrayIcon, ChartBarIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    role?: 'landlord' | 'caretaker';
    canInput?: boolean;
    canReview?: boolean;
    canSettings?: boolean;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    counts?: Record<string, number>;
    buildingsData?: unknown[];
    readings?: unknown;
    buildingsList?: unknown[];
    pendingReadings?: unknown;
    settings?: Record<string, unknown>;
    intelligence?: Record<string, unknown>;
    costCategories?: string[];
    compliance?: Record<string, unknown>;
    overviewStats?: Array<{ label: string; value: string | number; tone?: string }>;
}

const props = defineProps<Props>();
const { t } = useI18n();

const ReadingsTab = defineAsyncComponent({ loader: () => import('./tabs/ReadingsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const ReviewTab = defineAsyncComponent({ loader: () => import('./tabs/ReviewTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const HistoryTab = defineAsyncComponent({ loader: () => import('./tabs/HistoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const SettingsTab = defineAsyncComponent({ loader: () => import('./tabs/SettingsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const IntelligenceTab = defineAsyncComponent({ loader: () => import('./tabs/IntelligenceTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const ComplianceTab = defineAsyncComponent({ loader: () => import('./tabs/ComplianceTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

// Phase-79 WATER-ROLES-1: caretaker records, landlord reviews — each role only
// sees its own primary tab. Phase-83 follow-up: an Overview homepage leads.
const tabs = computed(() => {
    const list: Array<{ id: string; name: string; icon: unknown; badge?: number }> = [
        { id: 'overview', name: t('water.tabs.overview'), icon: Squares2X2Icon },
    ];
    if (props.canInput) {
        list.push({ id: 'readings', name: t('water.tabs.record'), icon: ClipboardDocumentListIcon, badge: props.counts?.pendingReadings });
    }
    if (props.canReview) {
        list.push({ id: 'review', name: t('water.tabs.review'), icon: CheckBadgeIcon, badge: props.counts?.pendingReadings });
    }
    list.push({ id: 'history', name: t('water.tabs.history'), icon: ClockIcon });
    // Phase-91: the Intelligence analytics surface is landlord-only (canSettings
    // doubles as the landlord flag), placed before Settings.
    if (props.canSettings) {
        list.push({ id: 'intelligence', name: t('water.tabs.intelligence'), icon: ChartBarIcon });
        // Phase-92: borehole compliance (permits/certs/abstraction) — landlord-only.
        list.push({ id: 'compliance', name: t('water.tabs.compliance'), icon: ShieldCheckIcon });
    }
    // Phase-86 ROLE-SPLIT: Settings is landlord-only — a caretaker never sees
    // the water billing configuration tab or its quick-link.
    if (props.canSettings) {
        list.push({ id: 'settings', name: t('water.tabs.settings'), icon: Cog6ToothIcon });
    }
    return list;
});

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    readings: ReadingsTab,
    review: ReviewTab,
    history: HistoryTab,
    settings: SettingsTab,
    intelligence: IntelligenceTab,
    compliance: ComplianceTab,
};

const currentTab = computed(() => props.activeTab || 'overview');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || HistoryTab);

const quickLinks = computed(() => {
    const links: Array<{ label: string; href: string; icon: unknown; badge?: number }> = tabs.value
        .filter((tab) => tab.id !== 'overview')
        .map((tab) => ({ label: tab.name, href: route('water.hub', { tab: tab.id }), icon: tab.icon, badge: tab.badge }));
    // Phase-86: landlord-only meter fleet management lives on its own page.
    if (props.canSettings) {
        links.push({ label: t('meter.title'), href: route('meters.index'), icon: BeakerIcon });
        // Phase-89: import historical readings from a spreadsheet.
        links.push({ label: t('water.import_history'), href: route('imports.index'), icon: ArrowUpTrayIcon });
    }
    return links;
});

const subtitle = computed(() =>
    props.canReview ? t('water.hub.subtitle_landlord') : t('water.hub.subtitle_caretaker'),
);
</script>

<template>
    <HubShell
        :title="t('water.hub.title')"
        :subtitle="subtitle"
        :icon="BeakerIcon"
        accent="cyan"
        route-name="water.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
        <HubOverview v-if="currentTab === 'overview'" :stats="overviewStats" :links="quickLinks" />
        <component
            v-else
            :is="currentTabComponent"
            :key="currentTab"
            :buildings-data="buildingsData"
            :readings="readings"
            :buildings-list="buildingsList"
            :pending-readings="pendingReadings"
            :settings="settings"
            :intelligence="intelligence"
            :cost-categories="costCategories"
            :compliance="compliance"
            :buildings="buildings"
            :filters="filters"
        />
    </HubShell>
</template>
