<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';
import AttachmentPreviewList from '@/Components/Inbox/AttachmentPreviewList.vue';
import { useI18n } from '@/composables/useI18n';
import { useEcho } from '@/composables/useEcho';
import { usePresenceChannel } from '@/composables/usePresenceChannel';
import { computed, onMounted, onUnmounted, reactive } from 'vue';

interface Sender {
    id: number | null;
    name: string | null;
    role: string | null;
}

interface ThreadMessage {
    id: number;
    sender_id: number | null;
    sender: Sender | null;
    body: string;
    message_type: 'text' | 'system' | 'attachment';
    created_at: string;
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

function isSeenByOthers(message: ThreadMessage): boolean {
    if (message.sender_id === null || message.sender_id !== currentUserId.value) {
        return false;
    }
    const sentAt = new Date(message.created_at).getTime();
    return Object.entries(readCursors).some(
        ([userId, cursor]) => Number(userId) !== message.sender_id && cursor !== null && new Date(cursor).getTime() >= sentAt,
    );
}

const lastOwnMessageId = computed<number | null>(() => {
    for (let i = props.thread.messages.length - 1; i >= 0; i--) {
        if (props.thread.messages[i].sender_id === currentUserId.value) {
            return props.thread.messages[i].id;
        }
    }
    return null;
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

function onPickFiles(event: Event): void {
    form.attachments = Array.from((event.target as HTMLInputElement).files || []);
}

function removeAttachment(index: number): void {
    form.attachments = form.attachments.filter((_, i) => i !== index);
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
                v-if="onlineMembers.length || typingNames.length"
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
                <span v-if="typingNames.length" class="italic" data-testid="presence-typing">
                    {{ t('inbox.presence.typing', { name: typingNames.join(', ') }) }}
                </span>
            </div>

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
                    <p
                        v-if="message.id === lastOwnMessageId && isSeenByOthers(message)"
                        class="mt-1 text-right text-[11px] font-medium text-emerald-600"
                        data-testid="message-seen"
                    >
                        {{ t('inbox.seen.label') }}
                    </p>
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
                    @input="onType"
                ></textarea>
                <div class="mt-2 flex items-center justify-between">
                    <input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        @change="onPickFiles"
                        class="text-xs"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing || form.body.length === 0"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Send reply
                    </button>
                </div>

                <AttachmentPreviewList
                    class="mt-3"
                    :files="form.attachments"
                    @remove="removeAttachment"
                />

                <p
                    v-if="form.errors.attachments"
                    class="mt-2 text-xs font-medium text-rose-600"
                    data-testid="attachment-blocked"
                >
                    {{ form.errors.attachments }}
                </p>
            </form>

            <p v-else class="text-sm text-gray-500">
                This thread is {{ thread.status }} and cannot accept new messages.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
