<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';
import ChatThread from '@/Components/Inbox/ChatThread.vue';
import ChatComposer from '@/Components/Inbox/ChatComposer.vue';
import { useI18n } from '@/composables/useI18n';
import { useEcho } from '@/composables/useEcho';
import { usePresenceChannel } from '@/composables/usePresenceChannel';
import { useThreadStream, type IncomingPosted, type IncomingReaction } from '@/composables/useThreadStream';
import type { BubbleMessage, ReplyPreview } from '@/Components/Inbox/MessageBubble.vue';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

interface Sender {
    id: number | null;
    name: string | null;
    role: string | null;
}

interface MessageDocument {
    id: number;
    title: string;
    mime_type: string;
    is_image: boolean;
    file_size_formatted: string;
    scan_status?: string | null;
    url: string;
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

interface ReadReceipt {
    user_id: number;
    name: string;
    role: string | null;
    last_read_at: string | null;
}

interface Props {
    thread: Thread;
    unreadCount: number;
    read_receipts: ReadReceipt[];
    reactionEmojis: string[];
}

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage();

const currentUserId = computed<number | null>(
    () => ((page.props as Record<string, any>)?.auth?.user?.id as number | null) ?? null,
);
const myName = computed<string>(
    () => String((page.props as Record<string, any>)?.auth?.user?.name ?? ''),
);
const myRole = computed<string | null>(
    () => ((page.props as Record<string, any>)?.auth?.user?.role as string | null) ?? null,
);

// user_id -> last_read_at ISO string; seeded from the server, kept live by
// the message.read broadcast.
const readCursors = reactive<Record<number, string | null>>({});
props.read_receipts.forEach((r) => {
    readCursors[r.user_id] = r.last_read_at;
});

// Re-merge authoritative cursors after an Inertia reload (we now preserveState
// across sends, so the setup-time seed alone would go stale).
watch(
    () => props.read_receipts,
    (receipts) => receipts.forEach((r) => { readCursors[r.user_id] = r.last_read_at; }),
);

// Max read cursor across other participants — drives the per-bubble seen
// ticks inside ChatThread (kept live by the .message.read broadcast).
const othersReadAt = computed<string | null>(() => {
    let max: number | null = null;
    for (const cursor of Object.values(readCursors)) {
        if (cursor === null) continue;
        const t = new Date(cursor).getTime();
        if (max === null || t > max) max = t;
    }
    return max === null ? null : new Date(max).toISOString();
});

// Live stream: seeds from the server prop, appends incoming broadcasts, and
// owns the optimistic outgoing bubble lifecycle.
const {
    messages: streamMessages,
    ingest,
    addOptimistic,
    resolveOptimistic,
    failOptimistic,
    dropFailed,
    toggleReaction,
    applyRemoteReaction,
} = useThreadStream(currentUserId.value, () => props.thread.messages);

// Phase-71 REACTIONS: optimistic toggle, reconciled by the back() reload.
function onReact({ message, emoji }: { message: BubbleMessage; emoji: string }): void {
    toggleReaction(message.id, emoji);
    router.post(route('message-threads.messages.react', [props.thread.id, message.id]), { emoji }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => toggleReaction(message.id, emoji),
    });
}

function markAllRead(): void {
    router.post(route('message-threads.read-all', props.thread.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

// Phase-71 REPLY-QUOTE: the message currently being quoted (null = none).
const replyTarget = ref<ReplyPreview | null>(null);

function onReply(message: BubbleMessage): void {
    replyTarget.value = {
        id: message.id,
        sender_name: message.sender?.name ?? null,
        // Match the server's Str::limit(120) so the optimistic quote and the
        // reconciled one carry identical text.
        body: message.body.length > 120 ? `${message.body.slice(0, 120)}...` : message.body,
    };
}

const form = useForm({
    body: '',
    attachments: [] as File[],
    reply_to_id: null as number | null,
});

function submit() {
    const sender = { id: currentUserId.value, name: myName.value, role: myRole.value };
    form.reply_to_id = replyTarget.value?.id ?? null;
    const tempId = addOptimistic(form.body, sender, replyTarget.value);
    form.post(route('message-threads.messages.store', props.thread.id), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resolveOptimistic(tempId);
            replyTarget.value = null;
            form.reset();
        },
        onError: () => failOptimistic(tempId),
    });
}

// Re-send a failed (text-only optimistic) bubble without touching the live
// composer draft — posts the stored body + its quote directly.
function onRetry(message: BubbleMessage): void {
    dropFailed(message);
    const sender = { id: currentUserId.value, name: myName.value, role: myRole.value };
    const tempId = addOptimistic(message.body, sender, message.reply_to ?? null);
    router.post(route('message-threads.messages.store', props.thread.id), {
        body: message.body,
        reply_to_id: message.reply_to?.id ?? null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resolveOptimistic(tempId),
        onError: () => failOptimistic(tempId),
    });
}

const { subscribePrivate, unsubscribe } = useEcho();
const channelName = `inbox.thread.${props.thread.id}`;

onMounted(() => {
    subscribePrivate<{ user_id: number; read_at: string }>(channelName, '.message.read', (event) => {
        readCursors[event.user_id] = event.read_at;
    });
    subscribePrivate<IncomingPosted>(channelName, '.message.posted', (event) => ingest(event));
    subscribePrivate<IncomingReaction>(channelName, '.message.reacted', (event) => applyRemoteReaction(event));
});

onUnmounted(() => {
    unsubscribe(channelName);
});

// Phase-67 PRESENCE: live online roster + typing.
const me = currentUserId.value !== null
    ? { id: currentUserId.value, name: myName.value }
    : null;
const { members: onlineMembers, typing: typingNames, notifyTyping } = usePresenceChannel(props.thread.id, me);

let typingTimer: ReturnType<typeof setTimeout> | null = null;
function onType(): void {
    if (typingTimer) {
        return;
    }
    notifyTyping();
    typingTimer = setTimeout(() => {
        typingTimer = null;
    }, 500);
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
                <div class="flex items-center gap-3">
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-blue-600 hover:underline"
                        data-testid="mark-all-read"
                        @click="markAllRead"
                    >
                        {{ t('inbox.seen.mark_all') }}
                    </button>
                    <span class="text-xs uppercase tracking-wide text-gray-500">
                        {{ thread.status }}
                    </span>
                </div>
            </header>

            <div
                v-if="onlineMembers.length"
                class="flex flex-wrap items-center gap-3 text-xs text-gray-500"
            >
                <span
                    v-for="m in onlineMembers"
                    :key="m.id"
                    class="inline-flex items-center gap-1"
                    data-testid="presence-online"
                >
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ m.name }}
                </span>
            </div>

            <ChatThread
                :messages="streamMessages"
                :current-user-id="currentUserId"
                :others-read-at="othersReadAt"
                :unread-count="unreadCount"
                :typing-names="typingNames"
                :reaction-emojis="reactionEmojis"
                list-testid="message-list"
                @retry="onRetry"
                @reply="onReply"
                @react="onReact"
            >
                <template #composer>
                    <ChatComposer
                        v-model:body="form.body"
                        v-model:attachments="form.attachments"
                        :processing="form.processing"
                        :locked="thread.status !== 'open'"
                        :locked-status="thread.status"
                        :attachments-error="form.errors.attachments"
                        :reply-target="replyTarget"
                        testid="message-compose"
                        @send="submit"
                        @typing="onType"
                        @clear-reply="replyTarget = null"
                    />
                </template>
            </ChatThread>
        </div>
    </AuthenticatedLayout>
</template>
