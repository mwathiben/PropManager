<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FunnelSankey from '@/Components/Growth/FunnelSankey.vue';
import { computed } from 'vue';

interface AttributionRow {
    channel: string;
    credit_pct: number;
}

interface CohortRow {
    cohort_month: string;
    source: string;
    size: number;
    retention: number[];
}

interface PromotedRow {
    experiment_key: string;
    winning_variant_key: string | null;
    chi_p: number | null;
    bayes_posterior: number | null;
    ended_at: string | null;
}

const props = defineProps<{
    attribution_summary: Record<string, AttributionRow[]>;
    funnel_sankey: { nodes: Array<{ id: string; label: string; count: number }>; links: Array<{ source: string; target: string; value: number }>; window_days: number };
    cohort_by_source: CohortRow[];
    experiments_auto_promoted: PromotedRow[];
}>();

const modelLabels: Record<string, string> = {
    first_touch: 'First touch',
    last_touch: 'Last touch',
    linear: 'Linear',
    u_shape: 'U-shape',
};

const cohortBySource = computed(() => {
    const grouped: Record<string, CohortRow[]> = {};
    props.cohort_by_source.forEach((row) => {
        grouped[row.source] ??= [];
        grouped[row.source].push(row);
    });
    return grouped;
});

const sourceColor = (source: string): string => {
    return {
        organic: '#10B981',
        referral: '#6366F1',
        paid: '#F59E0B',
        invitation: '#EF4444',
        unknown: '#9CA3AF',
    }[source] ?? '#9CA3AF';
};

const formatPercent = (n: number | null | undefined): string =>
    typeof n === 'number' ? `${n.toFixed(1)}%` : '—';

const formatProb = (n: number | null | undefined): string =>
    typeof n === 'number' ? n.toFixed(3) : '—';
</script>

<template>
    <Head title="Growth attribution" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Growth attribution</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl grid grid-cols-1 gap-6 sm:px-6 lg:px-8 lg:grid-cols-2">
                <!-- Attribution models -->
                <section
                    class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    data-testid="attribution-models-card"
                >
                    <h3 class="text-base font-semibold text-gray-900">Attribution models (last 30d)</h3>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div
                            v-for="(rows, model) in attribution_summary"
                            :key="model"
                            class="rounded-lg bg-gray-50 p-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ modelLabels[model] ?? model }}
                            </p>
                            <ul class="mt-2 space-y-1.5">
                                <li
                                    v-for="row in rows"
                                    :key="`${model}-${row.channel}`"
                                    class="flex items-center justify-between gap-2 text-sm"
                                >
                                    <span class="capitalize text-gray-700">{{ row.channel }}</span>
                                    <span class="font-semibold text-emerald-700">{{ row.credit_pct.toFixed(1) }}%</span>
                                </li>
                                <li v-if="rows.length === 0" class="text-xs italic text-gray-400">
                                    No touchpoints in window.
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Funnel sankey -->
                <section
                    class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    data-testid="funnel-sankey-card"
                >
                    <h3 class="text-base font-semibold text-gray-900">
                        Funnel (last {{ funnel_sankey.window_days }}d)
                    </h3>
                    <div class="mt-4 overflow-auto">
                        <FunnelSankey :payload="funnel_sankey" />
                    </div>
                </section>

                <!-- Cohort by source -->
                <section
                    class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    data-testid="cohort-by-source-card"
                >
                    <h3 class="text-base font-semibold text-gray-900">Cohort retention by source</h3>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(rows, source) in cohortBySource"
                            :key="source"
                            class="rounded-lg bg-gray-50 p-3"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block h-2.5 w-2.5 rounded-full"
                                    :style="{ backgroundColor: sourceColor(source) }"
                                />
                                <span class="text-sm font-medium capitalize text-gray-700">{{ source }}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-gray-600">
                                <div
                                    v-for="row in rows"
                                    :key="`${row.source}-${row.cohort_month}`"
                                    class="flex items-center gap-2"
                                >
                                    <span class="w-20 font-mono">{{ row.cohort_month }}</span>
                                    <span class="w-16">{{ row.size }} users</span>
                                    <span class="font-mono">
                                        {{ row.retention.map((r) => (r * 100).toFixed(0) + '%').join(' → ') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="Object.keys(cohortBySource).length === 0"
                            class="text-xs italic text-gray-400"
                        >
                            No cohorts in window.
                        </p>
                    </div>
                </section>

                <!-- Auto-promoted experiments timeline -->
                <section
                    class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
                    data-testid="auto-promoted-card"
                >
                    <h3 class="text-base font-semibold text-gray-900">Auto-promoted experiments</h3>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="row in experiments_auto_promoted"
                            :key="row.experiment_key"
                            class="rounded-lg bg-gray-50 p-3 text-sm"
                        >
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ row.experiment_key }}</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                    {{ row.winning_variant_key ?? '—' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                χ² p={{ formatPercent((row.chi_p ?? 0) * 100) }} · Bayes={{ formatProb(row.bayes_posterior) }} · ended {{ row.ended_at ?? '—' }}
                            </p>
                        </li>
                        <li
                            v-if="experiments_auto_promoted.length === 0"
                            class="text-xs italic text-gray-400"
                        >
                            No experiments auto-promoted yet.
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
