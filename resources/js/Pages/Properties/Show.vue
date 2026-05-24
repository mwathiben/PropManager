<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { BuildingOffice2Icon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

interface Metrics {
    building_count: number;
    unit_count: number;
    occupied_count: number;
    vacant_count: number;
    occupancy_pct: number;
    monthly_rent_roll: number;
    outstanding_arrears: number;
}
interface BuildingRow {
    id: number;
    name: string;
    building_type: string | null;
    unit_count: number;
    occupied_count: number;
    occupancy_pct: number;
}

defineProps<{
    property: { id: number; name: string; type: string; address: string | null; estimated_value: string | null };
    metrics: Metrics;
    buildings: BuildingRow[];
    noi: { revenue: number; noi: number; noi_margin: number | null } | null;
}>();

const { t } = useI18n();
const money = (v: number) => v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="property.name" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <BuildingOffice2Icon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ property.name }}</h1>
                        <p class="text-sm text-gray-500">{{ property.address || t('property.show.no_address') }}</p>
                    </div>
                </div>
                <a
                    :href="route('finances.reports.owner-statement', { property_id: property.id, period: '12' })"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    data-testid="owner-statement-link"
                >
                    <DocumentArrowDownIcon class="h-4 w-4" />
                    {{ t('property.show.owner_statement') }}
                </a>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-5" data-testid="property-show">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow">
                    <p class="text-xs uppercase text-gray-400">{{ t('property.show.occupancy') }}</p>
                    <p class="mt-1 text-xl font-semibold" :class="metrics.occupancy_pct < 70 ? 'text-rose-600' : 'text-emerald-700'">{{ metrics.occupancy_pct }}%</p>
                    <p class="text-xs text-gray-500">{{ metrics.occupied_count }}/{{ metrics.unit_count }} {{ t('property.show.units') }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <p class="text-xs uppercase text-gray-400">{{ t('property.show.rent_roll') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ money(metrics.monthly_rent_roll) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <p class="text-xs uppercase text-gray-400">{{ t('property.show.arrears') }}</p>
                    <p class="mt-1 text-xl font-semibold" :class="metrics.outstanding_arrears > 0 ? 'text-rose-600' : 'text-gray-500'">{{ money(metrics.outstanding_arrears) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <p class="text-xs uppercase text-gray-400">{{ t('property.show.noi') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ noi ? money(noi.noi) : '—' }}</p>
                    <p v-if="noi && noi.noi_margin !== null" class="text-xs text-gray-500">{{ Math.round(noi.noi_margin * 100) }}% {{ t('property.show.margin') }}</p>
                </div>
            </div>

            <section class="rounded-lg bg-white p-5 shadow">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('property.show.buildings') }} ({{ metrics.building_count }})</h2>
                <p v-if="buildings.length === 0" class="text-sm text-gray-400">{{ t('property.show.no_buildings') }}</p>
                <ul v-else class="divide-y divide-gray-50">
                    <li v-for="b in buildings" :key="b.id">
                        <Link :href="route('buildings.show', b.id)" class="flex items-center justify-between py-2.5 hover:bg-gray-50">
                            <span class="text-sm font-medium text-gray-900">{{ b.name }}</span>
                            <span class="text-xs text-gray-500">{{ b.occupied_count }}/{{ b.unit_count }} · {{ b.occupancy_pct }}%</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
