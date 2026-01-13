<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    unitsWithLeases: {
        type: Array,
        default: () => []
    },
    selectedLeaseIds: {
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

const emit = defineEmits(['update:selectedLeaseIds', 'success']);

const terminateForm = useForm({
    lease_ids: [],
    termination_date: new Date().toISOString().split('T')[0],
    reason: '',
    notify_tenants: true,
    update_unit_status: true,
    building_id: null,
    wing_id: null
});

const extendForm = useForm({
    lease_ids: [],
    extension_months: 12,
    notify_tenants: true,
    building_id: null,
    wing_id: null
});

const depositForm = useForm({
    lease_ids: [],
    adjustment_type: 'percentage',
    adjustment_value: 0,
    notify_tenants: true,
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

const submitTermination = () => {
    terminateForm.lease_ids = props.selectedLeaseIds;
    terminateForm.building_id = props.buildingId;
    terminateForm.wing_id = props.wingId;
    if (terminateForm.lease_ids.length === 0) {
        alert('Please select at least one lease');
        return;
    }
    if (!confirm(`Are you sure you want to terminate ${terminateForm.lease_ids.length} lease(s)?`)) {
        return;
    }
    terminateForm.post(route('bulk.terminateLeases'), {
        onSuccess: () => {
            terminateForm.reset();
            emit('success');
        }
    });
};

const submitExtension = () => {
    extendForm.lease_ids = props.selectedLeaseIds;
    extendForm.building_id = props.buildingId;
    extendForm.wing_id = props.wingId;
    if (extendForm.lease_ids.length === 0) {
        alert('Please select at least one lease');
        return;
    }
    extendForm.post(route('bulk.extendLeases'), {
        onSuccess: () => {
            extendForm.reset();
            emit('success');
        }
    });
};

const submitDepositAdjustment = () => {
    depositForm.lease_ids = props.selectedLeaseIds;
    depositForm.building_id = props.buildingId;
    depositForm.wing_id = props.wingId;
    if (depositForm.lease_ids.length === 0) {
        alert('Please select at least one lease');
        return;
    }
    depositForm.post(route('bulk.adjustDeposits'), {
        onSuccess: () => {
            depositForm.reset();
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

            <div class="border border-gray-200 rounded-lg max-h-80 overflow-y-auto">
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
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ selectedLeaseIds.length }} lease(s) selected
            </p>
        </div>

        <!-- Lease Operations -->
        <div class="space-y-6">
            <!-- Extend Leases -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Extend Leases</h4>
                <form @submit.prevent="submitExtension" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Extension Period</label>
                        <select v-model.number="extendForm.extension_months" class="w-full border-gray-300 rounded-md">
                            <option :value="1">1 Month</option>
                            <option :value="3">3 Months</option>
                            <option :value="6">6 Months</option>
                            <option :value="12">12 Months</option>
                            <option :value="24">24 Months</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="extendForm.notify_tenants" type="checkbox" id="notify-extend" class="rounded border-gray-300">
                        <label for="notify-extend" class="text-sm text-gray-700">Notify tenants</label>
                    </div>
                    <button
                        type="submit"
                        :disabled="extendForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 text-sm"
                    >
                        {{ extendForm.processing ? 'Processing...' : 'Extend Leases' }}
                    </button>
                </form>
            </div>

            <!-- Terminate Leases -->
            <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                <h4 class="font-semibold text-red-900 mb-3">Terminate Leases</h4>
                <form @submit.prevent="submitTermination" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Termination Date</label>
                        <input v-model="terminateForm.termination_date" type="date" class="w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input v-model="terminateForm.reason" type="text" class="w-full border-gray-300 rounded-md" placeholder="Optional">
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <input v-model="terminateForm.notify_tenants" type="checkbox" id="notify-term" class="rounded border-gray-300">
                            <label for="notify-term" class="text-sm text-gray-700">Notify tenants</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="terminateForm.update_unit_status" type="checkbox" id="update-status" class="rounded border-gray-300">
                            <label for="update-status" class="text-sm text-gray-700">Mark units as vacant</label>
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="terminateForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 text-sm"
                    >
                        {{ terminateForm.processing ? 'Processing...' : 'Terminate Leases' }}
                    </button>
                </form>
            </div>

            <!-- Adjust Deposits -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Adjust Deposits</h4>
                <form @submit.prevent="submitDepositAdjustment" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select v-model="depositForm.adjustment_type" class="w-full border-gray-300 rounded-md text-sm">
                                <option value="percentage">%</option>
                                <option value="fixed">+/-</option>
                                <option value="set">Set</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Value</label>
                            <input v-model.number="depositForm.adjustment_value" type="number" step="0.01" class="w-full border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="depositForm.notify_tenants" type="checkbox" id="notify-deposit" class="rounded border-gray-300">
                        <label for="notify-deposit" class="text-sm text-gray-700">Notify tenants</label>
                    </div>
                    <button
                        type="submit"
                        :disabled="depositForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 text-sm"
                    >
                        {{ depositForm.processing ? 'Processing...' : 'Adjust Deposits' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
