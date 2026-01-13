import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function usePushNotifications() {
    const isSupported = ref(false);
    const isSubscribed = ref(false);
    const subscription = ref(null);
    const permission = ref('default');
    const isLoading = ref(false);
    const error = ref(null);

    // Check if push notifications are supported
    const checkSupport = () => {
        isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window;
        permission.value = Notification.permission;
        return isSupported.value;
    };

    // Register service worker
    const registerServiceWorker = async () => {
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
            throw err;
        }
    };

    // Get current subscription
    const getSubscription = async () => {
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
    const requestPermission = async () => {
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

    // Subscribe to push notifications
    const subscribe = async (vapidPublicKey) => {
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

            // Send VAPID key to service worker
            registration.active?.postMessage({
                type: 'SET_VAPID_KEY',
                key: vapidPublicKey
            });

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
    const unsubscribe = async () => {
        if (!subscription.value) return true;

        isLoading.value = true;
        error.value = null;

        try {
            await subscription.value.unsubscribe();

            // Notify server
            await fetch(route('notifications.push.unsubscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({
                    endpoint: subscription.value.endpoint
                })
            });

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

    // Send subscription to server
    const sendSubscriptionToServer = async (sub) => {
        const subJson = sub.toJSON();

        const response = await fetch(route('notifications.push.subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
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

    // Convert VAPID key from base64 to Uint8Array
    const urlBase64ToUint8Array = (base64String) => {
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

    // Show a test notification
    const showTestNotification = async () => {
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
