<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    BellIcon,
    BellSlashIcon,
    DevicePhoneMobileIcon,
    ComputerDesktopIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
});

const isSupported = ref(false);
const permissionState = ref('default');
const isLoading = ref(false);
const isSubscribed = ref(false);
const vapidPublicKey = ref(null);
const error = ref(null);
const success = ref(null);

const browserInfo = computed(() => {
    const ua = navigator.userAgent;
    if (ua.includes('Chrome')) return { name: 'Chrome', icon: ComputerDesktopIcon };
    if (ua.includes('Firefox')) return { name: 'Firefox', icon: ComputerDesktopIcon };
    if (ua.includes('Safari')) return { name: 'Safari', icon: ComputerDesktopIcon };
    if (ua.includes('Edge')) return { name: 'Edge', icon: ComputerDesktopIcon };
    return { name: 'Browser', icon: ComputerDesktopIcon };
});

onMounted(async () => {
    isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window;

    if (isSupported.value) {
        permissionState.value = Notification.permission;

        try {
            const response = await fetch(route('notifications.push.key'));
            const data = await response.json();
            vapidPublicKey.value = data.public_key;

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            isSubscribed.value = !!subscription;
        } catch (e) {
            console.error('Failed to check push status:', e);
        }
    }
});

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
};

const arrayBufferToBase64 = (buffer) => {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
};

const subscribe = async () => {
    error.value = null;
    success.value = null;
    isLoading.value = true;

    try {
        const permission = await Notification.requestPermission();
        permissionState.value = permission;

        if (permission !== 'granted') {
            error.value = 'Push notification permission was denied. Please enable it in your browser settings.';
            return;
        }

        if (!vapidPublicKey.value) {
            error.value = 'Push notifications are not configured. Please contact your landlord.';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
        });

        const response = await fetch(route('notifications.push.subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                    auth: arrayBufferToBase64(subscription.getKey('auth')),
                },
            }),
        });

        if (response.ok) {
            isSubscribed.value = true;
            success.value = 'Push notifications enabled successfully!';
        } else {
            throw new Error('Failed to save subscription');
        }
    } catch (e) {
        console.error('Push subscription failed:', e);
        error.value = 'Failed to enable push notifications. Please try again.';
    } finally {
        isLoading.value = false;
    }
};

const unsubscribe = async () => {
    error.value = null;
    success.value = null;
    isLoading.value = true;

    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await subscription.unsubscribe();

            await fetch(route('notifications.push.unsubscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                }),
            });
        }

        isSubscribed.value = false;
        success.value = 'Push notifications disabled.';
    } catch (e) {
        console.error('Push unsubscribe failed:', e);
        error.value = 'Failed to disable push notifications.';
    } finally {
        isLoading.value = false;
    }
};

const testNotification = async () => {
    if (!isSubscribed.value) return;

    try {
        const registration = await navigator.serviceWorker.ready;
        await registration.showNotification('Test Notification', {
            body: 'Push notifications are working correctly!',
            icon: '/images/icon-192.png',
            badge: '/images/badge-72.png',
        });
    } catch (e) {
        console.error('Test notification failed:', e);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Push Notifications Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <BellIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Push Notifications</h3>
                    <p class="text-xs text-gray-500">Receive instant updates on your device</p>
                </div>
            </div>

            <!-- Not Supported -->
            <div v-if="!isSupported" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <ExclamationTriangleIcon class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Not Supported</p>
                        <p class="text-sm text-yellow-700 mt-1">
                            Push notifications are not supported in this browser. Please use Chrome, Firefox, Edge, or Safari for push notifications.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Permission Denied -->
            <div v-else-if="permissionState === 'denied'" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <BellSlashIcon class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-red-800">Notifications Blocked</p>
                        <p class="text-sm text-red-700 mt-1">
                            Push notifications are blocked in your browser. To enable them:
                        </p>
                        <ol class="text-sm text-red-700 mt-2 ml-4 list-decimal space-y-1">
                            <li>Click the lock icon in your browser's address bar</li>
                            <li>Find "Notifications" in the site settings</li>
                            <li>Change from "Block" to "Allow"</li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Supported and Available -->
            <div v-else class="space-y-4">
                <!-- Success/Error Messages -->
                <div v-if="success" class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-2">
                    <CheckCircleIcon class="w-5 h-5 text-green-500" />
                    <span class="text-sm text-green-700">{{ success }}</span>
                </div>

                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3 flex items-center gap-2">
                    <ExclamationTriangleIcon class="w-5 h-5 text-red-500" />
                    <span class="text-sm text-red-700">{{ error }}</span>
                </div>

                <!-- Current Status -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <component :is="browserInfo.icon" class="w-8 h-8 text-gray-400" />
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ browserInfo.name }}</p>
                                <p class="text-xs text-gray-500">
                                    <span v-if="isSubscribed" class="text-green-600">Notifications enabled</span>
                                    <span v-else class="text-gray-500">Notifications disabled</span>
                                </p>
                            </div>
                        </div>
                        <div :class="[
                            'px-2 py-1 rounded-full text-xs font-medium',
                            isSubscribed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                        ]">
                            {{ isSubscribed ? 'Active' : 'Inactive' }}
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <p class="text-sm text-gray-600">
                    Enable push notifications to receive instant alerts for:
                </p>
                <ul class="text-sm text-gray-600 ml-4 space-y-1">
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        New invoices and payment confirmations
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        Rent reminders and due date alerts
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        Important messages from your landlord
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        Maintenance updates and announcements
                    </li>
                </ul>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3 pt-2">
                    <PrimaryButton
                        v-if="!isSubscribed"
                        @click="subscribe"
                        :disabled="isLoading || !vapidPublicKey"
                        class="flex items-center gap-2"
                    >
                        <ArrowPathIcon v-if="isLoading" class="w-4 h-4 animate-spin" />
                        <BellIcon v-else class="w-4 h-4" />
                        {{ isLoading ? 'Enabling...' : 'Enable Push Notifications' }}
                    </PrimaryButton>

                    <template v-else>
                        <button
                            @click="testNotification"
                            class="px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors"
                        >
                            Send Test
                        </button>
                        <button
                            @click="unsubscribe"
                            :disabled="isLoading"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors disabled:opacity-50 flex items-center gap-2"
                        >
                            <ArrowPathIcon v-if="isLoading" class="w-4 h-4 animate-spin" />
                            <BellSlashIcon v-else class="w-4 h-4" />
                            {{ isLoading ? 'Disabling...' : 'Disable' }}
                        </button>
                    </template>
                </div>

                <!-- No VAPID Key Warning -->
                <div v-if="!vapidPublicKey && isSupported" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-sm text-amber-700">
                        Push notifications have not been configured yet. Please contact your property manager.
                    </p>
                </div>
            </div>
        </div>

        <!-- Other Devices Info -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <div class="flex items-start gap-3">
                <DevicePhoneMobileIcon class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" />
                <div>
                    <h4 class="text-xs font-medium text-gray-700">Multiple Devices</h4>
                    <p class="text-xs text-gray-600 mt-1">
                        You can enable push notifications on multiple devices. Each device requires separate setup by logging in and enabling notifications on that device.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
