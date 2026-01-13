<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import SendNotificationModal from '@/Components/Modals/SendNotificationModal.vue';
import BulkSendNotificationModal from '@/Components/Modals/BulkSendNotificationModal.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentNotifications: { type: Array, default: () => [] },
    channelStats: { type: Object, default: () => ({}) },
    tenants: { type: Array, default: () => [] },
    setupComplete: { type: Boolean, default: false },
});

const emit = defineEmits(['open-wizard']);

const { formatDateTime } = useFormatters();

const showSendModal = ref(false);
const showBulkModal = ref(false);

const notificationTypes = [
    { value: 'rent_reminder', label: 'Rent Reminder' },
    { value: 'arrears_notice', label: 'Arrears Notice' },
    { value: 'invoice', label: 'Invoice' },
    { value: 'receipt', label: 'Receipt' },
    { value: 'rent_hike', label: 'Rent Hike' },
    { value: 'lease_expiry', label: 'Lease Expiry' },
    { value: 'general', label: 'General' },
];

const channels = [
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'push', label: 'Push' },
];

const sendRentReminders = () => {
    if (confirm('Send rent reminders to all tenants with active leases?')) {
        router.post(route('notifications.sendRentReminders'));
    }
};

const sendArrearsNotices = () => {
    if (confirm('Send arrears notices to all tenants with outstanding balances?')) {
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
                    <h3 class="font-semibold text-amber-900">Complete Your Setup</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        Configure SMS, WhatsApp, or Push notifications to reach tenants through multiple channels.
                    </p>
                    <button
                        @click="$emit('open-wizard')"
                        class="mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium"
                    >
                        Run Setup Wizard
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
                        <p class="text-sm text-gray-500">Total Sent</p>
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
                        <p class="text-sm text-gray-500">Pending</p>
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
                        <p class="text-sm text-gray-500">Failed</p>
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
                        <p class="text-sm text-gray-500">This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Channel Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-3">
                    <button
                        @click="showSendModal = true"
                        class="w-full flex items-center gap-3 p-4 border-2 border-indigo-200 rounded-xl hover:border-indigo-400 hover:bg-indigo-50 transition-all text-left"
                    >
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <PaperAirplaneIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Send Notification</h3>
                            <p class="text-sm text-gray-500">Send to a specific tenant</p>
                        </div>
                    </button>

                    <button
                        @click="showBulkModal = true"
                        class="w-full flex items-center gap-3 p-4 border-2 border-purple-200 rounded-xl hover:border-purple-400 hover:bg-purple-50 transition-all text-left"
                    >
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Bulk Send</h3>
                            <p class="text-sm text-gray-500">Send to multiple tenants</p>
                        </div>
                    </button>

                    <button
                        @click="sendRentReminders"
                        class="w-full flex items-center gap-3 p-4 border-2 border-blue-200 rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-all text-left"
                    >
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Send Rent Reminders</h3>
                            <p class="text-sm text-gray-500">Notify all tenants about upcoming rent</p>
                        </div>
                    </button>

                    <button
                        @click="sendArrearsNotices"
                        class="w-full flex items-center gap-3 p-4 border-2 border-red-200 rounded-xl hover:border-red-400 hover:bg-red-50 transition-all text-left"
                    >
                        <div class="p-2 bg-red-100 rounded-lg">
                            <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Send Arrears Notices</h3>
                            <p class="text-sm text-gray-500">Notify tenants with outstanding balances</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Channel Distribution -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Channel Distribution</h2>
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
                        <p>No notifications sent yet</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>

            <div v-if="recentNotifications.length === 0" class="text-center py-12 text-gray-500">
                <PaperAirplaneIcon class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                <p class="text-lg">No notifications yet</p>
                <p class="text-sm mt-1">Send your first notification to get started</p>
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
                            To: {{ notification.recipient?.name || 'Unknown' }}
                        </p>
                    </div>
                    <div class="text-right">
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
