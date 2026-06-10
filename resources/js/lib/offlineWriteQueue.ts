/**
 * Phase-62 OFFLINE-WRITES-3: persistent offline write queue with
 * retry-with-backoff + dead-letter.
 *
 * Why this exists on top of Workbox BackgroundSyncPlugin:
 *   - Workbox persists requests in its own IDB store but has NO
 *     dead-letter mechanism. A permanently failing op (e.g., 422
 *     validation error on replay) just sits there or gets dropped
 *     after maxRetentionTime — the user is never told.
 *   - The Phase-26 queuedOps Pinia store is in-memory only. Close
 *     the tab and the user's visible list of pending writes is gone
 *     even though Workbox is still going to replay them.
 *
 * This module gives the host page durable visibility:
 *   - `queue` store: pending ops, keyed by ULID, with attempts count.
 *   - `dead-letter` store: ops that exceeded MAX_ATTEMPTS.
 *   - `replay-log` store: last 50 successful replays (audit/debug).
 *
 * Hydrate the Pinia store from IDB on app boot so reopened tabs see
 * pending work. See app.js for the boot hook.
 */

import { get, set, del, keys, createStore } from 'idb-keyval';
import type { RouteFamily } from '@/composables/useBackgroundSync';

export const MAX_ATTEMPTS = 5;
export const REPLAY_LOG_MAX = 50;

// idb-keyval's createStore() only creates its object store inside the
// DB's onupgradeneeded, which fires once per DB version. Three
// createStore() calls against the SAME db name share one v1 upgrade, so
// only the first store ('queue') is ever created — touching the other
// two throws NotFoundError ("object store not found"). Give each store
// its own database so every createStore() runs its own upgrade.
const queueStore = createStore('pm-offline-writes-queue', 'queue');
const deadLetterStore = createStore('pm-offline-writes-dead-letter', 'dead-letter');
const replayLogStore = createStore('pm-offline-writes-replay-log', 'replay-log');

export type OfflineWriteEntry = {
    id: string;
    routeFamily: RouteFamily;
    url: string;
    payload: unknown;
    idempotencyKey: string;
    createdAt: number;
    attempts: number;
    lastError?: string;
};

export type ReplayLogEntry = {
    id: string;
    routeFamily: RouteFamily;
    url: string;
    succeededAt: number;
    attempts: number;
};

export async function enqueueOfflineWrite(
    op: Pick<OfflineWriteEntry, 'id' | 'routeFamily' | 'url' | 'payload' | 'idempotencyKey'>,
): Promise<void> {
    const entry: OfflineWriteEntry = {
        ...op,
        createdAt: Date.now(),
        attempts: 0,
    };
    await set(op.id, entry, queueStore);
}

export async function listPending(): Promise<OfflineWriteEntry[]> {
    const ks = await keys(queueStore);
    const items = await Promise.all(
        ks.map((k) => get(k as string, queueStore) as Promise<OfflineWriteEntry | undefined>),
    );
    return items.filter((i): i is OfflineWriteEntry => i !== undefined);
}

export async function listDeadLetter(): Promise<OfflineWriteEntry[]> {
    const ks = await keys(deadLetterStore);
    const items = await Promise.all(
        ks.map((k) => get(k as string, deadLetterStore) as Promise<OfflineWriteEntry | undefined>),
    );
    return items.filter((i): i is OfflineWriteEntry => i !== undefined);
}

export async function listReplayLog(): Promise<ReplayLogEntry[]> {
    const ks = await keys(replayLogStore);
    const items = await Promise.all(
        ks.map((k) => get(k as string, replayLogStore) as Promise<ReplayLogEntry | undefined>),
    );
    return items.filter((i): i is ReplayLogEntry => i !== undefined);
}

export async function recordReplayAttempt(id: string, error?: string): Promise<'retry' | 'dead-letter'> {
    const entry = (await get(id, queueStore)) as OfflineWriteEntry | undefined;
    if (!entry) return 'retry';
    entry.attempts += 1;
    entry.lastError = error;
    if (entry.attempts >= MAX_ATTEMPTS) {
        await del(id, queueStore);
        await set(id, entry, deadLetterStore);
        return 'dead-letter';
    }
    await set(id, entry, queueStore);
    return 'retry';
}

export async function recordReplaySuccess(id: string): Promise<void> {
    const entry = (await get(id, queueStore)) as OfflineWriteEntry | undefined;
    if (!entry) return;
    await del(id, queueStore);
    const log: ReplayLogEntry = {
        id: entry.id,
        routeFamily: entry.routeFamily,
        url: entry.url,
        succeededAt: Date.now(),
        attempts: entry.attempts + 1,
    };
    await set(id, log, replayLogStore);
    await pruneReplayLog();
}

async function pruneReplayLog(): Promise<void> {
    const ks = await keys(replayLogStore);
    if (ks.length <= REPLAY_LOG_MAX) return;
    const entries = await Promise.all(
        ks.map(async (k) => ({
            key: k as string,
            entry: (await get(k as string, replayLogStore)) as ReplayLogEntry | undefined,
        })),
    );
    entries
        .filter((e): e is { key: string; entry: ReplayLogEntry } => e.entry !== undefined)
        .sort((a, b) => a.entry.succeededAt - b.entry.succeededAt)
        .slice(0, entries.length - REPLAY_LOG_MAX)
        .forEach(({ key }) => {
            void del(key, replayLogStore);
        });
}

export async function discardOfflineWrite(id: string): Promise<void> {
    await del(id, queueStore);
    await del(id, deadLetterStore);
}

export async function clearAll(): Promise<void> {
    const qk = await keys(queueStore);
    const dk = await keys(deadLetterStore);
    const lk = await keys(replayLogStore);
    await Promise.all([
        ...qk.map((k) => del(k as string, queueStore)),
        ...dk.map((k) => del(k as string, deadLetterStore)),
        ...lk.map((k) => del(k as string, replayLogStore)),
    ]);
}
