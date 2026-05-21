<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

interface PropertyRow {
    property_id: number;
    name: string;
    building_count: number;
    unit_count: number;
    occupied_count: number;
    vacant_count: number;
    occupancy_pct: number;
    monthly_rent_roll: number;
    outstanding_arrears: number;
}

defineProps<{ properties: PropertyRow[] }>();

const { t } = useI18n();

const money = (v: number) => v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('property.index.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <BuildingOffice2Icon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('property.index.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('property.index.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-3" data-testid="properties-index">
            <p v-if="properties.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('property.index.empty') }}
            </p>

            <Link
                v-for="p in properties"
                :key="p.property_id"
                :href="route('properties.show', p.property_id)"
                class="block rounded-lg bg-white p-5 shadow hover:ring-2 hover:ring-indigo-200"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">{{ p.name }}</p>
                        <p class="text-xs text-gray-500">{{ p.building_count }} {{ t('property.index.buildings') }} · {{ p.unit_count }} {{ t('property.index.units') }}</p>
                    </div>
                    <div class="flex items-center gap-6 text-end text-sm">
                        <div>
                            <p class="text-xs text-gray-400">{{ t('property.index.occupancy') }}</p>
                            <p class="font-semibold" :class="p.occupancy_pct < 70 ? 'text-rose-600' : 'text-emerald-700'">{{ p.occupancy_pct }}%</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">{{ t('property.index.rent_roll') }}</p>
                            <p class="font-semibold text-gray-900">{{ money(p.monthly_rent_roll) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">{{ t('property.index.arrears') }}</p>
                            <p class="font-semibold" :class="p.outstanding_arrears > 0 ? 'text-rose-600' : 'text-gray-500'">{{ money(p.outstanding_arrears) }}</p>
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
