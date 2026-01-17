<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import {
    BellIcon,
    PaperAirplaneIcon,
    ClockIcon,
    Cog6ToothIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    notifications: Object,
    notificationSettings: Object,
    templates: Array,
    scheduled: Array,
});

const { formatDate } = useFormatters();

const activeSubTab = ref('history');

const subTabs = [
    { id: 'history', name: 'History', icon: ClockIcon },
    { id: 'templates', name: 'Templates', icon: EnvelopeIcon },
    { id: 'scheduled', name: 'Scheduled', icon: ClockIcon },
    { id: 'settings', name: 'Settings', icon: Cog6ToothIcon },
];

const getChannelIcon = (channel) => {
    return channel === 'sms' ? DevicePhoneMobileIcon : EnvelopeIcon;
};

const getStatusColor = (status) => {
    const colors = {
        sent: 'bg-green-100 text-green-800',
        pending: 'bg-yellow-100 text-yellow-800',
        failed: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <div>
        <!-- Sub-tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-4">
                <button
                    v-for="tab in subTabs"
                    :key="tab.id"
                    @click="activeSubTab = tab.id"
                    :class="[
                        'px-3 py-2 text-sm font-medium rounded-t-lg transition-colors',
                        activeSubTab === tab.id
                            ? 'bg-purple-100 text-purple-700'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    <component :is="tab.icon" class="w-4 h-4 inline mr-1" />
                    {{ tab.name }}
                </button>
            </nav>
        </div>

        <!-- History Tab -->
        <div v-if="activeSubTab === 'history'">
            <div v-if="notifications?.data?.length > 0" class="space-y-3">
                <div
                    v-for="notif in notifications.data"
                    :key="notif.id"
                    class="bg-white border border-gray-200 rounded-lg p-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <component :is="getChannelIcon(notif.channel)" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900">{{ notif.subject || notif.type }}</span>
                                <span :class="getStatusColor(notif.status)" class="px-2 py-0.5 text-xs rounded-full">
                                    {{ notif.status }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">To: {{ notif.recipient }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ formatDate(notif.sent_at || notif.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <BellIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No notifications sent</h3>
                <p class="mt-1 text-sm text-gray-500">Notification history will appear here.</p>
            </div>
        </div>

        <!-- Templates Tab -->
        <div v-else-if="activeSubTab === 'templates'">
            <div v-if="templates?.length > 0" class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white border border-gray-200 rounded-lg p-4"
                >
                    <h4 class="font-medium text-gray-900">{{ template.name }}</h4>
                    <p class="text-sm text-gray-500 mt-1">{{ template.description }}</p>
                    <div class="mt-3 flex gap-2">
                        <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                            {{ template.channel }}
                        </span>
                        <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                            {{ template.trigger }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <EnvelopeIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No templates configured</h3>
                <p class="mt-1 text-sm text-gray-500">Create notification templates for automated messaging.</p>
            </div>
        </div>

        <!-- Scheduled Tab -->
        <div v-else-if="activeSubTab === 'scheduled'">
            <div v-if="scheduled?.length > 0" class="space-y-3">
                <div
                    v-for="item in scheduled"
                    :key="item.id"
                    class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between"
                >
                    <div>
                        <h4 class="font-medium text-gray-900">{{ item.name }}</h4>
                        <p class="text-sm text-gray-500">Scheduled: {{ formatDate(item.scheduled_at) }}</p>
                    </div>
                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                        Pending
                    </span>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <ClockIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No scheduled notifications</h3>
                <p class="mt-1 text-sm text-gray-500">Schedule notifications to send later.</p>
            </div>
        </div>

        <!-- Settings Tab -->
        <div v-else-if="activeSubTab === 'settings'" class="max-w-2xl space-y-6">
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Email Notifications</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" :checked="notificationSettings?.invoice_created" class="rounded border-gray-300 text-purple-600" />
                        <span class="text-sm text-gray-700">Invoice created</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" :checked="notificationSettings?.payment_received" class="rounded border-gray-300 text-purple-600" />
                        <span class="text-sm text-gray-700">Payment received</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" :checked="notificationSettings?.rent_reminder" class="rounded border-gray-300 text-purple-600" />
                        <span class="text-sm text-gray-700">Rent reminders</span>
                    </label>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900 mb-4">SMS Notifications</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" :checked="notificationSettings?.sms_enabled" class="rounded border-gray-300 text-purple-600" />
                        <span class="text-sm text-gray-700">Enable SMS notifications</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
