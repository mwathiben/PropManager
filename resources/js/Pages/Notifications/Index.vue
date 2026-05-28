<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from '@/composables/useI18n';
import {
    ChartBarIcon,
    DocumentTextIcon,
    ClockIcon,
    ArchiveBoxIcon,
    Cog6ToothIcon,
    BellAlertIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';

// Tab Components
import OverviewTab from './partials/OverviewTab.vue';
import TemplatesTab from './partials/TemplatesTab.vue';
import ScheduledTab from './partials/ScheduledTab.vue';
import HistoryTab from './partials/HistoryTab.vue';
import SettingsTab from './partials/SettingsTab.vue';
import SetupWizard from './components/SetupWizard.vue';
import CenterHero from '@/Components/Center/CenterHero.vue';
import type {
    NotificationEntry,
    TenantReference,
    BuildingReference,
    NotificationFilters,
    NotificationStats,
    ChannelStats,
    NotificationTemplate,
    ScheduleTypeOption,
    TemplatePlaceholders,
    ScheduledNotification,
    ProviderSettings,
    GlobalNotificationPreferences,
} from '@/types';
import type { PaginatedResponse } from '@/types/global';

const props = withDefaults(defineProps<{
    activeTab?: string;
    notifications?: PaginatedResponse<NotificationEntry>;
    tenants?: TenantReference[];
    buildings?: BuildingReference[];
    filters?: NotificationFilters;
    setupComplete?: boolean;
    stats?: NotificationStats;
    recentNotifications?: NotificationEntry[];
    channelStats?: ChannelStats;
    templates?: NotificationTemplate[];
    notificationTypes?: ScheduleTypeOption[];
    placeholders?: TemplatePlaceholders;
    schedules?: ScheduledNotification[];
    scheduleTypes?: ScheduleTypeOption[];
    providers?: ProviderSettings;
    smsProviders?: ScheduleTypeOption[];
    currentSmsProvider?: string;
    globalPreferences?: GlobalNotificationPreferences;
}>(), {
    activeTab: 'overview',
    notifications: () => ({ data: [], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: null, last_page: 1, path: '', per_page: 15, to: null, total: 0, links: [] } }),
    tenants: () => [],
    buildings: () => [],
    filters: () => ({}),
    setupComplete: false,
    stats: () => ({} as NotificationStats),
    recentNotifications: () => [],
    channelStats: () => ({} as ChannelStats),
    templates: () => [],
    notificationTypes: () => [],
    placeholders: () => ({}),
    schedules: () => [],
    scheduleTypes: () => [],
    providers: () => ({} as ProviderSettings),
    smsProviders: () => [],
    currentSmsProvider: 'none',
    globalPreferences: () => ({} as GlobalNotificationPreferences),
});

const { t } = useI18n();

// Tab state - use prop if provided, otherwise default to 'overview'
const currentTab = ref(props.activeTab || 'overview');

// Show setup wizard if not complete
const showSetupWizard = ref(false);

onMounted(() => {
    if (!props.setupComplete && currentTab.value === 'overview') {
        showSetupWizard.value = true;
    }
});

const tabs = computed(() => [
    { id: 'overview', name: t('notifications_index.tab_overview'), icon: ChartBarIcon, route: 'notifications.overview' },
    { id: 'templates', name: t('notifications_index.tab_templates'), icon: DocumentTextIcon, route: 'notifications.templates' },
    { id: 'scheduled', name: t('notifications_index.tab_scheduled'), icon: ClockIcon, route: 'notifications.schedules' },
    { id: 'history', name: t('notifications_index.tab_history'), icon: ArchiveBoxIcon, route: 'notifications.index' },
    { id: 'settings', name: t('notifications_index.tab_settings'), icon: Cog6ToothIcon, route: 'notifications.settings' },
]);

const navigateToTab = (tab) => {
    currentTab.value = tab.id;
    router.get(route(tab.route), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openSetupWizard = () => {
    showSetupWizard.value = true;
};
</script>

<template>
    <Head :title="t('notifications_index.head_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header with Welcome Message -->
                <CenterHero
                    :title="t('notifications_index.hero_title')"
                    :subtitle="t('notifications_index.hero_subtitle')"
                    :icon="BellAlertIcon"
                >
                    <template #action>
                        <button
                            v-if="!setupComplete"
                            @click="openSetupWizard"
                            class="hidden sm:flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-all"
                        >
                            <SparklesIcon class="w-5 h-5" />
                            {{ t('notifications_index.setup_wizard') }}
                        </button>
                    </template>
                </CenterHero>

                <!-- Tab Navigation -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5">
                    <nav class="flex gap-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="navigateToTab(tab)"
                            :class="[
                                'flex-1 group flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-medium text-sm transition-all duration-200',
                                currentTab === tab.id
                                    ? 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-md'
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                            ]"
                        >
                            <component :is="tab.icon" class="w-5 h-5" />
                            <span class="hidden sm:inline">{{ tab.name }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="transition-all duration-300">
                    <!-- Overview Tab -->
                    <OverviewTab
                        v-if="currentTab === 'overview'"
                        :stats="stats"
                        :recent-notifications="recentNotifications"
                        :channel-stats="channelStats"
                        :tenants="tenants"
                        :setup-complete="setupComplete"
                        @open-wizard="openSetupWizard"
                    />

                    <!-- Templates Tab -->
                    <TemplatesTab
                        v-if="currentTab === 'templates'"
                        :templates="templates"
                        :notification-types="notificationTypes"
                        :placeholders="placeholders"
                    />

                    <!-- Scheduled Tab -->
                    <ScheduledTab
                        v-if="currentTab === 'scheduled'"
                        :schedules="schedules"
                        :templates="templates"
                        :schedule-types="scheduleTypes"
                    />

                    <!-- History Tab -->
                    <HistoryTab
                        v-if="currentTab === 'history'"
                        :notifications="notifications"
                        :tenants="tenants"
                        :buildings="buildings"
                        :filters="filters"
                    />

                    <!-- Settings Tab -->
                    <SettingsTab
                        v-if="currentTab === 'settings'"
                        :settings="providers"
                        :global-preferences="globalPreferences"
                        :setup-complete="setupComplete"
                        @open-wizard="openSetupWizard"
                    />
                </div>

            </div>
        </div>

        <!-- Setup Wizard Modal -->
        <SetupWizard
            :show="showSetupWizard"
            :settings="providers"
            @close="showSetupWizard = false"
            @complete="showSetupWizard = false"
        />
    </AuthenticatedLayout>
</template>
