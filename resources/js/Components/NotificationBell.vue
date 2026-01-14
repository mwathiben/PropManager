<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
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
import BellIconSolid from '@heroicons/vue/24/solid/BellIcon';

const page = usePage();

const isOpen = ref(false);
const notifications = ref([]);
const loading = ref(false);
const dropdownRef = ref(null);

const unreadCount = computed(() => {
    // For tenant role, notifications count is directly on navBadges
    // For other roles, check nested structure
    return page.props.navBadges?.notifications || 0;
});

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

    if (!confirm('Are you sure you want to decline this invitation?')) {
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
        console.error('Failed to fetch notifications:', error);
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
        console.error('Failed to mark notification as read:', error);
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
        console.error('Failed to mark all notifications as read:', error);
    }
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Bell Button -->
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
        >
            <BellIconSolid v-if="unreadCount > 0" class="w-6 h-6 text-indigo-600" />
            <BellIcon v-else class="w-6 h-6" />

            <!-- Badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden z-50"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Notifications</h3>
                    <button
                        v-if="notifications.some(n => !n.read_at)"
                        @click="markAllAsRead"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Mark all read
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="loading" class="p-8 text-center">
                        <div class="animate-spin w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full mx-auto"></div>
                        <p class="text-sm text-gray-500 mt-2">Loading...</p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="notifications.length === 0" class="p-8 text-center">
                        <BellIcon class="w-12 h-12 text-gray-300 mx-auto" />
                        <p class="text-gray-500 mt-2">No notifications yet</p>
                    </div>

                    <!-- Notification Items -->
                    <div v-else>
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            @click="markAsRead(notification)"
                            :class="[
                                'px-4 py-3 border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors',
                                !notification.read_at ? 'bg-indigo-50/50' : ''
                            ]"
                        >
                            <div class="flex gap-3">
                                <!-- Type Icon -->
                                <div :class="[
                                    'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                    getTypeConfig(notification.type).bg
                                ]">
                                    <component
                                        :is="getTypeConfig(notification.type).icon"
                                        :class="['w-5 h-5', getTypeConfig(notification.type).color]"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p :class="[
                                            'text-sm truncate',
                                            !notification.read_at ? 'font-semibold text-gray-900' : 'text-gray-700'
                                        ]">
                                            {{ notification.subject }}
                                        </p>
                                        <!-- Unread Indicator -->
                                        <span
                                            v-if="!notification.read_at"
                                            class="flex-shrink-0 w-2 h-2 bg-indigo-500 rounded-full mt-1.5"
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
                                            Accept
                                        </button>
                                        <button
                                            @click="declineInvitation(notification, $event)"
                                            :disabled="processingInvitation === notification.id"
                                            class="px-3 py-1 text-xs bg-red-100 text-red-600 rounded hover:bg-red-200 disabled:opacity-50"
                                        >
                                            Decline
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
                        View All Notifications
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
