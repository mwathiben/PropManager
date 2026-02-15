<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import Modal from '@/Components/Modal.vue';
import type { MassHikeModalProps } from '@/types';

const { todayAsISODate } = useFormatters();
const { currencySymbol } = useCurrency();

const props = withDefaults(defineProps<MassHikeModalProps>(), {
    unitIds: () => [],
});

const emit = defineEmits(['close']);

const form = useForm({
    unit_ids: [],
    adjustment_type: 'percentage',
    value: 10,
    effective_date: todayAsISODate(),
    reason: 'Annual Review'
});

const submit = () => {
    form.unit_ids = props.unitIds;

    if (confirm(`This will increase rent for ${props.occupiedUnits} tenants. Proceed?`)) {
        form.post(route('leases.batch-adjust'), {
            onSuccess: () => emit('close')
        });
    }
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Mass Rent Adjustment</h2>
            <p class="text-sm text-gray-500 mb-4">
                This will apply a rent increase to all <strong>{{ occupiedUnits }} occupied units</strong> in {{ buildingName }}.
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Adjustment Type</label>
                    <select v-model="form.adjustment_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="percentage">Percentage Increase (%)</option>
                        <option value="fixed">Fixed Amount ({{ currencySymbol }})</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Value</label>
                    <input v-model="form.value" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                    <input v-model="form.effective_date" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                    <input v-model="form.reason" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="close" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="ml-3 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-bold"
                >
                    Apply Hike
                </button>
            </div>
        </div>
    </Modal>
</template>
