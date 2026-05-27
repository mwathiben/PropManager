<script setup lang="ts">
import { computed } from 'vue';
import FunnelIcon from '@heroicons/vue/24/outline/FunnelIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import type { UnitFiltersProps } from '@/types';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = withDefaults(defineProps<UnitFiltersProps>(), {
    floor: '',
    unitType: '',
    status: '',
    availableFloors: () => [],
    availableUnitTypes: () => ['residential', 'commercial'],
});

const emit = defineEmits(['update:floor', 'update:unitType', 'update:status', 'change', 'clear']);

const statusOptions = computed(() => [
    { value: '', label: t('unit_filters.status.all', 'All Status') },
    { value: 'occupied', label: t('unit_filters.status.occupied', 'Occupied'), color: 'bg-green-500' },
    { value: 'vacant', label: t('unit_filters.status.vacant', 'Vacant'), color: 'bg-gray-400' },
    { value: 'arrears', label: t('unit_filters.status.arrears', 'In Arrears'), color: 'bg-red-500' },
    { value: 'maintenance', label: t('unit_filters.status.maintenance', 'Maintenance'), color: 'bg-orange-500' },
]);

const hasActiveFilters = computed(() => {
    return props.floor || props.unitType || props.status;
});

const unitTypeLabel = (type: string) => t(`unit_filters.types.${type}`, type ? type.charAt(0).toUpperCase() + type.slice(1) : '');

const updateFilter = (key, value) => {
    emit(`update:${key}`, value);
    emit('change');
};

const clearAll = () => {
    emit('update:floor', '');
    emit('update:unitType', '');
    emit('update:status', '');
    emit('clear');
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <!-- Filter Icon -->
        <div class="flex items-center text-gray-500">
            <FunnelIcon class="w-5 h-5" />
        </div>

        <!-- Floor Filter -->
        <select
            :value="floor"
            @change="updateFilter('floor', $event.target.value)"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white min-w-[120px]"
        >
            <option value="">{{ t('unit_filters.all_floors', 'All Floors') }}</option>
            <option v-for="f in availableFloors" :key="f" :value="f">
                {{ t('unit_filters.floor_option', 'Floor {floor}', { floor: f }) }}
            </option>
        </select>

        <!-- Unit Type Filter -->
        <select
            :value="unitType"
            @change="updateFilter('unitType', $event.target.value)"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white min-w-[140px]"
        >
            <option value="">{{ t('unit_filters.all_types', 'All Types') }}</option>
            <option v-for="type in availableUnitTypes" :key="type" :value="type">
                {{ unitTypeLabel(type) }}
            </option>
        </select>

        <!-- Status Filter (Chips) -->
        <div class="flex items-center gap-2">
            <button
                v-for="opt in statusOptions"
                :key="opt.value"
                @click="updateFilter('status', status === opt.value ? '' : opt.value)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all"
                :class="status === opt.value
                    ? 'bg-indigo-50 text-indigo-700 border-indigo-200' /* i18n-ignore */
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' /* i18n-ignore */"
            >
                <span
                    v-if="opt.color"
                    class="w-2 h-2 rounded-full"
                    :class="opt.color"
                ></span>
                {{ opt.label }}
            </button>
        </div>

        <!-- Clear Filters -->
        <button
            v-if="hasActiveFilters"
            @click="clearAll"
            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"
        >
            <XMarkIcon class="w-4 h-4" />
            {{ t('unit_filters.clear', 'Clear') }}
        </button>
    </div>
</template>
