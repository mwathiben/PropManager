<script setup lang="ts">
/**
 * Phase-104 OWNER-REMITTANCE-NOTIFY: the owner's notifications (payout remittances +
 * statement notices). Optimistic mark-as-read; scoped to the authed owner server-side.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { BellIcon, BanknotesIcon, DocumentTextIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { useFormatters, useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';

interface NotificationRow {
    id: number;
    type: string;
    subject: string;
    message: string;
    read_at: string | null;
    created_at: string;
}
interface Paginated {
    data: NotificationRow[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = withDefaults(defineProps<{ notifications: Paginated; unreadCount?: number; filter?: string }>(), {
    unreadCount: 0,
    filter: 'all',
});

const { t } = useI18n();
const { formatRelativeTime } = useFormatters();
const { logError } = useErrorHandler();

const iconFor = (type: string) => (type === 'owner_payout_sent' ? BanknotesIcon : type === 'owner_statement_sent' ? DocumentTextIcon : BellIcon);

const filters = ['all', 'unread', 'read'];
const setFilter = (value: string) => {
    router.get(route('owner-portal.notifications'), { filter: value }, { preserveState: true, preserveScroll: true });
};

const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

const markAsRead = async (n: NotificationRow) => {
    if (n.read_at) return;
    const previous = n.read_at;
    n.read_at = new Date().toISOString();
    try {
        const res = await fetch(route('owner-portal.notifications.read', n.id), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        if (!res.ok) throw new Error(`mark-as-read failed: ${res.status}`);
    } catch (error) {
        n.read_at = previous;
        logError(error, { component: 'OwnerNotifications', action: 'markAsRead' });
    }
};

const markAllAsRead = async () => {
    const snapshot = props.notifications.data.filter((n) => !n.read_at).map((n) => ({ ref: n, previous: n.read_at }));
    const now = new Date().toISOString();
    snapshot.forEach(({ ref }) => { ref.read_at = now; });
    try {
        const res = await fetch(route('owner-portal.notifications.read-all'), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        if (!res.ok) throw new Error(`mark-all-as-read failed: ${res.status}`);
    } catch (error) {
        snapshot.forEach(({ ref, previous }) => { ref.read_at = previous; });
        logError(error, { component: 'OwnerNotifications', action: 'markAllAsRead' });
    }
};

const activeFilter = ref(props.filter);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('owners.portal.notifications_title')" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-100 p-2"><BellIcon class="h-6 w-6 text-indigo-600" /></div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.portal.notifications_title') }}</h1>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    data-testid="mark-all-read"
                    @click="markAllAsRead"
                >
                    <CheckIcon class="h-4 w-4" />
                    {{ t('owners.notifications.mark_all_read') }}
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8" data-testid="owner-notifications">
            <div class="flex gap-2">
                <button
                    v-for="f in filters"
                    :key="f"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium"
                    :class="activeFilter === f ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    @click="activeFilter = f; setFilter(f)"
                >
                    {{ t(`owners.notifications.filter_${f}`) }}
                </button>
            </div>

            <p v-if="!notifications.data.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('owners.notifications.none') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="n in notifications.data"
                    :key="n.id"
                    role="button"
                    tabindex="0"
                    class="flex items-start gap-3 rounded-xl border bg-white p-4 shadow-sm"
                    :class="n.read_at ? 'border-gray-200' : 'border-indigo-200 bg-indigo-50/30'"
                    :data-testid="`notification-${n.id}`"
                    @click="markAsRead(n)"
                    @keydown.enter="markAsRead(n)"
                    @keydown.space.prevent="markAsRead(n)"
                >
                    <div class="rounded-lg bg-indigo-100 p-2"><component :is="iconFor(n.type)" class="h-5 w-5 text-indigo-600" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900">{{ n.subject }}</p>
                        <p class="mt-0.5 text-sm text-gray-600">{{ n.message }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ formatRelativeTime(n.created_at) }}</p>
                    </div>
                    <span v-if="!n.read_at" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                </li>
            </ul>

            <div v-if="notifications.links && notifications.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <PaginatorLink v-for="(link, i) in notifications.links" :key="i" :link="link" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
