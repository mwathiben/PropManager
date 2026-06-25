<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { InboxShowPageProps } from '@/types/operations';
import {
    ArrowLeftIcon,
    ChatBubbleLeftIcon,
    PhoneIcon,
    EnvelopeIcon,
    TicketIcon,
    PaperAirplaneIcon,
    PhotoIcon,
    CheckCircleIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<InboxShowPageProps>();

const { t } = useI18n();

const replyForm = useForm({
    body: '',
});

const sendReply = () => {
    replyForm.post(route('inbox.reply', props.message.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
        },
    });
};

const markAsRead = () => {
    router.put(route('inbox.mark-read', props.message.id), {}, {
        preserveScroll: true,
    });
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
    <Head :title="t('inbox.show.head_title', { name: message.tenant_name })" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Back Button -->
                <div class="mb-4">
                    <Link
                        :href="route('inbox.index')"
                        class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        {{ t('inbox.show.back') }}
                    </Link>
                </div>

                <!-- Message Card -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="shrink-0 h-12 w-12 bg-gray-200 rounded-full flex items-center justify-center">
                                    <ChatBubbleLeftIcon class="w-6 h-6 text-gray-500" />
                                </div>
                                <div>
                                    <h1 class="text-lg font-semibold text-gray-900">
                                        {{ message.tenant_name }}
                                    </h1>
                                    <div class="flex items-center gap-3 text-sm text-gray-500">
                                        <span class="flex items-center gap-1">
                                            <PhoneIcon class="w-4 h-4" />
                                            {{ message.from_number }}
                                        </span>
                                        <span v-if="message.tenant_email" class="flex items-center gap-1">
                                            <EnvelopeIcon class="w-4 h-4" />
                                            {{ message.tenant_email }}
                                        </span>
                                    </div>
                                    <div v-if="message.unit_name" class="text-xs text-gray-400 mt-1">
                                        {{ message.unit_name }}
                                        <span v-if="message.building_name">&middot; {{ message.building_name }}</span>
                                        <span v-if="message.property_name">&middot; {{ message.property_name }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span
                                    :class="sourceBadge(message.source)"
                                    class="px-2 py-1 text-xs font-semibold rounded-full capitalize"
                                >
                                    {{ message.source }}
                                </span>
                                <span
                                    :class="statusBadge(message.status)"
                                    class="px-2 py-1 text-xs font-semibold rounded-full"
                                >
                                    {{ statusLabel(message.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Original Notification Context -->
                    <div v-if="message.is_reply && message.original_notification" class="px-6 py-3 bg-indigo-50 border-b border-indigo-100">
                        <div class="flex items-start gap-2">
                            <BellIcon class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-indigo-900">
                                    {{ t('inbox.show.replying_to', { subject: message.original_notification.subject }) }}
                                </p>
                                <p class="text-xs text-indigo-700 mt-1">
                                    {{ t('inbox.show.sent_at', { date: message.original_notification.created_at }) }}
                                </p>
                                <p v-if="message.original_notification.message" class="text-sm text-indigo-800 mt-2 bg-white/50 p-2 rounded">
                                    {{ message.original_notification.message }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Link -->
                    <div v-if="message.has_ticket && message.ticket" class="px-6 py-3 bg-green-50 border-b border-green-100">
                        <div class="flex items-center gap-2">
                            <TicketIcon class="w-5 h-5 text-green-600" />
                            <span class="text-sm text-green-800">
                                {{ t('inbox.show.auto_created_ticket') }}
                                <Link
                                    :href="route('tickets.show', message.ticket.id)"
                                    class="font-medium underline hover:text-green-900"
                                >
                                    #{{ message.ticket.id }} - {{ message.ticket.subject }}
                                </Link>
                                <span class="ms-2 px-2 py-0.5 bg-green-200 text-green-800 text-xs rounded-full">
                                    {{ message.ticket.status }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="px-6 py-6">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs text-gray-500">{{ message.created_at }}</span>
                            <button
                                v-if="message.status === 'received'"
                                @click="markAsRead"
                                class="text-xs text-indigo-600 hover:text-indigo-900 flex items-center gap-1"
                            >
                                <CheckCircleIcon class="w-4 h-4" />
                                {{ t('inbox.show.mark_as_read') }}
                            </button>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-gray-900 whitespace-pre-wrap">{{ message.body }}</p>
                        </div>

                        <!-- Media Attachments -->
                        <div v-if="message.media_urls && message.media_urls.length > 0" class="mt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                <PhotoIcon class="w-4 h-4" />
                                {{ t('inbox.show.attachments', { count: message.media_urls.length }) }}
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <a
                                    v-for="(url, index) in message.media_urls"
                                    :key="index"
                                    :href="url"
                                    target="_blank"
                                    class="block aspect-square bg-gray-100 rounded-lg overflow-hidden hover:opacity-90"
                                >
                                    <img
                                        :src="url"
                                        :alt="t('inbox.show.attachment_alt', { number: index + 1 })"
                                        class="w-full h-full object-cover"
                                    />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Reply Form -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">
                            {{ t('inbox.show.reply_via', { channel: message.source === 'whatsapp' ? 'WhatsApp' : 'SMS' }) }}
                        </h3>
                        <form @submit.prevent="sendReply">
                            <div class="mb-3">
                                <textarea
                                    v-model="replyForm.body"
                                    rows="3"
                                    :placeholder="t('inbox.show.reply_placeholder')"
                                    :aria-label="t('inbox.show.reply_placeholder')"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    :disabled="replyForm.processing"
                                ></textarea>
                                <p v-if="replyForm.errors.body" class="mt-1 text-sm text-red-600">
                                    {{ replyForm.errors.body }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ t('inbox.show.chars_remaining', { count: 1000 - (replyForm.body?.length || 0) }) }}
                                </p>
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="replyForm.processing || !replyForm.body"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                                >
                                    <PaperAirplaneIcon class="w-4 h-4" />
                                    {{ replyForm.processing ? t('inbox.show.sending') : t('inbox.show.send_reply') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
