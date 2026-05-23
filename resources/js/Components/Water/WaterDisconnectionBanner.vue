<script setup lang="ts">
/**
 * Phase-93: shared water-service disconnection banner. Pure presentational so the
 * tenant view (now) and the Phase-94+ water-client view reuse it — payUrl differs
 * per consumer (tenant.payments vs a client pay route).
 */
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

withDefaults(defineProps<{ disconnected?: boolean; reason?: string | null; payUrl?: string | null }>(), {
    disconnected: false,
    reason: null,
    payUrl: null,
});
</script>

<template>
    <div v-if="disconnected" class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4" data-testid="water-disconnection-banner">
        <h3 class="font-semibold text-red-900">{{ t('water.tenant.disconnected_title') }}</h3>
        <p class="mt-1 text-sm text-red-700">{{ reason || t('water.tenant.disconnected_default') }}</p>
        <a v-if="payUrl" :href="payUrl" class="mt-3 inline-block text-sm font-medium text-red-700 underline">{{ t('water.tenant.pay_to_reconnect') }}</a>
    </div>
</template>
