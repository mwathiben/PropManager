<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import SendNotificationModal from '@/Components/Modals/SendNotificationModal.vue';
import BulkSendNotificationModal from '@/Components/Modals/BulkSendNotificationModal.vue';
import {
    PaperAirplaneIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    SparklesIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';
import type { NotificationStats, ChannelStats, RecentNotification, TenantOption, NotificationTemplate, ScheduledNotification } from '@/types';

const props = withDefaults(defineProps<{
    stats?: NotificationStats;
    recentNotifications?: RecentNotification[];
    channelStats?: ChannelStats;
    tenants?: TenantOption[];
    templates?: NotificationTemplate[];
    scheduled?: ScheduledNotification[];
    setupComplete?: boolean;
}>(), {
    stats: () => ({} as NotificationStats),
    recentNotifications: () => [],
    channelStats: () => ({} as ChannelStats),
    tenants: () => [],
    templates: () => [],
    scheduled: () => [],
    setupComplete: false,
});

const { formatDateTime } = useFormatters();
const { t } = useI18n();

const showSendModal = ref(false);
const showBulkModal = ref(false);

const notificationTypes = computed(() => [
    { value: 'rent_reminder', label: t('operations_notifications.types.rent_reminder') },
    { value: 'arrears_notice', label: t('operations_notifications.types.arrears_notice') },
    { value: 'invoice', label: t('operations_notifications.types.invoice') },
    { value: 'receipt', label: t('operations_notifications.types.receipt') },
    { value: 'rent_hike', label: t('operations_notifications.types.rent_hike') },
    { value: 'lease_expiry', label: t('operations_notifications.types.lease_expiry') },
    { value: 'general', label: t('operations_notifications.types.general') },
]);

const channels = computed(() => [
    { value: 'email', label: t('operations_notifications.channels.email') },
    { value: 'sms', label: t('operations_notifications.channels.sms') },
    { value: 'whatsapp', label: t('operations_notifications.channels.whatsapp') },
    { value: 'push', label: t('operations_notifications.channels.push') },
]);

const sendRentReminders = () => {
    if (confirm(t('operations_notifications.confirm.rent_reminders'))) {
        router.post(route('notifications.sendRentReminders'));
    }
};

const sendArrearsNotices = () => {
    if (confirm(t('operations_notifications.confirm.arrears_notices'))) {
        router.post(route('notifications.sendArrearsNotices'));
    }
};

const getChannelIcon = (channel) => {
    switch (channel) {
        case 'email': return EnvelopeIcon;
        case 'sms': return DevicePhoneMobileIcon;
        case 'whatsapp': return ChatBubbleLeftRightIcon;
        case 'push': return BellIcon;
        default: return EnvelopeIcon;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'sent':
        case 'delivered':
        case 'read':
            return 'text-green-600 bg-green-50';
        case 'pending':
            return 'text-yellow-600 bg-yellow-50';
        case 'failed':
            return 'text-red-600 bg-red-50';
        default:
            return 'text-gray-600 bg-gray-50';
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Setup Alert -->
        <div v-if="!setupComplete" class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-amber-100 rounded-lg">
                    <SparklesIcon class="w-5 h-5 text-amber-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-medium text-amber-900">{{ t('operations_notifications.setup.title') }}</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        {{ t('operations_notifications.setup.body') }}
                    </p>
                    <Link
                        :href="route('notifications.settings')"
                        class="mt-2 inline-flex items-center text-sm font-medium text-amber-700 hover:text-amber-900"
                    >
                        {{ t('operations_notifications.setup.go_to_settings') }}
                        <ArrowTopRightOnSquareIcon class="w-4 h-4 ms-1" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <CheckCircleIcon class="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.total_sent || 0 }}</p>
                        <p class="text-xs text-gray-500">{{ t('operations_notifications.stats.total_sent') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <ClockIcon class="w-5 h-5 text-yellow-600" />
                    </div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.pending || 0 }}</p>
                        <p class="text-xs text-gray-500">{{ t('operations_notifications.stats.pending') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.failed || 0 }}</p>
                        <p class="text-xs text-gray-500">{{ t('operations_notifications.stats.failed') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <PaperAirplaneIcon class="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.this_month || 0 }}</p>
                        <p class="text-xs text-gray-500">{{ t('operations_notifications.stats.this_month') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Channel Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ t('operations_notifications.quick_actions.heading') }}</h2>
                <div class="space-y-2">
                    <button
                        @click="showSendModal = true"
                        class="w-full flex items-center gap-3 p-3 border border-purple-200 rounded-lg hover:border-purple-400 hover:bg-purple-50 transition-all text-start"
                    >
                        <div class="p-1.5 bg-purple-100 rounded">
                            <PaperAirplaneIcon class="w-4 h-4 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ t('operations_notifications.quick_actions.send.title') }}</h3>
                            <p class="text-xs text-gray-500">{{ t('operations_notifications.quick_actions.send.subtitle') }}</p>
                        </div>
                    </button>

                    <button
                        @click="showBulkModal = true"
                        class="w-full flex items-center gap-3 p-3 border border-blue-200 rounded-lg hover:border-blue-400 hover:bg-blue-50 transition-all text-start"
                    >
                        <div class="p-1.5 bg-blue-100 rounded">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ t('operations_notifications.quick_actions.bulk.title') }}</h3>
                            <p class="text-xs text-gray-500">{{ t('operations_notifications.quick_actions.bulk.subtitle') }}</p>
                        </div>
                    </button>

                    <button
                        @click="sendRentReminders"
                        class="w-full flex items-center gap-3 p-3 border border-green-200 rounded-lg hover:border-green-400 hover:bg-green-50 transition-all text-start"
                    >
                        <div class="p-1.5 bg-green-100 rounded">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ t('operations_notifications.quick_actions.rent_reminders.title') }}</h3>
                            <p class="text-xs text-gray-500">{{ t('operations_notifications.quick_actions.rent_reminders.subtitle') }}</p>
                        </div>
                    </button>

                    <button
                        @click="sendArrearsNotices"
                        class="w-full flex items-center gap-3 p-3 border border-red-200 rounded-lg hover:border-red-400 hover:bg-red-50 transition-all text-start"
                    >
                        <div class="p-1.5 bg-red-100 rounded">
                            <ExclamationTriangleIcon class="w-4 h-4 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ t('operations_notifications.quick_actions.arrears_notices.title') }}</h3>
                            <p class="text-xs text-gray-500">{{ t('operations_notifications.quick_actions.arrears_notices.subtitle') }}</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Channel Distribution -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ t('operations_notifications.channel_distribution.heading') }}</h2>
                <div class="space-y-3">
                    <div v-for="(count, channel) in channelStats" :key="channel" class="flex items-center gap-3">
                        <div class="p-1.5 bg-gray-100 rounded">
                            <component :is="getChannelIcon(channel)" class="w-4 h-4 text-gray-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-medium text-gray-700 capitalize">{{ channel }}</span>
                                <span class="text-xs text-gray-500">{{ count }}</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-purple-500 rounded-full"
                                    :style="{ width: `${Math.min((count / (stats.total_sent || 1)) * 100, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(channelStats || {}).length === 0" class="text-center py-6 text-gray-500">
                        <BellIcon class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                        <p class="text-sm">{{ t('operations_notifications.channel_distribution.empty') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">{{ t('operations_notifications.recent_activity.heading') }}</h2>
                <Link
                    :href="route('notifications.index')"
                    class="text-xs text-purple-600 hover:text-purple-800 font-medium"
                >
                    {{ t('operations_notifications.recent_activity.view_all') }}
                </Link>
            </div>

            <div v-if="recentNotifications?.length === 0" class="text-center py-8 text-gray-500">
                <PaperAirplaneIcon class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                <p class="text-sm">{{ t('operations_notifications.recent_activity.empty_title') }}</p>
                <p class="text-xs mt-1">{{ t('operations_notifications.recent_activity.empty_subtitle') }}</p>
            </div>

            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="notification in recentNotifications"
                    :key="notification.id"
                    class="py-3 flex items-center gap-3"
                >
                    <div class="p-1.5 bg-gray-100 rounded">
                        <component :is="getChannelIcon(notification.channel)" class="w-4 h-4 text-gray-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ notification.subject }}</p>
                        <p class="text-xs text-gray-500">
                            {{ t('operations_notifications.recent_activity.recipient', { name: notification.recipient?.name || t('operations_notifications.recent_activity.unknown_recipient') }) }}
                        </p>
                    </div>
                    <div class="text-end">
                        <span :class="['px-2 py-0.5 text-xs font-medium rounded-full capitalize', getStatusClass(notification.status)]">
                            {{ notification.status }}
                        </span>
                        <p class="text-xs text-gray-400 mt-1">{{ formatDateTime(notification.created_at) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Full Management Link -->
        <div class="bg-purple-50 rounded-xl border border-purple-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-medium text-purple-900">{{ t('operations_notifications.full_center.title') }}</h3>
                    <p class="text-sm text-purple-700">{{ t('operations_notifications.full_center.subtitle') }}</p>
                </div>
                <Link
                    :href="route('notifications.overview')"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium"
                >
                    {{ t('operations_notifications.full_center.open') }}
                    <ArrowTopRightOnSquareIcon class="w-4 h-4 ms-1" />
                </Link>
            </div>
        </div>

        <!-- Modals -->
        <SendNotificationModal
            :show="showSendModal"
            :tenants="tenants"
            :notification-types="notificationTypes"
            @close="showSendModal = false"
        />

        <BulkSendNotificationModal
            :show="showBulkModal"
            :tenants="tenants"
            :notification-types="notificationTypes"
            :channels="channels"
            @close="showBulkModal = false"
        />
    </div>
</template>
