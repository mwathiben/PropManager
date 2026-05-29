<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface KpiCardData {
    title: string;
    value: number | null;
    unit: string | null;
    agg: string;
    count: number;
}

const props = defineProps<{ card: KpiCardData }>();

const { t } = useI18n();

const display = computed(() => {
    if (props.card.value === null) return '—';
    const v = props.card.agg === 'count' || Number.isInteger(props.card.value)
        ? props.card.value.toLocaleString()
        : props.card.value.toFixed(2);
    return props.card.unit ? `${v} ${props.card.unit}` : v;
});

const aggLabel = computed<Record<string, string>>(() => ({
    sum: t('kpi_card.agg_sum'),
    avg: t('kpi_card.agg_avg'),
    min: t('kpi_card.agg_min'),
    max: t('kpi_card.agg_max'),
    count: t('kpi_card.agg_count'),
}));
</script>

<template>
    <div class="mt-3" data-testid="kpi-card">
        <p class="text-4xl font-semibold text-gray-900">{{ display }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ t('kpi_card.across_rows', { label: aggLabel[card.agg] ?? card.agg, count: card.count }) }}</p>
    </div>
</template>
