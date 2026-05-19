<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';

interface Sender {
    id: number | null;
    name: string | null;
    role: string | null;
}

interface MessageDocument {
    id: number;
    title: string;
    file_path: string;
    mime_type: string;
}

interface ThreadMessage {
    id: number;
    sender_id: number | null;
    sender: Sender | null;
    body: string;
    message_type: 'text' | 'system' | 'attachment';
    created_at: string;
    documents: MessageDocument[];
}

interface Thread {
    id: number;
    title: string | null;
    status: 'open' | 'archived' | 'locked';
    messages: ThreadMessage[];
}

interface Props {
    thread: Thread;
    unreadCount: number;
}

const props = defineProps<Props>();

const form = useForm({
    body: '',
    attachments: [] as File[],
});

function submit() {
    form.post(route('tenant.inbox.messages.store', props.thread.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="thread.title || `Thread #${thread.id}`" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">
                    {{ thread.title || `Thread #${thread.id}` }}
                </h1>
                <PendingSyncBadge route-family="messages" :resource-id="thread.id" />
            </header>

            <ol class="space-y-3" data-testid="tenant-message-list">
                <li
                    v-for="message in thread.messages"
                    :key="message.id"
                    class="rounded-lg bg-white p-4 shadow"
                    :class="{ 'bg-amber-50': message.message_type === 'system' }"
                >
                    <header class="flex items-center justify-between text-xs text-gray-500">
                        <span>{{ message.sender?.name || 'System' }}</span>
                        <time>{{ message.created_at }}</time>
                    </header>
                    <p class="mt-2 text-sm text-gray-900 whitespace-pre-wrap">{{ message.body }}</p>
                </li>
            </ol>

            <form
                v-if="thread.status === 'open'"
                @submit.prevent="submit"
                class="rounded-lg bg-white p-4 shadow"
                data-testid="tenant-message-compose"
            >
                <textarea
                    v-model="form.body"
                    rows="3"
                    maxlength="4000"
                    placeholder="Reply…"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                ></textarea>
                <button
                    type="submit"
                    :disabled="form.processing || form.body.length === 0"
                    class="mt-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    Send reply
                </button>
            </form>

            <p v-else class="text-sm text-gray-500">
                This thread is {{ thread.status }} and cannot accept new messages.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
