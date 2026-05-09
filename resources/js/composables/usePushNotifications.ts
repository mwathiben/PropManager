import { ref, onMounted, type Ref } from 'vue';
import { useErrorHandler } from './useErrorHandler';

declare function route(name: string): string;

export interface UsePushNotificationsReturn {
    isSupported: Ref<boolean>;
    isSubscribed: Ref<boolean>;
    subscription: Ref<PushSubscription | null>;
    permission: Ref<NotificationPermission | 'default'>;
    isLoading: Ref<boolean>;
    error: Ref<string | null>;
    checkSupport: () => boolean;
    registerServiceWorker: () => Promise<ServiceWorkerRegistration | null>;
    getSubscription: () => Promise<PushSubscription | null>;
    requestPermission: () => Promise<boolean>;
    subscribe: (vapidPublicKey: string) => Promise<boolean>;
    unsubscribe: () => Promise<boolean>;
    showTestNotification: () => Promise<boolean>;
}

export function usePushNotifications(): UsePushNotificationsReturn {
    const { logError, logDebug, logWarning } = useErrorHandler();
    const isSupported = ref(false);
    const isSubscribed = ref(false);
    const subscription = ref<PushSubscription | null>(null);
    const permission = ref<NotificationPermission | 'default'>('default');
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Check if push notifications are supported
    const checkSupport = (): boolean => {
        const hasServiceWorker = 'serviceWorker' in navigator;
        const hasPushManager = 'PushManager' in window;
        const hasNotification = typeof Notification !== 'undefined';

        isSupported.value = hasServiceWorker && hasPushManager && hasNotification;

        if (hasNotification) {
            permission.value = Notification.permission;
        } else {
            permission.value = 'default';
        }

        return isSupported.value;
    };

    // Register service worker
    const registerServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
        if (!isSupported.value) return null;

        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });

            logDebug('Service Worker registered: ' + registration.scope);
            return registration;
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'registerServiceWorker' });
            error.value = 'Failed to register service worker';
            return null;
        }
    };

    // Get current subscription
    const getSubscription = async (): Promise<PushSubscription | null> => {
        if (!isSupported.value) return null;

        try {
            const registration = await navigator.serviceWorker.ready;
            const sub = await registration.pushManager.getSubscription();
            subscription.value = sub;
            isSubscribed.value = !!sub;
            return sub;
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'getSubscription' });
            error.value = 'Failed to get push subscription';
            return null;
        }
    };

    // Request notification permission
    const requestPermission = async (): Promise<boolean> => {
        if (!isSupported.value) {
            error.value = 'Push notifications are not supported';
            return false;
        }

        try {
            const result = await Notification.requestPermission();
            permission.value = result;
            return result === 'granted';
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'requestPermission' });
            error.value = 'Failed to request notification permission';
            return false;
        }
    };

    // Convert VAPID key from base64 to Uint8Array
    const urlBase64ToUint8Array = (base64String: string): Uint8Array => {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    };

    // Send subscription to server
    const sendSubscriptionToServer = async (sub: PushSubscription): Promise<unknown> => {
        const subJson = sub.toJSON();

        const response = await fetch(route('notifications.push.subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                endpoint: subJson.endpoint,
                keys: subJson.keys
            })
        });

        if (!response.ok) {
            throw new Error('Failed to send subscription to server');
        }

        return response.json();
    };

    // Subscribe to push notifications
    const subscribe = async (vapidPublicKey: string): Promise<boolean> => {
        if (!isSupported.value) {
            error.value = 'Push notifications are not supported';
            return false;
        }

        if (permission.value !== 'granted') {
            const granted = await requestPermission();
            if (!granted) {
                error.value = 'Notification permission denied';
                return false;
            }
        }

        isLoading.value = true;
        error.value = null;

        try {
            const registration = await navigator.serviceWorker.ready;

            // Convert base64 to Uint8Array
            const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

            // Subscribe to push
            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey
            });

            subscription.value = sub;
            isSubscribed.value = true;

            // Send subscription to server
            await sendSubscriptionToServer(sub);

            return true;
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'subscribe' });
            error.value = 'Failed to subscribe to push notifications';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    // Unsubscribe from push notifications
    const unsubscribe = async (): Promise<boolean> => {
        if (!subscription.value) return true;

        isLoading.value = true;
        error.value = null;

        // Capture endpoint before unsubscribing (subscription will be invalidated after unsubscribe)
        const endpoint = subscription.value.endpoint;

        try {
            // Unsubscribe from push manager first
            await subscription.value.unsubscribe();

            // Clear local state immediately after successful unsubscribe
            subscription.value = null;
            isSubscribed.value = false;

            // Notify server about the unsubscription
            try {
                const response = await fetch(route('notifications.push.unsubscribe'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ endpoint })
                });

                if (!response.ok) {
                    const errorText = await response.text().catch(() => 'Unknown error');
                    logWarning(`Server unsubscribe failed: ${response.status} - ${errorText}`, {
                        component: 'usePushNotifications',
                        action: 'unsubscribe',
                        extra: { endpoint, status: response.status }
                    });
                }
            } catch (fetchErr) {
                // Log server notification failure but don't fail the unsubscribe operation
                logWarning('Failed to notify server of unsubscription', {
                    component: 'usePushNotifications',
                    action: 'unsubscribe',
                    extra: { endpoint, error: fetchErr instanceof Error ? fetchErr.message : String(fetchErr) }
                });
            }

            return true;
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'unsubscribe' });
            error.value = 'Failed to unsubscribe from push notifications';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    // Show a test notification
    const showTestNotification = async (): Promise<boolean> => {
        if (!isSupported.value || permission.value !== 'granted') {
            error.value = 'Cannot show notification - permission not granted';
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            await registration.showNotification('Test Notification', {
                body: 'Push notifications are working!',
                icon: '/images/icon-192.png',
                badge: '/images/badge-72.png',
                tag: 'test-notification',
                vibrate: [200, 100, 200]
            });
            return true;
        } catch (err) {
            logError(err, { component: 'usePushNotifications', action: 'showTestNotification' });
            error.value = 'Failed to show test notification';
            return false;
        }
    };

    // Initialize on mount
    onMounted(async () => {
        checkSupport();
        if (isSupported.value) {
            try {
                await registerServiceWorker();
                await getSubscription();
            } catch (err) {
                logError(err, { component: 'usePushNotifications', action: 'onMounted' });
            }
        }
    });

    return {
        isSupported,
        isSubscribed,
        subscription,
        permission,
        isLoading,
        error,
        checkSupport,
        registerServiceWorker,
        getSubscription,
        requestPermission,
        subscribe,
        unsubscribe,
        showTestNotification
    };
}
