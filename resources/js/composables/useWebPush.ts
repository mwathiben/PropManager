import { ref, computed, type Ref, type ComputedRef } from 'vue';
import { usePushNotifications } from './usePushNotifications';

declare function route(name: string): string;

/**
 * Phase-37 PWA-PUSH-FE-1: thin wrapper around the pre-existing
 * usePushNotifications composable that internalises the VAPID key
 * lifecycle (fetch + 60s memo + rotation auto-resubscribe), so
 * callers like Settings/Notifications.vue don't have to plumb
 * /notifications/push/key themselves.
 *
 * - subscribe() handles the full flow: fetch key → permission →
 *   PushManager.subscribe → POST /notifications/push/subscribe.
 * - When the server rotates its VAPID key, refreshKey() detects the
 *   delta against the cached subscription's applicationServerKey and
 *   triggers an unsubscribe + re-subscribe so push delivery resumes
 *   without operator intervention.
 */

let cachedVapidKey: string | null = null;
let cachedVapidKeyAt = 0;
const VAPID_KEY_TTL_MS = 60_000;

export interface UseWebPushReturn {
    isSupported: Ref<boolean>;
    isSubscribed: Ref<boolean>;
    permission: Ref<NotificationPermission | 'default'>;
    isLoading: Ref<boolean>;
    error: Ref<string | null>;
    isUnsupportedOrBlocked: ComputedRef<boolean>;
    subscribe: () => Promise<boolean>;
    unsubscribe: () => Promise<boolean>;
    refreshKey: () => Promise<string | null>;
}

export function useWebPush(): UseWebPushReturn {
    const push = usePushNotifications();
    const lastSubscribedKey = ref<string | null>(null);

    const fetchVapidKey = async (): Promise<string | null> => {
        const now = Date.now();
        if (cachedVapidKey && now - cachedVapidKeyAt < VAPID_KEY_TTL_MS) {
            return cachedVapidKey;
        }
        try {
            const response = await fetch(route('notifications.push.key'), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                push.error.value = 'Failed to fetch VAPID key';
                return null;
            }
            const body = (await response.json()) as { public_key?: string | null };
            const key = body.public_key ?? null;
            cachedVapidKey = key;
            cachedVapidKeyAt = now;
            return key;
        } catch (err) {
            push.error.value = err instanceof Error ? err.message : 'Failed to fetch VAPID key';
            return null;
        }
    };

    const subscribe = async (): Promise<boolean> => {
        const key = await fetchVapidKey();
        if (!key) {
            push.error.value = push.error.value ?? 'VAPID key not configured';
            return false;
        }
        const ok = await push.subscribe(key);
        if (ok) {
            lastSubscribedKey.value = key;
        }
        return ok;
    };

    const unsubscribe = async (): Promise<boolean> => {
        const ok = await push.unsubscribe();
        if (ok) {
            lastSubscribedKey.value = null;
        }
        return ok;
    };

    const refreshKey = async (): Promise<string | null> => {
        cachedVapidKey = null;
        cachedVapidKeyAt = 0;
        const next = await fetchVapidKey();
        if (next && lastSubscribedKey.value && next !== lastSubscribedKey.value && push.isSubscribed.value) {
            await push.unsubscribe();
            await push.subscribe(next);
            lastSubscribedKey.value = next;
        }
        return next;
    };

    const isUnsupportedOrBlocked = computed(
        () => !push.isSupported.value || push.permission.value === 'denied',
    );

    return {
        isSupported: push.isSupported,
        isSubscribed: push.isSubscribed,
        permission: push.permission,
        isLoading: push.isLoading,
        error: push.error,
        isUnsupportedOrBlocked,
        subscribe,
        unsubscribe,
        refreshKey,
    };
}
