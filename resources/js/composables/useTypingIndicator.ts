/**
 * Phase-63 INBOX-REALTIME-3: ephemeral typing indicators via Echo
 * whisper API. Whispers do NOT round-trip through Laravel — they are
 * client-to-client on the same private channel, so they cost nothing
 * server-side and never persist.
 *
 * Usage:
 *   const { typingUser, broadcastTyping } = useTypingIndicator(threadId, currentUser);
 *   // In compose textarea @input: broadcastTyping()
 *   // Render `{{ typingUser?.name }} is typing…` when typingUser is set.
 */

import { onBeforeUnmount, onMounted, ref } from 'vue';

interface TypingPayload {
    user_id: number;
    name: string;
}

declare global {
    interface Window {
        Echo?: any;
    }
}

const DEBOUNCE_MS = 500;
const CLEAR_MS = 3000;

export function useTypingIndicator(
    threadId: number,
    currentUser: { id: number; name: string },
) {
    const typingUser = ref<TypingPayload | null>(null);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let clearTimer: ReturnType<typeof setTimeout> | null = null;
    let channel: any = null;

    function broadcastTyping(): void {
        if (debounceTimer !== null) {
            return;
        }

        debounceTimer = setTimeout(() => {
            channel?.whisper('typing', {
                user_id: currentUser.id,
                name: currentUser.name,
            });
            debounceTimer = null;
        }, DEBOUNCE_MS);
    }

    function handleWhisper(payload: TypingPayload): void {
        if (payload.user_id === currentUser.id) {
            return;
        }

        typingUser.value = payload;

        if (clearTimer !== null) {
            clearTimeout(clearTimer);
        }
        clearTimer = setTimeout(() => {
            typingUser.value = null;
        }, CLEAR_MS);
    }

    onMounted(() => {
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        channel = window.Echo.private(`inbox.thread.${threadId}`);
        channel.listenForWhisper('typing', handleWhisper);
    });

    onBeforeUnmount(() => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        if (clearTimer !== null) {
            clearTimeout(clearTimer);
        }
        if (channel !== null && typeof channel.stopListeningForWhisper === 'function') {
            channel.stopListeningForWhisper('typing');
        }
    });

    return {
        typingUser,
        broadcastTyping,
    };
}
