<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    propertyId: [Number, String],
    buildings: {
        type: Array,
        default: () => []
    },
    defaultBuildingId: {
        type: [Number, String],
        default: null
    }
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    unit_prefix: '',
    floors: 5,
    units_per_floor: 4,
    parent_building_id: props.defaultBuildingId || null,
    is_wing: true
});

// Update parent_building_id when defaultBuildingId changes
watch(() => props.defaultBuildingId, (newValue) => {
    if (newValue) {
        form.parent_building_id = newValue;
    }
});

// Auto-generate prefix from wing name
watch(() => form.name, (newName) => {
    if (newName && form.parent_building_id) {
        // Extract first letter or first letters of each word
        const words = newName.trim().split(/\s+/);
        if (words.length === 1) {
            form.unit_prefix = newName.charAt(0).toUpperCase();
        } else {
            // Use initials of each word (e.g., "Block A" -> "BA", but cap at 3)
            form.unit_prefix = words.map(w => w.charAt(0).toUpperCase()).join('').slice(0, 3);
        }
    }
});

// Filter to only show main buildings (not wings) for the parent selector
const mainBuildings = computed(() => {
    return props.buildings?.filter(b => !b.is_wing && !b.parent_building_id) || [];
});

// Mode: if there are buildings to choose from, show building selector
const hasBuildings = computed(() => mainBuildings.value.length > 0);

// Get selected building name for display
const selectedBuildingName = computed(() => {
    const building = mainBuildings.value.find(b => b.id === form.parent_building_id);
    return building?.name || 'Select a building';
});

// Preview unit naming
const unitPreview = computed(() => {
    if (!form.parent_building_id || !form.unit_prefix) return '';
    const prefix = form.unit_prefix.toUpperCase();
    const samples = [];
    // Show first floor samples
    for (let u = 1; u <= Math.min(form.units_per_floor, 3); u++) {
        samples.push(`${prefix}10${u}`);
    }
    if (form.units_per_floor > 3) samples.push('...');
    // Show second floor sample if multiple floors
    if (form.floors > 1) {
        samples.push(`${prefix}201`);
        if (form.floors > 2) samples.push('...');
    }
    return samples.join(', ');
});

// Get existing prefixes for this building
const existingPrefixes = computed(() => {
    if (!form.parent_building_id) return [];
    const parentBuilding = mainBuildings.value.find(b => b.id === form.parent_building_id);
    if (!parentBuilding) return [];
    // Get prefixes from sibling wings
    return props.buildings
        ?.filter(b => b.parent_building_id === form.parent_building_id && b.unit_prefix)
        .map(b => b.unit_prefix.toUpperCase()) || [];
});

// Check if current prefix is a duplicate
const isPrefixDuplicate = computed(() => {
    if (!form.unit_prefix) return false;
    return existingPrefixes.value.includes(form.unit_prefix.toUpperCase());
});

const submit = () => {
    // If adding as a wing to an existing building
    if (form.parent_building_id) {
        form.post(route('buildings.store-wing', form.parent_building_id), {
            onSuccess: () => {
                emit('close');
                form.reset();
            }
        });
    } else {
        // Adding as a new standalone building
        form.is_wing = false;
        form.post(route('buildings.store', props.propertyId), {
            onSuccess: () => {
                emit('close');
                form.reset();
            }
        });
    }
};

const close = () => {
    emit('close');
    form.reset();
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity backdrop-blur-sm" @click="close"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        {{ form.parent_building_id ? 'Add Wing to Building' : 'Add New Building / Block' }}
                    </h3>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Building Selector (for adding wings) -->
                        <div v-if="hasBuildings">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Add to Building</label>
                            <select
                                v-model="form.parent_building_id"
                                class="block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option :value="null">-- Create as New Building --</option>
                                <option v-for="building in mainBuildings" :key="building.id" :value="building.id">
                                    {{ building.name }} (add wing)
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ form.parent_building_id
                                    ? 'This wing will be added to the selected building'
                                    : 'This will create a new standalone building' }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                {{ form.parent_building_id ? 'Wing Name' : 'Building Name' }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :placeholder="form.parent_building_id ? 'e.g. Wing A, Block B' : 'e.g. Main Building'"
                                required
                            >
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <!-- Unit Prefix (only for wings) -->
                        <div v-if="form.parent_building_id">
                            <label class="block text-sm font-medium text-gray-700">Unit Prefix</label>
                            <div class="mt-1 flex items-center gap-3">
                                <input
                                    v-model="form.unit_prefix"
                                    type="text"
                                    maxlength="3"
                                    class="block w-24 border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm uppercase"
                                    :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': isPrefixDuplicate }"
                                    placeholder="A"
                                    required
                                >
                                <span class="text-sm text-gray-500">1-3 characters</span>
                            </div>
                            <p v-if="form.errors.unit_prefix" class="mt-1 text-sm text-red-600">{{ form.errors.unit_prefix }}</p>
                            <p v-else-if="isPrefixDuplicate" class="mt-1 text-sm text-red-600">This prefix is already in use by another wing.</p>
                            <p v-else-if="unitPreview" class="mt-1 text-xs text-gray-500">
                                Units will be named: {{ unitPreview }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Floors</label>
                                <input
                                    v-model="form.floors"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    required
                                >
                                <p v-if="form.errors.floors" class="mt-1 text-sm text-red-600">{{ form.errors.floors }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Units/Floor</label>
                                <input
                                    v-model="form.units_per_floor"
                                    type="number"
                                    min="1"
                                    max="50"
                                    class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    required
                                >
                                <p v-if="form.errors.units_per_floor" class="mt-1 text-sm text-red-600">{{ form.errors.units_per_floor }}</p>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                            <span class="font-medium">This will create:</span>
                            {{ form.floors * form.units_per_floor }} units ({{ form.floors }} floors x {{ form.units_per_floor }} units/floor)
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                @click="close"
                                type="button"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm shadow-sm"
                                :disabled="form.processing || (form.parent_building_id && isPrefixDuplicate)"
                            >
                                {{ form.processing ? 'Creating...' : (form.parent_building_id ? 'Add Wing' : 'Create Building') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
