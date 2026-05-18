/**
 * Phase-62 OFFLINE-PHOTOS-1/2/3: persistent blob store for ticket
 * annotation captures that fail to upload due to a network blip.
 *
 * TicketPhotoAnnotator.vue produces a canvas snapshot (PNG dataURL) +
 * scene JSON on save. Without this store, a network failure during
 * upload throws away the whole annotation — the user redraws from
 * scratch. With it, the blob lands in IDB first; the upload fires
 * second; on network failure the blob stays put + the boot-time
 * walker hands it off to the offline upload queue on the next online
 * tick.
 *
 * Budget: 50MB per user — eviction is oldest-first. We need a hard
 * cap because Chrome's Persistent Storage quota is per-origin and
 * sharing it with the rest of the PWA risks pushing other caches out.
 */

import { get, set, del, keys, createStore } from 'idb-keyval';

export const PHOTO_BUDGET_BYTES = 50 * 1024 * 1024;

const photoStore = createStore('pm-offline-photos', 'photos');

export type OfflinePhotoEntry = {
    key: string;
    ticketId: number;
    documentId: number;
    blob: Blob;
    annotationData: unknown;
    createdAt: number;
    attempts: number;
    status: 'pending' | 'uploading' | 'failed';
    lastError?: string;
};

export class PhotoQuotaExceededError extends Error {
    constructor() {
        super('Offline photo store is full and the new capture is larger than the budget');
        this.name = 'PhotoQuotaExceededError';
    }
}

function makeKey(ticketId: number, documentId: number): string {
    const ulid = crypto.randomUUID();
    return `ticket-${ticketId}-doc-${documentId}-${ulid}`;
}

export async function enqueuePhoto(
    op: Pick<OfflinePhotoEntry, 'ticketId' | 'documentId' | 'blob' | 'annotationData'>,
): Promise<string> {
    await enforceBudget(PHOTO_BUDGET_BYTES, op.blob.size);

    const key = makeKey(op.ticketId, op.documentId);
    const entry: OfflinePhotoEntry = {
        key,
        ticketId: op.ticketId,
        documentId: op.documentId,
        blob: op.blob,
        annotationData: op.annotationData,
        createdAt: Date.now(),
        attempts: 0,
        status: 'pending',
    };
    await set(key, entry, photoStore);
    return key;
}

export async function listPending(): Promise<OfflinePhotoEntry[]> {
    const ks = await keys(photoStore);
    const items = await Promise.all(
        ks.map((k) => get(k as string, photoStore) as Promise<OfflinePhotoEntry | undefined>),
    );
    return items.filter((i): i is OfflinePhotoEntry => i !== undefined);
}

export async function listPendingForTicket(ticketId: number): Promise<OfflinePhotoEntry[]> {
    const all = await listPending();
    return all.filter((e) => e.ticketId === ticketId);
}

export async function markUploading(key: string): Promise<void> {
    const entry = (await get(key, photoStore)) as OfflinePhotoEntry | undefined;
    if (!entry) return;
    entry.status = 'uploading';
    entry.attempts += 1;
    await set(key, entry, photoStore);
}

export async function markFailed(key: string, error: string): Promise<void> {
    const entry = (await get(key, photoStore)) as OfflinePhotoEntry | undefined;
    if (!entry) return;
    entry.status = 'failed';
    entry.lastError = error;
    await set(key, entry, photoStore);
}

export async function discardPhoto(key: string): Promise<void> {
    await del(key, photoStore);
}

export async function getTotalBytes(): Promise<number> {
    const items = await listPending();
    return items.reduce((sum, e) => sum + e.blob.size, 0);
}

/**
 * Evict oldest entries until total + incomingBytes <= maxBytes.
 * Returns the number of evicted entries. Throws PhotoQuotaExceededError
 * if even after evicting everything the incoming blob would not fit.
 */
export async function enforceBudget(maxBytes: number, incomingBytes: number = 0): Promise<number> {
    if (incomingBytes > maxBytes) {
        throw new PhotoQuotaExceededError();
    }
    let evicted = 0;
    let total = await getTotalBytes();
    if (total + incomingBytes <= maxBytes) return 0;

    const items = await listPending();
    items.sort((a, b) => a.createdAt - b.createdAt);
    for (const item of items) {
        if (total + incomingBytes <= maxBytes) break;
        await del(item.key, photoStore);
        total -= item.blob.size;
        evicted += 1;
    }
    return evicted;
}

export async function clearAll(): Promise<void> {
    const ks = await keys(photoStore);
    await Promise.all(ks.map((k) => del(k as string, photoStore)));
}
