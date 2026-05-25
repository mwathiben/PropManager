<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { LeaseManagementTabProps } from '@/types';

const { todayAsISODate } = useFormatters();
const { t } = useI18n();

const props = withDefaults(defineProps<LeaseManagementTabProps>(), {
    unitsWithLeases: () => [],
    selectedLeaseIds: () => [],
    buildingId: null,
    wingId: null,
});

const emit = defineEmits(['update:selectedLeaseIds', 'success']);

const terminateForm = useForm({
    lease_ids: [],
    termination_date: todayAsISODate(),
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

const extensionPeriodOptions = computed(() => [
    { value: 1, label: t('bulk_lease_management.extend.months_1') },
    { value: 3, label: t('bulk_lease_management.extend.months_3') },
    { value: 6, label: t('bulk_lease_management.extend.months_6') },
    { value: 12, label: t('bulk_lease_management.extend.months_12') },
    { value: 24, label: t('bulk_lease_management.extend.months_24') },
]);

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
        alert(t('bulk_lease_management.alert.select_at_least_one'));
        return;
    }
    if (!confirm(t('bulk_lease_management.confirm.terminate', { count: terminateForm.lease_ids.length }))) {
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
        alert(t('bulk_lease_management.alert.select_at_least_one'));
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
        alert(t('bulk_lease_management.alert.select_at_least_one'));
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
                <h3 class="text-lg font-semibold">{{ t('bulk_lease_management.select_leases') }}</h3>
                <div class="flex gap-2">
                    <button @click="selectAllLeases" class="text-sm text-indigo-600 hover:text-indigo-800">
                        {{ t('bulk_lease_management.select_all') }}
                    </button>
                    <button @click="deselectAllLeases" class="text-sm text-gray-600 hover:text-gray-800">
                        {{ t('bulk_lease_management.deselect_all') }}
                    </button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg max-h-80 overflow-y-auto">
                <div v-if="unitsWithLeases.length === 0" class="p-4 text-center text-gray-500">
                    {{ t('bulk_lease_management.no_units') }}
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
                                {{ unit.active_lease.tenant?.name || t('bulk_lease_management.unknown_tenant') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ t('bulk_lease_management.selected_count', selectedLeaseIds.length) }}
            </p>
        </div>

        <!-- Lease Operations -->
        <div class="space-y-6">
            <!-- Extend Leases -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">{{ t('bulk_lease_management.extend.title') }}</h4>
                <form @submit.prevent="submitExtension" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_lease_management.extend.period_label') }}</label>
                        <select v-model.number="extendForm.extension_months" class="w-full border-gray-300 rounded-md">
                            <option v-for="option in extensionPeriodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="extendForm.notify_tenants" type="checkbox" id="notify-extend" class="rounded border-gray-300">
                        <label for="notify-extend" class="text-sm text-gray-700">{{ t('bulk_lease_management.notify_tenants') }}</label>
                    </div>
                    <button
                        type="submit"
                        :disabled="extendForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 text-sm"
                    >
                        {{ extendForm.processing ? t('bulk_lease_management.extend.processing') : t('bulk_lease_management.extend.submit') }}
                    </button>
                </form>
            </div>

            <!-- Terminate Leases -->
            <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                <h4 class="font-semibold text-red-900 mb-3">{{ t('bulk_lease_management.terminate.title') }}</h4>
                <form @submit.prevent="submitTermination" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_lease_management.terminate.date_label') }}</label>
                        <input v-model="terminateForm.termination_date" type="date" class="w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_lease_management.terminate.reason_label') }}</label>
                        <input v-model="terminateForm.reason" type="text" class="w-full border-gray-300 rounded-md" :placeholder="t('bulk_lease_management.terminate.reason_placeholder')">
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <input v-model="terminateForm.notify_tenants" type="checkbox" id="notify-term" class="rounded border-gray-300">
                            <label for="notify-term" class="text-sm text-gray-700">{{ t('bulk_lease_management.notify_tenants') }}</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="terminateForm.update_unit_status" type="checkbox" id="update-status" class="rounded border-gray-300">
                            <label for="update-status" class="text-sm text-gray-700">{{ t('bulk_lease_management.terminate.mark_vacant') }}</label>
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="terminateForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 text-sm"
                    >
                        {{ terminateForm.processing ? t('bulk_lease_management.terminate.processing') : t('bulk_lease_management.terminate.submit') }}
                    </button>
                </form>
            </div>

            <!-- Adjust Deposits -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">{{ t('bulk_lease_management.deposit.title') }}</h4>
                <form @submit.prevent="submitDepositAdjustment" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_lease_management.deposit.type_label') }}</label>
                            <select v-model="depositForm.adjustment_type" class="w-full border-gray-300 rounded-md text-sm">
                                <option value="percentage">{{ t('bulk_lease_management.deposit.type_percentage') }}</option>
                                <option value="fixed">{{ t('bulk_lease_management.deposit.type_fixed') }}</option>
                                <option value="set">{{ t('bulk_lease_management.deposit.type_set') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_lease_management.deposit.value_label') }}</label>
                            <input v-model.number="depositForm.adjustment_value" type="number" step="0.01" class="w-full border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="depositForm.notify_tenants" type="checkbox" id="notify-deposit" class="rounded border-gray-300">
                        <label for="notify-deposit" class="text-sm text-gray-700">{{ t('bulk_lease_management.notify_tenants') }}</label>
                    </div>
                    <button
                        type="submit"
                        :disabled="depositForm.processing || selectedLeaseIds.length === 0"
                        class="w-full px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 text-sm"
                    >
                        {{ depositForm.processing ? t('bulk_lease_management.deposit.processing') : t('bulk_lease_management.deposit.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
