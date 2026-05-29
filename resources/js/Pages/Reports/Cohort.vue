<script setup lang="ts">
/**
 * Phase-27 BI-COHORT-1/2/3: tenant cohort analytics page.
 *
 * Three tabs:
 *   1. Retention — triangular heatmap (cohort × offset month)
 *   2. Acquisition — table of new/reactivated/churned/net per month
 *   3. Lifetime value — single-cohort LTV summary card
 *
 * Heatmap uses CSS-based cell colouring (no Chart.js dependency for
 * this view — it's a simple matrix, not a time-series). Keeps the
 * cohort page off the leaflet/chart chunk so it loads fast under
 * PWA-NETWORK-2 isSlow conditions.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

type RetentionMatrix = Record<string, Array<number | null>>;
type AcquisitionRow = {
    month: string;
    new: number;
    reactivated: number;
    churned: number;
    net_delta: number;
};
type LifetimeValue = {
    tenants_count: number;
    total_payments: number;
    mean_ltv: number;
    median_ltv: number;
};

const props = defineProps<{
    retentionMatrix: RetentionMatrix;
    acquisitionTable: AcquisitionRow[];
    lifetimeValue: LifetimeValue;
    lookback: number;
    cohortMonth: string;
}>();

const { t } = useI18n();
const activeTab = ref<'retention' | 'acquisition' | 'ltv'>('retention');

const cohortMonths = computed(() => Object.keys(props.retentionMatrix).sort());

function survivalCellClass(value: number | null): string {
    if (value === null) return 'bg-gray-50 text-gray-300';
    if (value >= 0.9) return 'bg-emerald-500 text-white';
    if (value >= 0.75) return 'bg-emerald-300 text-emerald-900';
    if (value >= 0.5) return 'bg-amber-200 text-amber-900';
    if (value >= 0.25) return 'bg-rose-200 text-rose-900';
    return 'bg-rose-500 text-white';
}

function formatPct(value: number | null): string {
    if (value === null) return '—';
    return `${Math.round(value * 100)}%`;
}

function formatKes(amount: number): string {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(amount);
}

function pickCohort(month: string): void {
    router.get(route('reports.cohort'), { lookback: props.lookback, cohort: month }, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Cohort analytics" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('reports_cohort.title') }}</h1>
        </template>

        <div class="px-4 py-6 lg:px-8">
            <div class="mb-6 flex gap-2 border-b border-gray-200">
                <button
                    v-for="tab in (['retention', 'acquisition', 'ltv'] as const)"
                    :key="tab"
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                    @click="activeTab = tab"
                >
                    {{ tab === 'retention' ? 'Retention' : tab === 'acquisition' ? 'Acquisition' : 'Lifetime value' }}
                </button>
            </div>

            <!-- Retention matrix -->
            <section v-if="activeTab === 'retention'" aria-labelledby="retention-heading">
                <h2 id="retention-heading" class="sr-only">Tenant retention matrix</h2>
                <p class="mb-4 text-sm text-gray-600">
                    Each row is a cohort of tenants who started leases in the same month. Each
                    column is the percentage of that cohort still active N months later.
                </p>

                <div v-if="cohortMonths.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                    No cohorts in the lookback window. Start a lease to seed the first cohort.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-1 text-center text-xs">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-start font-semibold text-gray-700">Cohort</th>
                                <th v-for="offset in props.lookback + 1" :key="offset"
                                    class="px-2 py-2 font-medium text-gray-500">
                                    M+{{ offset - 1 }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="month in cohortMonths" :key="month">
                                <th scope="row" class="px-3 py-2 text-start text-sm font-medium text-gray-800">
                                    <button
                                        type="button"
                                        class="underline-offset-2 hover:underline"
                                        @click="pickCohort(month)"
                                    >{{ month }}</button>
                                </th>
                                <td
                                    v-for="(value, offset) in (props.retentionMatrix[month] ?? [])"
                                    :key="offset"
                                    class="rounded px-2 py-1.5 font-medium"
                                    :class="survivalCellClass(value)"
                                >
                                    {{ formatPct(value) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Acquisition table -->
            <section v-else-if="activeTab === 'acquisition'" aria-labelledby="acquisition-heading">
                <h2 id="acquisition-heading" class="sr-only">Monthly acquisition + churn</h2>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Month</th>
                            <th class="px-3 py-2 text-end">New</th>
                            <th class="px-3 py-2 text-end">Reactivated</th>
                            <th class="px-3 py-2 text-end">Churned</th>
                            <th class="px-3 py-2 text-end">Net Δ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in props.acquisitionTable" :key="row.month">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.month }}</td>
                            <td class="px-3 py-2 text-end">{{ row.new }}</td>
                            <td class="px-3 py-2 text-end">{{ row.reactivated }}</td>
                            <td class="px-3 py-2 text-end">{{ row.churned }}</td>
                            <td class="px-3 py-2 text-end font-semibold"
                                :class="row.net_delta > 0 ? 'text-emerald-600' : row.net_delta < 0 ? 'text-rose-600' : 'text-gray-500'">
                                {{ row.net_delta > 0 ? '+' : '' }}{{ row.net_delta }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- LTV summary -->
            <section v-else aria-labelledby="ltv-heading">
                <h2 id="ltv-heading" class="mb-4 text-base font-semibold text-gray-900">
                    Lifetime value — cohort {{ props.cohortMonth }}
                </h2>
                <p class="mb-4 text-sm text-gray-600">
                    Sum of non-voided payments from tenants who started leases in
                    {{ props.cohortMonth }}. Refunds are not yet subtracted — see
                    the BI runbook for methodology + known limitations.
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Tenants</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ props.lifetimeValue.tenants_count }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Total payments</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatKes(props.lifetimeValue.total_payments) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Mean LTV</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatKes(props.lifetimeValue.mean_ltv) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Median LTV</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatKes(props.lifetimeValue.median_ltv) }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
