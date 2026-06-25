<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import type { UnitStatusTabProps } from '@/types';

const { t } = useI18n();

const props = withDefaults(defineProps<UnitStatusTabProps>(), {
    filteredUnits: () => [],
    selectedUnitIds: () => [],
    buildingId: null,
    wingId: null,
});

const emit = defineEmits(['update:selectedUnitIds', 'success']);

const form = useForm({
    unit_ids: [],
    new_status: 'vacant',
    notes: '',
    building_id: null,
    wing_id: null
});

const statusOptions = computed(() => [
    { value: 'vacant', label: t('bulk_unit_status.status_vacant') },
    { value: 'occupied', label: t('bulk_unit_status.status_occupied') },
    { value: 'maintenance', label: t('bulk_unit_status.status_maintenance') },
    { value: 'arrears', label: t('bulk_unit_status.status_arrears') },
]);

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
        alert(t('bulk_unit_status.alert.select_at_least_one'));
        return;
    }
    form.post(route('bulk.updateUnitStatus'), {
        onSuccess: () => {
            form.reset();
            emit('success');
        }
    });
};

const getStatusColor = (status) => {
    return {
        'vacant': 'bg-gray-100 text-gray-800',
        'occupied': 'bg-green-100 text-green-800',
        'maintenance': 'bg-orange-100 text-orange-800',
        'arrears': 'bg-red-100 text-red-800'
    }[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Selection Panel -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">{{ t('bulk_unit_status.select_units') }}</h3>
                <div class="flex gap-2">
                    <button @click="selectAllUnits" class="text-sm text-indigo-600 hover:text-indigo-800">
                        {{ t('bulk_unit_status.select_all') }}
                    </button>
                    <button @click="deselectAllUnits" class="text-sm text-gray-600 hover:text-gray-800">
                        {{ t('bulk_unit_status.deselect_all') }}
                    </button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                <div v-if="filteredUnits.length === 0" class="p-4 text-center text-gray-500">
                    {{ t('bulk_unit_status.no_units') }}
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
                            :aria-label="unit.unit_number"
                            class="rounded border-gray-300"
                        >
                        <div class="flex-1">
                            <div class="font-medium">{{ unit.unit_number }}</div>
                            <div class="text-sm text-gray-600">
                                {{ unit.building?.property?.name }} - {{ unit.building?.name }}
                            </div>
                        </div>
                        <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(unit.status)]">
                            {{ unit.status }}
                        </span>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ t('bulk_unit_status.selected_count', selectedUnitIds.length) }}
            </p>
        </div>

        <!-- Status Form -->
        <div>
            <h3 class="text-lg font-semibold mb-4">{{ t('bulk_unit_status.new_status') }}</h3>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="unit-status-select" class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_unit_status.status_label') }}</label>
                    <select id="unit-status-select" v-model="form.new_status" class="w-full border-gray-300 rounded-md">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="unit-status-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_unit_status.notes_label') }}</label>
                    <textarea
                        id="unit-status-notes"
                        v-model="form.notes"
                        rows="3"
                        class="w-full border-gray-300 rounded-md"
                        :placeholder="t('bulk_unit_status.notes_placeholder')"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || selectedUnitIds.length === 0"
                    class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? t('bulk_unit_status.processing') : t('bulk_unit_status.submit', selectedUnitIds.length) }}
                </button>
            </form>
        </div>
    </div>
</template>
