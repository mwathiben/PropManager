<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import { ArrowUturnLeftIcon, CheckIcon, ClockIcon, DocumentIcon, ExclamationCircleIcon, FaceSmileIcon } from '@heroicons/vue/24/outline';

export interface BubbleSender {
    id: number | null;
    name: string | null;
    role: string | null;
}
export interface BubbleDocument {
    id: number;
    title: string;
    mime_type: string;
    is_image: boolean;
    file_size_formatted: string;
    scan_status?: string | null;
    url: string;
}
export interface ReplyPreview {
    id: number;
    sender_name: string | null;
    body: string;
}
export interface ReactionSummary {
    emoji: string;
    count: number;
    reacted: boolean;
}
export interface BubbleMessage {
    id: number;
    sender_id: number | null;
    sender: BubbleSender | null;
    body: string;
    message_type: 'text' | 'system' | 'attachment';
    created_at: string;
    documents: BubbleDocument[];
    /** Optimistic outgoing lifecycle (Phase-71 LIVE-DELIVERY); absent once confirmed. */
    pending?: 'sending' | 'failed';
    /** Phase-71 REPLY-QUOTE: compact quote of the message this one replies to. */
    reply_to?: ReplyPreview | null;
    /** Phase-71 REACTIONS: grouped emoji reaction summary. */
    reactions?: ReactionSummary[];
}

const props = withDefaults(
    defineProps<{
        message: BubbleMessage;
        isOwn: boolean;
        /** First message of a same-sender group (show avatar + name). */
        groupStart: boolean;
        /** Last of a group (show tail + timestamp + seen tick). */
        groupEnd: boolean;
        /** true = seen by another participant, false = sent only, null = not applicable. */
        seen: boolean | null;
        /** Phase-71 REACTIONS: allow-list emojis offered by the picker. */
        reactionEmojis?: string[];
    }>(),
    { reactionEmojis: () => [] },
);

const emit = defineEmits<{
    retry: [BubbleMessage];
    reply: [BubbleMessage];
    jumpTo: [number];
    react: [{ message: BubbleMessage; emoji: string }];
    openImage: [{ url: string; title: string }];
}>();

const { t } = useI18n();
const { formatRelativeTime } = useFormatters();

const isSystem = computed(() => props.message.message_type === 'system');
const pickerOpen = ref(false);

function react(emoji: string): void {
    pickerOpen.value = false;
    emit('react', { message: props.message, emoji });
}
const initials = computed(() => {
    const n = props.message.sender?.name ?? '';
    return n.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
});
</script>

<template>
    <!-- System message: centered chip -->
    <li v-if="isSystem" class="my-2 flex justify-center" data-testid="chat-system">
        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800 ring-1 ring-amber-200">
            {{ message.body }}
        </span>
    </li>

    <li
        v-else
        class="group flex items-end gap-2"
        :class="[isOwn ? 'flex-row-reverse' : 'flex-row', groupEnd ? 'mb-2' : 'mb-0.5']"
        :data-message-id="message.id"
        data-testid="chat-bubble"
    >
        <!-- Avatar (others only, once per group) -->
        <div class="h-7 w-7 flex-shrink-0">
            <div
                v-if="!isOwn && groupStart"
                class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold text-gray-600"
                aria-hidden="true"
            >
                {{ initials }}
            </div>
        </div>

        <div class="flex max-w-[78%] flex-col" :class="isOwn ? 'items-end' : 'items-start'">
            <span v-if="!isOwn && groupStart" class="mb-0.5 ms-1 text-xs font-medium text-gray-500">
                {{ message.sender?.name }}
            </span>

            <div
                class="px-3 py-2 text-sm shadow-sm"
                :class="[
                    isOwn ? 'bg-indigo-600 text-white' : 'bg-white text-gray-900 ring-1 ring-gray-100',
                    isOwn
                        ? (groupEnd ? 'rounded-2xl rounded-ee-md' : 'rounded-2xl')
                        : (groupEnd ? 'rounded-2xl rounded-es-md' : 'rounded-2xl'),
                ]"
            >
                <button
                    v-if="message.reply_to"
                    type="button"
                    class="mb-1 block w-full rounded-md border-s-2 px-2 py-1 text-start text-xs"
                    :class="isOwn ? 'border-indigo-200 bg-indigo-500/40' : 'border-indigo-400 bg-gray-50'"
                    data-testid="bubble-quote"
                    @click="$emit('jumpTo', message.reply_to.id)"
                >
                    <span class="block font-medium" :class="isOwn ? 'text-indigo-100' : 'text-indigo-700'">
                        {{ message.reply_to.sender_name }}
                    </span>
                    <span class="block truncate" :class="isOwn ? 'text-indigo-50/90' : 'text-gray-500'">
                        {{ message.reply_to.body }}
                    </span>
                </button>

                <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>

                <div v-if="message.documents.length" class="mt-1.5 space-y-1.5">
                    <template v-for="doc in message.documents" :key="doc.id">
                        <!-- Blocked / not-yet-clean: neutral placeholder. -->
                        <div
                            v-if="doc.scan_status && doc.scan_status !== 'clean'"
                            class="flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-400"
                            data-testid="bubble-attachment"
                        >
                            <ExclamationCircleIcon class="h-4 w-4" />
                            {{ t('inbox.chat.attachment.unavailable') }}
                        </div>

                        <!-- Image: inline thumbnail opening the lightbox. -->
                        <button
                            v-else-if="doc.is_image"
                            type="button"
                            class="block overflow-hidden rounded-lg"
                            :aria-label="t('inbox.chat.attachment.open_image')"
                            data-testid="bubble-attachment"
                            @click="$emit('openImage', { url: doc.url, title: doc.title })"
                        >
                            <img
                                :src="doc.url"
                                :alt="doc.title"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                class="max-h-48 w-auto rounded-lg object-cover"
                            />
                        </button>

                        <!-- Other files: download chip. -->
                        <a
                            v-else
                            :href="doc.url"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs ring-1"
                            :class="isOwn ? 'bg-indigo-500/40 ring-indigo-300' : 'bg-gray-50 text-gray-700 ring-gray-200'"
                            data-testid="bubble-attachment"
                        >
                            <DocumentIcon class="h-5 w-5 flex-shrink-0" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ doc.title }}</span>
                                <span class="block text-[10px] opacity-75">{{ doc.file_size_formatted }}</span>
                            </span>
                        </a>
                    </template>
                </div>
            </div>

            <div v-if="groupEnd" class="mt-0.5 flex items-center gap-1 px-1 text-[10px] text-gray-400">
                <time>{{ formatRelativeTime(message.created_at) }}</time>

                <button
                    v-if="isOwn && message.pending === 'failed'"
                    type="button"
                    class="inline-flex items-center gap-0.5 font-medium text-rose-500 hover:underline"
                    data-testid="message-failed"
                    @click="$emit('retry', message)"
                >
                    <ExclamationCircleIcon class="h-3 w-3" />
                    {{ t('inbox.chat.retry') }}
                </button>
                <span
                    v-else-if="isOwn && message.pending === 'sending'"
                    class="inline-flex items-center"
                    :aria-label="t('inbox.chat.sending')"
                    data-testid="message-sending"
                >
                    <ClockIcon class="h-3 w-3" />
                </span>
                <span
                    v-else-if="isOwn && seen !== null"
                    class="inline-flex items-center"
                    :class="seen ? 'text-sky-500' : 'text-gray-400'"
                    :aria-label="seen ? t('inbox.seen.label') : t('inbox.chat.sent')"
                    data-testid="message-seen"
                >
                    <CheckIcon class="h-3 w-3" />
                    <CheckIcon v-if="seen" class="-ms-1.5 h-3 w-3" />
                </span>
            </div>

            <div
                v-if="message.reactions && message.reactions.length"
                class="mt-1 flex flex-wrap gap-1"
                :class="isOwn ? 'justify-end' : 'justify-start'"
                data-testid="bubble-reactions"
            >
                <button
                    v-for="r in message.reactions"
                    :key="r.emoji"
                    type="button"
                    class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs ring-1"
                    :class="r.reacted ? 'bg-indigo-50 text-indigo-700 ring-indigo-300' : 'bg-white text-gray-600 ring-gray-200'"
                    :aria-label="t('inbox.chat.reactions.pill_label', { emoji: r.emoji, count: r.count })"
                    :aria-pressed="r.reacted"
                    data-testid="reaction-pill"
                    @click="react(r.emoji)"
                >
                    <span aria-hidden="true">{{ r.emoji }}</span>
                    <span>{{ r.count }}</span>
                </button>
            </div>
        </div>

        <div v-if="!message.pending" class="relative flex flex-col items-center gap-1 self-center">
            <button
                type="button"
                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 focus:opacity-100 group-hover:opacity-100"
                :aria-label="t('inbox.chat.reply')"
                data-testid="message-reply"
                @click="$emit('reply', message)"
            >
                <ArrowUturnLeftIcon class="h-4 w-4" />
            </button>

            <button
                v-if="reactionEmojis.length"
                type="button"
                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 focus:opacity-100 group-hover:opacity-100"
                :class="{ 'opacity-100': pickerOpen }"
                :aria-label="t('inbox.chat.reactions.add')"
                :aria-expanded="pickerOpen"
                data-testid="reaction-picker-toggle"
                @click="pickerOpen = !pickerOpen"
            >
                <FaceSmileIcon class="h-4 w-4" />
            </button>

            <div
                v-if="pickerOpen"
                class="absolute bottom-full z-20 mb-1 flex gap-1 rounded-full bg-white p-1 shadow-lg ring-1 ring-gray-200"
                data-testid="reaction-picker"
            >
                <button
                    v-for="emoji in reactionEmojis"
                    :key="emoji"
                    type="button"
                    class="flex h-7 w-7 items-center justify-center rounded-full text-base hover:bg-gray-100"
                    :aria-label="t('inbox.chat.reactions.react_with', { emoji })"
                    @click="react(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>
    </li>
</template>
