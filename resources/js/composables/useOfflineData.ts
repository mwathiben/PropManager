/**
 * Phase-26 PWA-OFFLINE-1: cached-fallback fetch wrapper.
 *
 * Usage:
 *   const { data, isFresh, cachedAt, refresh } = useOfflineData(
 *     'dashboard.last-payment',
 *     () => axios.get('/api/v1/tenant/lease').then(r => r.data),
 *     { ttlMs: 5 * 60 * 1000 }, // optional, default null (no expiry)
 *   );
 *
 * Behaviour:
 *   - On mount: kicks off fetch, returns cached value immediately if
 *     present (so the UI never blank-renders).
 *   - On fetch success: writes value to IDB + sets isFresh=true.
 *   - On fetch failure (network or HTTP error): if cached, returns
 *     cached + isFresh=false + cachedAt. If no cache, error is
 *     surfaced via `error.value`.
 *   - refresh() forces a re-fetch (e.g. from a manual "Refresh" button
 *     when the user knows they're back online).
 *
 * Pairs with OnlineIndicator (PWA-OFFLINE-3): the indicator listens
 * to navigator.onLine events to show online/offline state; this
 * composable serves the actual data through the offline gap.
 */

import { ref, shallowRef, type Ref } from 'vue';
import { getCached, setCached } from '@/lib/offlineStore';

type Options = {
    ttlMs?: number | null;
};

export type UseOfflineDataReturn<T> = {
    data: Ref<T | null>;
    isFresh: Ref<boolean>;
    cachedAt: Ref<number | null>;
    error: Ref<Error | null>;
    loading: Ref<boolean>;
    refresh: () => Promise<void>;
};

export function useOfflineData<T>(
    key: string,
    fetcher: () => Promise<T>,
    options: Options = {},
): UseOfflineDataReturn<T> {
    const data = shallowRef<T | null>(null);
    const isFresh = ref(false);
    const cachedAt = ref<number | null>(null);
    const error = ref<Error | null>(null);
    const loading = ref(false);

    async function load(): Promise<void> {
        loading.value = true;
        error.value = null;

        // Read cache first so the UI never blank-flashes.
        const cached = await getCached<T>(key);
        if (cached) {
            data.value = cached.value;
            cachedAt.value = cached.cachedAt;
            isFresh.value = false;
        }

        try {
            const fresh = await fetcher();
            data.value = fresh;
            isFresh.value = true;
            cachedAt.value = Date.now();
            await setCached(key, fresh, options.ttlMs ?? null);
        } catch (e) {
            // If we have a cached copy, the UI continues with stale
            // data — surface the error for logging but don't blow up.
            if (!cached) {
                error.value = e instanceof Error ? e : new Error(String(e));
            }
        } finally {
            loading.value = false;
        }
    }

    // Kick off load eagerly.
    void load();

    return {
        data,
        isFresh,
        cachedAt,
        error,
        loading,
        refresh: load,
    };
}
