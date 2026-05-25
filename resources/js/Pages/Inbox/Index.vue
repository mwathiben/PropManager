<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { InboxIndexPageProps } from '@/types/operations';
import {
    InboxIcon,
    ChatBubbleLeftIcon,
    PhoneIcon,
    CheckCircleIcon,
    EnvelopeOpenIcon,
    TicketIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<InboxIndexPageProps>();

const { t } = useI18n();

const search = ref(props.filters.search);
const statusFilter = ref(props.filters.status);

let filterTimeout = null;
const applyFilters = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(route('inbox.index'), {
            search: search.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        }, {
            preserveState: true,
            replace: true,
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
    if (confirm(t('inbox.confirm_mark_all_read'))) {
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
        'received': t('inbox.status.received'),
        'processed': t('inbox.status.processed'),
        'action_taken': t('inbox.status.action_taken'),
        'ignored': t('inbox.status.ignored'),
    };
    return labels[status] || status;
};
</script>

<template>
    <Head :title="t('inbox.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ t('inbox.title') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ t('inbox.subtitle') }}
                            <span v-if="unreadCount > 0" class="font-medium text-indigo-600">
                                {{ t('inbox.unread_count', { count: unreadCount }) }}
                            </span>
                        </p>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 flex items-center gap-2"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ t('inbox.mark_all_read') }}
                    </button>
                </div>

                <!-- Filters -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4">
                    <!-- Search -->
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('inbox.search_placeholder')"
                            class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <FunnelIcon class="w-5 h-5 text-gray-400" />
                        <select
                            v-model="statusFilter"
                            class="border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="all">{{ t('inbox.filter.all') }}</option>
                            <option value="unread">{{ t('inbox.filter.unread') }}</option>
                            <option value="processed">{{ t('inbox.filter.processed') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.tenant') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.message') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.source') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.status') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.time') }}
                                </th>
                                <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('inbox.table.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="message in messages.data"
                                :key="message.id"
                                :class="[
                                    'hover:bg-gray-50 cursor-pointer',
                                    message.status === 'received' ? 'bg-indigo-50/50' : ''
                                ]"
                                @click="router.get(route('inbox.show', message.id))"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="shrink-0 h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center">
                                            <ChatBubbleLeftIcon class="w-5 h-5 text-gray-500" />
                                        </div>
                                        <div class="ms-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ message.tenant_name }}
                                            </div>
                                            <div class="text-xs text-gray-500 flex items-center gap-1">
                                                <PhoneIcon class="w-3 h-3" />
                                                {{ message.from_number }}
                                            </div>
                                            <div v-if="message.unit_name" class="text-xs text-gray-400">
                                                {{ message.unit_name }}
                                                <span v-if="message.building_name">
                                                    &middot; {{ message.building_name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <p class="text-sm text-gray-900 truncate">
                                            {{ message.body_preview }}
                                        </p>
                                        <p v-if="message.is_reply && message.original_notification" class="text-xs text-gray-500 mt-1">
                                            {{ t('inbox.reply_prefix', { subject: message.original_notification.subject }) }}
                                        </p>
                                        <div v-if="message.has_ticket" class="flex items-center gap-1 mt-1 text-xs text-indigo-600">
                                            <TicketIcon class="w-3 h-3" />
                                            {{ t('inbox.ticket_label', { id: message.ticket_id }) }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="sourceBadge(message.source)"
                                        class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize"
                                    >
                                        {{ message.source }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="statusBadge(message.status)"
                                        class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    >
                                        {{ statusLabel(message.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span :title="message.created_at_full">
                                        {{ message.created_at }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-end text-sm" @click.stop>
                                    <button
                                        v-if="message.status === 'received'"
                                        @click.stop="markAsRead(message.id)"
                                        class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1"
                                        :title="t('inbox.mark_read_title')"
                                    >
                                        <EnvelopeOpenIcon class="w-4 h-4" />
                                        {{ t('inbox.mark_read') }}
                                    </button>
                                    <Link
                                        v-else
                                        :href="route('inbox.show', message.id)"
                                        class="text-gray-600 hover:text-gray-900"
                                    >
                                        {{ t('inbox.view') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <EmptyState
                        v-if="messages.data.length === 0"
                        :icon="InboxIcon"
                        :title="t('inbox.empty.title')"
                        :description="t('inbox.empty.description')"
                    />

                    <!-- Pagination -->
                    <div v-if="messages.data.length > 0" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="messages.prev_page_url"
                                :href="messages.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {{ t('inbox.pagination.previous') }}
                            </Link>
                            <Link
                                v-if="messages.next_page_url"
                                :href="messages.next_page_url"
                                class="ms-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {{ t('inbox.pagination.next') }}
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    {{ t('inbox.pagination.showing', { from: messages.from, to: messages.to, total: messages.total }) }}
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <Link
                                        v-for="link in messages.links"
                                        :key="link.label"
                                        :href="link.url"
                                        :class="[
                                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium', /* i18n-ignore */
                                            link.active
                                                ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50', /* i18n-ignore */
                                            !link.url ? 'cursor-not-allowed opacity-50' : '', /* i18n-ignore */
                                            link.label.includes('Previous') ? 'rounded-s-md' : '',
                                            link.label.includes('Next') ? 'rounded-e-md' : '',
                                        ]"
                                    >
                                        <PaginatorLink :label="link.label" />
                                    </Link>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
