<template>
    <div class="flex flex-wrap gap-2 items-center">
        <!-- Building Dropdown -->
        <div class="relative">
            <select
                :value="buildingId"
                @change="onBuildingChange($event.target.value)"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                :aria-label="buildingPlaceholder"
            >
                <option value="">{{ buildingPlaceholder }}</option>
                <option v-for="building in buildings" :key="building.id" :value="building.id">
                    {{ building.name }}
                </option>
            </select>
        </div>

        <!-- Wing Dropdown (shows when building has wings) -->
        <div v-if="selectedBuildingWings.length > 0" class="relative">
            <select
                :value="wingId"
                @change="$emit('update:wingId', $event.target.value || null)"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                :aria-label="wingPlaceholder"
            >
                <option value="">{{ wingPlaceholder }}</option>
                <option v-for="wing in selectedBuildingWings" :key="wing.id" :value="wing.id">
                    {{ wing.name }}
                </option>
            </select>
        </div>

        <!-- Active Filter Badge -->
        <div v-if="activeFilterLabel && showBadge" class="flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs">
            <span>{{ activeFilterLabel }}</span>
            <button @click="clearFilters" class="hover:text-indigo-900">
                <XMarkIcon class="w-3 h-3" />
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import XMarkIcon from '@heroicons/vue/20/solid/XMarkIcon';
import type { BuildingWingFilterProps } from '@/types';

const props = withDefaults(defineProps<BuildingWingFilterProps>(), {
    buildings: () => [],
    buildingId: null,
    wingId: null,
    buildingPlaceholder: 'All Buildings',
    wingPlaceholder: 'All Wings',
    showBadge: true,
});

const emit = defineEmits(['update:buildingId', 'update:wingId', 'change']);

const selectedBuilding = computed(() =>
    props.buildings.find(b => b.id == props.buildingId)
);

const selectedBuildingWings = computed(() =>
    selectedBuilding.value?.wings || []
);

const activeFilterLabel = computed(() => {
    if (props.wingId) {
        const wing = selectedBuildingWings.value.find(w => w.id == props.wingId);
        return wing?.name || 'Wing';
    }
    if (props.buildingId) {
        return selectedBuilding.value?.name || 'Building';
    }
    return null;
});

function onBuildingChange(value) {
    emit('update:buildingId', value || null);
    emit('update:wingId', null); // Reset wing when building changes
    emit('change', { buildingId: value || null, wingId: null });
}

function clearFilters() {
    emit('update:buildingId', null);
    emit('update:wingId', null);
    emit('change', { buildingId: null, wingId: null });
}
</script>
