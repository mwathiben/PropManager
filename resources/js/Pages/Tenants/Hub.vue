<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
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

const tabs = [
    { id: 'directory', name: 'Directory', icon: UsersIcon },
    { id: 'onboarding', name: 'Onboarding', icon: UserPlusIcon, badgeKey: 'pendingInvitations' },
    { id: 'verifications', name: 'Verifications', icon: ShieldCheckIcon, badgeKey: 'pendingVerifications' },
    { id: 'payment-verifications', name: 'Payments', icon: BanknotesIcon, badgeKey: 'paymentVerifications' },
    { id: 'move-outs', name: 'Move-outs', icon: ArrowRightOnRectangleIcon, badgeKey: 'moveOuts' },
    { id: 'history', name: 'History', icon: ClockIcon },
];

const tabComponents: Record<string, unknown> = {
    'directory': DirectoryTab,
    'onboarding': OnboardingTab,
    'verifications': VerificationsTab,
    'payment-verifications': PaymentVerificationsTab,
    'move-outs': MoveOutsTab,
    'history': HistoryTab,
};

const currentTab = computed(() => props.activeTab || 'directory');
const currentTabComponent = computed(() => tabComponents[currentTab.value] || DirectoryTab);

const navigateToTab = (tab: { id: string }) => {
    router.get(route('tenants.hub', { tab: tab.id }), {}, { preserveState: true, preserveScroll: true });
};

const prefetchTab = (tab: { id: string }) => {
    if (tab.id === currentTab.value) return;
    router.prefetch(route('tenants.hub', { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};

const pageTitle = computed(() => `Tenants - ${tabs.find((t) => t.id === currentTab.value)?.name || 'Directory'}`);
const breadcrumbItems = computed(() => [
    { label: 'Tenants', href: route('tenants.hub') },
    { label: tabs.find((t) => t.id === currentTab.value)?.name || 'Directory' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <UsersIcon class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Tenants</h1>
                    <p class="text-sm text-gray-500">Directory, onboarding, verifications, and move-outs</p>
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
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component :is="tab.icon" :class="['w-5 h-5', currentTab === tab.id ? 'text-blue-500' : 'text-gray-400']" />
                                {{ tab.name }}
                                <span
                                    v-if="tab.badgeKey && counts && counts[tab.badgeKey] > 0"
                                    class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                >
                                    {{ counts[tab.badgeKey] > 99 ? '99+' : counts[tab.badgeKey] }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
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
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
