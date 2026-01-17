<script setup>
import { router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

const props = defineProps({
    inbox: Object,
    inboxUnreadCount: Number,
});

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
    if (confirm('Mark all messages as read?')) {
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
        'received': 'Unread',
        'processed': 'Read',
        'action_taken': 'Actioned',
        'ignored': 'Ignored',
    };
    return labels[status] || status;
};
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Inbox</h2>
                <p class="text-sm text-gray-500">
                    Tenant messages from WhatsApp and SMS
                    <span v-if="inboxUnreadCount > 0" class="font-medium text-indigo-600">
                        ({{ inboxUnreadCount }} unread)
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
                    Mark All Read
                </button>
                <Link
                    :href="route('inbox.index')"
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center gap-1"
                >
                    <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                    Full View
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <div class="relative flex-1">
                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search messages..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
            <div class="flex items-center gap-2">
                <FunnelIcon class="w-5 h-5 text-gray-400" />
                <select
                    v-model="statusFilter"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="all">All</option>
                    <option value="unread">Unread</option>
                    <option value="processed">Read</option>
                </select>
            </div>
        </div>

        <!-- Messages List -->
        <div v-if="inbox?.data?.length > 0" class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
            <div
                v-for="message in inbox.data"
                :key="message.id"
                :class="[
                    'p-4 hover:bg-gray-50 cursor-pointer transition-colors',
                    message.status === 'received' ? 'bg-indigo-50/50' : ''
                ]"
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
                            <span v-if="message.unit_name" class="ml-2">
                                &middot; {{ message.unit_name }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 truncate">{{ message.body_preview }}</p>
                        <div v-if="message.has_ticket" class="flex items-center gap-1 mt-1 text-xs text-indigo-600">
                            <TicketIcon class="w-3 h-3" />
                            Ticket #{{ message.ticket_id }}
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
        <div v-else class="text-center py-12 border border-gray-200 rounded-lg bg-gray-50">
            <InboxIcon class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900">No messages</h3>
            <p class="mt-1 text-sm text-gray-500">
                Tenant messages from WhatsApp and SMS will appear here.
            </p>
        </div>

        <!-- Pagination -->
        <div v-if="inbox?.data?.length > 0" class="mt-4 flex justify-between items-center text-sm text-gray-500">
            <span>
                Showing {{ inbox.from }} - {{ inbox.to }} of {{ inbox.total }}
            </span>
            <div class="flex gap-2">
                <Link
                    v-if="inbox.prev_page_url"
                    :href="inbox.prev_page_url"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    Previous
                </Link>
                <Link
                    v-if="inbox.next_page_url"
                    :href="inbox.next_page_url"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    Next
                </Link>
            </div>
        </div>
    </div>
</template>
