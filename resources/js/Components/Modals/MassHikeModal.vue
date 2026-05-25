<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import Modal from '@/Components/Modal.vue';
import type { MassHikeModalProps } from '@/types';

const { t } = useI18n();
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

    if (confirm(t('mass_hike_modal.confirm', { count: props.occupiedUnits }))) {
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
            <h2 class="text-lg font-bold text-gray-900 mb-4">{{ t('mass_hike_modal.title') }}</h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ t('mass_hike_modal.body_prefix') }} <strong>{{ t('mass_hike_modal.body_units', { count: occupiedUnits }) }}</strong> {{ t('mass_hike_modal.body_suffix', { buildingName }) }}
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ t('mass_hike_modal.adjustment_type') }}</label>
                    <select v-model="form.adjustment_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="percentage">{{ t('mass_hike_modal.type_percentage') }}</option>
                        <option value="fixed">{{ t('mass_hike_modal.type_fixed', { currency: currencySymbol }) }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ t('mass_hike_modal.value') }}</label>
                    <input v-model="form.value" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ t('mass_hike_modal.effective_date') }}</label>
                    <input v-model="form.effective_date" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ t('mass_hike_modal.reason') }}</label>
                    <input v-model="form.reason" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="close" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">{{ t('mass_hike_modal.cancel') }}</button>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="ms-3 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-bold"
                >
                    {{ t('mass_hike_modal.apply') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
