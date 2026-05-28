<script setup lang="ts">
/**
 * Canonical water-settings editor — the single source of truth for water billing
 * config (global PaymentConfiguration defaults + per-building overrides, which
 * WaterRateService / WaterTariffService bill from). Rendered identically by both
 * the standalone /water/settings page AND the Water hub's Settings tab so the two
 * surfaces can never drift. Posts to water.settings.update.
 */
import { onMounted, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useCurrency } from '@/composables';
import { Cog6ToothIcon, BeakerIcon, HomeModernIcon, CheckIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

interface Band { from: number | string; to: number | string | null; rate: number | string }

interface BuildingRow {
    id: number;
    name: string;
    units_count?: number;
    water_billing_type?: string | null;
    water_unit_rate?: number | string | null;
    water_flat_rate?: number | string | null;
    water_standing_charge?: number | string | null;
    water_minimum_charge?: number | string | null;
    water_sewerage_percent?: number | string | null;
    water_vat_percent?: number | string | null;
    water_source?: string | null;
    water_reading_day?: number | string | null;
    water_review_days?: number | string | null;
    water_reconnection_fee?: number | string | null;
}

interface GlobalSettings {
    water_billing_type?: string;
    water_unit_rate?: number | string;
    flat_water_rate?: number | string;
    tiered_tariffs?: Band[];
    water_standing_charge?: number | string | null;
    water_minimum_charge?: number | string | null;
    water_sewerage_percent?: number | string | null;
    water_vat_percent?: number | string | null;
    water_source?: string | null;
    water_reading_day?: number | string | null;
    water_review_days?: number | string | null;
    water_reconnection_fee?: number | string | null;
}

const props = withDefaults(defineProps<{
    buildings: BuildingRow[];
    globalSettings: GlobalSettings;
    highlightBuildingId?: number | null;
}>(), {
    highlightBuildingId: null,
});

const { currencyCode } = useCurrency();

const form = useForm({
    water_billing_type: props.globalSettings.water_billing_type || 'consumption',
    water_unit_rate: props.globalSettings.water_unit_rate || '',
    flat_water_rate: props.globalSettings.flat_water_rate || 0,
    tiered_tariffs: (props.globalSettings.tiered_tariffs || []).map((b) => ({ from: b.from, to: b.to ?? '', rate: b.rate })),
    water_standing_charge: props.globalSettings.water_standing_charge ?? '',
    water_minimum_charge: props.globalSettings.water_minimum_charge ?? '',
    water_sewerage_percent: props.globalSettings.water_sewerage_percent ?? '',
    water_vat_percent: props.globalSettings.water_vat_percent ?? '',
    water_source: props.globalSettings.water_source ?? '',
    water_reading_day: props.globalSettings.water_reading_day ?? '',
    water_review_days: props.globalSettings.water_review_days ?? '',
    water_reconnection_fee: props.globalSettings.water_reconnection_fee ?? '',
    building_overrides: props.buildings.map((b) => ({
        id: b.id,
        water_billing_type: b.water_billing_type || 'inherit',
        water_unit_rate: b.water_unit_rate || '',
        water_flat_rate: b.water_flat_rate || '',
        water_standing_charge: b.water_standing_charge ?? '',
        water_minimum_charge: b.water_minimum_charge ?? '',
        water_sewerage_percent: b.water_sewerage_percent ?? '',
        water_vat_percent: b.water_vat_percent ?? '',
        water_source: b.water_source ?? '',
        water_reading_day: b.water_reading_day ?? '',
        water_review_days: b.water_review_days ?? '',
        water_reconnection_fee: b.water_reconnection_fee ?? '',
    })),
});

const submit = () => {
    form.put(route('water.settings.update'), { preserveScroll: true });
};

const getBuildingOverrideIndex = (buildingId: number) =>
    form.building_overrides.findIndex((b) => b.id === buildingId);

const addBand = () => form.tiered_tariffs.push({ from: 0, to: '', rate: 0 });
const removeBand = (i: number) => form.tiered_tariffs.splice(i, 1);

const sourceOptions = ['borehole', 'county', 'mixed'];

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
                <h2 class="ms-3 text-lg font-semibold text-gray-900">{{ $t('water_settings_form.global_title') }}</h2>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ $t('water_settings_form.global_hint') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('water_settings_form.billing_method') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label
                            v-for="opt in [
                                { value: 'consumption', title: $t('water_settings_form.type_consumption'), desc: $t('water_settings_form.type_consumption_hint') },
                                { value: 'flat_rate', title: $t('water_settings_form.type_flat'), desc: $t('water_settings_form.type_flat_hint') },
                                { value: 'none', title: $t('water_settings_form.type_none'), desc: $t('water_settings_form.type_none_hint') },
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water_settings_form.rate_per_unit') }} ({{ currencyCode }})</label>
                    <input v-model="form.water_unit_rate" type="number" min="0" step="0.01" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="150" />
                    <p class="mt-1 text-xs text-gray-500">{{ $t('water_settings_form.rate_per_unit_hint') }}</p>
                    <p v-if="form.errors.water_unit_rate" class="mt-1 text-sm text-red-600">{{ form.errors.water_unit_rate }}</p>
                </div>

                <div v-if="form.water_billing_type === 'flat_rate'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water_settings_form.flat_rate') }} ({{ currencyCode }})</label>
                    <input v-model="form.flat_water_rate" type="number" min="0" step="0.01" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="500" />
                    <p class="mt-1 text-xs text-gray-500">{{ $t('water_settings_form.flat_rate_hint') }}</p>
                    <p v-if="form.errors.flat_water_rate" class="mt-1 text-sm text-red-600">{{ form.errors.flat_water_rate }}</p>
                </div>
            </div>

            <!-- Phase-87: tiered bands (consumption only) -->
            <div v-if="form.water_billing_type === 'consumption'" class="mt-6 border-t border-gray-100 pt-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">{{ $t('water_settings_form.tiers_title') }}</label>
                    <button type="button" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800" @click="addBand">
                        <PlusIcon class="w-4 h-4" /> {{ $t('water_settings_form.tiers_add') }}
                    </button>
                </div>
                <p class="text-xs text-gray-500 mb-3">{{ $t('water_settings_form.tiers_hint') }}</p>
                <div v-for="(band, i) in form.tiered_tariffs" :key="i" class="flex flex-wrap items-end gap-3 mb-2">
                    <label class="block text-xs">
                        <span class="text-gray-600">{{ $t('water_settings_form.tier_from') }}</span>
                        <input v-model="band.from" type="number" min="0" step="0.01" class="mt-1 w-28 border-gray-300 rounded-md text-sm" />
                    </label>
                    <label class="block text-xs">
                        <span class="text-gray-600">{{ $t('water_settings_form.tier_to') }}</span>
                        <input v-model="band.to" type="number" min="0" step="0.01" class="mt-1 w-28 border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.tier_open')" />
                    </label>
                    <label class="block text-xs">
                        <span class="text-gray-600">{{ $t('water_settings_form.tier_rate') }} ({{ currencyCode }})</span>
                        <input v-model="band.rate" type="number" min="0" step="0.01" class="mt-1 w-28 border-gray-300 rounded-md text-sm" />
                    </label>
                    <button type="button" class="p-2 text-red-500 hover:text-red-700" @click="removeBand(i)"><TrashIcon class="w-4 h-4" /></button>
                </div>
            </div>

            <!-- Phase-87: levies + source (all billing types except none) -->
            <div v-if="form.water_billing_type !== 'none'" class="mt-6 border-t border-gray-100 pt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.standing_charge') }} ({{ currencyCode }})</span>
                    <input v-model="form.water_standing_charge" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.minimum_charge') }} ({{ currencyCode }})</span>
                    <input v-model="form.water_minimum_charge" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.sewerage_percent') }} (%)</span>
                    <input v-model="form.water_sewerage_percent" type="number" min="0" max="100" step="0.01" class="mt-1 w-full border-gray-300 rounded-md" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.vat_percent') }} (%)</span>
                    <input v-model="form.water_vat_percent" type="number" min="0" max="100" step="0.01" class="mt-1 w-full border-gray-300 rounded-md" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.water_source') }}</span>
                    <select v-model="form.water_source" class="mt-1 w-full border-gray-300 rounded-md">
                        <option value="">{{ $t('water_settings_form.source_unset') }}</option>
                        <option v-for="s in sourceOptions" :key="s" :value="s">{{ $t(`water_settings_form.source_${s}`, s ?? '') }}</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.reading_day') }}</span>
                    <input v-model="form.water_reading_day" type="number" min="1" max="28" step="1" class="mt-1 w-full border-gray-300 rounded-md" :placeholder="$t('water_settings_form.reading_day_hint')" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.review_days') }}</span>
                    <input v-model="form.water_review_days" type="number" min="1" max="31" step="1" class="mt-1 w-full border-gray-300 rounded-md" :placeholder="$t('water_settings_form.review_days_hint')" />
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700">{{ $t('water_settings_form.reconnection_fee') }} ({{ currencyCode }})</span>
                    <input v-model="form.water_reconnection_fee" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md" />
                </label>
            </div>
        </div>

        <!-- Per-Building Overrides -->
        <div v-if="buildings.length > 0" class="bg-white shadow-sm rounded-lg p-6 mb-6 border border-gray-200">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-green-100 rounded-lg">
                    <HomeModernIcon class="w-6 h-6 text-green-600" />
                </div>
                <h2 class="ms-3 text-lg font-semibold text-gray-900">{{ $t('water_settings_form.building_title') }}</h2>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ $t('water_settings_form.building_hint') }}</p>

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
                            <p class="text-sm text-gray-500">{{ building.units_count }} {{ $t('water_settings_form.units') }}</p>
                        </div>
                        <select
                            v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type"
                            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                        >
                            <option value="inherit">{{ $t('water_settings_form.type_inherit') }}</option>
                            <option value="consumption">{{ $t('water_settings_form.type_consumption') }}</option>
                            <option value="flat_rate">{{ $t('water_settings_form.type_flat') }}</option>
                            <option value="none">{{ $t('water_settings_form.type_none') }}</option>
                        </select>
                    </div>

                    <div
                        v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'consumption'"
                        class="mt-3 ps-4 border-s-2 border-indigo-200"
                    >
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water_settings_form.rate_per_unit') }} ({{ currencyCode }})</label>
                        <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_unit_rate"
                            type="number" min="0" step="0.01"
                            class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            :placeholder="String(form.water_unit_rate || '150')" />
                    </div>

                    <div
                        v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'flat_rate'"
                        class="mt-3 ps-4 border-s-2 border-indigo-200"
                    >
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('water_settings_form.flat_rate') }} ({{ currencyCode }})</label>
                        <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_flat_rate"
                            type="number" min="0" step="0.01"
                            class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            :placeholder="String(form.flat_water_rate || '500')" />
                    </div>

                    <!-- Phase-87: per-building levy + source overrides (blank = inherit global) -->
                    <div
                        v-if="['consumption', 'flat_rate'].includes(form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type)"
                        class="mt-3 ps-4 border-s-2 border-indigo-200 grid grid-cols-2 md:grid-cols-3 gap-3"
                    >
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.standing_charge') }}</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_standing_charge" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.minimum_charge') }}</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_minimum_charge" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.sewerage_percent') }} (%)</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_sewerage_percent" type="number" min="0" max="100" step="0.01" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.vat_percent') }} (%)</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_vat_percent" type="number" min="0" max="100" step="0.01" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.water_source') }}</span>
                            <select v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_source" class="mt-1 w-full border-gray-300 rounded-md text-sm">
                                <option value="">{{ $t('water_settings_form.source_inherit') }}</option>
                                <option v-for="s in sourceOptions" :key="s" :value="s">{{ $t(`water_settings_form.source_${s}`, s ?? '') }}</option>
                            </select>
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.reading_day') }}</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_reading_day" type="number" min="1" max="28" step="1" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.review_days') }}</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_review_days" type="number" min="1" max="31" step="1" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                        <label class="block text-xs">
                            <span class="text-gray-600">{{ $t('water_settings_form.reconnection_fee') }}</span>
                            <input v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_reconnection_fee" type="number" min="0" step="0.01" class="mt-1 w-full border-gray-300 rounded-md text-sm" :placeholder="$t('water_settings_form.inherit_placeholder')" />
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-white shadow-sm rounded-lg mb-6 border border-gray-200">
            <EmptyState :icon="BeakerIcon" :title="$t('water_settings_form.no_buildings_title')" :description="$t('water_settings_form.no_buildings_hint')" size="sm" />
        </div>

        <div class="flex justify-end">
            <button type="submit" :disabled="form.processing"
                class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                {{ form.processing ? $t('water_settings_form.saving') : $t('water_settings_form.save') }}
            </button>
        </div>
    </form>
</template>
