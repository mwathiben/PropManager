<script setup lang="ts">
/**
 * Phase-27 BI-NOI-1/2/3: NOI + cap rate analytics page.
 *
 * Two tabs:
 *   1. NOI by property — sortable table of revenue, expenses
 *      (direct + allocated), NOI, margin. Portfolio totals at top.
 *   2. Cap rate — sortable table of annualised NOI / estimated_value
 *      with Kenyan-market band colouring.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

type PropertyNoi = {
    property_id: number;
    name: string;
    revenue: number;
    direct_expenses: number;
    allocated_expenses: number;
    noi: number;
    noi_margin: number | null;
};

type PortfolioNoi = Omit<PropertyNoi, 'property_id' | 'name'>;

type CapRateRow = {
    property_id: number;
    name: string;
    annualised_noi: number;
    estimated_value: number | null;
    cap_rate: number | null;
    band: 'unknown' | 'amber' | 'green' | 'emerald';
};

const props = defineProps<{
    byProperty: {
        period: { start: string; end: string };
        properties: PropertyNoi[];
        portfolio: PortfolioNoi;
    };
    capRate: CapRateRow[];
    window: string;
}>();

const { t } = useI18n();
const activeTab = ref<'noi' | 'cap'>('noi');

const windowOptions = [
    { value: '1m', label: '1 month' },
    { value: '3m', label: '3 months' },
    { value: '6m', label: '6 months' },
    { value: '12m', label: '12 months' },
    { value: 'ytd', label: 'Year to date' },
] as const;

const sortedProperties = computed(() =>
    [...props.byProperty.properties].sort((a, b) => b.noi - a.noi),
);

const sortedCapRates = computed(() =>
    [...props.capRate].sort((a, b) => (b.cap_rate ?? -Infinity) - (a.cap_rate ?? -Infinity)),
);

function formatKes(amount: number): string {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatPct(value: number | null, decimals = 1): string {
    if (value === null) return '—';
    return `${(value * 100).toFixed(decimals)}%`;
}

function bandClass(band: CapRateRow['band']): string {
    return {
        unknown: 'bg-gray-100 text-gray-500',
        amber: 'bg-amber-100 text-amber-900',
        green: 'bg-emerald-100 text-emerald-900',
        emerald: 'bg-emerald-500 text-white',
    }[band];
}

function changeWindow(value: string): void {
    router.get(route('reports.noi'), { window: value }, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="NOI + cap rate" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('reports_noi.title') }}</h1>
        </template>

        <div class="px-4 py-6 lg:px-8">
            <!-- Window picker -->
            <div class="mb-4 flex items-center gap-2 text-sm">
                <span class="text-gray-600">Window:</span>
                <button
                    v-for="opt in windowOptions"
                    :key="opt.value"
                    type="button"
                    class="rounded-full px-3 py-1 transition"
                    :class="props.window === opt.value
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'"
                    @click="changeWindow(opt.value)"
                >
                    {{ opt.label }}
                </button>
                <span class="ms-auto text-xs text-gray-500">
                    {{ props.byProperty.period.start }} → {{ props.byProperty.period.end }}
                </span>
            </div>

            <!-- Tabs -->
            <div class="mb-6 flex gap-2 border-b border-gray-200">
                <button
                    v-for="tab in (['noi', 'cap'] as const)"
                    :key="tab"
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                    @click="activeTab = tab"
                >
                    {{ tab === 'noi' ? 'NOI by property' : 'Cap rate' }}
                </button>
            </div>

            <!-- NOI tab -->
            <section v-if="activeTab === 'noi'" aria-labelledby="noi-heading">
                <h2 id="noi-heading" class="sr-only">NOI by property</h2>

                <!-- Portfolio summary -->
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Portfolio revenue</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatKes(props.byProperty.portfolio.revenue) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Expenses (direct + alloc)</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">
                            {{ formatKes(props.byProperty.portfolio.direct_expenses + props.byProperty.portfolio.allocated_expenses) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Portfolio NOI</p>
                        <p class="mt-1 text-lg font-semibold"
                            :class="props.byProperty.portfolio.noi > 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ formatKes(props.byProperty.portfolio.noi) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">NOI margin</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatPct(props.byProperty.portfolio.noi_margin) }}</p>
                    </div>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Property</th>
                            <th class="px-3 py-2 text-end">Revenue</th>
                            <th class="px-3 py-2 text-end">Direct expenses</th>
                            <th class="px-3 py-2 text-end">Allocated</th>
                            <th class="px-3 py-2 text-end">NOI</th>
                            <th class="px-3 py-2 text-end">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in sortedProperties" :key="row.property_id">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.name }}</td>
                            <td class="px-3 py-2 text-end">{{ formatKes(row.revenue) }}</td>
                            <td class="px-3 py-2 text-end">{{ formatKes(row.direct_expenses) }}</td>
                            <td class="px-3 py-2 text-end text-gray-500">{{ formatKes(row.allocated_expenses) }}</td>
                            <td class="px-3 py-2 text-end font-semibold"
                                :class="row.noi > 0 ? 'text-emerald-700' : row.noi < 0 ? 'text-rose-700' : 'text-gray-500'">
                                {{ formatKes(row.noi) }}
                            </td>
                            <td class="px-3 py-2 text-end">{{ formatPct(row.noi_margin) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Cap rate tab -->
            <section v-else aria-labelledby="cap-heading">
                <h2 id="cap-heading" class="sr-only">Cap rate by property</h2>
                <p class="mb-4 text-sm text-gray-600">
                    Cap rate = annualised NOI ÷ estimated property value. Properties without an
                    estimated value show N/A — set one on the property edit page to compute the
                    rate. Kenyan residential typically falls 6-9% (green); &gt;9% suggests
                    commercial-tier yield (emerald); &lt;6% may signal overvaluation or under-
                    performance (amber).
                </p>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Property</th>
                            <th class="px-3 py-2 text-end">Estimated value</th>
                            <th class="px-3 py-2 text-end">Annualised NOI</th>
                            <th class="px-3 py-2 text-end">Cap rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in sortedCapRates" :key="row.property_id">
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.name }}</td>
                            <td class="px-3 py-2 text-end">
                                {{ row.estimated_value !== null ? formatKes(row.estimated_value) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-end">{{ formatKes(row.annualised_noi) }}</td>
                            <td class="px-3 py-2 text-end">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="bandClass(row.band)">
                                    {{ formatPct(row.cap_rate, 2) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
