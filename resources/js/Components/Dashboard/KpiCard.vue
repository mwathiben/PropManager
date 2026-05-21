<script setup lang="ts">
import { computed } from 'vue';

interface KpiCardData {
    title: string;
    value: number | null;
    unit: string | null;
    agg: string;
    count: number;
}

const props = defineProps<{ card: KpiCardData }>();

const display = computed(() => {
    if (props.card.value === null) return '—';
    const v = props.card.agg === 'count' || Number.isInteger(props.card.value)
        ? props.card.value.toLocaleString()
        : props.card.value.toFixed(2);
    return props.card.unit ? `${v} ${props.card.unit}` : v;
});

const aggLabel: Record<string, string> = {
    sum: 'Total', avg: 'Average', min: 'Minimum', max: 'Maximum', count: 'Count',
};
</script>

<template>
    <div class="mt-3" data-testid="kpi-card">
        <p class="text-4xl font-semibold text-gray-900">{{ display }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ aggLabel[card.agg] ?? card.agg }} across {{ card.count }} row(s)</p>
    </div>
</template>
