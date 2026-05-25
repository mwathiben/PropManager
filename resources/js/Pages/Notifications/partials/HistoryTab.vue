<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    EyeIcon,
    ArrowPathIcon,
    ChevronLeftIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { NotificationEntry, NotificationFilters } from '@/types';
import type { PaginatedResponse } from '@/types/global';

const props = withDefaults(defineProps<{
    notifications?: PaginatedResponse<NotificationEntry>;
    filters?: NotificationFilters;
}>(), {
    notifications: () => ({ data: [], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: null, last_page: 1, path: '', per_page: 15, to: null, total: 0, links: [] } }),
    filters: () => ({}),
});

const { formatDateTime } = useFormatters();
const { t } = useI18n();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const channelFilter = ref(props.filters.channel || '');
const typeFilter = ref(props.filters.type || '');
const showDetailModal = ref(false);
const selectedNotification = ref(null);

const statuses = computed(() => [
    { value: '', label: t('notifications_history.status_options.all') },
    { value: 'pending', label: t('notifications_history.status_options.pending') },
    { value: 'sent', label: t('notifications_history.status_options.sent') },
    { value: 'delivered', label: t('notifications_history.status_options.delivered') },
    { value: 'read', label: t('notifications_history.status_options.read') },
    { value: 'failed', label: t('notifications_history.status_options.failed') },
]);

const channels = computed(() => [
    { value: '', label: t('notifications_history.channel_options.all') },
    { value: 'email', label: t('notifications_history.channel_options.email') },
    { value: 'sms', label: t('notifications_history.channel_options.sms') },
    { value: 'whatsapp', label: t('notifications_history.channel_options.whatsapp') },
    { value: 'push', label: t('notifications_history.channel_options.push') },
]);

const types = computed(() => [
    { value: '', label: t('notifications_history.type_options.all') },
    { value: 'rent_reminder', label: t('notifications_history.type_options.rent_reminder') },
    { value: 'arrears_notice', label: t('notifications_history.type_options.arrears_notice') },
    { value: 'invoice', label: t('notifications_history.type_options.invoice') },
    { value: 'receipt', label: t('notifications_history.type_options.receipt') },
    { value: 'rent_hike', label: t('notifications_history.type_options.rent_hike') },
    { value: 'lease_expiry', label: t('notifications_history.type_options.lease_expiry') },
    { value: 'general', label: t('notifications_history.type_options.general') },
]);

let debounceTimeout = null;

const applyFilters = () => {
    router.get(route('notifications.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        channel: channelFilter.value || undefined,
        type: typeFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([statusFilter, channelFilter, typeFilter], () => {
    applyFilters();
});

watch(search, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    channelFilter.value = '';
    typeFilter.value = '';
    applyFilters();
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

const getStatusIcon = (status) => {
    switch (status) {
        case 'sent':
        case 'delivered':
            return CheckCircleIcon;
        case 'read':
            return EyeIcon;
        case 'pending':
            return ClockIcon;
        case 'failed':
            return XCircleIcon;
        default:
            return ClockIcon;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'sent':
        case 'delivered':
            return 'text-green-600 bg-green-50';
        case 'read':
            return 'text-blue-600 bg-blue-50';
        case 'pending':
            return 'text-yellow-600 bg-yellow-50';
        case 'failed':
            return 'text-red-600 bg-red-50';
        default:
            return 'text-gray-600 bg-gray-50';
    }
};

const getTypeLabel = (type) => {
    const found = types.value.find(item => item.value === type);
    return found ? found.label : type;
};

const getStatusLabel = (status) => {
    const found = statuses.value.find(item => item.value === status);
    return found ? found.label : status;
};

const viewDetails = (notification) => {
    selectedNotification.value = notification;
    showDetailModal.value = true;
};

const resendNotification = (notification) => {
    if (confirm(t('notifications_history.confirm.resend'))) {
        router.post(route('notifications.retry', notification.id));
    }
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const hasActiveFilters = () => {
    return search.value || statusFilter.value || channelFilter.value || typeFilter.value;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header & Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <!-- Search -->
                <div class="relative flex-1">
                    <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('notifications_history.search_placeholder')"
                        class="w-full ps-10 pe-4 py-2 border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-3">
                    <select
                        v-model="statusFilter"
                        class="border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                        <option v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>

                    <select
                        v-model="channelFilter"
                        class="border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                        <option v-for="channel in channels" :key="channel.value" :value="channel.value">
                            {{ channel.label }}
                        </option>
                    </select>

                    <select
                        v-model="typeFilter"
                        class="border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                        <option v-for="type in types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>

                    <button
                        v-if="hasActiveFilters()"
                        @click="clearFilters"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        {{ t('notifications_history.clear') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div v-if="notifications.data && notifications.data.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.channel') }}
                            </th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.recipient') }}
                            </th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.subject') }}
                            </th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.type') }}
                            </th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.status') }}
                            </th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.sent_at') }}
                            </th>
                            <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ t('notifications_history.table.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr
                            v-for="notification in notifications.data"
                            :key="notification.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="p-2 bg-gray-100 rounded-lg inline-block">
                                    <component :is="getChannelIcon(notification.channel)" class="w-5 h-5 text-gray-600" />
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <p class="font-medium text-gray-900">{{ notification.recipient?.name || t('notifications_history.unknown') }}</p>
                                    <p class="text-sm text-gray-500">{{ notification.recipient?.email || '-' }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-900 truncate max-w-xs">{{ notification.subject }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">
                                    {{ getTypeLabel(notification.type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full', getStatusClass(notification.status)]">
                                    <component :is="getStatusIcon(notification.status)" class="w-3.5 h-3.5" />
                                    {{ getStatusLabel(notification.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDateTime(notification.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        @click="viewDetails(notification)"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                        :title="t('notifications_history.actions.view_details')"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="notification.status === 'failed'"
                                        @click="resendNotification(notification)"
                                        class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                        :title="t('notifications_history.actions.resend')"
                                    >
                                        <ArrowPathIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else class="p-12 text-center">
                <div class="p-4 bg-gray-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <EnvelopeIcon class="w-8 h-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('notifications_history.empty.title') }}</h3>
                <p class="text-gray-500">
                    {{ hasActiveFilters() ? t('notifications_history.empty.filtered') : t('notifications_history.empty.default') }}
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.data && notifications.data.length > 0" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    {{ t('notifications_history.pagination.showing', {
                        from: notifications.meta?.from || 1,
                        to: notifications.meta?.to || notifications.data.length,
                        total: notifications.meta?.total || notifications.data.length,
                    }) }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(notifications.links?.prev)"
                        :disabled="!notifications.links?.prev"
                        :class="['p-2 rounded-lg transition-colors', notifications.links?.prev ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed']"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </button>
                    <button
                        @click="goToPage(notifications.links?.next)"
                        :disabled="!notifications.links?.next"
                        :class="['p-2 rounded-lg transition-colors', notifications.links?.next ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed']"
                    >
                        <ChevronRightIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedNotification" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showDetailModal = false"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-lg w-full">
                        <div class="border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">{{ t('notifications_history.detail.title') }}</h3>
                                <button
                                    @click="showDetailModal = false"
                                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-gray-100 rounded-xl">
                                    <component :is="getChannelIcon(selectedNotification.channel)" class="w-6 h-6 text-gray-600" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ selectedNotification.recipient?.name }}</p>
                                    <p class="text-sm text-gray-500">{{ selectedNotification.recipient?.email }}</p>
                                </div>
                                <span :class="['ms-auto px-3 py-1 text-sm font-medium rounded-full', getStatusClass(selectedNotification.status)]">
                                    {{ getStatusLabel(selectedNotification.status) }}
                                </span>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ t('notifications_history.detail.subject') }}</p>
                                <p class="font-medium text-gray-900">{{ selectedNotification.subject }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ t('notifications_history.detail.message') }}</p>
                                <p class="text-gray-700 whitespace-pre-wrap text-sm">{{ selectedNotification.message }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">{{ t('notifications_history.detail.type') }}</p>
                                    <p class="font-medium text-gray-900">{{ getTypeLabel(selectedNotification.type) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">{{ t('notifications_history.detail.channel') }}</p>
                                    <p class="font-medium text-gray-900 capitalize">{{ selectedNotification.channel }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">{{ t('notifications_history.detail.sent_at') }}</p>
                                    <p class="font-medium text-gray-900">{{ formatDateTime(selectedNotification.created_at) }}</p>
                                </div>
                                <div v-if="selectedNotification.delivered_at">
                                    <p class="text-gray-500">{{ t('notifications_history.detail.delivered_at') }}</p>
                                    <p class="font-medium text-gray-900">{{ formatDateTime(selectedNotification.delivered_at) }}</p>
                                </div>
                            </div>

                            <div v-if="selectedNotification.error_message" class="bg-red-50 border border-red-200 rounded-xl p-4">
                                <p class="text-xs text-red-600 uppercase tracking-wide mb-1">{{ t('notifications_history.detail.error') }}</p>
                                <p class="text-red-700 text-sm">{{ selectedNotification.error_message }}</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
                            <button
                                v-if="selectedNotification.status === 'failed'"
                                @click="resendNotification(selectedNotification); showDetailModal = false;"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                            >
                                <ArrowPathIcon class="w-4 h-4" />
                                {{ t('notifications_history.actions.resend') }}
                            </button>
                            <button
                                @click="showDetailModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                {{ t('notifications_history.close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
