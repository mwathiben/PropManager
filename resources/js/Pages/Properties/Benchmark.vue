<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { ChartBarIcon } from '@heroicons/vue/24/outline';

interface BenchmarkRow {
    property_id: number;
    name: string;
    occupancy_pct: number;
    noi_margin: number | null;
    gross_yield: number | null;
    occupancy_percentile: number | null;
    margin_percentile: number | null;
    yield_percentile: number | null;
    rank: number;
}

interface Portfolio {
    property_count: number;
    avg_occupancy_pct: number;
    median_occupancy_pct: number;
    median_noi_margin: number | null;
    median_gross_yield: number | null;
}

defineProps<{ properties: BenchmarkRow[]; portfolio: Portfolio }>();

const { t } = useI18n();

const pct = (v: number | null) => (v === null ? '—' : `${v}%`);
const ratio = (v: number | null) => (v === null ? '—' : `${(v * 100).toFixed(1)}%`);

function barClass(p: number | null): string {
    if (p === null) return 'bg-gray-200';
    if (p >= 66) return 'bg-emerald-500';
    if (p >= 33) return 'bg-amber-400';
    return 'bg-rose-400';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('property.benchmark.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <ChartBarIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('property.benchmark.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('property.benchmark.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-4" data-testid="properties-benchmark">
            <p v-if="properties.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('property.benchmark.empty') }}
            </p>

            <template v-else>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-400">{{ t('property.benchmark.median') }} · {{ t('property.benchmark.occupancy') }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ portfolio.median_occupancy_pct }}%</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-400">{{ t('property.benchmark.median') }} · {{ t('property.benchmark.margin') }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ ratio(portfolio.median_noi_margin) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <p class="text-xs text-gray-400">{{ t('property.benchmark.median') }} · {{ t('property.benchmark.yield') }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ ratio(portfolio.median_gross_yield) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-start text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ t('property.benchmark.rank') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('property.index.title') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('property.benchmark.occupancy') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('property.benchmark.margin') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('property.benchmark.yield') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="row in properties" :key="row.property_id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700">{{ row.rank }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link :href="route('properties.show', row.property_id)" class="font-medium text-gray-900 hover:text-indigo-600">{{ row.name }}</Link>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ pct(row.occupancy_pct) }}</div>
                                    <div class="mt-1 h-1.5 w-20 rounded-full bg-gray-100">
                                        <div class="h-1.5 rounded-full" :class="barClass(row.occupancy_percentile)" :style="{ width: `${row.occupancy_percentile ?? 0}%` }"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ ratio(row.noi_margin) }}</div>
                                    <div class="mt-1 h-1.5 w-20 rounded-full bg-gray-100">
                                        <div class="h-1.5 rounded-full" :class="barClass(row.margin_percentile)" :style="{ width: `${row.margin_percentile ?? 0}%` }"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ ratio(row.gross_yield) }}</div>
                                    <div class="mt-1 h-1.5 w-20 rounded-full bg-gray-100">
                                        <div class="h-1.5 rounded-full" :class="barClass(row.yield_percentile)" :style="{ width: `${row.yield_percentile ?? 0}%` }"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
