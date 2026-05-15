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
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';

declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<{ url: string; revision: string | null }>;
    vapidPublicKey?: string;
};

// Phase-26 PWA-SHELL-1: precache the app shell (HTML + JS chunks +
// CSS + woff2). Injected at build time by vite-plugin-pwa.
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// Phase-26 PWA-SHELL-2: navigation fallback. Any navigation that the
// network can't satisfy (offline, server down, slow timeout) falls
// back to /offline. The denylist keeps API + admin + docs requests
// from being hijacked into the SPA shell (they have their own
// failure semantics — RFC 7807 problem+json from Phase-25 ERROR-1).
const navigationHandler = new NetworkFirst({
    cacheName: 'pm-navigation',
    networkTimeoutSeconds: 4,
    plugins: [
        new ExpirationPlugin({
            maxEntries: 32,
            maxAgeSeconds: 24 * 60 * 60,
        }),
    ],
});
registerRoute(
    new NavigationRoute(navigationHandler, {
        denylist: [/^\/api\//, /^\/docs\//, /^\/admin\//, /^\/livewire\//, /^\/webhooks\//, /^\/sanctum\//],
    }),
);

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

// API read paths: StaleWhileRevalidate with a 5min ceiling. Read-only
// GET endpoints under /api/v1/ are safe to cache briefly. Mutation
// methods (POST/PUT/PATCH/DELETE) bypass this — registerRoute below
// only matches GETs by default.
registerRoute(
    ({ url, request }) =>
        request.method === 'GET' &&
        url.pathname.startsWith('/api/v1/') &&
        !url.pathname.includes('/auth/') &&
        !url.pathname.includes('/webhooks/'),
    new StaleWhileRevalidate({
        cacheName: 'pm-api-reads',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 5 * 60,
            }),
        ],
    }),
);

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

self.addEventListener('message', (event: ExtendableMessageEvent) => {
    const data = event.data as { type?: string; key?: string } | null;
    if (!data) return;
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (data.type === 'SET_VAPID_KEY' && typeof data.key === 'string') {
        self.vapidPublicKey = data.key;
    }
});
