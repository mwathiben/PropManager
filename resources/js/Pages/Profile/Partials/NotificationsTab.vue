<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { ProfileNotificationsTabProps } from '@/types';
import {
    BellIcon,
    BellSlashIcon,
    DevicePhoneMobileIcon,
    ComputerDesktopIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ProfileNotificationsTabProps>();

const { logError } = useErrorHandler();
const { t } = useI18n();
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
            logError(e, { component: 'NotificationsTab', action: 'checkPushStatus' });
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
            error.value = t('profile_notifications.script.permission_denied');
            return;
        }

        if (!vapidPublicKey.value) {
            error.value = t('profile_notifications.script.not_configured');
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
            success.value = t('profile_notifications.script.enable_success');
        } else {
            throw new Error('Failed to save subscription');
        }
    } catch (e) {
        logError(e, { component: 'NotificationsTab', action: 'subscribe' });
        error.value = t('profile_notifications.script.enable_failed');
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
        success.value = t('profile_notifications.script.disable_success');
    } catch (e) {
        logError(e, { component: 'NotificationsTab', action: 'unsubscribe' });
        error.value = t('profile_notifications.script.disable_failed');
    } finally {
        isLoading.value = false;
    }
};

const testNotification = async () => {
    if (!isSubscribed.value) return;

    try {
        const registration = await navigator.serviceWorker.ready;
        await registration.showNotification(t('profile_notifications.test.title'), {
            body: t('profile_notifications.test.body'),
            icon: '/images/icon-192.png',
            badge: '/images/badge-72.png',
        });
    } catch (e) {
        logError(e, { component: 'NotificationsTab', action: 'testNotification' });
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
                    <h3 class="text-sm font-medium text-gray-900">{{ t('profile_notifications.card.title') }}</h3>
                    <p class="text-xs text-gray-500">{{ t('profile_notifications.card.subtitle') }}</p>
                </div>
            </div>

            <!-- Not Supported -->
            <div v-if="!isSupported" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <ExclamationTriangleIcon class="w-5 h-5 text-yellow-500 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-yellow-800">{{ t('profile_notifications.not_supported.title') }}</p>
                        <p class="text-sm text-yellow-700 mt-1">
                            {{ t('profile_notifications.not_supported.body') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Permission Denied -->
            <div v-else-if="permissionState === 'denied'" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <BellSlashIcon class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-red-800">{{ t('profile_notifications.blocked.title') }}</p>
                        <p class="text-sm text-red-700 mt-1">
                            {{ t('profile_notifications.blocked.body') }}
                        </p>
                        <ol class="text-sm text-red-700 mt-2 ms-4 list-decimal space-y-1">
                            <li>{{ t('profile_notifications.blocked.step_lock') }}</li>
                            <li>{{ t('profile_notifications.blocked.step_find') }}</li>
                            <li>{{ t('profile_notifications.blocked.step_change') }}</li>
                            <li>{{ t('profile_notifications.blocked.step_refresh') }}</li>
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
                                    <span v-if="isSubscribed" class="text-green-600">{{ t('profile_notifications.status.enabled') }}</span>
                                    <span v-else class="text-gray-500">{{ t('profile_notifications.status.disabled') }}</span>
                                </p>
                            </div>
                        </div>
                        <div :class="['px-2 py-1 rounded-full text-xs font-medium', isSubscribed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                            {{ isSubscribed ? t('profile_notifications.status.active') : t('profile_notifications.status.inactive') }}
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <p class="text-sm text-gray-600">
                    {{ t('profile_notifications.alerts.intro') }}
                </p>
                <ul class="text-sm text-gray-600 ms-4 space-y-1">
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        {{ t('profile_notifications.alerts.invoices') }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        {{ t('profile_notifications.alerts.rent') }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        {{ t('profile_notifications.alerts.messages') }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                        {{ t('profile_notifications.alerts.maintenance') }}
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
                        {{ isLoading ? t('profile_notifications.button.enabling') : t('profile_notifications.button.enable') }}
                    </PrimaryButton>

                    <template v-else>
                        <button
                            @click="testNotification"
                            class="px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors"
                        >
                            {{ t('profile_notifications.button.send_test') }}
                        </button>
                        <button
                            @click="unsubscribe"
                            :disabled="isLoading"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors disabled:opacity-50 flex items-center gap-2"
                        >
                            <ArrowPathIcon v-if="isLoading" class="w-4 h-4 animate-spin" />
                            <BellSlashIcon v-else class="w-4 h-4" />
                            {{ isLoading ? t('profile_notifications.button.disabling') : t('profile_notifications.button.disable') }}
                        </button>
                    </template>
                </div>

                <!-- No VAPID Key Warning -->
                <div v-if="!vapidPublicKey && isSupported" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-sm text-amber-700">
                        {{ t('profile_notifications.no_vapid') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Other Devices Info -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <div class="flex items-start gap-3">
                <DevicePhoneMobileIcon class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                    <h4 class="text-xs font-medium text-gray-700">{{ t('profile_notifications.devices.title') }}</h4>
                    <p class="text-xs text-gray-600 mt-1">
                        {{ t('profile_notifications.devices.body') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
