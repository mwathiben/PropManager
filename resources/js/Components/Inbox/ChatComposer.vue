<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import AttachmentPreviewList from '@/Components/Inbox/AttachmentPreviewList.vue';
import type { ReplyPreview } from '@/Components/Inbox/MessageBubble.vue';
import { PaperAirplaneIcon, PaperClipIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = withDefaults(
    defineProps<{
        body: string;
        attachments: File[];
        processing: boolean;
        locked: boolean;
        /** Raw thread status surfaced in the locked message. */
        lockedStatus?: string;
        attachmentsError?: string;
        /** Preserves the per-page compose test hook (message-compose / tenant-message-compose). */
        testid: string;
        maxLength?: number;
        /** Phase-71 REPLY-QUOTE: the message being quoted, shown above the input. */
        replyTarget?: ReplyPreview | null;
    }>(),
    { processing: false, locked: false, lockedStatus: '', attachmentsError: '', maxLength: 4000, replyTarget: null },
);

const MAX_ATTACHMENTS = 5;

const emit = defineEmits<{
    'update:body': [string];
    'update:attachments': [File[]];
    send: [];
    typing: [];
    'clear-reply': [];
}>();

const { t } = useI18n();

const MAX_HEIGHT_PX = 160;
const textarea = ref<HTMLTextAreaElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

function autoGrow(): void {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, MAX_HEIGHT_PX)}px`;
}

// Reset the height after a successful send clears the bound value.
watch(
    () => props.body,
    () => nextTick(autoGrow),
);

const canSend = () => !props.processing && props.body.trim().length > 0;

function requestSend(): void {
    if (!canSend()) return;
    emit('send');
}

function onInput(event: Event): void {
    emit('update:body', (event.target as HTMLTextAreaElement).value);
    autoGrow();
    emit('typing');
}

// Enter sends, Shift+Enter inserts a newline. isComposing guards IME input
// (Arabic/CJK) so committing a candidate never fires a send.
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        requestSend();
    }
}

// Append to the current selection (don't clobber it), de-dupe identical
// files, cap at the server's max, then clear the input so removing a file
// and re-picking the same one fires `change` again.
function onPickFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const merged = [...props.attachments];
    for (const file of Array.from(input.files || [])) {
        const dup = merged.some(
            (e) => e.name === file.name && e.size === file.size && e.lastModified === file.lastModified,
        );
        if (!dup) merged.push(file);
    }
    emit('update:attachments', merged.slice(0, MAX_ATTACHMENTS));
    input.value = '';
}

function removeAttachment(index: number): void {
    emit('update:attachments', props.attachments.filter((_, i) => i !== index));
}

const remaining = () => props.maxLength - props.body.length;
const showCounter = () => props.body.length > props.maxLength * 0.9;
</script>

<template>
    <p
        v-if="locked"
        class="mt-3 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500"
        data-testid="composer-locked"
    >
        {{ t('inbox.chat.locked', { status: lockedStatus }) }}
    </p>

    <form
        v-else
        @submit.prevent="requestSend"
        class="sticky bottom-0 z-10 mt-3 rounded-2xl bg-white p-3 shadow ring-1 ring-gray-100"
        :data-testid="testid"
    >
        <div
            v-if="replyTarget"
            class="mb-2 flex items-start gap-2 rounded-lg border-s-2 border-indigo-400 bg-gray-50 px-3 py-2"
            data-testid="composer-reply-preview"
        >
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-indigo-700">
                    {{ t('inbox.chat.replying_to', { name: replyTarget.sender_name ?? '' }) }}
                </p>
                <p class="truncate text-xs text-gray-500">{{ replyTarget.body }}</p>
            </div>
            <button
                type="button"
                class="flex-shrink-0 rounded-full p-0.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600"
                :aria-label="t('inbox.chat.cancel_reply')"
                @click="emit('clear-reply')"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <div class="flex items-end gap-2">
            <button
                type="button"
                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                :aria-label="t('inbox.chat.attach')"
                @click="fileInput?.click()"
            >
                <PaperClipIcon class="h-5 w-5" />
            </button>
            <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,application/pdf"
                class="hidden"
                :aria-label="t('inbox.chat.attach')"
                @change="onPickFiles"
            />

            <textarea
                ref="textarea"
                :value="body"
                rows="1"
                :maxlength="maxLength"
                :placeholder="t('inbox.chat.placeholder')"
                :aria-label="t('inbox.chat.body_label')"
                class="max-h-40 min-h-[2.25rem] w-full resize-none border-0 bg-transparent p-1.5 text-sm focus:ring-0"
                @input="onInput"
                @keydown="onKeydown"
            ></textarea>

            <button
                type="submit"
                :disabled="!body.trim().length || processing"
                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white transition disabled:opacity-40"
                :aria-label="t('inbox.chat.send')"
            >
                <PaperAirplaneIcon class="h-5 w-5 rtl:-scale-x-100" />
            </button>
        </div>

        <div class="mt-1 flex items-center justify-end px-1">
            <span v-if="showCounter" class="text-[11px] text-gray-400" data-testid="composer-counter">
                {{ t('inbox.chat.chars_remaining', { count: remaining() }, remaining()) }}
            </span>
        </div>

        <AttachmentPreviewList
            class="mt-2"
            :files="attachments"
            @remove="removeAttachment"
        />

        <p
            v-if="attachmentsError"
            class="mt-2 text-xs font-medium text-rose-600"
            data-testid="attachment-blocked"
        >
            {{ attachmentsError }}
        </p>
    </form>
</template>
