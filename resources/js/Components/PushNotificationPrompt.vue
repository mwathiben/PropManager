<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePushNotifications, useErrorHandler } from '@/composables';
import {
    BellAlertIcon,
    XMarkIcon,
    CheckCircleIcon,
    ExclamationCircleIcon
} from '@heroicons/vue/24/outline';

const {
    isSupported,
    isSubscribed,
    permission,
    isLoading,
    error,
    subscribe
} = usePushNotifications();
const { logError } = useErrorHandler();

const isVisible = ref(false);
const isDismissedSession = ref(false);
const subscribeSuccess = ref(false);
const subscribeError = ref(null);

const STORAGE_KEY = 'push_prompt_dismissed';
const DISMISS_DURATION = 7 * 24 * 60 * 60 * 1000; // 7 days in ms

// Check if we should show the prompt
const shouldShow = computed(() => {
    if (!isSupported.value) return false;
    if (isSubscribed.value) return false;
    if (permission.value === 'denied') return false;
    if (isDismissedSession.value) return false;
    if (subscribeSuccess.value) return false;
    return isVisible.value;
});

const checkDismissed = () => {
    const dismissed = localStorage.getItem(STORAGE_KEY);
    if (dismissed) {
        const dismissedTime = parseInt(dismissed, 10);
        if (Date.now() - dismissedTime < DISMISS_DURATION) {
            return true;
        }
        // Expired, remove the key
        localStorage.removeItem(STORAGE_KEY);
    }
    return false;
};

const handleEnable = async () => {
    subscribeError.value = null;

    try {
        // Get VAPID public key from server
        const response = await fetch(route('notifications.push.key'), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (!data.key) {
            subscribeError.value = 'Push notifications are not configured by your landlord yet.';
            return;
        }

        const success = await subscribe(data.key);

        if (success) {
            subscribeSuccess.value = true;
            // Auto-hide after 3 seconds
            setTimeout(() => {
                isVisible.value = false;
            }, 3000);
        } else {
            subscribeError.value = error.value || 'Failed to enable notifications. Please try again.';
        }
    } catch (err) {
        logError(err, { component: 'PushNotificationPrompt', action: 'handleEnable' });
        subscribeError.value = 'Something went wrong. Please try again later.';
    }
};

const handleMaybeLater = () => {
    localStorage.setItem(STORAGE_KEY, Date.now().toString());
    isVisible.value = false;
};

const handleDismiss = () => {
    isDismissedSession.value = true;
    isVisible.value = false;
};

onMounted(() => {
    // Wait a short delay before showing the prompt
    setTimeout(() => {
        if (!checkDismissed() && isSupported.value && !isSubscribed.value && permission.value !== 'denied') {
            isVisible.value = true;
        }
    }, 2000);
});
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform opacity-0 -translate-y-4"
        enter-to-class="transform opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform opacity-100 translate-y-0"
        leave-to-class="transform opacity-0 -translate-y-4"
    >
        <div
            v-if="shouldShow"
            class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-5 mb-6"
        >
            <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="shrink-0 p-3 bg-white/20 rounded-xl">
                    <BellAlertIcon class="w-6 h-6 text-white" />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <!-- Success State -->
                    <template v-if="subscribeSuccess">
                        <div class="flex items-center gap-2 text-white">
                            <CheckCircleIcon class="w-5 h-5" />
                            <span class="font-medium">Notifications enabled!</span>
                        </div>
                        <p class="text-white/80 text-sm mt-1">
                            You'll now receive instant updates about your rent and payments.
                        </p>
                    </template>

                    <!-- Error State -->
                    <template v-else-if="subscribeError">
                        <div class="flex items-center gap-2 text-white">
                            <ExclamationCircleIcon class="w-5 h-5" />
                            <span class="font-medium">Couldn't enable notifications</span>
                        </div>
                        <p class="text-white/80 text-sm mt-1">{{ subscribeError }}</p>
                        <div class="flex gap-3 mt-3">
                            <button
                                @click="handleEnable"
                                :disabled="isLoading"
                                class="px-4 py-2 bg-white text-indigo-600 font-medium rounded-lg hover:bg-white/90 transition-colors disabled:opacity-50"
                            >
                                Try Again
                            </button>
                            <button
                                @click="handleDismiss"
                                class="px-4 py-2 text-white/90 hover:text-white transition-colors"
                            >
                                Dismiss
                            </button>
                        </div>
                    </template>

                    <!-- Default State -->
                    <template v-else>
                        <h3 class="font-semibold text-white">Stay Updated!</h3>
                        <p class="text-white/80 text-sm mt-1">
                            Enable push notifications to receive instant updates about rent reminders, payments, and important announcements.
                        </p>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-3 mt-4">
                            <button
                                @click="handleEnable"
                                :disabled="isLoading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white text-indigo-600 font-medium rounded-lg hover:bg-white/90 transition-colors disabled:opacity-50"
                            >
                                <template v-if="isLoading">
                                    <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                                    Enabling...
                                </template>
                                <template v-else>
                                    Enable Notifications
                                </template>
                            </button>
                            <button
                                @click="handleMaybeLater"
                                class="px-4 py-2 text-white/90 hover:text-white transition-colors"
                            >
                                Maybe Later
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Close Button -->
                <button
                    @click="handleDismiss"
                    class="shrink-0 p-1 text-white/60 hover:text-white transition-colors"
                >
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>
        </div>
    </Transition>
</template>
