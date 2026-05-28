<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface ChartPoint { label: string; value: number }

const props = defineProps<{ card: { points: ChartPoint[] } }>();

const { t } = useI18n();

const max = computed(() => props.card.points.reduce((m, p) => Math.max(m, p.value), 0) || 1);
</script>

<template>
    <div class="mt-3 space-y-2" data-testid="chart-card">
        <div v-for="(point, i) in card.points" :key="i" class="space-y-1">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span class="truncate pe-2">{{ point.label || '—' }}</span>
                <span class="font-medium text-gray-700">{{ point.value.toLocaleString() }}</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100">
                <div
                    class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all"
                    :style="{ width: `${Math.round((point.value / max) * 100)}%` }"
                />
            </div>
        </div>
        <p v-if="card.points.length === 0" class="text-xs text-gray-500">{{ t('chart_card.no_data_points') }}</p>
    </div>
</template>
