/**
 * Phase-26 PWA-OFFLINE-2: thin IndexedDB wrapper.
 *
 * Why not localStorage? localStorage is a synchronous 5MB SHARED store
 * — it wedges the main thread under quota pressure and is shared
 * across browser tabs unsafely. IndexedDB is the right tool; idb-keyval
 * makes its async API painless (<1KB on the wire).
 *
 * Why per-user namespacing? A landlord using two browser profiles, or
 * an admin impersonating a tenant (Phase-14 IMPERSONATE-2), must not
 * see each other's cached data. Keys are prefixed
 * `pm:${userId}:${landlordId}:${key}`. On logout, clearForCurrentUser
 * wipes the prefix.
 *
 * Why TTL? Stale cached invoice totals are worse than no cache —
 * landlord acts on outdated info. Per-entry TTL evicts on read.
 */

import { get, set, del, keys, clear } from 'idb-keyval';

type Envelope<T> = {
    value: T;
    cachedAt: number;
    ttlMs: number | null;
};

let identityResolver: () => { userId: number | null; landlordId: number | null } = () => ({
    userId: null,
    landlordId: null,
});

/**
 * Wire the identity resolver once at app boot (resources/js/app.js).
 * Defaults to nulls until set so calls before boot are non-fatal.
 */
export function configureOfflineStoreIdentity(
    resolver: () => { userId: number | null; landlordId: number | null },
): void {
    identityResolver = resolver;
}

function prefix(): string {
    const { userId, landlordId } = identityResolver();
    return `pm:${userId ?? 'anon'}:${landlordId ?? 'none'}:`;
}

function fullKey(key: string): string {
    return prefix() + key;
}

export async function getCached<T>(key: string): Promise<{ value: T; cachedAt: number } | null> {
    const envelope = (await get(fullKey(key))) as Envelope<T> | undefined;
    if (!envelope) return null;

    if (envelope.ttlMs !== null && Date.now() - envelope.cachedAt > envelope.ttlMs) {
        await del(fullKey(key));
        return null;
    }

    return { value: envelope.value, cachedAt: envelope.cachedAt };
}

export async function setCached<T>(key: string, value: T, ttlMs: number | null = null): Promise<void> {
    const envelope: Envelope<T> = {
        value,
        cachedAt: Date.now(),
        ttlMs,
    };
    await set(fullKey(key), envelope);
}

export async function delCached(key: string): Promise<void> {
    await del(fullKey(key));
}

/**
 * Clear every entry that belongs to the currently-identified user.
 * Call on logout to prevent the next user from inheriting cached state.
 */
export async function clearForCurrentUser(): Promise<void> {
    const ownPrefix = prefix();
    const allKeys = await keys();
    await Promise.all(
        allKeys
            .filter((k): k is string => typeof k === 'string' && k.startsWith(ownPrefix))
            .map((k) => del(k)),
    );
}

/**
 * Hard-wipe the entire IDB store. Used by the runbook's stuck-client
 * recipe. Don't call from app code — the per-user variant is what you
 * want on logout.
 */
export async function clearAllCachedData(): Promise<void> {
    await clear();
}
