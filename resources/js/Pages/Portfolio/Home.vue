<script setup lang="ts">
/**
 * Phase-105 PORTFOLIO-HOME: the landlord's landing — a cross-property overview. Portfolio
 * KPIs + an at-a-glance "needs attention" row + per-property cards that drill into a
 * building dashboard. The rich building-scoped dashboard lives at /dashboard?building_id=…
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import {
    BuildingOffice2Icon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    HomeModernIcon,
    DocumentTextIcon,
    TicketIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';

interface Kpis {
    property_count: number;
    building_count: number;
    unit_count: number;
    occupied_count: number;
    vacant_count: number;
    occupancy_pct: number;
    monthly_rent_roll: number;
    outstanding_arrears: number;
}
interface Actions {
    overdue_invoices: number;
    overdue_amount: number;
    open_tickets: number;
    expiring_leases: number;
}
interface PropertyRow {
    property_id: number;
    name: string;
    building_count: number;
    unit_count: number;
    occupied_count: number;
    occupancy_pct: number;
    monthly_rent_roll: number;
    outstanding_arrears: number;
    primary_building_id: number | null;
}

const props = withDefaults(defineProps<{ kpis?: Kpis; actions?: Actions; properties?: PropertyRow[] }>(), {
    kpis: () => ({ property_count: 0, building_count: 0, unit_count: 0, occupied_count: 0, vacant_count: 0, occupancy_pct: 0, monthly_rent_roll: 0, outstanding_arrears: 0 }),
    actions: () => ({ overdue_invoices: 0, overdue_amount: 0, open_tickets: 0, expiring_leases: 0 }),
    properties: () => [],
});

const { t } = useI18n();
const { formatMoney } = useFormatters();

// Drill from a property card into its building dashboard (one click for the primary
// building); fall back to the property detail when we don't have a building yet.
const drillHref = (p: PropertyRow): string =>
    p.primary_building_id
        ? route('dashboard', { property_id: p.property_id, building_id: p.primary_building_id })
        : route('properties.show', p.property_id);

const hasActions = (a: Actions): boolean => a.overdue_invoices > 0 || a.open_tickets > 0 || a.expiring_leases > 0;
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('portfolio.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-100 p-2"><BuildingOffice2Icon class="h-6 w-6 text-indigo-600" /></div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('portfolio.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('portfolio.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-testid="portfolio-home">
            <!-- Portfolio KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    :title="t('portfolio.kpi.occupancy')"
                    :value="kpis.occupancy_pct"
                    format="percent"
                    :subtitle="t('portfolio.kpi.units_subtitle', { occupied: kpis.occupied_count, total: kpis.unit_count })"
                    :icon="HomeModernIcon"
                    :color="kpis.occupancy_pct < 70 ? 'red' : 'emerald'"
                />
                <MetricCard
                    :title="t('portfolio.kpi.rent_roll')"
                    :value="kpis.monthly_rent_roll"
                    format="currency"
                    :icon="BanknotesIcon"
                    color="blue"
                />
                <MetricCard
                    :title="t('portfolio.kpi.arrears')"
                    :value="kpis.outstanding_arrears"
                    format="currency"
                    :icon="ExclamationTriangleIcon"
                    :color="kpis.outstanding_arrears > 0 ? 'red' : 'gray'"
                />
                <MetricCard
                    :title="t('portfolio.kpi.properties')"
                    :value="kpis.property_count"
                    format="number"
                    :subtitle="t('portfolio.kpi.properties_subtitle', { buildings: kpis.building_count })"
                    :icon="BuildingOffice2Icon"
                    color="indigo"
                />
            </div>

            <!-- Needs attention -->
            <div v-if="hasActions(actions)" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('portfolio.actions.title') }}</h2>
                <div class="flex flex-wrap gap-3">
                    <Link
                        v-if="actions.overdue_invoices > 0"
                        :href="route('finances.index')"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                    >
                        <DocumentTextIcon class="h-4 w-4" />
                        {{ actions.overdue_invoices }} {{ t('portfolio.actions.overdue_invoices') }} · {{ formatMoney(actions.overdue_amount) }}
                    </Link>
                    <Link
                        v-if="actions.open_tickets > 0"
                        :href="route('tickets.index')"
                        class="inline-flex items-center gap-2 rounded-lg bg-yellow-50 px-3 py-2 text-sm font-medium text-yellow-700 hover:bg-yellow-100"
                    >
                        <TicketIcon class="h-4 w-4" />
                        {{ actions.open_tickets }} {{ t('portfolio.actions.open_tickets') }}
                    </Link>
                    <span
                        v-if="actions.expiring_leases > 0"
                        class="inline-flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2 text-sm font-medium text-orange-700"
                    >
                        <CalendarDaysIcon class="h-4 w-4" />
                        {{ actions.expiring_leases }} {{ t('portfolio.actions.expiring_leases') }}
                    </span>
                </div>
            </div>

            <!-- Per-property cards -->
            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('portfolio.properties_heading') }}</h2>

                <p v-if="!properties.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                    {{ t('portfolio.none') }}
                </p>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="p in properties"
                        :key="p.property_id"
                        :href="drillHref(p)"
                        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-all hover:border-gray-300 hover:shadow-md"
                        :data-testid="`portfolio-property-${p.property_id}`"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-gray-900">{{ p.name }}</h3>
                            <span class="text-xs text-indigo-600">{{ t('portfolio.card.open') }} →</span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">{{ t('portfolio.card.units', { occupied: p.occupied_count, total: p.unit_count }) }}</p>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div>
                                <p class="text-xs uppercase text-gray-400">{{ t('portfolio.card.occupancy') }}</p>
                                <p class="mt-0.5 text-sm font-semibold" :class="p.occupancy_pct < 70 ? 'text-rose-600' : 'text-emerald-700'">{{ p.occupancy_pct }}%</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-gray-400">{{ t('portfolio.card.rent_roll') }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-gray-900">{{ formatMoney(p.monthly_rent_roll) }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-gray-400">{{ t('portfolio.card.arrears') }}</p>
                                <p class="mt-0.5 text-sm font-semibold" :class="p.outstanding_arrears > 0 ? 'text-rose-600' : 'text-gray-500'">{{ formatMoney(p.outstanding_arrears) }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
