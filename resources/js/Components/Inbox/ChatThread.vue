<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
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
        /** Phase-71 REACTIONS: allow-list emojis offered by the picker. */
        reactionEmojis?: string[];
        listTestid?: string;
    }>(),
    { typingNames: () => [], reactionEmojis: () => [] },
);

defineEmits<{
    retry: [BubbleMessage];
    reply: [BubbleMessage];
    react: [{ message: BubbleMessage; emoji: string }];
}>();

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

// Anchor the unread divider to the id of the first unread message, captured
// once at load, so streaming/optimistic appends never shift it.
const firstUnreadId =
    props.unreadCount > 0 && props.messages.length >= props.unreadCount
        ? props.messages[props.messages.length - props.unreadCount].id
        : null;

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

// Scroll management: stick to the newest message while the reader is at the
// bottom; otherwise surface a jump-to-latest pill counting what arrived below.
const NEAR_BOTTOM_PX = 80;
const scrollEl = ref<HTMLElement | null>(null);
const atBottom = ref(true);
const unreadBelow = ref(0);

function isNearBottom(): boolean {
    const el = scrollEl.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight < NEAR_BOTTOM_PX;
}

function scrollToBottom(smooth = false): void {
    const el = scrollEl.value;
    if (!el) return;
    el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    atBottom.value = true;
    unreadBelow.value = 0;
}

function onScroll(): void {
    atBottom.value = isNearBottom();
    if (atBottom.value) unreadBelow.value = 0;
}

// Tap on a quoted snippet scrolls to (and briefly highlights) the original.
function jumpToMessage(id: number): void {
    const el = scrollEl.value?.querySelector<HTMLElement>(`[data-message-id="${id}"]`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('ring-2', 'ring-indigo-300', 'rounded-2xl');
    window.setTimeout(() => el.classList.remove('ring-2', 'ring-indigo-300', 'rounded-2xl'), 1200);
}

onMounted(() => nextTick(() => scrollToBottom(false)));

watch(
    () => props.messages.length,
    (newLen, oldLen) => {
        if (newLen <= oldLen) return;
        if (atBottom.value) {
            nextTick(() => scrollToBottom(false));
        } else {
            unreadBelow.value += newLen - oldLen;
        }
    },
);
</script>

<template>
    <div class="flex min-h-0 flex-col">
        <div class="relative flex-1">
            <ol
                ref="scrollEl"
                class="max-h-[65vh] space-y-0 overflow-y-auto px-1 py-2"
                :data-testid="listTestid ?? 'message-list'"
                @scroll="onScroll"
            >
                <template v-for="(message, i) in messages" :key="message.id">
                    <li v-if="showDay(i)" class="my-3 flex justify-center" data-testid="chat-day-separator">
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
                            {{ dayLabel(message.created_at) }}
                        </span>
                    </li>

                    <li
                        v-if="firstUnreadId !== null && message.id === firstUnreadId"
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
                        :reaction-emojis="reactionEmojis"
                        @retry="$emit('retry', $event)"
                        @reply="$emit('reply', $event)"
                        @jump-to="jumpToMessage"
                        @react="$emit('react', $event)"
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

            <button
                v-if="!atBottom"
                type="button"
                class="absolute bottom-3 end-4 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-lg ring-1 ring-gray-200 hover:bg-gray-50"
                :aria-label="t('inbox.chat.jump_latest')"
                data-testid="chat-jump-latest"
                @click="scrollToBottom(true)"
            >
                <span
                    v-if="unreadBelow > 0"
                    class="rounded-full bg-rose-500 px-1.5 py-px text-[10px] font-semibold text-white"
                >
                    {{ unreadBelow }}
                </span>
                <ChevronDownIcon class="h-4 w-4" />
            </button>
        </div>

        <slot name="composer" />
    </div>
</template>
