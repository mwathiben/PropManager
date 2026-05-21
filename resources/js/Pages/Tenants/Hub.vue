<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import HubShell from '@/Components/Hub/HubShell.vue';
import { TabLoadingPlaceholder } from '@/Components/Finances';
import {
    UsersIcon,
    UserPlusIcon,
    ShieldCheckIcon,
    BanknotesIcon,
    ArrowRightOnRectangleIcon,
    ClockIcon,
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
}

const props = defineProps<Props>();

const DirectoryTab = defineAsyncComponent({ loader: () => import('./tabs/DirectoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const OnboardingTab = defineAsyncComponent({ loader: () => import('./tabs/OnboardingTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const VerificationsTab = defineAsyncComponent({ loader: () => import('./tabs/VerificationsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const PaymentVerificationsTab = defineAsyncComponent({ loader: () => import('./tabs/PaymentVerificationsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const MoveOutsTab = defineAsyncComponent({ loader: () => import('./tabs/MoveOutsTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });
const HistoryTab = defineAsyncComponent({ loader: () => import('./tabs/HistoryTab.vue'), loadingComponent: TabLoadingPlaceholder, delay: 100 });

const tabs = computed(() => [
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

const currentTab = computed(() => props.activeTab || 'directory');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DirectoryTab);
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
        <component
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
