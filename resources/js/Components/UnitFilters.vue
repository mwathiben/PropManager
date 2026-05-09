<script setup lang="ts">
import { computed } from 'vue';
import FunnelIcon from '@heroicons/vue/24/outline/FunnelIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import type { UnitFiltersProps } from '@/types';

const props = withDefaults(defineProps<UnitFiltersProps>(), {
    floor: '',
    unitType: '',
    status: '',
    availableFloors: () => [],
    availableUnitTypes: () => ['residential', 'commercial'],
});

const emit = defineEmits(['update:floor', 'update:unitType', 'update:status', 'change', 'clear']);

const statusOptions = [
    { value: '', label: 'All Status' },
    { value: 'occupied', label: 'Occupied', color: 'bg-green-500' },
    { value: 'vacant', label: 'Vacant', color: 'bg-gray-400' },
    { value: 'arrears', label: 'In Arrears', color: 'bg-red-500' },
    { value: 'maintenance', label: 'Maintenance', color: 'bg-orange-500' },
];

const hasActiveFilters = computed(() => {
    return props.floor || props.unitType || props.status;
});

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
            <option value="">All Floors</option>
            <option v-for="f in availableFloors" :key="f" :value="f">
                Floor {{ f }}
            </option>
        </select>

        <!-- Unit Type Filter -->
        <select
            :value="unitType"
            @change="updateFilter('unitType', $event.target.value)"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white min-w-[140px]"
        >
            <option value="">All Types</option>
            <option v-for="type in availableUnitTypes" :key="type" :value="type">
                {{ type.charAt(0).toUpperCase() + type.slice(1) }}
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
                    ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
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
            Clear
        </button>
    </div>
</template>
