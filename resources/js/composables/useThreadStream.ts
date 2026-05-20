/**
 * Phase-71 LIVE-DELIVERY: turns the server-rendered message list into a live
 * stream. Seeds from the Inertia prop, merges later prop reloads by id, and
 * `ingest()`s incoming `.message.posted` broadcasts (deduped, sender-echo
 * skipped — the broadcast already chains ->toOthers()). Also owns the
 * optimistic outgoing lifecycle: a 'sending' bubble appears instantly and is
 * reconciled away once the authoritative row arrives via the Inertia reload,
 * or marked 'failed' (with retry) when the post errors.
 *
 * This is a pure state machine — the Echo channel is owned by the page (which
 * already subscribes the same channel for `.message.read`), so there is exactly
 * one subscriber/unsubscriber per channel. Both inbox Show pages use this so
 * the streaming logic lives in one place; ChatThread stays presentational.
 */
import { ref, watch } from 'vue';
import type { BubbleMessage, BubbleSender } from '@/Components/Inbox/MessageBubble.vue';

export interface StreamMessage extends BubbleMessage {
    /** undefined = confirmed by the server; otherwise an optimistic bubble. */
    pending?: 'sending' | 'failed';
    /** Client correlation id for an optimistic bubble; absent once confirmed. */
    tempId?: string;
}

export interface IncomingPosted {
    message_id: number;
    thread_id: number;
    sender: BubbleSender | null;
    body: string;
    message_type: 'text' | 'system' | 'attachment';
    created_at: string;
}

export function useThreadStream(
    currentUserId: number | null,
    serverMessages: () => BubbleMessage[],
) {
    const messages = ref<StreamMessage[]>(serverMessages().map((m) => ({ ...m })));
    const knownIds = new Set<number>(messages.value.map((m) => m.id));
    let tempCounter = 0;

    function mergeServer(list: BubbleMessage[]): void {
        for (const m of list) {
            if (knownIds.has(m.id)) continue;
            knownIds.add(m.id);
            messages.value.push({ ...m });
        }
    }

    // Inertia reloads (the sender's own send, navigations) bring authoritative
    // rows; merge anything not already streamed.
    watch(serverMessages, (list) => mergeServer(list));

    // Called by the page's `.message.posted` listener.
    function ingest(event: IncomingPosted): void {
        if (event.sender?.id != null && event.sender.id === currentUserId) return;
        if (knownIds.has(event.message_id)) return;
        knownIds.add(event.message_id);
        messages.value.push({
            id: event.message_id,
            sender_id: event.sender?.id ?? null,
            sender: event.sender,
            body: event.body,
            message_type: event.message_type,
            created_at: event.created_at,
            documents: [],
        });
    }

    function addOptimistic(body: string, sender: BubbleSender): string {
        const tempId = `temp-${++tempCounter}`;
        messages.value.push({
            // Negative id never collides with a real (positive) row.
            id: -Date.now() - tempCounter,
            sender_id: sender.id ?? currentUserId,
            sender,
            body,
            message_type: 'text',
            created_at: new Date().toISOString(),
            documents: [],
            pending: 'sending',
            tempId,
        });
        return tempId;
    }

    function resolveOptimistic(tempId: string): void {
        const i = messages.value.findIndex((m) => m.tempId === tempId);
        if (i !== -1) messages.value.splice(i, 1);
    }

    function failOptimistic(tempId: string): void {
        const m = messages.value.find((mm) => mm.tempId === tempId);
        if (m) m.pending = 'failed';
    }

    // Remove a failed bubble by reference (the same object MessageBubble emits
    // back on retry), so the retry can re-add a fresh optimistic one.
    function dropFailed(message: BubbleMessage): void {
        const i = messages.value.indexOf(message as StreamMessage);
        if (i !== -1) messages.value.splice(i, 1);
    }

    return { messages, ingest, addOptimistic, resolveOptimistic, failOptimistic, dropFailed };
}
