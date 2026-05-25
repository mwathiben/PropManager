<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { TargetRentTabProps } from '@/types';

const { formatMoney } = useFormatters();
const { t } = useI18n();

const props = withDefaults(defineProps<TargetRentTabProps>(), {
    filteredUnits: () => [],
    selectedUnitIds: () => [],
    buildingId: null,
    wingId: null,
});

const emit = defineEmits(['update:selectedUnitIds', 'success']);

const form = useForm({
    unit_ids: [],
    adjustment_type: 'percentage',
    adjustment_value: 0,
    building_id: null,
    wing_id: null
});

const valuePlaceholder = computed(() =>
    form.adjustment_type === 'set'
        ? t('bulk_target_rent.value_placeholder_set')
        : t('bulk_target_rent.value_placeholder_adjust')
);

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
        alert(t('bulk_target_rent.alert.select_at_least_one'));
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
                <h3 class="text-lg font-semibold">{{ t('bulk_target_rent.select_units') }}</h3>
                <div class="flex gap-2">
                    <button @click="selectAllUnits" class="text-sm text-indigo-600 hover:text-indigo-800">
                        {{ t('bulk_target_rent.select_all') }}
                    </button>
                    <button @click="deselectAllUnits" class="text-sm text-gray-600 hover:text-gray-800">
                        {{ t('bulk_target_rent.deselect_all') }}
                    </button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                <div v-if="filteredUnits.length === 0" class="p-4 text-center text-gray-500">
                    {{ t('bulk_target_rent.no_units') }}
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
                                {{ t('bulk_target_rent.target', { amount: unit.target_rent ? formatMoney(unit.target_rent) : t('bulk_target_rent.not_set') }) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ t('bulk_target_rent.selected_count', selectedUnitIds.length) }}
            </p>
        </div>

        <!-- Target Rent Form -->
        <div>
            <h3 class="text-lg font-semibold mb-4">{{ t('bulk_target_rent.settings_title') }}</h3>
            <p class="text-sm text-gray-600 mb-4">
                {{ t('bulk_target_rent.description') }}
            </p>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_target_rent.type_label') }}</label>
                    <select v-model="form.adjustment_type" class="w-full border-gray-300 rounded-md">
                        <option value="percentage">{{ t('bulk_target_rent.type_percentage') }}</option>
                        <option value="fixed">{{ t('bulk_target_rent.type_fixed') }}</option>
                        <option value="set">{{ t('bulk_target_rent.type_set') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_target_rent.value_label') }}</label>
                    <input
                        v-model.number="form.adjustment_value"
                        type="number"
                        step="0.01"
                        class="w-full border-gray-300 rounded-md"
                        :placeholder="valuePlaceholder"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || selectedUnitIds.length === 0"
                    class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? t('bulk_target_rent.processing') : t('bulk_target_rent.submit', selectedUnitIds.length) }}
                </button>
            </form>
        </div>
    </div>
</template>
