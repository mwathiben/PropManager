/**
 * Phase-26 PWA-NETWORK-3 + Phase-62 CONNECTIVITY-UX-2: queued-operations
 * Pinia store.
 *
 * Background-sync (PWA-NETWORK-1) is invisible by default — the SW
 * replays POSTs silently. This store + the QueuedOpsTray component
 * make the queue user-visible: when a user creates an invoice/ticket
 * offline, an entry lands here; when the SW replays it, the entry
 * clears.
 *
 * Wired to the SW via a postMessage bridge in app.js: the SW posts
 * { type: 'BG_SYNC_DRAINED', queue } on successful replay; the host
 * page consumes that and calls drain(queue).
 *
 * Phase-62 additions:
 *   - routeFamily tag on each op (so QueuedOpsTray can group by type
 *     and PendingSyncBadge can filter by family).
 *   - resourceId tag so PendingSyncBadge can match a specific row.
 *   - hasPendingFor() selector for the badge component.
 *   - markDeadLetter() called by offlineWriteQueue when an op exceeds
 *     MAX_ATTEMPTS — surfaces a "Permanently failed" section in the
 *     tray.
 */

import { defineStore } from 'pinia';
import type { RouteFamily } from '@/composables/useBackgroundSync';

export type QueuedOp = {
    id: string;
    queue: string;
    label: string;
    queuedAt: number;
    routeFamily?: RouteFamily;
    resourceId?: string | number;
    deadLetter?: boolean;
    lastError?: string;
};

type State = {
    items: QueuedOp[];
};

export const useQueuedOpsStore = defineStore('queuedOps', {
    state: (): State => ({ items: [] }),
    getters: {
        count: (state) => state.items.filter((i) => !i.deadLetter).length,
        deadLetterCount: (state) => state.items.filter((i) => i.deadLetter).length,
        hasPending: (state) => state.items.some((i) => !i.deadLetter),
        byQueue: (state) => (queue: string) => state.items.filter((i) => i.queue === queue),
        byRouteFamily: (state) => (family: RouteFamily) =>
            state.items.filter((i) => i.routeFamily === family),
        hasPendingFor: (state) => (family: RouteFamily, resourceId?: string | number) =>
            state.items.some(
                (i) =>
                    !i.deadLetter &&
                    i.routeFamily === family &&
                    (resourceId === undefined || i.resourceId === resourceId),
            ),
    },
    actions: {
        add(op: Omit<QueuedOp, 'id' | 'queuedAt'> & { id?: string }): QueuedOp {
            const entry: QueuedOp = {
                id: op.id ?? crypto.randomUUID(),
                queue: op.queue,
                label: op.label,
                queuedAt: Date.now(),
                routeFamily: op.routeFamily,
                resourceId: op.resourceId,
            };
            this.items.push(entry);
            return entry;
        },
        cancel(id: string): void {
            this.items = this.items.filter((i) => i.id !== id);
        },
        drain(queue: string): void {
            this.items = this.items.filter((i) => i.queue !== queue || i.deadLetter);
        },
        markDeadLetter(id: string, error: string): void {
            const item = this.items.find((i) => i.id === id);
            if (item) {
                item.deadLetter = true;
                item.lastError = error;
            }
        },
        clear(): void {
            this.items = [];
        },
    },
});
