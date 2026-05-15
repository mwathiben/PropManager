/**
 * Phase-26 PWA-NETWORK-3: queued-operations Pinia store.
 *
 * Background-sync (PWA-NETWORK-1) is invisible by default — the SW
 * replays POSTs silently. This store + the QueuedOpsTray component
 * make the queue user-visible: when a user creates an invoice
 * offline, an entry lands here; when the SW replays it, the entry
 * clears.
 *
 * Wired to the SW via a postMessage bridge in app.js: the SW posts
 * { type: 'BG_SYNC_DRAINED', queue } on successful replay; the host
 * page consumes that and calls drain(queue).
 */

import { defineStore } from 'pinia';

export type QueuedOp = {
    id: string;
    queue: string;
    label: string;
    queuedAt: number;
};

type State = {
    items: QueuedOp[];
};

export const useQueuedOpsStore = defineStore('queuedOps', {
    state: (): State => ({ items: [] }),
    getters: {
        count: (state) => state.items.length,
        hasPending: (state) => state.items.length > 0,
        byQueue: (state) => (queue: string) => state.items.filter((i) => i.queue === queue),
    },
    actions: {
        add(op: Omit<QueuedOp, 'id' | 'queuedAt'> & { id?: string }): QueuedOp {
            const entry: QueuedOp = {
                id: op.id ?? crypto.randomUUID(),
                queue: op.queue,
                label: op.label,
                queuedAt: Date.now(),
            };
            this.items.push(entry);
            return entry;
        },
        cancel(id: string): void {
            this.items = this.items.filter((i) => i.id !== id);
        },
        drain(queue: string): void {
            this.items = this.items.filter((i) => i.queue !== queue);
        },
        clear(): void {
            this.items = [];
        },
    },
});
