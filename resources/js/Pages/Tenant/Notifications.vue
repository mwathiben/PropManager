<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import {
    BellIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    CheckCircleIcon,
    ArrowTrendingUpIcon,
    CalendarIcon,
    WrenchScrewdriverIcon,
    ExclamationCircleIcon,
    FunnelIcon,
    CheckIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { useFormatters, useErrorHandler } from '@/composables';
import type { TenantNotificationsPaginated, NotificationFilterType } from '@/types';

const { logError } = useErrorHandler();

const props = withDefaults(defineProps<{
    notifications: TenantNotificationsPaginated;
    unreadCount?: number;
    filter?: NotificationFilterType;
}>(), {
    unreadCount: 0,
    filter: 'all',
});

const { formatDate, formatRelativeTime } = useFormatters();

const typeConfig = {
    rent_reminder: { icon: ClockIcon, color: 'text-orange-500', bg: 'bg-orange-100', label: 'Rent Reminder' },
    arrears_notice: { icon: ExclamationTriangleIcon, color: 'text-red-500', bg: 'bg-red-100', label: 'Arrears Notice' },
    invoice: { icon: DocumentTextIcon, color: 'text-blue-500', bg: 'bg-blue-100', label: 'Invoice' },
    receipt: { icon: CheckCircleIcon, color: 'text-green-500', bg: 'bg-green-100', label: 'Receipt' },
    rent_hike: { icon: ArrowTrendingUpIcon, color: 'text-purple-500', bg: 'bg-purple-100', label: 'Rent Adjustment' },
    lease_expiry: { icon: CalendarIcon, color: 'text-yellow-500', bg: 'bg-yellow-100', label: 'Lease Expiry' },
    lease_renewal: { icon: DocumentTextIcon, color: 'text-teal-500', bg: 'bg-teal-100', label: 'Lease Renewal' },
    maintenance_notice: { icon: WrenchScrewdriverIcon, color: 'text-gray-500', bg: 'bg-gray-100', label: 'Maintenance' },
    general: { icon: BellIcon, color: 'text-indigo-500', bg: 'bg-indigo-100', label: 'General' },
    eviction_notice: { icon: ExclamationCircleIcon, color: 'text-red-500', bg: 'bg-red-100', label: 'Eviction Notice' },
    caretaker_invitation: { icon: UserPlusIcon, color: 'text-purple-500', bg: 'bg-purple-100', label: 'Caretaker Invitation' },
    tenant_invitation: { icon: UserPlusIcon, color: 'text-indigo-500', bg: 'bg-indigo-100', label: 'Tenant Invitation' },
};

const processingInvitation = ref(null);

const getTypeConfig = (type) => {
    return typeConfig[type] || typeConfig.general;
};

const filters = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'read', label: 'Read' },
];

const currentFilter = ref(props.filter);

const setFilter = (value) => {
    currentFilter.value = value;
    router.get(route('tenant.notifications'), { filter: value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Phase-21 DEFER-FRONT-4: optimistic mark-as-read. Update local state
// FIRST, fire the request, revert on error. Pre-Phase-21 markAsRead
// waited for the response before updating (perceptible lag on slow
// Kenyan networks) and markAllAsRead did a full page refresh —
// re-fetching the entire page payload for a state change the client
// already knows the outcome of.
const markAsRead = async (notification) => {
    if (notification.read_at) return;

    const previous = notification.read_at;
    notification.read_at = new Date().toISOString();

    try {
        const response = await fetch(route('tenant.notifications.read', notification.id), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        if (!response.ok) {
            throw new Error(`mark-as-read failed: ${response.status}`);
        }
    } catch (error) {
        notification.read_at = previous;
        logError(error, { component: 'TenantNotifications', action: 'markAsRead' });
    }
};

const markAllAsRead = async () => {
    // Snapshot for revert-on-error, then optimistically flip every
    // unread notification locally — no whole-page refresh round trip.
    // markAsRead already mutates prop objects directly (Vue 3 props
    // are reactive); markAllAsRead follows the same pattern.
    const snapshot = props.notifications.data
        .filter((n) => !n.read_at)
        .map((n) => ({ ref: n, previous: n.read_at }));
    const now = new Date().toISOString();
    snapshot.forEach(({ ref }) => { ref.read_at = now; });

    try {
        const response = await fetch(route('tenant.notifications.read-all'), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        if (!response.ok) {
            throw new Error(`mark-all-as-read failed: ${response.status}`);
        }
    } catch (error) {
        snapshot.forEach(({ ref, previous }) => { ref.read_at = previous; });
        logError(error, { component: 'TenantNotifications', action: 'markAllAsRead' });
    }
};

const acceptInvitation = (notification, event) => {
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
            router.reload();
        },
    });
};

const declineInvitation = (notification, event) => {
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
            router.reload();
        },
    });
};

// Group notifications by date
const groupedNotifications = computed(() => {
    const groups = {};
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    props.notifications.data.forEach(notification => {
        const notifDate = new Date(notification.created_at);
        notifDate.setHours(0, 0, 0, 0);

        let label;
        if (notifDate.getTime() === today.getTime()) {
            label = 'Today';
        } else if (notifDate.getTime() === yesterday.getTime()) {
            label = 'Yesterday';
        } else {
            label = formatDate(notification.created_at);
        }

        if (!groups[label]) {
            groups[label] = [];
        }
        groups[label].push(notification);
    });

    return groups;
});
</script>

<template>
    <Head title="My Notifications" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Header -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-indigo-100 rounded-xl">
                                <BellIcon class="w-6 h-6 text-indigo-600" />
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">My Notifications</h1>
                                <p class="text-sm text-gray-500">
                                    {{ unreadCount > 0 ? `${unreadCount} unread` : 'All caught up!' }}
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="unreadCount > 0"
                            @click="markAllAsRead"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
                        >
                            <CheckIcon class="w-4 h-4" />
                            Mark All as Read
                        </button>
                    </div>
                </div>

                <!-- Filter -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-1.5">
                    <nav class="flex gap-1">
                        <button
                            v-for="filter in filters"
                            :key="filter.value"
                            @click="setFilter(filter.value)"
                            :class="[
                                'flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-all text-center',
                                currentFilter === filter.value
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                            ]"
                        >
                            {{ filter.label }}
                            <span
                                v-if="filter.value === 'unread' && unreadCount > 0"
                                class="ml-1.5 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full"
                                :class="currentFilter === filter.value ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-600'"
                            >
                                {{ unreadCount }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Notifications List -->
                <div class="space-y-4">
                    <!-- Empty State -->
                    <div
                        v-if="notifications.data.length === 0"
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center"
                    >
                        <BellIcon class="w-16 h-16 text-gray-300 mx-auto" />
                        <h3 class="mt-4 text-lg font-medium text-gray-900">No notifications</h3>
                        <p class="mt-2 text-gray-500">
                            {{ currentFilter === 'unread' ? "You've read all your notifications!" : "You don't have any notifications yet." }}
                        </p>
                    </div>

                    <!-- Grouped Notifications -->
                    <template v-else>
                        <div
                            v-for="(items, date) in groupedNotifications"
                            :key="date"
                            class="space-y-2"
                        >
                            <!-- Date Header -->
                            <h3 class="text-sm font-medium text-gray-500 px-2">{{ date }}</h3>

                            <!-- Notification Cards -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-50">
                                <div
                                    v-for="notification in items"
                                    :key="notification.id"
                                    @click="markAsRead(notification)"
                                    :class="[
                                        'p-4 hover:bg-gray-50 cursor-pointer transition-colors',
                                        !notification.read_at ? 'bg-indigo-50/30' : ''
                                    ]"
                                >
                                    <div class="flex gap-4">
                                        <!-- Type Icon -->
                                        <div :class="[
                                            'shrink-0 w-12 h-12 rounded-xl flex items-center justify-center',
                                            getTypeConfig(notification.type).bg
                                        ]">
                                            <component
                                                :is="getTypeConfig(notification.type).icon"
                                                :class="['w-6 h-6', getTypeConfig(notification.type).color]"
                                            />
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span :class="[
                                                            'text-xs font-medium px-2 py-0.5 rounded-full',
                                                            getTypeConfig(notification.type).bg,
                                                            getTypeConfig(notification.type).color
                                                        ]">
                                                            {{ getTypeConfig(notification.type).label }}
                                                        </span>
                                                        <span
                                                            v-if="!notification.read_at"
                                                            class="w-2 h-2 bg-indigo-500 rounded-full"
                                                        ></span>
                                                    </div>
                                                    <h4 :class="[
                                                        'mt-1 text-base',
                                                        !notification.read_at ? 'font-semibold text-gray-900' : 'text-gray-700'
                                                    ]">
                                                        {{ notification.subject }}
                                                    </h4>
                                                    <p class="mt-1 text-sm text-gray-500 line-clamp-2">
                                                        {{ notification.message }}
                                                    </p>

                                                    <!-- Invitation Action Buttons -->
                                                    <div
                                                        v-if="notification.is_invitation && !notification.read_at"
                                                        class="mt-3 flex gap-2"
                                                    >
                                                        <button
                                                            @click="acceptInvitation(notification, $event)"
                                                            :disabled="processingInvitation === notification.id"
                                                            class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors"
                                                        >
                                                            {{ processingInvitation === notification.id ? 'Processing...' : 'Accept' }}
                                                        </button>
                                                        <button
                                                            @click="declineInvitation(notification, $event)"
                                                            :disabled="processingInvitation === notification.id"
                                                            class="px-4 py-1.5 text-sm bg-red-100 text-red-600 rounded-lg hover:bg-red-200 disabled:opacity-50 transition-colors"
                                                        >
                                                            Decline
                                                        </button>
                                                    </div>
                                                </div>
                                                <span class="text-xs text-gray-400 whitespace-nowrap">
                                                    {{ formatRelativeTime(notification.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Pagination -->
                    <div
                        v-if="notifications.links && notifications.links.length > 3"
                        class="flex justify-center gap-2 pt-4"
                    >
                        <Link
                            v-for="link in notifications.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : link.url
                                        ? 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'
                                        : 'text-gray-400 bg-gray-100 cursor-not-allowed'
                            ]"
                        >
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
