/**
 * PropManager Push Notification Service Worker
 * Handles push notifications in the background
 */

// Cache version for offline support
const CACHE_VERSION = 'propmanager-v1';

// Install event - cache essential assets
self.addEventListener('install', (event) => {
    console.log('[SW] Service Worker installing...');
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Service Worker activated');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_VERSION)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');

    let data = {
        title: 'PropManager',
        body: 'You have a new notification',
        icon: '/images/icon-192.png',
        badge: '/images/badge-72.png',
        tag: 'propmanager-notification',
        data: {}
    };

    if (event.data) {
        try {
            const payload = event.data.json();
            data = {
                title: payload.title || data.title,
                body: payload.body || data.body,
                icon: payload.icon || data.icon,
                badge: payload.badge || data.badge,
                tag: payload.tag || data.tag,
                data: payload.data || {},
                actions: payload.actions || [],
                requireInteraction: payload.requireInteraction || false,
                vibrate: payload.vibrate || [200, 100, 200]
            };
        } catch (e) {
            console.error('[SW] Error parsing push data:', e);
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        tag: data.tag,
        data: data.data,
        actions: data.actions,
        requireInteraction: data.requireInteraction,
        vibrate: data.vibrate,
        timestamp: Date.now()
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event - handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification.tag);

    event.notification.close();

    const data = event.notification.data || {};
    let targetUrl = '/notifications';

    // Handle action buttons
    if (event.action) {
        switch (event.action) {
            case 'view':
                targetUrl = data.url || '/notifications';
                break;
            case 'dismiss':
                return;
            case 'pay':
                targetUrl = data.paymentUrl || '/invoices';
                break;
            default:
                targetUrl = data.url || '/notifications';
        }
    } else if (data.url) {
        targetUrl = data.url;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // If a window is already open, focus it
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// Notification close event - track dismissed notifications
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification closed:', event.notification.tag);

    const data = event.notification.data || {};
    if (data.notificationId) {
        // Optionally track that the notification was dismissed
        fetch('/api/notifications/dismissed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_id: data.notificationId,
                dismissed_at: new Date().toISOString()
            })
        }).catch(console.error);
    }
});

// Push subscription change event
self.addEventListener('pushsubscriptionchange', (event) => {
    console.log('[SW] Push subscription changed');

    event.waitUntil(
        self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: self.vapidPublicKey
        }).then((subscription) => {
            // Send new subscription to server
            return fetch('/notifications/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription.toJSON())
            });
        })
    );
});

// Message handler for communication with the main thread
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'SET_VAPID_KEY') {
        self.vapidPublicKey = event.data.key;
    }
});
