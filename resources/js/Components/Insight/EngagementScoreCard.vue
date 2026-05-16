<script setup lang="ts">
const props = defineProps<{
    score: number;
    delta?: number;
}>();

const tierColor = (() => {
    if (props.score > 70) return 'green';
    if (props.score > 30) return 'yellow';
    return 'red';
})();

const tierClasses: Record<string, string> = {
    green: 'bg-green-50 text-green-800 ring-green-200',
    yellow: 'bg-yellow-50 text-yellow-800 ring-yellow-200',
    red: 'bg-red-50 text-red-800 ring-red-200',
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $t('insight.landlord_growth.engagement_card_heading') }}</p>
        <div class="mt-2 flex items-baseline gap-3">
            <p class="text-2xl font-semibold text-gray-900">{{ score }}</p>
            <span class="rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="tierClasses[tierColor]">/ 100</span>
        </div>
        <p v-if="delta !== undefined" class="mt-1 text-sm" :class="delta >= 0 ? 'text-green-700' : 'text-red-700'">
            {{ delta >= 0 ? '+' : '' }}{{ delta }} vs 7d ago
        </p>
    </div>
</template>
