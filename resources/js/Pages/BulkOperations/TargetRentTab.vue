<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    filteredUnits: {
        type: Array,
        default: () => []
    },
    selectedUnitIds: {
        type: Array,
        default: () => []
    },
    buildingId: {
        type: [Number, null],
        default: null
    },
    wingId: {
        type: [Number, null],
        default: null
    }
});

const emit = defineEmits(['update:selectedUnitIds', 'success']);

const form = useForm({
    unit_ids: [],
    adjustment_type: 'percentage',
    adjustment_value: 0,
    building_id: null,
    wing_id: null
});

const selectAllUnits = () => {
    emit('update:selectedUnitIds', props.filteredUnits.map(u => u.id));
};

const deselectAllUnits = () => {
    emit('update:selectedUnitIds', []);
};

const updateSelection = (unitId, checked) => {
    const newSelection = checked
        ? [...props.selectedUnitIds, unitId]
        : props.selectedUnitIds.filter(id => id !== unitId);
    emit('update:selectedUnitIds', newSelection);
};

const submit = () => {
    form.unit_ids = props.selectedUnitIds;
    form.building_id = props.buildingId;
    form.wing_id = props.wingId;
    if (form.unit_ids.length === 0) {
        alert('Please select at least one unit');
        return;
    }
    form.post(route('bulk.updateTargetRent'), {
        onSuccess: () => {
            form.reset();
            emit('success');
        }
    });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Selection Panel -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Select Units</h3>
                <div class="flex gap-2">
                    <button @click="selectAllUnits" class="text-sm text-indigo-600 hover:text-indigo-800">
                        Select All
                    </button>
                    <button @click="deselectAllUnits" class="text-sm text-gray-600 hover:text-gray-800">
                        Deselect All
                    </button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                <div v-if="filteredUnits.length === 0" class="p-4 text-center text-gray-500">
                    No units found
                </div>
                <div v-else>
                    <div
                        v-for="unit in filteredUnits"
                        :key="unit.id"
                        class="flex items-center gap-3 p-3 border-b border-gray-100 hover:bg-gray-50"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedUnitIds.includes(unit.id)"
                            @change="updateSelection(unit.id, $event.target.checked)"
                            class="rounded border-gray-300"
                        >
                        <div class="flex-1">
                            <div class="font-medium">{{ unit.unit_number }}</div>
                            <div class="text-sm text-gray-600">
                                {{ unit.building?.property?.name }} - {{ unit.building?.name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Target: KES {{ unit.target_rent ? Number(unit.target_rent).toLocaleString() : 'Not set' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ selectedUnitIds.length }} unit(s) selected
            </p>
        </div>

        <!-- Target Rent Form -->
        <div>
            <h3 class="text-lg font-semibold mb-4">Target Rent Settings</h3>
            <p class="text-sm text-gray-600 mb-4">
                Target rent is the market rate for a unit. This helps track potential revenue vs actual rent.
            </p>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment Type</label>
                    <select v-model="form.adjustment_type" class="w-full border-gray-300 rounded-md">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (+/-)</option>
                        <option value="set">Set Exact Amount</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Value</label>
                    <input
                        v-model.number="form.adjustment_value"
                        type="number"
                        step="0.01"
                        class="w-full border-gray-300 rounded-md"
                        :placeholder="form.adjustment_type === 'set' ? 'Enter target rent amount' : 'Enter adjustment value'"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || selectedUnitIds.length === 0"
                    class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? 'Processing...' : `Update Target Rent for ${selectedUnitIds.length} Unit(s)` }}
                </button>
            </form>
        </div>
    </div>
</template>
