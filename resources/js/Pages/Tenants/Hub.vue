<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import HubOverview from '@/Components/Hub/HubOverview.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import {
    UsersIcon,
    UserPlusIcon,
    ShieldCheckIcon,
    BanknotesIcon,
    ArrowRightOnRectangleIcon,
    ClockIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline';

interface Props {
    activeTab?: string;
    filters?: Record<string, unknown>;
    buildings?: unknown[];
    counts?: Record<string, number>;
    tenants?: Record<string, unknown>;
    invitations?: Record<string, unknown>;
    verifications?: Record<string, unknown>;
    paymentVerifications?: Record<string, unknown>;
    moveOuts?: Record<string, unknown>;
    pastTenants?: Record<string, unknown>;
    stats?: Record<string, unknown>;
    overviewStats?: Array<{ label: string; value: string | number; tone?: string }>;
}

const props = defineProps<Props>();

const DirectoryTab = defineAsyncComponent({ loader: () => import('./tabs/DirectoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const OnboardingTab = defineAsyncComponent({ loader: () => import('./tabs/OnboardingTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const VerificationsTab = defineAsyncComponent({ loader: () => import('./tabs/VerificationsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const PaymentVerificationsTab = defineAsyncComponent({ loader: () => import('./tabs/PaymentVerificationsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const MoveOutsTab = defineAsyncComponent({ loader: () => import('./tabs/MoveOutsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const HistoryTab = defineAsyncComponent({ loader: () => import('./tabs/HistoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = computed(() => [
    { id: 'overview', name: 'Overview', icon: Squares2X2Icon },
    { id: 'directory', name: 'Directory', icon: UsersIcon },
    { id: 'onboarding', name: 'Onboarding', icon: UserPlusIcon, badge: props.counts?.pendingInvitations },
    { id: 'verifications', name: 'Verifications', icon: ShieldCheckIcon, badge: props.counts?.pendingVerifications },
    { id: 'payment-verifications', name: 'Payments', icon: BanknotesIcon, badge: props.counts?.paymentVerifications },
    { id: 'move-outs', name: 'Move-outs', icon: ArrowRightOnRectangleIcon, badge: props.counts?.moveOuts },
    { id: 'history', name: 'History', icon: ClockIcon },
]);

const tabComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    'directory': DirectoryTab,
    'onboarding': OnboardingTab,
    'verifications': VerificationsTab,
    'payment-verifications': PaymentVerificationsTab,
    'move-outs': MoveOutsTab,
    'history': HistoryTab,
};

const currentTab = computed(() => props.activeTab || 'overview');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DirectoryTab);

const quickLinks = computed(() => tabs.value
    .filter((t) => t.id !== 'overview')
    .map((t) => ({ label: t.name, href: route('tenants.hub', { tab: t.id }), icon: t.icon, badge: t.badge })));
</script>

<template>
    <HubShell
        title="Tenants"
        subtitle="Directory, onboarding, verifications, and move-outs"
        :icon="UsersIcon"
        accent="blue"
        route-name="tenants.hub"
        :tabs="tabs"
        :current-tab="currentTab"
    >
        <HubOverview v-if="currentTab === 'overview'" :stats="overviewStats" :links="quickLinks" />
        <component
            v-else
            :is="currentTabComponent"
            :key="currentTab"
            :tenants="tenants"
            :invitations="invitations"
            :verifications="verifications"
            :payment-verifications="paymentVerifications"
            :move-outs="moveOuts"
            :past-tenants="pastTenants"
            :stats="stats"
            :buildings="buildings"
            :filters="filters"
        />
    </HubShell>
</template>
