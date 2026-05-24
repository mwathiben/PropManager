<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { useEcho, useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import BellIcon from '@heroicons/vue/24/outline/BellIcon';
import ClockIcon from '@heroicons/vue/24/outline/ClockIcon';
import ExclamationTriangleIcon from '@heroicons/vue/24/outline/ExclamationTriangleIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import CheckCircleIcon from '@heroicons/vue/24/outline/CheckCircleIcon';
import ArrowTrendingUpIcon from '@heroicons/vue/24/outline/ArrowTrendingUpIcon';
import CalendarIcon from '@heroicons/vue/24/outline/CalendarIcon';
import WrenchScrewdriverIcon from '@heroicons/vue/24/outline/WrenchScrewdriverIcon';
import ExclamationCircleIcon from '@heroicons/vue/24/outline/ExclamationCircleIcon';
import UserPlusIcon from '@heroicons/vue/24/outline/UserPlusIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import BellIconSolid from '@heroicons/vue/24/solid/BellIcon';

const page = usePage();
const { subscribePrivate, unsubscribe } = useEcho();
const { logError } = useErrorHandler();
const { t } = useI18n();

const isOpen = ref(false);
const notifications = ref([]);
const loading = ref(false);
const dropdownRef = ref(null);
const localUnreadCount = ref(0);
const toast = ref({ show: false, subject: '', message: '', type: '' });

// Sync local count with server-side props on page navigation
watch(() => page.props.navBadges?.notifications, (val) => {
    if (val !== undefined) localUnreadCount.value = val;
}, { immediate: true });

const unreadCount = computed(() => localUnreadCount.value);

const typeConfig = {
    rent_reminder: { icon: ClockIcon, color: 'text-orange-500', bg: 'bg-orange-100' },
    arrears_notice: { icon: ExclamationTriangleIcon, color: 'text-red-500', bg: 'bg-red-100' },
    invoice: { icon: DocumentTextIcon, color: 'text-blue-500', bg: 'bg-blue-100' },
    receipt: { icon: CheckCircleIcon, color: 'text-green-500', bg: 'bg-green-100' },
    rent_hike: { icon: ArrowTrendingUpIcon, color: 'text-purple-500', bg: 'bg-purple-100' },
    lease_expiry: { icon: CalendarIcon, color: 'text-yellow-500', bg: 'bg-yellow-100' },
    lease_renewal: { icon: DocumentTextIcon, color: 'text-teal-500', bg: 'bg-teal-100' },
    maintenance_notice: { icon: WrenchScrewdriverIcon, color: 'text-gray-500', bg: 'bg-gray-100' },
    general: { icon: BellIcon, color: 'text-indigo-500', bg: 'bg-indigo-100' },
    eviction_notice: { icon: ExclamationCircleIcon, color: 'text-red-500', bg: 'bg-red-100' },
    caretaker_invitation: { icon: UserPlusIcon, color: 'text-purple-500', bg: 'bg-purple-100' },
    tenant_invitation: { icon: UserPlusIcon, color: 'text-indigo-500', bg: 'bg-indigo-100' },
};

const processingInvitation = ref(null);

const acceptInvitation = async (notification, event) => {
    event.stopPropagation();
    if (!notification.invitation_id) return;

    processingInvitation.value = notification.id;
    const routeName = notification.invitation_type === 'caretaker'
        ? 'invitations.accept-authenticated'
        : 'tenant-invitations.accept-authenticated';

    router.post(route(routeName, notification.invitation_id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processingInvitation.value = null;
            fetchNotifications();
        },
    });
};

const declineInvitation = async (notification, event) => {
    event.stopPropagation();
    if (!notification.invitation_id) return;

    if (!confirm(t('notification_bell.confirm_decline'))) {
        return;
    }

    processingInvitation.value = notification.id;
    const routeName = notification.invitation_type === 'caretaker'
        ? 'invitations.decline-authenticated'
        : 'tenant-invitations.decline-authenticated';

    router.post(route(routeName, notification.invitation_id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processingInvitation.value = null;
            fetchNotifications();
        },
    });
};

const getTypeConfig = (type) => {
    return typeConfig[type] || typeConfig.general;
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value && notifications.value.length === 0) {
        await fetchNotifications();
    }
};

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch(route('notifications.api'), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();
        notifications.value = data.notifications || [];
    } catch (error) {
        logError(error, { component: 'NotificationBell', action: 'fetchNotifications' });
    } finally {
        loading.value = false;
    }
};

const markAsRead = async (notification) => {
    if (notification.read_at) return;

    try {
        await fetch(route('notifications.read', notification.id), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        notification.read_at = new Date().toISOString();
    } catch (error) {
        logError(error, { component: 'NotificationBell', action: 'markAsRead' });
    }
};

const markAllAsRead = async () => {
    try {
        await fetch(route('notifications.read-all'), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        notifications.value.forEach(n => {
            n.read_at = new Date().toISOString();
        });
    } catch (error) {
        logError(error, { component: 'NotificationBell', action: 'markAllAsRead' });
    }
};

onClickOutside(dropdownRef, () => { isOpen.value = false; });

// Handle new notification from WebSocket
const handleNewNotification = (event) => {
    localUnreadCount.value++;

    // Refresh list if dropdown is open
    if (isOpen.value) {
        fetchNotifications();
    }

    // Show toast for high-priority notifications
    if (event.priority === 'high') {
        toast.value = { show: true, ...event };
        setTimeout(() => { toast.value.show = false; }, 5000);
    }
};

// Subscribe to user's notification channel
onMounted(() => {
    const userId = page.props.auth?.user?.id;
    if (userId) {
        subscribePrivate(`notifications.${userId}`, 'NewNotification', handleNewNotification);
    }
});

onUnmounted(() => {
    const userId = page.props.auth?.user?.id;
    if (userId) {
        unsubscribe(`notifications.${userId}`);
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Bell Button -->
        <button
            @click="toggleDropdown"
            :aria-label="unreadCount > 0 ? t('notification_bell.aria_label_unread', { count: unreadCount }) : t('notification_bell.aria_label')"
            :aria-expanded="isOpen"
            class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
        >
            <BellIconSolid v-if="unreadCount > 0" class="w-6 h-6 text-indigo-600" aria-hidden="true" />
            <BellIcon v-else class="w-6 h-6" aria-hidden="true" />

            <!-- Badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex items-center justify-center min-w-5 h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown Panel -->
        <!-- i18n-ignore -->
        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
            <div
                v-if="isOpen"
                class="absolute end-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden z-50"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                    <h3 class="font-semibold text-gray-900">{{ t('notification_bell.heading') }}</h3>
                    <button
                        v-if="notifications.some(n => !n.read_at)"
                        @click="markAllAsRead"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        {{ t('notification_bell.mark_all_read') }}
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="loading" class="p-8 text-center">
                        <div class="animate-spin w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full mx-auto"></div>
                        <p class="text-sm text-gray-500 mt-2">{{ t('notification_bell.loading') }}</p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="notifications.length === 0" class="p-8 text-center">
                        <BellIcon class="w-12 h-12 text-gray-300 mx-auto" />
                        <p class="text-gray-500 mt-2">{{ t('notification_bell.empty') }}</p>
                    </div>

                    <!-- Notification Items -->
                    <div v-else>
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            @click="markAsRead(notification)"
                            :class="['px-4 py-3 border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors', !notification.read_at ? 'bg-indigo-50/50' : '']"
                        >
                            <div class="flex gap-3">
                                <!-- Type Icon -->
                                <div :class="['shrink-0 w-10 h-10 rounded-full flex items-center justify-center', getTypeConfig(notification.type).bg]">
                                    <component
                                        :is="getTypeConfig(notification.type).icon"
                                        :class="['w-5 h-5', getTypeConfig(notification.type).color]"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p :class="['text-sm truncate', !notification.read_at ? 'font-semibold text-gray-900' : 'text-gray-700']">
                                            {{ notification.subject }}
                                        </p>
                                        <!-- Unread Indicator -->
                                        <span
                                            v-if="!notification.read_at"
                                            class="shrink-0 w-2 h-2 bg-indigo-500 rounded-full mt-1.5"
                                        ></span>
                                    </div>
                                    <p class="text-sm text-gray-500 truncate mt-0.5">
                                        {{ notification.message }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ notification.time_ago }}
                                    </p>

                                    <!-- Invitation Action Buttons -->
                                    <div
                                        v-if="notification.is_invitation && !notification.read_at"
                                        class="mt-2 flex gap-2"
                                    >
                                        <button
                                            @click="acceptInvitation(notification, $event)"
                                            :disabled="processingInvitation === notification.id"
                                            class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                                        >
                                            {{ t('notification_bell.accept') }}
                                        </button>
                                        <button
                                            @click="declineInvitation(notification, $event)"
                                            :disabled="processingInvitation === notification.id"
                                            class="px-3 py-1 text-xs bg-red-100 text-red-600 rounded hover:bg-red-200 disabled:opacity-50"
                                        >
                                            {{ t('notification_bell.decline') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    <Link
                        :href="route('tenant.notifications')"
                        class="block text-center text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                        @click="isOpen = false"
                    >
                        {{ t('notification_bell.view_all') }}
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Toast Notification for High-Priority Notifications -->
        <Teleport to="body">
            <!-- i18n-ignore -->
            <Transition enter-active-class="transition ease-out duration-300" enter-from-class="transform translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition ease-in duration-200" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform translate-y-2 opacity-0">
                <div
                    v-if="toast.show"
                    class="fixed bottom-4 end-4 z-50 max-w-sm bg-white rounded-lg shadow-lg border border-gray-200 p-4"
                >
                    <div class="flex items-start gap-3">
                        <div :class="['shrink-0 w-10 h-10 rounded-full flex items-center justify-center', getTypeConfig(toast.type).bg]">
                            <component
                                :is="getTypeConfig(toast.type).icon"
                                :class="['w-5 h-5', getTypeConfig(toast.type).color]"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ toast.subject }}</p>
                            <p class="text-sm text-gray-500 truncate mt-0.5">{{ toast.message }}</p>
                        </div>
                        <button @click="toast.show = false" class="shrink-0 text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
