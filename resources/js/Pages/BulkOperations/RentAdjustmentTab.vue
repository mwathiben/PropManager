<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import type { RentAdjustmentTabProps } from '@/types';

const { formatMoney, todayAsISODate } = useFormatters();
const { currencyCode } = useCurrency();

const props = withDefaults(defineProps<RentAdjustmentTabProps>(), {
    unitsWithLeases: () => [],
    selectedLeaseIds: () => [],
    buildingId: null,
    wingId: null,
});

const emit = defineEmits(['update:selectedLeaseIds', 'success']);

const form = useForm({
    lease_ids: [],
    adjustment_type: 'percentage',
    adjustment_value: 0,
    effective_date: todayAsISODate(),
    notify_tenants: true,
    reason: '',
    building_id: null,
    wing_id: null
});

const selectAllLeases = () => {
    emit('update:selectedLeaseIds', props.unitsWithLeases.map(u => u.active_lease.id));
};

const deselectAllLeases = () => {
    emit('update:selectedLeaseIds', []);
};

const updateSelection = (leaseId, checked) => {
    const newSelection = checked
        ? [...props.selectedLeaseIds, leaseId]
        : props.selectedLeaseIds.filter(id => id !== leaseId);
    emit('update:selectedLeaseIds', newSelection);
};

const submit = () => {
    form.lease_ids = props.selectedLeaseIds;
    form.building_id = props.buildingId;
    form.wing_id = props.wingId;
    if (form.lease_ids.length === 0) {
        alert('Please select at least one lease');
        return;
    }
    form.post(route('bulk.adjustRent'), {
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
                <h3 class="text-lg font-semibold">Select Leases</h3>
                <div class="flex gap-2">
                    <button @click="selectAllLeases" class="text-sm text-indigo-600 hover:text-indigo-800">
                        Select All
                    </button>
                    <button @click="deselectAllLeases" class="text-sm text-gray-600 hover:text-gray-800">
                        Deselect All
                    </button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                <div v-if="unitsWithLeases.length === 0" class="p-4 text-center text-gray-500">
                    No units with active leases found
                </div>
                <div v-else>
                    <div
                        v-for="unit in unitsWithLeases"
                        :key="unit.id"
                        class="flex items-center gap-3 p-3 border-b border-gray-100 hover:bg-gray-50"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedLeaseIds.includes(unit.active_lease.id)"
                            @change="updateSelection(unit.active_lease.id, $event.target.checked)"
                            class="rounded border-gray-300"
                        >
                        <div class="flex-1">
                            <div class="font-medium">{{ unit.unit_number }}</div>
                            <div class="text-sm text-gray-600">
                                {{ unit.active_lease.tenant?.name || 'Unknown Tenant' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Current Rent: {{ formatMoney(unit.active_lease.rent_amount) }}
                            </div>
                        </div>
                        <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(unit.status)]">
                            {{ unit.status }}
                        </span>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ selectedLeaseIds.length }} lease(s) selected
            </p>
        </div>

        <!-- Adjustment Form -->
        <div>
            <h3 class="text-lg font-semibold mb-4">Adjustment Settings</h3>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment Type</label>
                    <select v-model="form.adjustment_type" class="w-full border-gray-300 rounded-md">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ({{ currencyCode }})</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ form.adjustment_type === 'percentage' ? 'Percentage Change' : 'Amount Change' }}
                    </label>
                    <input
                        v-model.number="form.adjustment_value"
                        type="number"
                        step="0.01"
                        class="w-full border-gray-300 rounded-md"
                        :placeholder="form.adjustment_type === 'percentage' ? 'e.g., 10 for 10% increase' : 'e.g., 1000'"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        Use negative values for decreases
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Effective Date</label>
                    <input
                        v-model="form.effective_date"
                        type="date"
                        class="w-full border-gray-300 rounded-md"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea
                        v-model="form.reason"
                        rows="2"
                        class="w-full border-gray-300 rounded-md"
                        placeholder="e.g., Annual rent review"
                    ></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="form.notify_tenants"
                        type="checkbox"
                        id="notify-rent"
                        class="rounded border-gray-300"
                    >
                    <label for="notify-rent" class="text-sm text-gray-700">
                        Notify tenants about rent change
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || selectedLeaseIds.length === 0"
                    class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? 'Processing...' : `Adjust Rent for ${selectedLeaseIds.length} Lease(s)` }}
                </button>
            </form>
        </div>
    </div>
</template>
