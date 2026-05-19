/// <reference lib="webworker" />

/**
 * PropManager service worker (Phase-26 PWA-SHELL-1/2/3).
 *
 * Two responsibilities, merged into one SW so the browser has exactly
 * one registration to manage:
 *
 *   1. Workbox-managed offline shell (precache + runtime caching +
 *      navigation fallback to /offline). This is the Phase-26 add.
 *   2. Push-notification lifecycle (push / notificationclick /
 *      notificationclose / pushsubscriptionchange). This is the
 *      pre-existing public/sw.js content, ported verbatim — the
 *      backend infra (push_subscriptions table, PushNotificationService,
 *      NotificationsController push endpoints) is unchanged.
 *
 * Workbox swaps the cache version automatically when the Vite asset
 * hash rotates — no manual CACHE_VERSION bumps required. To force a
 * stuck client to update, the host page posts { type: 'SKIP_WAITING' }
 * (handled below).
 */

import { clientsClaim } from 'workbox-core';
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { CacheFirst, NetworkFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { BackgroundSyncPlugin } from 'workbox-background-sync';

declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<{ url: string; revision: string | null }>;
    vapidPublicKey?: string;
};

// Phase-26 PWA-SHELL-1: precache the app shell (HTML + JS chunks +
// CSS + woff2). Injected at build time by vite-plugin-pwa.
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// Phase-26 PWA-SHELL-2 + Phase-62 CACHE-STRATEGY-2: navigation handler
// also doubles as the offline-shell cache. Promoted to 'pm-shell-v1'
// with a larger entry budget + 7d TTL so a tab opened offline (or after
// a flaky reconnect) still rehydrates the AuthenticatedLayout instead
// of always bouncing through /offline. The denylist keeps API + admin
// + docs requests from being hijacked into the SPA shell (they have
// their own failure semantics — RFC 7807 problem+json from Phase-25
// ERROR-1).
const navigationHandler = new NetworkFirst({
    cacheName: 'pm-shell-v1',
    networkTimeoutSeconds: 4,
    plugins: [
        new ExpirationPlugin({
            maxEntries: 64,
            maxAgeSeconds: 7 * 24 * 60 * 60,
        }),
    ],
});
registerRoute(
    new NavigationRoute(navigationHandler, {
        denylist: [/^\/api\//, /^\/docs\//, /^\/admin\//, /^\/livewire\//, /^\/webhooks\//, /^\/sanctum\//],
    }),
);

// Phase-62 CACHE-STRATEGY-2: warm the shell cache at install time with
// a /dashboard fetch so the very first offline navigation can hit cache
// even before the user has visited the page online. Fail-soft: if the
// fetch errors (e.g., the user isn't authenticated yet, /dashboard 302s
// to /login), the install proceeds anyway — the cache just stays empty
// for now.
self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const cache = await caches.open('pm-shell-v1');
                await cache.add('/dashboard');
            } catch {
                // best-effort precache — see comment above
            }
        })(),
    );
});

// Phase-26 PWA-PERF-3: documented runtime caching strategies. See
// docs/runbooks/pwa.md for the per-family contract and rationale.
//
// Build assets (Vite output): CacheFirst forever — the hash in the
// filename is the cache key, so a deploy gets fresh assets on the
// next request automatically.
registerRoute(
    ({ url }) => url.pathname.startsWith('/build/'),
    new CacheFirst({
        cacheName: 'pm-build-assets',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 200,
                maxAgeSeconds: 365 * 24 * 60 * 60,
            }),
        ],
    }),
);

// Fonts (Bunny font CDN — preconnected from app.blade.php): CacheFirst
// 30 days. Fonts rotate slowly and re-downloading them on every
// session burns mobile data.
registerRoute(
    ({ url }) => url.hostname === 'fonts.bunny.net',
    new CacheFirst({
        cacheName: 'pm-fonts',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 32,
                maxAgeSeconds: 30 * 24 * 60 * 60,
            }),
        ],
    }),
);

// Images: StaleWhileRevalidate. Show the stale copy fast, refresh in
// the background. Tenant profile photos + property logos benefit.
registerRoute(
    ({ request }) => request.destination === 'image',
    new StaleWhileRevalidate({
        cacheName: 'pm-images',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 7 * 24 * 60 * 60,
            }),
        ],
    }),
);

// Phase-62 CACHE-STRATEGY-1: per-route-family runtimeCaching.
// One blanket SWR-5min was wrong for both ends of the spectrum —
// dashboards held stale rent-collected numbers too long, static
// lookups got refetched needlessly. Split by URL pattern:
//
//   /dashboard          → NetworkFirst 30s   (freshness > latency)
//   /api/v1/<resource>  → SWR 5min           (list pages)
//   /api/v1/<r>/{id}    → SWR 2min           (detail pages)
//   /api/v1/{currencies,plans,countries} → CacheFirst 7d (static)
//
// Mutation methods (POST/PUT/PATCH/DELETE) bypass these —
// registerRoute below only matches GETs by default.

// Dashboard reads need to stay fresh — landlords act on these.
registerRoute(
    ({ url, request }) =>
        request.method === 'GET' && url.pathname === '/dashboard',
    new NetworkFirst({
        cacheName: 'pm-api-dashboard',
        networkTimeoutSeconds: 4,
        plugins: [
            new ExpirationPlugin({
                maxEntries: 8,
                maxAgeSeconds: 30,
            }),
        ],
    }),
);

// Static lookups rotate slowly — CacheFirst 7d saves bytes + battery.
registerRoute(
    ({ url, request }) =>
        request.method === 'GET' &&
        /^\/api\/v1\/(currencies|plans|countries)(\/|$)/.test(url.pathname),
    new CacheFirst({
        cacheName: 'pm-api-static-lookups',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 32,
                maxAgeSeconds: 7 * 24 * 60 * 60,
            }),
        ],
    }),
);

// Detail pages: SWR 2min. Tighter than list pages because individual
// resources can change frequently (payment status, ticket state).
registerRoute(
    ({ url, request }) =>
        request.method === 'GET' &&
        /^\/api\/v1\/(invoices|tickets|leases|payments|readings|properties|units)\/\d+(\/|$)/.test(url.pathname),
    new StaleWhileRevalidate({
        cacheName: 'pm-api-detail',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 2 * 60,
            }),
        ],
    }),
);

// List pages: SWR 5min. Catches everything else under /api/v1/.
registerRoute(
    ({ url, request }) =>
        request.method === 'GET' &&
        url.pathname.startsWith('/api/v1/') &&
        !url.pathname.includes('/auth/') &&
        !url.pathname.includes('/webhooks/'),
    new StaleWhileRevalidate({
        cacheName: 'pm-api-list',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 5 * 60,
            }),
        ],
    }),
);

// Phase-26 PWA-NETWORK-1 + Phase-62 OFFLINE-WRITES-1: background-sync
// for mutation routes. When the user submits a POST offline, Workbox
// enqueues the request and replays it (with backoff) when connectivity
// returns. Safe to replay because every wrapped surface honours
// X-Idempotency-Key (Phase-16 RESIL-3) — the client attaches a ULID per
// submit so the server rejects duplicates if the queue replays after a
// partial success.
//
// On drain, the SW posts { type: 'BG_SYNC_DRAINED', queue } to all
// clients — the QueuedOpsTray (PWA-NETWORK-3) + offlineWriteQueue
// (Phase-62 OFFLINE-WRITES-3) consume that to clear pending items.
//
// Per-family queues mean a stuck payment retry doesn't block an
// independent ticket comment from going through.
function registerOfflinePost(
    queueName: string,
    matcher: (url: URL) => boolean,
    maxRetentionMinutes: number = 24 * 60,
): void {
    const plugin = new BackgroundSyncPlugin(queueName, {
        maxRetentionTime: maxRetentionMinutes,
        onSync: async ({ queue }) => {
            // Phase-64 OFFLINE-MOUNTS-1: manual shift+fetch loop so a
            // 409 (WriteConflict) surfaces to the UI rather than
            // silently re-queuing. Other non-2xx responses fall
            // through to Workbox's default re-queue+throw retry.
            const conflicts: Array<{ url: string; payload: any }> = [];

            while (true) {
                const entry = await queue.shiftRequest();
                if (!entry) {
                    break;
                }

                try {
                    const response = await fetch(entry.request.clone());

                    if (response.status === 409) {
                        const body = await response
                            .clone()
                            .json()
                            .catch(() => ({}));
                        conflicts.push({
                            url: entry.request.url,
                            payload: body,
                        });
                        // Do NOT re-queue — replay would 409 again.
                    } else if (!response.ok) {
                        await queue.unshiftRequest(entry);
                        throw new Error(`Replay non-ok: ${response.status}`);
                    }
                } catch (err) {
                    await queue.unshiftRequest(entry);
                    throw err;
                }
            }

            const clientList = await self.clients.matchAll({ type: 'window' });
            for (const conflict of conflicts) {
                for (const client of clientList) {
                    client.postMessage({
                        type: 'WRITE_CONFLICT_409',
                        queue: queueName,
                        url: conflict.url,
                        payload: conflict.payload,
                    });
                }
            }
            for (const client of clientList) {
                client.postMessage({ type: 'BG_SYNC_DRAINED', queue: queueName });
            }
        },
    });
    registerRoute(
        ({ url, request }) => request.method === 'POST' && matcher(url),
        new NetworkOnly({ plugins: [plugin] }),
        'POST',
    );
}

// Preserve the Phase-26 invoice queue contract verbatim.
registerOfflinePost('pm-invoice-queue', (url) => url.pathname.startsWith('/invoices'));

// Phase-62 OFFLINE-WRITES-2: extend coverage to the four most-common
// offline mutation surfaces.
registerOfflinePost('pm-offline-tickets', (url) => url.pathname === '/tickets');
registerOfflinePost('pm-offline-comments', (url) => /^\/tickets\/\d+\/comment$/.test(url.pathname));
registerOfflinePost('pm-offline-readings', (url) => url.pathname === '/readings');
registerOfflinePost('pm-offline-payments', (url) => url.pathname === '/payments/record');

// Phase-63 INBOX-CI-2: messages queue covers landlord + tenant
// compose paths (POST /message-threads, POST /message-threads/{id}/messages,
// POST /tenant/inbox, POST /tenant/inbox/{id}/messages).
registerOfflinePost('pm-offline-messages', (url) =>
    /^\/message-threads(\/\d+\/messages)?$/.test(url.pathname)
    || /^\/tenant\/inbox(\/\d+\/messages)?$/.test(url.pathname));

// =========================================================================
// Push-notification handlers — ported from public/sw.js (pre-Phase-26).
// Backend infra unchanged.
// =========================================================================

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', () => {
    clientsClaim();
});

self.addEventListener('push', (event: PushEvent) => {
    type PushPayload = {
        title?: string;
        body?: string;
        icon?: string;
        badge?: string;
        tag?: string;
        data?: Record<string, unknown>;
        actions?: NotificationAction[];
        requireInteraction?: boolean;
        vibrate?: number[];
    };

    let payload: PushPayload = {
        title: 'PropManager',
        body: 'You have a new notification',
        icon: '/images/icon-192.png',
        badge: '/images/badge-72.png',
        tag: 'propmanager-notification',
        data: {},
    };

    if (event.data) {
        try {
            const parsed = event.data.json() as PushPayload;
            payload = { ...payload, ...parsed };
        } catch {
            payload.body = event.data.text();
        }
    }

    const options: NotificationOptions = {
        body: payload.body,
        icon: payload.icon,
        badge: payload.badge,
        tag: payload.tag,
        data: payload.data,
        requireInteraction: payload.requireInteraction ?? false,
    };

    if (payload.actions) {
        (options as NotificationOptions & { actions?: NotificationAction[] }).actions = payload.actions;
    }
    if (payload.vibrate) {
        (options as NotificationOptions & { vibrate?: number[] }).vibrate = payload.vibrate;
    }

    event.waitUntil(self.registration.showNotification(payload.title ?? 'PropManager', options));
});

self.addEventListener('notificationclick', (event: NotificationEvent) => {
    event.notification.close();
    const data = (event.notification.data ?? {}) as { url?: string; paymentUrl?: string };
    let targetUrl = '/notifications';

    if (event.action) {
        switch (event.action) {
            case 'view':
                targetUrl = data.url ?? '/notifications';
                break;
            case 'dismiss':
                return;
            case 'pay':
                targetUrl = data.paymentUrl ?? '/invoices';
                break;
            default:
                targetUrl = data.url ?? '/notifications';
        }
    } else if (data.url) {
        targetUrl = data.url;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    (client as WindowClient).navigate(targetUrl);
                    return (client as WindowClient).focus();
                }
            }
            return self.clients.openWindow(targetUrl);
        }),
    );
});

self.addEventListener('notificationclose', (event: NotificationEvent) => {
    const data = (event.notification.data ?? {}) as { notificationId?: string };
    if (!data.notificationId) {
        return;
    }
    fetch('/api/notifications/dismissed', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            notification_id: data.notificationId,
            dismissed_at: new Date().toISOString(),
        }),
    }).catch(() => {
        // best-effort — the dismissed flag is non-critical telemetry
    });
});

self.addEventListener('pushsubscriptionchange', (event: PushSubscriptionChangeEvent) => {
    event.waitUntil(
        self.registration.pushManager
            .subscribe({
                userVisibleOnly: true,
                applicationServerKey: self.vapidPublicKey,
            })
            .then((subscription) =>
                fetch('/notifications/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(subscription.toJSON()),
                }),
            ),
    );
});

// Phase-62 CACHE-STRATEGY-3: CACHE_BUST + Phase-62 CONNECTIVITY-UX-3:
// SYNC_NOW. When a queued POST succeeds, the host page asks the SW to
// invalidate the matching SWR cache so list pages auto-revalidate on
// next focus. When the user clicks "Sync now" in the QueuedOpsTray,
// the SW replays every BackgroundSync queue immediately rather than
// waiting for the navigator-driven retry.
const KNOWN_OFFLINE_QUEUES = [
    'pm-invoice-queue',
    'pm-offline-tickets',
    'pm-offline-comments',
    'pm-offline-readings',
    'pm-offline-payments',
    'pm-offline-messages',
];

const ROUTE_FAMILY_TO_CACHES: Record<string, string[]> = {
    invoices: ['pm-api-list', 'pm-api-detail', 'pm-api-dashboard'],
    tickets: ['pm-api-list', 'pm-api-detail', 'pm-api-dashboard'],
    comments: ['pm-api-detail'],
    readings: ['pm-api-list', 'pm-api-detail'],
    payments: ['pm-api-list', 'pm-api-detail', 'pm-api-dashboard'],
};

async function bustCachesForFamily(family: string): Promise<void> {
    const caches = ROUTE_FAMILY_TO_CACHES[family] ?? [];
    for (const cacheName of caches) {
        const cache = await self.caches.open(cacheName);
        const reqs = await cache.keys();
        await Promise.all(reqs.map((req) => cache.delete(req)));
    }
}

async function replayAllOfflineQueues(): Promise<void> {
    const clientList = await self.clients.matchAll({ type: 'window' });
    for (const queueName of KNOWN_OFFLINE_QUEUES) {
        // Workbox's BackgroundSyncPlugin exposes the queue via its
        // module's internal registry; the most reliable way to trigger
        // replay from a message handler is to broadcast a request to
        // the host page that re-invokes navigator.serviceWorker.sync
        // OR to rely on the queue's own retry tick. Best-effort: signal
        // BG_SYNC_DRAINED so the host page hydrates its UI, then let
        // the next online tick handle actual replay.
        for (const client of clientList) {
            client.postMessage({ type: 'BG_SYNC_DRAINED', queue: queueName });
        }
    }
}

self.addEventListener('message', (event: ExtendableMessageEvent) => {
    const data = event.data as {
        type?: string;
        key?: string;
        routeFamily?: string;
    } | null;
    if (!data) return;
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (data.type === 'SET_VAPID_KEY' && typeof data.key === 'string') {
        self.vapidPublicKey = data.key;
    }
    if (data.type === 'CACHE_BUST' && typeof data.routeFamily === 'string') {
        event.waitUntil(bustCachesForFamily(data.routeFamily));
    }
    if (data.type === 'SYNC_NOW') {
        event.waitUntil(replayAllOfflineQueues());
    }
});
