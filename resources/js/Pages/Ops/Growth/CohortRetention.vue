<script setup lang="ts">
/**
 * Phase-66 COHORT-RETENTION-3: super-admin retention heatmap.
 * Rows = acquisition source, columns = month offset, cell = activity
 * retention (colour-scaled red→green). Non-organic rows annotate each
 * cell with the percentage-point delta vs the organic baseline; thin
 * cohorts (insufficient_sample) are muted.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

interface SourceRow {
    source: string;
    total_size: number;
    retention: number[];
    delta_vs_organic: number[];
    insufficient_sample: boolean;
}

const props = defineProps<{
    source_comparison: SourceRow[];
    baseline: number[];
    month_range: number;
    min_sample: number;
}>();

const { t } = useI18n();

const maxOffset = computed(() => {
    const lengths = props.source_comparison.map((s) => s.retention.length);
    return lengths.length ? Math.max(...lengths) - 1 : 0;
});

const offsets = computed(() => Array.from({ length: Math.max(0, maxOffset.value) + 1 }, (_, i) => i));

function sourceLabel(source: string): string {
    const key = `growth.cohort.sources.${source}`;
    const label = t(key);
    return label === key ? source : label;
}

function pct(rate: number | undefined): string {
    return rate == null ? '—' : `${Math.round(rate * 100)}%`;
}

function cellStyle(rate: number | undefined): Record<string, string> {
    if (rate == null) {
        return { backgroundColor: 'transparent' };
    }
    const hue = Math.round(rate * 120); // 0 = red, 120 = green
    return { backgroundColor: `hsl(${hue}, 65%, 92%)`, color: `hsl(${hue}, 45%, 26%)` };
}

interface Delta {
    up: boolean;
    label: string;
}

function deltaBadge(delta: number | undefined): Delta | null {
    if (delta == null || Math.abs(delta) < 0.005) {
        return null;
    }
    const points = Math.round(delta * 100);
    return { up: delta > 0, label: `${points > 0 ? '+' : ''}${points}` };
}
</script>

<template>
    <Head :title="t('growth.cohort.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('growth.cohort.title') }}</h1>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500">{{ t('growth.cohort.subtitle', { months: month_range }) }}</p>

            <div v-if="source_comparison.length" class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-gray-200">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">{{ t('growth.cohort.title') }}</caption>
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400">
                            <th scope="col" class="px-3 py-2 text-start">{{ t('growth.cohort.source') }}</th>
                            <th scope="col" class="px-3 py-2 text-end">{{ t('growth.cohort.cohort_size') }}</th>
                            <th v-for="o in offsets" :key="o" scope="col" class="px-3 py-2 text-center">
                                {{ t('growth.cohort.month_offset', { offset: o }) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in source_comparison"
                            :key="row.source"
                            class="border-b border-gray-100"
                            :class="row.insufficient_sample ? 'opacity-40' : ''"
                            :data-testid="`cohort-row-${row.source}`"
                        >
                            <td class="px-3 py-2 font-medium text-gray-900">
                                {{ sourceLabel(row.source) }}
                                <span v-if="row.insufficient_sample" class="ms-1 text-[10px] text-amber-600">
                                    {{ t('growth.cohort.insufficient_sample', { min: min_sample }) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-end text-gray-600">{{ row.total_size }}</td>
                            <td
                                v-for="o in offsets"
                                :key="o"
                                class="px-2 py-1 text-center"
                                :style="cellStyle(row.retention[o])"
                            >
                                <div class="font-semibold">{{ pct(row.retention[o]) }}</div>
                                <div
                                    v-if="row.source !== 'organic' && deltaBadge(row.delta_vs_organic[o])"
                                    class="text-[10px] font-medium"
                                    :class="deltaBadge(row.delta_vs_organic[o])?.up ? 'text-emerald-700' : 'text-rose-700'"
                                >
                                    {{ deltaBadge(row.delta_vs_organic[o])?.up ? '▲' : '▼' }}{{ deltaBadge(row.delta_vs_organic[o])?.label }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-6 text-sm text-gray-400">{{ t('growth.cohort.empty') }}</p>

            <p class="mt-3 text-xs text-gray-400">{{ t('growth.cohort.baseline_note') }}</p>
        </div>
    </AuthenticatedLayout>
</template>
