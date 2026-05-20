<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import MessageBubble, { type BubbleMessage } from '@/Components/Inbox/MessageBubble.vue';

const props = withDefaults(
    defineProps<{
        messages: BubbleMessage[];
        currentUserId: number | null;
        /** Max read cursor across OTHER participants (ISO) — drives seen ticks. */
        othersReadAt: string | null;
        /** Count of trailing unread messages (drives the divider). */
        unreadCount: number;
        /** Names of participants currently typing (Phase-67 presence whisper). */
        typingNames?: string[];
        listTestid?: string;
    }>(),
    { typingNames: () => [] },
);

const { t } = useI18n();
const { formatDate } = useFormatters();

const GROUP_GAP_MS = 5 * 60 * 1000;

const sameDay = (a: string, b: string) =>
    new Date(a).toDateString() === new Date(b).toDateString();

const dayLabel = (iso: string): string => {
    const d = new Date(iso);
    const today = new Date();
    const yest = new Date();
    yest.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return t('inbox.chat.today');
    if (d.toDateString() === yest.toDateString()) return t('inbox.chat.yesterday');
    return formatDate(iso, 'long');
};

const unreadIndex = computed(() =>
    props.unreadCount > 0 ? props.messages.length - props.unreadCount : -1,
);

const isOwn = (m: BubbleMessage) => m.sender_id !== null && m.sender_id === props.currentUserId;

const showDay = (i: number) =>
    i === 0 || !sameDay(props.messages[i - 1].created_at, props.messages[i].created_at);

const groupBreak = (a: BubbleMessage | undefined, b: BubbleMessage): boolean => {
    if (!a) return true;
    if (a.sender_id !== b.sender_id) return true;
    if (a.message_type === 'system' || b.message_type === 'system') return true;
    return new Date(b.created_at).getTime() - new Date(a.created_at).getTime() > GROUP_GAP_MS;
};

const isGroupStart = (i: number) => showDay(i) || groupBreak(props.messages[i - 1], props.messages[i]);
const isGroupEnd = (i: number) =>
    i === props.messages.length - 1 || showDay(i + 1) || groupBreak(props.messages[i], props.messages[i + 1]);

const seenFor = (m: BubbleMessage): boolean | null => {
    if (!isOwn(m)) return null;
    if (props.othersReadAt === null) return false;
    return new Date(m.created_at).getTime() <= new Date(props.othersReadAt).getTime();
};
</script>

<template>
    <div class="flex min-h-0 flex-col">
        <ol class="flex-1 space-y-0 overflow-y-auto px-1 py-2" :data-testid="listTestid ?? 'message-list'">
            <template v-for="(message, i) in messages" :key="message.id">
                <li v-if="showDay(i)" class="my-3 flex justify-center" data-testid="chat-day-separator">
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
                        {{ dayLabel(message.created_at) }}
                    </span>
                </li>

                <li
                    v-if="i === unreadIndex"
                    class="my-2 flex items-center gap-2 text-[11px] font-medium uppercase tracking-wide text-rose-500"
                    data-testid="chat-unread-divider"
                >
                    <span class="h-px flex-1 bg-rose-200"></span>
                    {{ t('inbox.chat.unread') }}
                    <span class="h-px flex-1 bg-rose-200"></span>
                </li>

                <MessageBubble
                    :message="message"
                    :is-own="isOwn(message)"
                    :group-start="isGroupStart(i)"
                    :group-end="isGroupEnd(i)"
                    :seen="seenFor(message)"
                />
            </template>

            <li
                v-if="typingNames.length"
                class="flex items-end gap-2"
                data-testid="chat-typing-bubble"
            >
                <div class="h-7 w-7 flex-shrink-0"></div>
                <div class="flex flex-col items-start">
                    <span class="mb-0.5 ms-1 text-xs font-medium text-gray-500" data-testid="presence-typing">
                        {{ t('inbox.presence.typing', { name: typingNames.join(', ') }, typingNames.length) }}
                    </span>
                    <div class="flex items-center gap-1 rounded-2xl rounded-es-md bg-white px-3 py-2.5 shadow-sm ring-1 ring-gray-100">
                        <span
                            v-for="dot in 3"
                            :key="dot"
                            class="h-1.5 w-1.5 rounded-full bg-gray-400 motion-safe:animate-bounce"
                            :style="{ animationDelay: `${(dot - 1) * 150}ms` }"
                        ></span>
                    </div>
                </div>
            </li>
        </ol>

        <slot name="composer" />
    </div>
</template>
