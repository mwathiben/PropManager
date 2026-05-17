<script setup lang="ts">
/**
 * Phase-51 LEASE-COUNTER-UI-2: live 14-day expiry countdown.
 *
 * Phase-45 lease_renewals.counter_expires_at + the
 * lease-renewal:expire-stale-counters cron exist, but the tenant
 * doesn't see how many days are left before the counter auto-expires.
 * This component reads :expiresAt ISO string + ticks every 60s.
 *
 * Tone keys:
 *   > 72h remaining → neutral indigo (calm)
 *   <= 72h, > 0    → urgent amber (act soon)
 *   <= 0           → gray expired (negotiation closed)
 *
 * Cleans up its setInterval on unmount.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    expiresAt: string | null;
}>();

const now = ref<number>(Date.now());
let timer: number | null = null;

onMounted(() => {
    timer = window.setInterval(() => {
        now.value = Date.now();
    }, 60_000);
});

onUnmounted(() => {
    if (timer !== null) {
        window.clearInterval(timer);
    }
});

const remainingMs = computed<number>(() => {
    if (!props.expiresAt) return 0;
    const expiry = Date.parse(props.expiresAt);
    if (Number.isNaN(expiry)) return 0;
    return expiry - now.value;
});

const isExpired = computed(() => remainingMs.value <= 0);
const isUrgent = computed(() => !isExpired.value && remainingMs.value <= 72 * 3_600_000);

const labelText = computed(() => {
    if (!props.expiresAt) return '';
    if (isExpired.value) return 'Expired';
    const hours = remainingMs.value / 3_600_000;
    if (hours < 24) {
        const h = Math.max(1, Math.floor(hours));
        return `Expires in ${h} hour${h === 1 ? '' : 's'}`;
    }
    const days = Math.floor(hours / 24);
    return `Expires in ${days} day${days === 1 ? '' : 's'}`;
});

const classes = computed(() => {
    if (isExpired.value) return 'bg-gray-100 text-gray-600 ring-gray-200';
    if (isUrgent.value) return 'bg-amber-50 text-amber-700 ring-amber-200';
    return 'bg-indigo-50 text-indigo-700 ring-indigo-200';
});
</script>

<template>
    <span
        v-if="expiresAt"
        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', classes]"
        :aria-live="isUrgent ? 'polite' : 'off'"
    >
        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9" />
            <polyline points="12 7 12 12 15 14" />
        </svg>
        {{ labelText }}
    </span>
</template>
