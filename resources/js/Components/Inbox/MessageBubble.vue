<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import { ArrowUturnLeftIcon, CheckIcon, ClockIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

export interface BubbleSender {
    id: number | null;
    name: string | null;
    role: string | null;
}
export interface BubbleDocument {
    id: number;
    title: string;
    mime_type: string;
}
export interface ReplyPreview {
    id: number;
    sender_name: string | null;
    body: string;
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
}

const props = defineProps<{
    message: BubbleMessage;
    isOwn: boolean;
    /** First message of a same-sender group (show avatar + name). */
    groupStart: boolean;
    /** Last of a group (show tail + timestamp + seen tick). */
    groupEnd: boolean;
    /** true = seen by another participant, false = sent only, null = not applicable. */
    seen: boolean | null;
}>();

defineEmits<{ retry: [BubbleMessage]; reply: [BubbleMessage]; jumpTo: [number] }>();

const { t } = useI18n();
const { formatRelativeTime } = useFormatters();

const isSystem = computed(() => props.message.message_type === 'system');
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

                <ul v-if="message.documents.length" class="mt-1.5 space-y-1">
                    <li
                        v-for="doc in message.documents"
                        :key="doc.id"
                        class="truncate text-xs"
                        :class="isOwn ? 'text-indigo-100' : 'text-indigo-700'"
                        data-testid="bubble-attachment"
                    >
                        📎 {{ doc.title }}
                    </li>
                </ul>
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
        </div>

        <button
            v-if="!message.pending"
            type="button"
            class="flex h-7 w-7 flex-shrink-0 items-center justify-center self-center rounded-full text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 focus:opacity-100 group-hover:opacity-100"
            :aria-label="t('inbox.chat.reply')"
            data-testid="message-reply"
            @click="$emit('reply', message)"
        >
            <ArrowUturnLeftIcon class="h-4 w-4" />
        </button>
    </li>
</template>
