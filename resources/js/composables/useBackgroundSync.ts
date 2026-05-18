/**
 * Phase-26 PWA-NETWORK-1 + Phase-62 OFFLINE-WRITES-2: client-side
 * wrapper around Workbox's background-sync queue.
 *
 * Usage in a page (Invoices/Create.vue, Tickets/Create.vue, etc.):
 *
 *   const { submit } = useBackgroundSync({
 *       routeFamily: 'tickets',
 *       label: 'New ticket',
 *   });
 *   async function onSave() {
 *       try {
 *           await submit('/tickets', form.value);
 *       } catch (e) {
 *           if (e instanceof QueuedOfflineError) {
 *               // Offline: the SW captured the request, store now shows it
 *           }
 *       }
 *   }
 *
 * Mechanics:
 *   1. Compose POST with X-Idempotency-Key (a ULID generated client
 *      side) so replays are safe — server rejects duplicates by key
 *      (Phase-16 RESIL-3).
 *   2. axios.post fires. On success, return the response. On a
 *      network error, Workbox's BackgroundSyncPlugin (registered in
 *      resources/js/sw.ts) has already enqueued the request — record
 *      it in the queuedOps store + persistent offlineWriteQueue
 *      (Phase-62 OFFLINE-WRITES-3) so the UI shows it across tab
 *      restarts, then throw a sentinel `QueuedOfflineError`.
 *   3. The SW replays the request when connectivity returns and
 *      posts BG_SYNC_DRAINED — app.js routes that to
 *      queuedOps.drain(queue).
 */

import axios, { isAxiosError, type AxiosResponse } from 'axios';
import { useQueuedOpsStore } from '@/stores/queuedOps';
import { enqueueOfflineWrite } from '@/lib/offlineWriteQueue';

export class QueuedOfflineError extends Error {
    constructor(public readonly opId: string) {
        super('Request queued for offline replay');
        this.name = 'QueuedOfflineError';
    }
}

export type RouteFamily = 'invoices' | 'tickets' | 'comments' | 'readings' | 'payments';

const QUEUE_NAMES: Record<RouteFamily, string> = {
    invoices: 'pm-invoice-queue',
    tickets: 'pm-offline-tickets',
    comments: 'pm-offline-comments',
    readings: 'pm-offline-readings',
    payments: 'pm-offline-payments',
};

type Options = {
    routeFamily: RouteFamily;
    label: string;
};

function ulid(): string {
    // Crypto.randomUUID is good enough for idempotency; we don't need
    // strict ULID timestamps.
    return crypto.randomUUID();
}

export function useBackgroundSync(options: Options) {
    const store = useQueuedOpsStore();
    const queueName = QUEUE_NAMES[options.routeFamily];

    async function submit<T = unknown>(url: string, data: unknown): Promise<AxiosResponse<T>> {
        const idempotencyKey = ulid();

        try {
            return await axios.post<T>(url, data, {
                headers: { 'X-Idempotency-Key': idempotencyKey },
            });
        } catch (e) {
            // Network failure = Workbox grabbed the request. axios
            // throws ERR_NETWORK in that case (no `response` field).
            const isNetworkError =
                isAxiosError(e) && (!e.response || e.code === 'ERR_NETWORK');
            if (isNetworkError) {
                const op = store.add({
                    queue: queueName,
                    label: options.label,
                    routeFamily: options.routeFamily,
                });
                // Phase-62 OFFLINE-WRITES-3: persist so reopened tabs
                // still see the pending op even after the in-memory
                // Pinia store is gone.
                await enqueueOfflineWrite({
                    id: op.id,
                    routeFamily: options.routeFamily,
                    url,
                    payload: data,
                    idempotencyKey,
                });
                throw new QueuedOfflineError(op.id);
            }
            throw e;
        }
    }

    return { submit, queueName };
}

export { QUEUE_NAMES };
