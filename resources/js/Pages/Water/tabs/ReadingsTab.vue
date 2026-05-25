<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/EmptyState.vue';
import { BeakerIcon, PlusIcon } from '@heroicons/vue/24/outline';

interface UnitRow {
    id: number;
    unit_number: string;
    last_reading: { current_reading: number; reading_date: string } | null;
}
interface BuildingRow {
    id: number;
    name: string;
    units: UnitRow[];
}

defineProps<{
    buildingsData?: BuildingRow[];
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-semibold text-gray-900">{{ t('water_readings.title') }}</h3>
                <p class="text-sm text-gray-500">{{ t('water_readings.subtitle') }}</p>
            </div>
            <Link
                :href="route('readings.index')"
                class="inline-flex items-center gap-1 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium"
            >
                <PlusIcon class="w-5 h-5" />
                {{ t('water_readings.record') }}
            </Link>
        </div>

        <div v-if="buildingsData?.length" class="space-y-6">
            <div v-for="building in buildingsData" :key="building.id" class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-900">{{ building.name }}</h4>
                </div>
                <table v-if="building.units?.length" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_readings.table.unit') }}</th>
                            <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_readings.table.last_reading') }}</th>
                            <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_readings.table.date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="unit in building.units" :key="unit.id" class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ t('water_readings.unit_prefix', { number: unit.unit_number }) }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900 text-end">
                                {{ unit.last_reading ? unit.last_reading.current_reading : '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-500 text-end">
                                {{ unit.last_reading ? formatDate(unit.last_reading.reading_date) : t('water_readings.no_reading_yet') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-6 py-4 text-sm text-gray-500">{{ t('water_readings.no_metered_units_in_building') }}</p>
            </div>
        </div>

        <EmptyState
            v-else
            :icon="BeakerIcon"
            :title="t('water_readings.empty.title')"
            :description="t('water_readings.empty.description')"
        />
    </div>
</template>
