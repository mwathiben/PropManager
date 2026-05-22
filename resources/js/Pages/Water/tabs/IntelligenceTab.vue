<script setup lang="ts">
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ChartCard from '@/Components/Dashboard/ChartCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon, ExclamationTriangleIcon, TrashIcon } from '@heroicons/vue/24/outline';

interface TrendPoint { label: string; value: number; period: string }
interface Consumer { unit: string | null; building: string | null; consumption: number }
interface Anomaly { unit: string | null; building: string | null; date: string | null; consumption: number }
interface Nrw { meter: string; building: string | null; main: number; sub: number; loss: number | null; loss_pct: number | null; complete: boolean }
interface RecentCost { id: number; date: string; amount: number; category: string; note: string | null; building: string | null }

interface Intelligence {
    trend: TrendPoint[];
    summary: {
        avg_monthly_consumption: number;
        window_consumption: number;
        period_delta_pct: number | null;
        projection_next: number | null;
        collection_rate_pct: number | null;
        margin: number | null;
        margin_pct: number | null;
        costs_logged: boolean;
        anomaly_count: number;
    };
    building_comparison: { label: string; value: number }[];
    top_consumers: Consumer[];
    anomalies: Anomaly[];
    non_revenue_water: Nrw[];
    billing: { billed: number; collected: number; collection_rate_pct: number | null; outstanding: number };
    production: { cost: number; revenue: number; margin: number | null; margin_pct: number | null; cost_per_unit: number | null; costs_logged: boolean };
    recent_costs: RecentCost[];
}

const props = withDefaults(defineProps<{
    intelligence?: Intelligence;
    costCategories?: string[];
    buildings?: Array<{ id: number; name: string }>;
}>(), {
    costCategories: () => [],
    buildings: () => [],
});

const { formatMoney, formatNumber, formatPercent, formatDate } = useFormatters();
const { t } = useI18n();

const i = computed<Intelligence>(() => props.intelligence ?? ({
    trend: [], summary: { avg_monthly_consumption: 0, window_consumption: 0, period_delta_pct: null, projection_next: null, collection_rate_pct: null, margin: null, margin_pct: null, costs_logged: false, anomaly_count: 0 },
    building_comparison: [], top_consumers: [], anomalies: [], non_revenue_water: [], billing: { billed: 0, collected: 0, collection_rate_pct: null, outstanding: 0 }, production: { cost: 0, revenue: 0, margin: null, margin_pct: null, cost_per_unit: null, costs_logged: false }, recent_costs: [],
} as Intelligence));

const delta = computed(() => i.value.summary.period_delta_pct);
const collectionRate = computed(() => Math.max(0, Math.min(100, i.value.billing.collection_rate_pct ?? 0)));

function categoryLabel(cat: string): string {
    return t(`water.intelligence.category.${cat}`);
}

const today = new Date().toISOString().slice(0, 10);
const costForm = useForm({ building_id: '', cost_date: today, amount: '', category: props.costCategories[0] ?? 'electricity', note: '' });

function submitCost(): void {
    costForm.post(route('water.production-costs.store'), {
        preserveScroll: true,
        onSuccess: () => costForm.reset('amount', 'note'),
    });
}

function deleteCost(id: number): void {
    router.delete(route('water.production-costs.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-8" data-testid="water-intelligence-tab">
        <!-- KPI summary -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-sm text-gray-500">{{ t('water.intelligence.kpi.avg_monthly') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ formatNumber(i.summary.avg_monthly_consumption) }}</div>
                <div class="mt-1 text-xs text-gray-400">{{ t('water.intelligence.units') }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-sm text-gray-500">{{ t('water.intelligence.kpi.month_change') }}</div>
                <div
                    class="mt-1 flex items-center gap-1 text-2xl font-semibold"
                    :class="delta === null ? 'text-gray-400' : delta > 0 ? 'text-amber-600' : 'text-emerald-600'"
                >
                    <component :is="delta !== null && delta < 0 ? ArrowTrendingDownIcon : ArrowTrendingUpIcon" v-if="delta !== null" class="h-5 w-5" />
                    <span>{{ delta === null ? '—' : `${delta > 0 ? '+' : ''}${formatPercent(delta, 1)}` }}</span>
                </div>
                <div class="mt-1 text-xs text-gray-400">{{ t('water.intelligence.kpi.vs_last_month') }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-sm text-gray-500">{{ t('water.intelligence.kpi.projection') }}</div>
                <div class="mt-1 text-2xl font-semibold" :class="i.summary.projection_next === null ? 'text-gray-400' : 'text-blue-600'">{{ i.summary.projection_next === null ? '—' : formatNumber(i.summary.projection_next) }}</div>
                <div class="mt-1 text-xs text-gray-400">{{ i.summary.projection_next === null ? t('water.intelligence.kpi.need_history') : t('water.intelligence.kpi.next_month') }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-sm text-gray-500">{{ t('water.intelligence.kpi.margin') }}</div>
                <div v-if="i.summary.margin === null" class="mt-1 text-2xl font-semibold text-gray-400">—</div>
                <div v-else class="mt-1 text-2xl font-semibold" :class="i.summary.margin >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatMoney(i.summary.margin) }}</div>
                <div class="mt-1 text-xs text-gray-400">{{ i.summary.margin === null ? t('water.intelligence.kpi.log_costs') : (i.summary.margin_pct === null ? t('water.intelligence.window') : `${formatPercent(i.summary.margin_pct, 1)} · ${t('water.intelligence.window')}`) }}</div>
            </div>
        </div>

        <!-- Trend + building comparison -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="text-sm font-semibold text-gray-900">{{ t('water.intelligence.trend_title') }}</h3>
                <p class="mt-0.5 text-xs text-gray-500">{{ t('water.intelligence.trend_hint') }}</p>
                <ChartCard :card="{ points: i.trend }" />
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="text-sm font-semibold text-gray-900">{{ t('water.intelligence.by_building_title') }}</h3>
                <p class="mt-0.5 text-xs text-gray-500">{{ t('water.intelligence.window') }}</p>
                <ChartCard :card="{ points: i.building_comparison }" />
            </div>
        </div>

        <!-- Top consumers + leak signals -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ t('water.intelligence.top_consumers') }}</h3>
                <p v-if="i.top_consumers.length === 0" class="text-sm text-gray-500">{{ t('water.intelligence.no_data') }}</p>
                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="(c, idx) in i.top_consumers" :key="idx" class="flex items-center justify-between py-2 text-sm">
                        <span class="text-gray-700">
                            <span class="me-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-500">{{ idx + 1 }}</span>
                            {{ c.building ? `${c.building} · ` : '' }}{{ c.unit ?? '—' }}
                        </span>
                        <span class="font-semibold text-gray-900">{{ formatNumber(c.consumption) }}</span>
                    </li>
                </ul>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <ExclamationTriangleIcon class="h-4 w-4 text-amber-500" />
                    {{ t('water.intelligence.leak_signals') }}
                    <span v-if="i.summary.anomaly_count > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ i.summary.anomaly_count }}</span>
                </h3>
                <p v-if="i.anomalies.length === 0" class="text-sm text-gray-500">{{ t('water.intelligence.no_anomalies') }}</p>
                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="(a, idx) in i.anomalies" :key="idx" class="flex items-center justify-between py-2 text-sm">
                        <span class="text-gray-700">{{ a.building ? `${a.building} · ` : '' }}{{ a.unit ?? '—' }}
                            <span class="ms-1 text-xs text-gray-400">{{ a.date ? formatDate(a.date) : '' }}</span>
                        </span>
                        <span class="font-semibold text-amber-700">{{ formatNumber(a.consumption) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Non-revenue water (only when a main/sub-meter hierarchy exists) -->
        <div v-if="i.non_revenue_water.length" class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ t('water.intelligence.nrw_title') }}</h3>
            <p class="mt-0.5 text-xs text-gray-500">{{ t('water.intelligence.nrw_hint') }}</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs uppercase tracking-wide text-gray-400">
                            <th class="py-2 pe-4 text-start font-medium">{{ t('water.intelligence.nrw_meter') }}</th>
                            <th class="py-2 pe-4 text-end font-medium">{{ t('water.intelligence.nrw_main') }}</th>
                            <th class="py-2 pe-4 text-end font-medium">{{ t('water.intelligence.nrw_sub') }}</th>
                            <th class="py-2 pe-4 text-end font-medium">{{ t('water.intelligence.nrw_loss') }}</th>
                            <th class="py-2 text-end font-medium">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(n, idx) in i.non_revenue_water" :key="idx">
                            <td class="py-2 pe-4 text-gray-700">{{ n.building ? `${n.building} · ` : '' }}{{ n.meter }}</td>
                            <td class="py-2 pe-4 text-end text-gray-700">{{ formatNumber(n.main) }}</td>
                            <td class="py-2 pe-4 text-end text-gray-700">{{ n.complete ? formatNumber(n.sub) : '—' }}</td>
                            <td class="py-2 pe-4 text-end font-semibold" :class="n.complete && (n.loss ?? 0) > 0 ? 'text-amber-700' : 'text-gray-700'">
                                <span v-if="n.complete">{{ formatNumber(n.loss) }}</span>
                                <span v-else class="text-xs font-normal text-gray-400">{{ t('water.intelligence.nrw_incomplete') }}</span>
                            </td>
                            <td class="py-2 text-end" :class="n.complete && (n.loss_pct ?? 0) > 0 ? 'text-amber-700' : 'text-gray-500'">{{ n.complete ? formatPercent(n.loss_pct, 1) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Billing vs collection -->
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ t('water.intelligence.billing_title') }}</h3>
            <p class="mt-0.5 text-xs text-gray-500">{{ t('water.intelligence.billing_hint') }}</p>
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.billed') }}</div>
                    <div class="text-lg font-semibold text-gray-900">{{ formatMoney(i.billing.billed) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.collected') }}</div>
                    <div class="text-lg font-semibold text-emerald-600">{{ formatMoney(i.billing.collected) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.outstanding') }}</div>
                    <div class="text-lg font-semibold text-amber-600">{{ formatMoney(i.billing.outstanding) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.collection_rate') }}</div>
                    <div class="text-lg font-semibold text-gray-900">{{ i.billing.collection_rate_pct === null ? '—' : formatPercent(i.billing.collection_rate_pct, 1) }}</div>
                </div>
            </div>
            <div class="mt-3 h-2 rounded-full bg-gray-100">
                <div class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all" :style="{ width: `${collectionRate}%` }" />
            </div>
        </div>

        <!-- Production cost & margin -->
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ t('water.intelligence.production_title') }}</h3>
            <p class="mt-0.5 text-xs text-gray-500">{{ t('water.intelligence.production_hint') }}</p>
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.prod_revenue') }}</div>
                    <div class="text-lg font-semibold text-gray-900">{{ formatMoney(i.production.revenue) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.prod_cost') }}</div>
                    <div class="text-lg font-semibold text-red-600">{{ formatMoney(i.production.cost) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.prod_margin') }}</div>
                    <div v-if="i.production.margin === null" class="text-lg font-semibold text-gray-400">—</div>
                    <div v-else class="text-lg font-semibold" :class="i.production.margin >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatMoney(i.production.margin) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ t('water.intelligence.prod_cost_per_unit') }}</div>
                    <div class="text-lg font-semibold text-gray-900">{{ i.production.cost_per_unit === null ? '—' : formatMoney(i.production.cost_per_unit) }}</div>
                </div>
            </div>
            <p v-if="!i.production.costs_logged" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">{{ t('water.intelligence.no_costs_hint') }}</p>

            <!-- Log a production cost -->
            <form class="mt-6 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="submitCost">
                <label class="block lg:col-span-1">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.intelligence.cost_date') }}</span>
                    <input v-model="costForm.cost_date" type="date" :max="today" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" required />
                </label>
                <label class="block lg:col-span-1">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.intelligence.cost_amount') }}</span>
                    <input v-model="costForm.amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" required />
                </label>
                <label class="block lg:col-span-1">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.intelligence.cost_category') }}</span>
                    <select v-model="costForm.category" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                        <option v-for="cat in costCategories" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                    </select>
                </label>
                <label class="block lg:col-span-1">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.intelligence.cost_building') }}</span>
                    <select v-model="costForm.building_id" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                        <option value="">{{ t('water.intelligence.cost_all_buildings') }}</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </label>
                <label class="block lg:col-span-1">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.intelligence.cost_note') }}</span>
                    <input v-model="costForm.note" type="text" maxlength="255" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                </label>
                <div class="flex items-end lg:col-span-1">
                    <button type="submit" :disabled="costForm.processing" class="w-full rounded-md bg-cyan-600 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{{ t('water.intelligence.cost_add') }}</button>
                </div>
            </form>
            <p v-if="costForm.errors.amount || costForm.errors.cost_date || costForm.errors.building_id || costForm.errors.category" class="mt-2 text-xs text-red-600">
                {{ costForm.errors.amount || costForm.errors.cost_date || costForm.errors.building_id || costForm.errors.category }}
            </p>

            <!-- Recent cost entries -->
            <div v-if="i.recent_costs.length" class="mt-4">
                <ul class="divide-y divide-gray-100">
                    <li v-for="cost in i.recent_costs" :key="cost.id" class="flex items-center justify-between py-2 text-sm">
                        <span class="text-gray-700">
                            {{ formatDate(cost.date) }} · {{ categoryLabel(cost.category) }}
                            <span v-if="cost.building" class="text-gray-400">· {{ cost.building }}</span>
                            <span v-if="cost.note" class="text-gray-400">· {{ cost.note }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold text-gray-900">{{ formatMoney(cost.amount) }}</span>
                            <button class="text-gray-300 hover:text-red-600" :title="t('water.intelligence.cost_delete')" @click="deleteCost(cost.id)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
