<script setup lang="ts">
/**
 * Phase-50 LANDLORD-DASHBOARDS-3: render assembled DashboardService payload.
 *
 * Cards come in two shapes:
 *   - saved_report: title + tabular rows (raw report output)
 *   - metric: title + count of rows + average value + optional unit
 *
 * Sizing is wide (col-span-2) | narrow (col-span-1).
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import ChartCard from '@/Components/Dashboard/ChartCard.vue';
import TextCard from '@/Components/Dashboard/TextCard.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

type DashboardMeta = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
};

type SavedReportCard = {
    type: 'saved_report';
    title: string;
    size: 'wide' | 'narrow';
    saved_report_id: number;
    rows: Array<Record<string, unknown>>;
};

type MetricCard = {
    type: 'metric';
    title: string;
    size: 'wide' | 'narrow';
    metric_slug: string;
    saved_report_id: number;
    unit: string | null;
    count: number;
    average: number | null;
};

type KpiCardT = {
    type: 'kpi';
    title: string;
    size: 'wide' | 'narrow';
    value: number | null;
    unit: string | null;
    agg: string;
    count: number;
};

type ChartCardT = {
    type: 'chart';
    title: string;
    size: 'wide' | 'narrow';
    points: Array<{ label: string; value: number }>;
};

type TextCardT = {
    type: 'text';
    title: string;
    size: 'wide' | 'narrow';
    body: string;
};

type AnyCard = SavedReportCard | MetricCard | KpiCardT | ChartCardT | TextCardT;

type Payload = {
    dashboard: DashboardMeta;
    cards: AnyCard[];
};

const props = defineProps<{ payload: Payload }>();

const dashboard = computed(() => props.payload.dashboard);
const cards = computed(() => props.payload.cards);

function columnsFor(card: SavedReportCard): string[] {
    return card.rows.length > 0 ? Object.keys(card.rows[0]) : [];
}

function formattedAverage(card: MetricCard): string {
    if (card.average === null) return '—';
    const fixed = card.average.toFixed(2);
    return card.unit ? `${fixed} ${card.unit}` : fixed;
}
</script>

<template>
    <Head :title="dashboard.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ dashboard.name }}</h1>
                    <p v-if="dashboard.description" class="text-sm text-gray-500">
                        {{ dashboard.description }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('dashboards.export-pdf', dashboard.id)"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        data-testid="dashboard-export-pdf"
                    >
                        {{ t('reports.dashboards.export_pdf') }}
                    </a>
                    <a
                        :href="route('dashboards.export-xlsx', dashboard.id)"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        data-testid="dashboard-export-xlsx"
                    >
                        {{ t('reports.dashboards.export_xlsx') }}
                    </a>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 px-4 py-6 lg:grid-cols-2 lg:px-8">
            <article
                v-for="(card, index) in cards"
                :key="index"
                class="rounded-lg border border-gray-200 bg-white p-4"
                :class="card.size === 'wide' ? 'lg:col-span-2' : 'lg:col-span-1'"
            >
                <header class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ card.title }}</h2>
                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs uppercase tracking-wide text-gray-600">
                        {{ card.type }}
                    </span>
                </header>

                <template v-if="card.type === 'saved_report'">
                    <div v-if="card.rows.length > 0" class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead>
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th v-for="col in columnsFor(card)" :key="col" class="px-2 py-2">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, i) in card.rows" :key="i">
                                    <td v-for="col in columnsFor(card)" :key="col" class="px-2 py-1.5 text-gray-700">
                                        {{ row[col] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-3 text-xs text-gray-500">No rows.</p>
                </template>

                <KpiCard v-else-if="card.type === 'kpi'" :card="card" />
                <ChartCard v-else-if="card.type === 'chart'" :card="card" />
                <TextCard v-else-if="card.type === 'text'" :card="card" />

                <template v-else-if="card.type === 'metric'">
                    <div class="mt-3">
                        <p class="text-3xl font-semibold text-gray-900">{{ formattedAverage(card) }}</p>
                        <p class="text-xs text-gray-500">Average across {{ card.count }} row(s)</p>
                    </div>
                </template>
            </article>

            <p v-if="cards.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 lg:col-span-2">
                No cards configured on this dashboard yet.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
