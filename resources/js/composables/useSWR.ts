/**
 * Stale-While-Revalidate (SWR) data fetching composable
 * Returns cached data immediately while revalidating in background
 */

import { ref, shallowRef, computed, type Ref, type ShallowRef } from 'vue';

export interface UseSWROptions<T> {
    /** Time in ms before cached data is considered stale (default: 60000) */
    staleTime?: number;
    /** Time in ms to keep data in cache (default: 300000) */
    cacheTime?: number;
    /** Initial data to use before fetch completes */
    initialData?: T;
    /** Whether to fetch immediately (default: true) */
    immediate?: boolean;
    /** Deduplicate concurrent requests to same key (default: true) */
    dedupe?: boolean;
}

export interface UseSWRReturn<T> {
    data: ShallowRef<T | null>;
    error: Ref<Error | null>;
    isLoading: Ref<boolean>;
    isValidating: Ref<boolean>;
    isStale: Ref<boolean>;
    mutate: (newData?: T) => Promise<T | null>;
    refresh: () => Promise<T | null>;
}

interface CacheEntry<T> {
    data: T;
    timestamp: number;
    expiresAt: number;
}

const cache = new Map<string, CacheEntry<unknown>>();
const pendingRequests = new Map<string, Promise<unknown>>();

function getCacheEntry<T>(key: string): CacheEntry<T> | undefined {
    const entry = cache.get(key) as CacheEntry<T> | undefined;
    if (entry && Date.now() > entry.expiresAt) {
        cache.delete(key);
        return undefined;
    }
    return entry;
}

function setCacheEntry<T>(key: string, data: T, cacheTime: number): void {
    cache.set(key, {
        data,
        timestamp: Date.now(),
        expiresAt: Date.now() + cacheTime,
    });
}

export function useSWR<T>(
    key: string | (() => string),
    fetcher: (key: string) => Promise<T>,
    options: UseSWROptions<T> = {}
): UseSWRReturn<T> {
    const {
        staleTime = 60000,
        cacheTime = 300000,
        initialData,
        immediate = true,
        dedupe = true,
    } = options;

    const data = shallowRef<T | null>(initialData ?? null) as ShallowRef<T | null>;
    const error = ref<Error | null>(null);
    const isLoading = ref(false);
    const isValidating = ref(false);

    const resolvedKey = computed(() => typeof key === 'function' ? key() : key);

    const isStale = computed(() => {
        const entry = getCacheEntry<T>(resolvedKey.value);
        if (!entry) return true;
        return Date.now() - entry.timestamp > staleTime;
    });

    const fetchData = async (forceRefresh = false): Promise<T | null> => {
        const currentKey = resolvedKey.value;
        if (!currentKey) return null;

        const cachedEntry = getCacheEntry<T>(currentKey);
        if (cachedEntry) {
            data.value = cachedEntry.data;
            if (!forceRefresh && !isStale.value) {
                return cachedEntry.data;
            }
        }

        if (dedupe && pendingRequests.has(currentKey)) {
            try {
                const result = await pendingRequests.get(currentKey) as T;
                data.value = result;
                return result;
            } catch (e) {
                throw e;
            }
        }

        const isInitialLoad = data.value === null && !cachedEntry;
        if (isInitialLoad) {
            isLoading.value = true;
        }
        isValidating.value = true;
        error.value = null;

        const fetchPromise = fetcher(currentKey);
        if (dedupe) {
            pendingRequests.set(currentKey, fetchPromise);
        }

        try {
            const result = await fetchPromise;
            data.value = result;
            setCacheEntry(currentKey, result, cacheTime);
            return result;
        } catch (e) {
            error.value = e instanceof Error ? e : new Error(String(e));
            return null;
        } finally {
            isLoading.value = false;
            isValidating.value = false;
            if (dedupe) {
                pendingRequests.delete(currentKey);
            }
        }
    };

    const mutate = async (newData?: T): Promise<T | null> => {
        if (newData !== undefined) {
            data.value = newData;
            setCacheEntry(resolvedKey.value, newData, cacheTime);
            return newData;
        }
        return fetchData(true);
    };

    const refresh = () => fetchData(true);

    if (immediate) {
        fetchData();
    }

    return {
        data,
        error,
        isLoading,
        isValidating,
        isStale,
        mutate,
        refresh,
    };
}

export function clearSWRCache(keyPattern?: string | RegExp): void {
    if (!keyPattern) {
        cache.clear();
        return;
    }
    for (const key of cache.keys()) {
        if (typeof keyPattern === 'string' ? key.includes(keyPattern) : keyPattern.test(key)) {
            cache.delete(key);
        }
    }
}
