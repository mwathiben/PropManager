<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';
import ChatThread from '@/Components/Inbox/ChatThread.vue';
import ChatComposer from '@/Components/Inbox/ChatComposer.vue';
import { useI18n } from '@/composables/useI18n';
import { useEcho } from '@/composables/useEcho';
import { usePresenceChannel } from '@/composables/usePresenceChannel';
import { computed, onMounted, onUnmounted, reactive } from 'vue';

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
}

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage();

const currentUserId = computed<number | null>(
    () => ((page.props as Record<string, any>)?.auth?.user?.id as number | null) ?? null,
);

const readCursors = reactive<Record<number, string | null>>({});
props.read_receipts.forEach((r) => {
    readCursors[r.user_id] = r.last_read_at;
});

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

function markAllRead(): void {
    router.post(route('tenant.inbox.read-all', props.thread.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

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

const { subscribePrivate, unsubscribe } = useEcho();
const channelName = `inbox.thread.${props.thread.id}`;

onMounted(() => {
    subscribePrivate<{ user_id: number; read_at: string }>(channelName, '.message.read', (event) => {
        readCursors[event.user_id] = event.read_at;
    });
});

onUnmounted(() => {
    unsubscribe(channelName);
});

// Phase-67 PRESENCE: live online roster + typing.
const me = currentUserId.value !== null
    ? { id: currentUserId.value, name: String((page.props as Record<string, any>)?.auth?.user?.name ?? '') }
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
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs font-medium text-blue-600 hover:underline"
                    data-testid="mark-all-read"
                    @click="markAllRead"
                >
                    {{ t('inbox.seen.mark_all') }}
                </button>
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
                :messages="thread.messages"
                :current-user-id="currentUserId"
                :others-read-at="othersReadAt"
                :unread-count="unreadCount"
                :typing-names="typingNames"
                list-testid="tenant-message-list"
            >
                <template #composer>
                    <ChatComposer
                        v-model:body="form.body"
                        v-model:attachments="form.attachments"
                        :processing="form.processing"
                        :locked="thread.status !== 'open'"
                        :locked-status="thread.status"
                        :attachments-error="form.errors.attachments"
                        testid="tenant-message-compose"
                        @send="submit"
                        @typing="onType"
                    />
                </template>
            </ChatThread>
        </div>
    </AuthenticatedLayout>
</template>
