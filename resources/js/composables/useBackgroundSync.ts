/**
 * Phase-26 PWA-NETWORK-1: client-side wrapper around Workbox's
 * background-sync queue.
 *
 * Usage in a page (Invoices/Create.vue) is typically:
 *
 *   const { submit } = useBackgroundSync({
 *       queue: 'pm-invoice-queue',
 *       label: 'New invoice',
 *   });
 *   async function onSave() {
 *       try {
 *           await submit('/invoices', form.value);
 *       } catch {
 *           // Offline: the SW captured the request, store now shows it
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
 *      it in the queuedOps store so the UI shows it, then throw a
 *      sentinel `QueuedOfflineError` so the caller knows the request
 *      was queued (vs. a real 4xx/5xx).
 *   3. The SW replays the request when connectivity returns and
 *      posts BG_SYNC_DRAINED — app.js routes that to
 *      queuedOps.drain(queue).
 */

import axios, { isAxiosError, type AxiosResponse } from 'axios';
import { useQueuedOpsStore } from '@/stores/queuedOps';

export class QueuedOfflineError extends Error {
    constructor(public readonly opId: string) {
        super('Request queued for offline replay');
        this.name = 'QueuedOfflineError';
    }
}

type Options = {
    queue: string;
    label: string;
};

function ulid(): string {
    // Lightweight ULID — Crockford base32 timestamp + 80-bit random.
    // Crypto.randomUUID is good enough for idempotency; we don't need
    // strict ULID timestamps.
    return crypto.randomUUID();
}

export function useBackgroundSync(options: Options) {
    const store = useQueuedOpsStore();

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
                const op = store.add({ queue: options.queue, label: options.label });
                throw new QueuedOfflineError(op.id);
            }
            throw e;
        }
    }

    return { submit };
}
