<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { OperationsInboxTabProps } from '@/types/operations';
import {
    InboxIcon,
    ChatBubbleLeftIcon,
    PhoneIcon,
    CheckCircleIcon,
    EnvelopeOpenIcon,
    TicketIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<OperationsInboxTabProps>();

const { t } = useI18n();

const search = ref('');
const statusFilter = ref('all');

let filterTimeout = null;
const applyFilters = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(route('operations.hub', { tab: 'inbox' }), {
            search: search.value || undefined,
            inbox_status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        }, {
            preserveState: true,
            replace: true,
            only: ['inbox', 'inboxUnreadCount'],
        });
    }, 300);
};

watch(search, applyFilters);
watch(statusFilter, applyFilters);

const markAsRead = (messageId) => {
    router.put(route('inbox.mark-read', messageId), {}, {
        preserveScroll: true,
    });
};

const markAllAsRead = () => {
    if (confirm(t('operations_inbox.confirm.mark_all_read'))) {
        router.put(route('inbox.mark-all-read'), {}, {
            preserveScroll: true,
        });
    }
};

const sourceBadge = (source) => {
    return source === 'whatsapp'
        ? 'bg-green-100 text-green-800'
        : 'bg-blue-100 text-blue-800';
};

const statusBadge = (status) => {
    const badges = {
        'received': 'bg-yellow-100 text-yellow-800',
        'processed': 'bg-gray-100 text-gray-800',
        'action_taken': 'bg-green-100 text-green-800',
        'ignored': 'bg-red-100 text-red-800',
    };
    return badges[status] || 'bg-gray-100 text-gray-800';
};

const statusLabel = (status) => {
    const labels = {
        'received': t('operations_inbox.status.received'),
        'processed': t('operations_inbox.status.processed'),
        'action_taken': t('operations_inbox.status.action_taken'),
        'ignored': t('operations_inbox.status.ignored'),
    };
    return labels[status] || status;
};
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('operations_inbox.title') }}</h2>
                <p class="text-sm text-gray-500">
                    {{ t('operations_inbox.subtitle') }}
                    <span v-if="inboxUnreadCount > 0" class="font-medium text-indigo-600">
                        {{ t('operations_inbox.unread_count', { count: inboxUnreadCount }) }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="inboxUnreadCount > 0"
                    @click="markAllAsRead"
                    class="px-3 py-1.5 text-sm border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 flex items-center gap-1"
                >
                    <CheckCircleIcon class="w-4 h-4" />
                    {{ t('operations_inbox.mark_all_read') }}
                </button>
                <Link
                    :href="route('inbox.index')"
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center gap-1"
                >
                    <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                    {{ t('operations_inbox.full_view') }}
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <div class="relative flex-1">
                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-1/2 -translate-y-1/2" />
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('operations_inbox.search_placeholder')"
                    :aria-label="t('operations_inbox.search_placeholder')"
                    class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
            <div class="flex items-center gap-2">
                <FunnelIcon class="w-5 h-5 text-gray-400" />
                <select
                    v-model="statusFilter"
                    :aria-label="t('operations_inbox.filter.status')"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="all">{{ t('operations_inbox.filter.all') }}</option>
                    <option value="unread">{{ t('operations_inbox.filter.unread') }}</option>
                    <option value="processed">{{ t('operations_inbox.filter.read') }}</option>
                </select>
            </div>
        </div>

        <!-- Messages List -->
        <div v-if="inbox?.data?.length > 0" class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
            <div
                v-for="message in inbox.data"
                :key="message.id"
                :class="['p-4 hover:bg-gray-50 cursor-pointer transition-colors', message.status === 'received' ? 'bg-indigo-50/50' : '']"
                @click="router.get(route('inbox.show', message.id))"
            >
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center">
                        <ChatBubbleLeftIcon class="w-5 h-5 text-gray-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 truncate">{{ message.tenant_name }}</span>
                                <span
                                    :class="sourceBadge(message.source)"
                                    class="px-2 py-0.5 text-xs font-medium rounded-full capitalize"
                                >
                                    {{ message.source }}
                                </span>
                                <span
                                    :class="statusBadge(message.status)"
                                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                                >
                                    {{ statusLabel(message.status) }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ message.created_at }}</span>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                            <PhoneIcon class="w-3 h-3" />
                            {{ message.from_number }}
                            <span v-if="message.unit_name" class="ms-2">
                                &middot; {{ message.unit_name }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 truncate">{{ message.body_preview }}</p>
                        <div v-if="message.has_ticket" class="flex items-center gap-1 mt-1 text-xs text-indigo-600">
                            <TicketIcon class="w-3 h-3" />
                            {{ t('operations_inbox.ticket', { id: message.ticket_id }) }}
                        </div>
                    </div>
                    <div v-if="message.status === 'received'" class="flex-shrink-0" @click.stop>
                        <button
                            @click="markAsRead(message.id)"
                            class="text-indigo-600 hover:text-indigo-900 text-xs flex items-center gap-1"
                        >
                            <EnvelopeOpenIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="border border-gray-200 rounded-lg bg-gray-50">
            <EmptyState
                :icon="InboxIcon"
                :title="t('operations_inbox.empty.title')"
                :description="t('operations_inbox.empty.description')"
            />
        </div>

        <!-- Pagination -->
        <div v-if="inbox?.data?.length > 0" class="mt-4 flex justify-between items-center text-sm text-gray-500">
            <span>
                {{ t('operations_inbox.showing', { from: inbox.from, to: inbox.to, total: inbox.total }) }}
            </span>
            <div class="flex gap-2">
                <Link
                    v-if="inbox.prev_page_url"
                    :href="inbox.prev_page_url"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    {{ t('operations_inbox.previous') }}
                </Link>
                <Link
                    v-if="inbox.next_page_url"
                    :href="inbox.next_page_url"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    {{ t('operations_inbox.next') }}
                </Link>
            </div>
        </div>
    </div>
</template>
