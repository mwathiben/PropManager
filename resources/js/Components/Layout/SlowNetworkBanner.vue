<script setup lang="ts">
/**
 * Phase-62 CONNECTIVITY-UX-1: surface useConnection.isSlow as a
 * visible banner so users aren't left wondering why a form submit
 * appears to hang on 2g / saveData connections.
 *
 * Wires the dormant useConnection.isSlow primitive (Phase-26 PWA-
 * NETWORK-2) which until Phase 62 had a single repo-wide consumer
 * (Reports/Cohort.vue for deferred chart loading).
 *
 * Mounted into AuthenticatedLayout above <main>. Dismissable per-
 * session via localStorage so users on persistently slow networks
 * aren't nagged every page nav.
 */
import { computed, ref, onMounted, watch } from 'vue';
import { useConnection } from '@/composables/useConnection';
import { useI18n } from '@/composables/useI18n';

const DISMISS_KEY = 'pm.slow_banner.dismissed_until';
const DISMISS_WINDOW_MS = 5 * 60 * 1000;

const { isSlow } = useConnection();
const { t } = useI18n();
const dismissedUntil = ref<number>(0);

onMounted(() => {
    const stored = window.localStorage?.getItem(DISMISS_KEY);
    if (stored) {
        const ts = Number(stored);
        if (!Number.isNaN(ts)) {
            dismissedUntil.value = ts;
        }
    }
});

const visible = computed(() => isSlow.value && Date.now() > dismissedUntil.value);

function dismiss(): void {
    const until = Date.now() + DISMISS_WINDOW_MS;
    dismissedUntil.value = until;
    window.localStorage?.setItem(DISMISS_KEY, String(until));
}

// Reset the dismiss flag when isSlow flips back to false → true.
watch(isSlow, (now, prev) => {
    if (!prev && now) {
        dismissedUntil.value = 0;
    }
});
</script>

<template>
    <div
        v-if="visible"
        role="status"
        aria-live="polite"
        data-testid="slow-network-banner"
        class="w-full bg-amber-50 border-b border-amber-200 px-4 py-2 text-sm text-amber-900 flex items-center justify-between gap-3"
    >
        <span>{{ t('connectivity.slow_banner') }}</span>
        <button
            type="button"
            class="text-amber-700 hover:text-amber-900 underline"
            @click="dismiss"
        >
            {{ t('connectivity.dismiss') }}
        </button>
    </div>
</template>
