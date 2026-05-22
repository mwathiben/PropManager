<script setup lang="ts">
/**
 * Canonical water-settings editor — the single source of truth for water billing
 * config (global PaymentConfiguration defaults + per-building overrides, which
 * WaterRateService actually bills from). Rendered identically by both the
 * standalone /water/settings page AND the Water hub's Settings tab so the two
 * surfaces can never drift. Posts to water.settings.update.
 */
import { onMounted, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useCurrency } from '@/composables';
import { Cog6ToothIcon, BeakerIcon, HomeModernIcon, CheckIcon } from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

interface BuildingRow {
    id: number;
    name: string;
    units_count?: number;
    water_billing_type?: string | null;
    water_unit_rate?: number | string | null;
    water_flat_rate?: number | string | null;
}

const props = withDefaults(defineProps<{
    buildings: BuildingRow[];
    globalSettings: { water_billing_type?: string; water_unit_rate?: number | string; flat_water_rate?: number | string };
    highlightBuildingId?: number | null;
}>(), {
    highlightBuildingId: null,
});

const { currencyCode } = useCurrency();

const form = useForm({
    water_billing_type: props.globalSettings.water_billing_type || 'consumption',
    water_unit_rate: props.globalSettings.water_unit_rate || '',
    flat_water_rate: props.globalSettings.flat_water_rate || 0,
    building_overrides: props.buildings.map((b) => ({
        id: b.id,
        water_billing_type: b.water_billing_type || 'inherit',
        water_unit_rate: b.water_unit_rate || '',
        water_flat_rate: b.water_flat_rate || '',
    })),
});

const submit = () => {
    form.put(route('water.settings.update'), { preserveScroll: true });
};

const getBuildingOverrideIndex = (buildingId: number) =>
    form.building_overrides.findIndex((b) => b.id === buildingId);

// Deep-link from a building page scrolls to + highlights that building's row.
onMounted(async () => {
    if (props.highlightBuildingId) {
        await nextTick();
        document.getElementById(`water-building-${props.highlightBuildingId}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<template>
    <form @submit.prevent="submit">
        <!-- Global Settings Card -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-6 border border-gray-200">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <Cog6ToothIcon class="w-6 h-6 text-blue-600" />
                </div>
                <h2 class="ms-3 text-lg font-semibold text-gray-900">{{ $t('water.settings.global_title') }}</h2>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ $t('water.settings.global_hint') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('water.settings.billing_method') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label
                            v-for="opt in [
                                { value: 'consumption', title: $t('water.settings.type_consumption'), desc: $t('water.settings.type_consumption_hint') },
                                { value: 'flat_rate', title: $t('water.settings.type_flat'), desc: $t('water.settings.type_flat_hint') },
                                { value: 'none', title: $t('water.settings.type_none'), desc: $t('water.settings.type_none_hint') },
                            ]"
                            :key="opt.value"
                            class="relative flex cursor-pointer rounded-lg border p-4"
                            :class="form.water_billing_type === opt.value ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'"
                        >
                            <input type="radio" v-model="form.water_billing_type" :value="opt.value" class="sr-only" />
                            <div class="flex flex-1">
                                <div class="flex flex-col">
                                    <span class="block text-sm font-medium text-gray-900">{{ opt.title }}</span>
                                    <span class="mt-1 flex items-center text-sm text-gray-500">{{ opt.desc }}</span>
                                </div>
                            </div>
                            <CheckIcon v-if="form.water_billing_type === opt.value" class="h-5 w-5 text-indigo-600" />
                        </label>
                    </div>
                </div>

                <div v-if="form.water_billing_type === 'consumption'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water.settings.rate_per_unit') }} ({{ currencyCode }})</label>
                    <input v-model="form.water_unit_rate" type="number" min="0" step="0.01"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="150" />
                    <p class="mt-1 text-xs text-gray-500">{{ $t('water.settings.rate_per_unit_hint') }}</p>
                    <p v-if="form.errors.water_unit_rate" class="mt-1 text-sm text-red-600">{{ form.errors.water_unit_rate }}</p>
                </div>

                <div v-if="form.water_billing_type === 'flat_rate'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water.settings.flat_rate') }} ({{ currencyCode }})</label>
                    <input v-model="form.flat_water_rate" type="number" min="0" step="0.01"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="500" />
                    <p class="mt-1 text-xs text-gray-500">{{ $t('water.settings.flat_rate_hint') }}</p>
                    <p v-if="form.errors.flat_water_rate" class="mt-1 text-sm text-red-600">{{ form.errors.flat_water_rate }}</p>
                </div>
            </div>
        </div>

        <!-- Per-Building Overrides -->
        <div v-if="buildings.length > 0" class="bg-white shadow-sm rounded-lg p-6 mb-6 border border-gray-200">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-green-100 rounded-lg">
                    <HomeModernIcon class="w-6 h-6 text-green-600" />
                </div>
                <h2 class="ms-3 text-lg font-semibold text-gray-900">{{ $t('water.settings.building_title') }}</h2>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ $t('water.settings.building_hint') }}</p>

            <div class="space-y-4">
                <div
                    v-for="building in buildings"
                    :key="building.id"
                    :id="`water-building-${building.id}`"
                    class="border rounded-lg p-4 transition-colors"
                    :class="highlightBuildingId === building.id ? 'border-indigo-400 ring-2 ring-indigo-200' : 'border-gray-200'"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ building.name }}</h3>
                            <p class="text-sm text-gray-500">{{ building.units_count }} {{ $t('water.settings.units') }}</p>
                        </div>
                        <select
                            v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type"
                            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                        >
                            <option value="inherit">{{ $t('water.settings.type_inherit') }}</option>
                            <option value="consumption">{{ $t('water.settings.type_consumption') }}</option>
                            <option value="flat_rate">{{ $t('water.settings.type_flat') }}</option>
                            <option value="none">{{ $t('water.settings.type_none') }}</option>
                        </select>
                    </div>

                    <div
                        v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'consumption'"
                        class="mt-3 ps-4 border-s-2 border-indigo-200"
                    >
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water.settings.rate_per_unit') }} ({{ currencyCode }})</label>
                        <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_unit_rate"
                            type="number" min="0" step="0.01"
                            class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            :placeholder="String(form.water_unit_rate || '150')" />
                    </div>

                    <div
                        v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'flat_rate'"
                        class="mt-3 ps-4 border-s-2 border-indigo-200"
                    >
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water.settings.flat_rate') }} ({{ currencyCode }})</label>
                        <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_flat_rate"
                            type="number" min="0" step="0.01"
                            class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            :placeholder="String(form.flat_water_rate || '500')" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-white shadow-sm rounded-lg mb-6 border border-gray-200">
            <EmptyState :icon="BeakerIcon" :title="$t('water.settings.no_buildings_title')" :description="$t('water.settings.no_buildings_hint')" size="sm" />
        </div>

        <div class="flex justify-end">
            <button type="submit" :disabled="form.processing"
                class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                {{ form.processing ? $t('water.settings.saving') : $t('water.settings.save') }}
            </button>
        </div>
    </form>
</template>
