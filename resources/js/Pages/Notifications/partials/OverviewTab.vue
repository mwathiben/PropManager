<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    PaperAirplaneIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import SendNotificationModal from '@/Components/Modals/SendNotificationModal.vue';
import BulkSendNotificationModal from '@/Components/Modals/BulkSendNotificationModal.vue';
import type { NotificationStats, NotificationEntry, ChannelStats, TenantReference } from '@/types';

const props = withDefaults(defineProps<{
    stats?: NotificationStats;
    recentNotifications?: NotificationEntry[];
    channelStats?: ChannelStats;
    tenants?: TenantReference[];
    setupComplete?: boolean;
}>(), {
    stats: () => ({} as NotificationStats),
    recentNotifications: () => [],
    channelStats: () => ({} as ChannelStats),
    tenants: () => [],
    setupComplete: false,
});

const emit = defineEmits(['open-wizard']);

const { formatDateTime } = useFormatters();
const { t } = useI18n();

const showSendModal = ref(false);
const showBulkModal = ref(false);

const notificationTypes = computed(() => [
    { value: 'rent_reminder', label: t('notifications_overview.types.rent_reminder') },
    { value: 'arrears_notice', label: t('notifications_overview.types.arrears_notice') },
    { value: 'invoice', label: t('notifications_overview.types.invoice') },
    { value: 'receipt', label: t('notifications_overview.types.receipt') },
    { value: 'rent_hike', label: t('notifications_overview.types.rent_hike') },
    { value: 'lease_expiry', label: t('notifications_overview.types.lease_expiry') },
    { value: 'general', label: t('notifications_overview.types.general') },
]);

const channels = computed(() => [
    { value: 'email', label: t('notifications_overview.channels.email') },
    { value: 'sms', label: t('notifications_overview.channels.sms') },
    { value: 'whatsapp', label: t('notifications_overview.channels.whatsapp') },
    { value: 'push', label: t('notifications_overview.channels.push') },
]);

const sendRentReminders = () => {
    if (confirm(t('notifications_overview.confirm.rent_reminders'))) {
        router.post(route('notifications.sendRentReminders'));
    }
};

const sendArrearsNotices = () => {
    if (confirm(t('notifications_overview.confirm.arrears_notices'))) {
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
        <div v-if="!setupComplete" class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-6">
            <div class="flex items-start gap-4">
                <div class="p-3 bg-amber-100 rounded-xl">
                    <SparklesIcon class="w-6 h-6 text-amber-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-amber-900">{{ t('notifications_overview.setup.title') }}</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        {{ t('notifications_overview.setup.body') }}
                    </p>
                    <button
                        @click="$emit('open-wizard')"
                        class="mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium"
                    >
                        {{ t('notifications_overview.setup.run_wizard') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 rounded-xl">
                        <CheckCircleIcon class="w-6 h-6 text-green-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total_sent || 0 }}</p>
                        <p class="text-sm text-gray-500">{{ t('notifications_overview.stats.total_sent') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-yellow-100 rounded-xl">
                        <ClockIcon class="w-6 h-6 text-yellow-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.pending || 0 }}</p>
                        <p class="text-sm text-gray-500">{{ t('notifications_overview.stats.pending') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-red-100 rounded-xl">
                        <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.failed || 0 }}</p>
                        <p class="text-sm text-gray-500">{{ t('notifications_overview.stats.failed') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-100 rounded-xl">
                        <PaperAirplaneIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.this_month || 0 }}</p>
                        <p class="text-sm text-gray-500">{{ t('notifications_overview.stats.this_month') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Channel Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('notifications_overview.quick_actions.heading') }}</h2>
                <div class="space-y-3">
                    <button
                        @click="showSendModal = true"
                        class="w-full flex items-center gap-3 p-4 border-2 border-indigo-200 rounded-xl hover:border-indigo-400 hover:bg-indigo-50 transition-all text-start"
                    >
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <PaperAirplaneIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">{{ t('notifications_overview.quick_actions.send.title') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('notifications_overview.quick_actions.send.description') }}</p>
                        </div>
                    </button>

                    <button
                        @click="showBulkModal = true"
                        class="w-full flex items-center gap-3 p-4 border-2 border-purple-200 rounded-xl hover:border-purple-400 hover:bg-purple-50 transition-all text-start"
                    >
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">{{ t('notifications_overview.quick_actions.bulk.title') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('notifications_overview.quick_actions.bulk.description') }}</p>
                        </div>
                    </button>

                    <button
                        @click="sendRentReminders"
                        class="w-full flex items-center gap-3 p-4 border-2 border-blue-200 rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-all text-start"
                    >
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">{{ t('notifications_overview.quick_actions.rent_reminders.title') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('notifications_overview.quick_actions.rent_reminders.description') }}</p>
                        </div>
                    </button>

                    <button
                        @click="sendArrearsNotices"
                        class="w-full flex items-center gap-3 p-4 border-2 border-red-200 rounded-xl hover:border-red-400 hover:bg-red-50 transition-all text-start"
                    >
                        <div class="p-2 bg-red-100 rounded-lg">
                            <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">{{ t('notifications_overview.quick_actions.arrears_notices.title') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('notifications_overview.quick_actions.arrears_notices.description') }}</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Channel Distribution -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('notifications_overview.channel_distribution.heading') }}</h2>
                <div class="space-y-4">
                    <div v-for="(count, channel) in channelStats" :key="channel" class="flex items-center gap-4">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <component :is="getChannelIcon(channel)" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 capitalize">{{ channel }}</span>
                                <span class="text-sm text-gray-500">{{ count }}</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full"
                                    :style="{ width: `${Math.min((count / (stats.total_sent || 1)) * 100, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(channelStats).length === 0" class="text-center py-8 text-gray-500">
                        <BellIcon class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                        <p>{{ t('notifications_overview.channel_distribution.empty') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('notifications_overview.recent_activity.heading') }}</h2>

            <div v-if="recentNotifications.length === 0" class="text-center py-12 text-gray-500">
                <PaperAirplaneIcon class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                <p class="text-lg">{{ t('notifications_overview.recent_activity.empty_title') }}</p>
                <p class="text-sm mt-1">{{ t('notifications_overview.recent_activity.empty_body') }}</p>
            </div>

            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="notification in recentNotifications"
                    :key="notification.id"
                    class="py-4 flex items-center gap-4"
                >
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <component :is="getChannelIcon(notification.channel)" class="w-5 h-5 text-gray-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ notification.subject }}</p>
                        <p class="text-sm text-gray-500">
                            {{ t('notifications_overview.recent_activity.recipient', { name: notification.recipient?.name || t('notifications_overview.recent_activity.unknown_recipient') }) }}
                        </p>
                    </div>
                    <div class="text-end">
                        <span :class="['px-2 py-1 text-xs font-medium rounded-full capitalize', getStatusClass(notification.status)]">
                            {{ notification.status }}
                        </span>
                        <p class="text-xs text-gray-400 mt-1">{{ formatDateTime(notification.created_at) }}</p>
                    </div>
                </div>
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
