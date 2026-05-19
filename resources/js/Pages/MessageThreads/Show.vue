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
    form.post(route('message-threads.messages.store', props.thread.id), {
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
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ thread.title || `Thread #${thread.id}` }}
                    </h1>
                    <PendingSyncBadge route-family="messages" :resource-id="thread.id" />
                </div>
                <span class="text-xs uppercase tracking-wide text-gray-500">
                    {{ thread.status }}
                </span>
            </header>

            <ol class="space-y-3" data-testid="message-list">
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
                    <ul v-if="message.documents.length" class="mt-2 space-y-1 text-xs text-blue-700">
                        <li v-for="doc in message.documents" :key="doc.id">
                            {{ doc.title }} ({{ doc.mime_type }})
                        </li>
                    </ul>
                </li>
            </ol>

            <form
                v-if="thread.status === 'open'"
                @submit.prevent="submit"
                class="rounded-lg bg-white p-4 shadow"
                data-testid="message-compose"
            >
                <label for="body" class="sr-only">Message body</label>
                <textarea
                    id="body"
                    v-model="form.body"
                    rows="3"
                    maxlength="4000"
                    placeholder="Type a message…"
                    class="w-full rounded-md border-gray-300 shadow-sm"
                ></textarea>
                <div class="mt-2 flex items-center justify-between">
                    <input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        @change="(event) => form.attachments = Array.from((event.target as HTMLInputElement).files || [])"
                        class="text-xs"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing || form.body.length === 0"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Send
                    </button>
                </div>
            </form>

            <p v-else class="text-sm text-gray-500">
                This thread is {{ thread.status }} and cannot accept new messages.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
