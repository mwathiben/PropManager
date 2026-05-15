<script setup lang="ts">
/**
 * Phase-27 BI-FORECAST-1/2/3: rent-roll forecast page.
 *
 * Two tabs:
 *   1. Rent roll — table of expected/low/high per month with
 *      seasonality factor visible per row. Simple bars rendered in
 *      CSS (no Chart.js — keeps the forecast page off the heavy
 *      chart chunk).
 *   2. Vacancy — sortable list of vacant units with expected fill
 *      date + lost-revenue projection.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

type MonthRow = {
    month: string;
    active_rent: number;
    expected_revenue: number;
    low_estimate: number;
    high_estimate: number;
    seasonality: number;
};

type VacancyRow = {
    unit_id: number;
    unit_number: string;
    vacant_since: string | null;
    expected_fill_date: string;
    lost_revenue_kes: number;
};

const props = defineProps<{
    rentRoll: {
        collection_rate: number;
        vacancy_fill_rate: number;
        vacant_unit_count: number;
        average_rent: number;
        months: MonthRow[];
    };
    vacancyProjection: VacancyRow[];
    months: number;
}>();

const activeTab = ref<'rent_roll' | 'vacancy'>('rent_roll');

const horizonOptions = [
    { value: 1, label: '1 month' },
    { value: 3, label: '3 months' },
    { value: 6, label: '6 months' },
    { value: 12, label: '12 months' },
] as const;

const maxHigh = computed(() => Math.max(1, ...props.rentRoll.months.map(m => m.high_estimate)));

function formatKes(amount: number): string {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatPct(value: number, decimals = 1): string {
    return `${(value * 100).toFixed(decimals)}%`;
}

function changeHorizon(value: number): void {
    router.get(route('reports.forecast'), { months: value }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function barWidth(amount: number): string {
    return `${Math.max(2, (amount / maxHigh.value) * 100)}%`;
}
</script>

<template>
    <Head title="Rent-roll forecast" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Rent-roll forecast</h1>
        </template>

        <div class="px-4 py-6 lg:px-8">
            <!-- Horizon picker -->
            <div class="mb-4 flex items-center gap-2 text-sm">
                <span class="text-gray-600">Horizon:</span>
                <button
                    v-for="opt in horizonOptions"
                    :key="opt.value"
                    type="button"
                    class="rounded-full px-3 py-1 transition"
                    :class="props.months === opt.value
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'"
                    @click="changeHorizon(opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- Tabs -->
            <div class="mb-6 flex gap-2 border-b border-gray-200">
                <button
                    v-for="tab in (['rent_roll', 'vacancy'] as const)"
                    :key="tab"
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                    @click="activeTab = tab"
                >
                    {{ tab === 'rent_roll' ? 'Rent roll' : 'Vacancy' }}
                </button>
            </div>

            <!-- Rent roll tab -->
            <section v-if="activeTab === 'rent_roll'" aria-labelledby="rent-roll-heading">
                <h2 id="rent-roll-heading" class="sr-only">Rent-roll forecast</h2>

                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Collection rate</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatPct(props.rentRoll.collection_rate) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Vacancy fill rate</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatPct(props.rentRoll.vacancy_fill_rate) }}/mo</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Vacant units</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ props.rentRoll.vacant_unit_count }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Average rent</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatKes(props.rentRoll.average_rent) }}</p>
                    </div>
                </div>

                <p class="mb-3 text-xs text-gray-500">
                    Low = active rent × collection rate. Expected = low × seasonality. High = active rent + (vacant × avg rent × fill rate).
                    Seasonality factor is derived from the last 3 years of payments — it falls back to 1.0 if there isn't enough history.
                </p>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Month</th>
                            <th class="px-3 py-2 text-right">Low</th>
                            <th class="px-3 py-2 text-right">Expected</th>
                            <th class="px-3 py-2 text-right">High</th>
                            <th class="px-3 py-2 text-right">Seasonality</th>
                            <th class="px-3 py-2 w-1/3">Range</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in props.rentRoll.months" :key="row.month">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.month }}</td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ formatKes(row.low_estimate) }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ formatKes(row.expected_revenue) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ formatKes(row.high_estimate) }}</td>
                            <td class="px-3 py-2 text-right text-xs">
                                <span class="rounded-full px-2 py-0.5 font-medium"
                                    :class="row.seasonality >= 1 ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900'">
                                    × {{ row.seasonality.toFixed(2) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="relative h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div class="absolute inset-y-0 left-0 bg-gray-300" :style="{ width: barWidth(row.low_estimate) }" />
                                    <div class="absolute inset-y-0 left-0 bg-emerald-500" :style="{ width: barWidth(row.expected_revenue) }" />
                                    <div class="absolute inset-y-0 left-0 border-r-2 border-emerald-700" :style="{ width: barWidth(row.high_estimate) }" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Vacancy tab -->
            <section v-else aria-labelledby="vacancy-heading">
                <h2 id="vacancy-heading" class="sr-only">Vacant units</h2>

                <p class="mb-4 text-sm text-gray-600">
                    Expected fill date uses your portfolio's mean time-to-fill. Lost
                    revenue is the daily rent × days remaining until the projected fill.
                </p>

                <div v-if="props.vacancyProjection.length === 0"
                    class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                    No vacant units. Healthy occupancy — keep it up.
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Unit</th>
                            <th class="px-3 py-2">Vacant since</th>
                            <th class="px-3 py-2">Expected fill</th>
                            <th class="px-3 py-2 text-right">Lost revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in props.vacancyProjection" :key="row.unit_id">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.unit_number }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ row.vacant_since ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ row.expected_fill_date }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-rose-700">{{ formatKes(row.lost_revenue_kes) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
