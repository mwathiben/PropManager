/**
 * Phase-67 PRESENCE-2: live presence roster + typing for an inbox thread.
 *
 * Wraps Echo.join() on the pivot-authorised presence channel
 * `inbox.presence.{threadId}` (a non-participant can't join — the channel
 * auth returns false). Exposes the reactive online roster, a derived
 * "who is typing" list, and a notifyTyping() whisper. Typing is carried
 * as a presence-channel whisper (client-to-client, never persisted) and
 * auto-expires after 3s. Replaces the standalone useTypingIndicator on
 * the thread pages by unifying typing into the presence roster.
 */
import { ref, onUnmounted, type Ref } from 'vue';

export interface PresenceMember {
    id: number;
    name: string;
    role: string | null;
}

interface CurrentUser {
    id: number;
    name: string;
}

export interface UsePresenceChannelReturn {
    members: Ref<PresenceMember[]>;
    onlineIds: Ref<number[]>;
    typing: Ref<string[]>;
    notifyTyping: () => void;
}

const TYPING_TTL_MS = 3000;

export function usePresenceChannel(threadId: number, currentUser: CurrentUser | null): UsePresenceChannelReturn {
    const members = ref<PresenceMember[]>([]);
    const onlineIds = ref<number[]>([]);
    const typing = ref<string[]>([]);

    // user_id -> { name, at } of the last typing whisper.
    const typingState = new Map<number, { name: string; at: number }>();

    function syncOnline(): void {
        onlineIds.value = members.value.map((m) => m.id);
    }

    function recomputeTyping(): void {
        const now = Date.now();
        const live: string[] = [];
        for (const [id, entry] of typingState) {
            if (now - entry.at >= TYPING_TTL_MS) {
                typingState.delete(id);
                continue;
            }
            if (id !== currentUser?.id) {
                live.push(entry.name);
            }
        }
        typing.value = live;
    }

    const echo = typeof window !== 'undefined' ? (window as { Echo?: any }).Echo : null;
    const channelName = `inbox.presence.${threadId}`;
    let channel: any = null;

    if (echo) {
        channel = echo.join(channelName)
            .here((users: PresenceMember[]) => {
                members.value = users;
                syncOnline();
            })
            .joining((user: PresenceMember) => {
                if (!members.value.some((m) => m.id === user.id)) {
                    members.value = [...members.value, user];
                    syncOnline();
                }
            })
            .leaving((user: PresenceMember) => {
                members.value = members.value.filter((m) => m.id !== user.id);
                syncOnline();
            })
            .listenForWhisper('typing', (event: { user_id: number; name: string }) => {
                typingState.set(event.user_id, { name: event.name, at: Date.now() });
                recomputeTyping();
                window.setTimeout(recomputeTyping, TYPING_TTL_MS + 100);
            });
    }

    function notifyTyping(): void {
        if (channel && currentUser) {
            channel.whisper('typing', { user_id: currentUser.id, name: currentUser.name });
        }
    }

    onUnmounted(() => {
        if (echo) {
            echo.leave(channelName);
        }
    });

    return { members, onlineIds, typing, notifyTyping };
}
