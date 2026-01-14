import { ref, onMounted, type Ref } from 'vue';

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
    const isSupported = ref(false);
    const isSubscribed = ref(false);
    const subscription = ref<PushSubscription | null>(null);
    const permission = ref<NotificationPermission | 'default'>('default');
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Check if push notifications are supported
    const checkSupport = (): boolean => {
        isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        permission.value = 'Notification' in window ? Notification.permission : 'default';
        return isSupported.value;
    };

    // Register service worker
    const registerServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
        if (!isSupported.value) return null;

        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });

            console.log('Service Worker registered:', registration.scope);
            return registration;
        } catch (err) {
            console.error('Service Worker registration failed:', err);
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
            console.error('Error getting subscription:', err);
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
            console.error('Permission request failed:', err);
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
            console.error('Push subscription failed:', err);
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

        try {
            const endpoint = subscription.value.endpoint;
            await subscription.value.unsubscribe();

            // Notify server
            const response = await fetch(route('notifications.push.unsubscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ endpoint })
            });

            if (!response.ok) {
                console.warn('Server unsubscribe notification failed:', response.status);
            }

            subscription.value = null;
            isSubscribed.value = false;
            return true;
        } catch (err) {
            console.error('Unsubscribe failed:', err);
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
            console.error('Test notification failed:', err);
            error.value = 'Failed to show test notification';
            return false;
        }
    };

    // Initialize on mount
    onMounted(async () => {
        checkSupport();
        if (isSupported.value) {
            await registerServiceWorker();
            await getSubscription();
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
