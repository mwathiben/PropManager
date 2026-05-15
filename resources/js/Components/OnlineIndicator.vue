<script setup lang="ts">
/**
 * Phase-26 PWA-OFFLINE-3: connectivity indicator.
 *
 * Slot in the AuthenticatedLayout's top bar. Renders nothing when the
 * user is online (the absence of a warning IS the signal); shows a
 * small pill when offline with a tooltip pointing at the offline page
 * pattern.
 *
 * Why is "online" the silent state? Because most users are online
 * most of the time and a persistent green dot adds visual noise
 * without delivering signal. Offline IS the surprising state and
 * deserves an alarm.
 *
 * Complementary to ConnectionStatus.vue which tracks the
 * WebSocket/Echo realtime channel — different layer (Echo can fail
 * while HTTP fetches still succeed and vice versa).
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { WifiIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);

function update(): void {
    isOnline.value = navigator.onLine;
}

onMounted(() => {
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', update);
    window.removeEventListener('offline', update);
});
</script>

<template>
    <span
        v-if="!isOnline"
        role="status"
        :aria-label="t('offline.indicator.aria') as string"
        :title="t('offline.indicator.tooltip') as string"
        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200"
    >
        <WifiIcon class="h-3.5 w-3.5" aria-hidden="true" />
        {{ t('offline.indicator.label') }}
    </span>
</template>
