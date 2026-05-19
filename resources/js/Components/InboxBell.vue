<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import EnvelopeIcon from '@heroicons/vue/24/outline/EnvelopeIcon';

/**
 * Phase-64 INBOX-MOUNT-2: topbar bell that reads
 * $page.props.auth.inbox_unread_total (shared by Phase 63
 * HandleInertiaRequests) + links to the correct inbox surface for
 * the current role.
 *
 * Mirrors NotificationBell.vue mount + a11y conventions but stays
 * simple — no dropdown panel, click navigates straight to the inbox.
 */
const page = usePage();

const unreadCount = computed<number>(() => {
    const total = (page.props.auth as any)?.inbox_unread_total;

    return typeof total === 'number' ? total : 0;
});

const inboxRoute = computed<string>(() => {
    const role = (page.props.auth as any)?.user?.role;
    if (role === 'tenant') {
        return route('tenant.inbox.index');
    }

    return route('message-threads.index');
});

const ariaLabel = computed<string>(() =>
    unreadCount.value > 0
        ? `Inbox (${unreadCount.value} unread)`
        : 'Inbox',
);
</script>

<template>
    <Link
        :href="inboxRoute"
        :aria-label="ariaLabel"
        class="relative inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        data-testid="inbox-bell"
    >
        <EnvelopeIcon class="h-6 w-6" aria-hidden="true" />
        <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-semibold text-white bg-rose-600 rounded-full"
            data-testid="inbox-bell-badge"
        >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
    </Link>
</template>
