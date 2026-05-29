<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';

defineProps<{
    ratios: Array<{ feature: string; usage: number; limit: number; ratio: number }>;
}>();

const { t } = useI18n();

function barColor(ratio: number): string {
    if (ratio > 0.9) return 'bg-red-500';
    if (ratio > 0.7) return 'bg-yellow-500';
    return 'bg-green-500';
}

function clamped(ratio: number): number {
    return Math.min(100, Math.round(ratio * 100));
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $t('insight.landlord_growth.usage_card_heading') }}</p>
        <ul class="mt-3 space-y-2">
            <li v-for="r in ratios" :key="r.feature">
                <div class="flex items-baseline justify-between text-xs text-gray-700">
                    <span class="font-medium capitalize">{{ r.feature }}</span>
                    <span>{{ r.usage }} / {{ r.limit }}</span>
                </div>
                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full transition-all" :class="barColor(r.ratio)" :style="{ width: clamped(r.ratio) + '%' }"></div>
                </div>
            </li>
            <li v-if="!ratios.length" class="text-sm text-gray-500">{{ t('usage_ratio_card.empty_state') }}</li>
        </ul>
    </div>
</template>
