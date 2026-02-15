<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';
import type { Property, Building } from '@/types/finances';

interface LateFeePolicy {
    id: number;
    name: string;
    property_id?: number;
    building_id?: number;
    property?: Property;
    building?: Building;
    grace_period_days: number;
    fee_type: 'percentage' | 'fixed';
    fee_percentage?: number;
    fee_amount?: number;
    is_compounding: boolean;
    compounding_frequency?: string;
    max_fee_cap?: number;
    is_active: boolean;
}

interface LateFeeStats {
    total_policies: number;
    active_policies: number;
    total_fees_collected: number;
}

interface Props {
    policies?: LateFeePolicy[];
    properties?: Property[];
    buildings?: Building[];
    stats?: LateFeeStats;
}

const props = withDefaults(defineProps<Props>(), {
    policies: () => [],
    properties: () => [],
    buildings: () => [],
    stats: () => ({}),
});

const { formatMoney: formatCurrency } = useFormatters();
const { currencySymbol } = useCurrency();

const showForm = ref(false);
const editingPolicy = ref(null);
const showDeleteConfirm = ref(false);
const policyToDelete = ref(null);

const form = useForm({
    name: '',
    property_id: null,
    building_id: null,
    grace_period_days: 5,
    fee_type: 'percentage',
    fee_percentage: 5.0,
    fee_amount: null,
    is_compounding: false,
    compounding_frequency: null,
    max_fee_cap: null,
    is_active: true,
});

const filteredBuildings = computed(() => {
    if (!form.property_id) return props.buildings;
    return props.buildings.filter(b => b.property_id === form.property_id);
});

watch(() => form.property_id, (newVal) => {
    if (!newVal) {
        form.building_id = null;
    }
});

watch(() => form.fee_type, (newVal) => {
    if (newVal === 'percentage') {
        form.fee_amount = null;
        if (!form.fee_percentage) form.fee_percentage = 5.0;
    } else {
        form.fee_percentage = null;
        if (!form.fee_amount) form.fee_amount = 500;
    }
});

watch(() => form.is_compounding, (newVal) => {
    if (!newVal) {
        form.compounding_frequency = null;
    } else if (!form.compounding_frequency) {
        form.compounding_frequency = 'monthly';
    }
});

const openCreateForm = () => {
    editingPolicy.value = null;
    form.reset();
    form.grace_period_days = 5;
    form.fee_type = 'percentage';
    form.fee_percentage = 5.0;
    form.is_active = true;
    showForm.value = true;
};

const openEditForm = (policy) => {
    editingPolicy.value = policy;
    form.name = policy.name;
    form.property_id = policy.property_id;
    form.building_id = policy.building_id;
    form.grace_period_days = policy.grace_period_days;
    form.fee_type = policy.fee_type;
    form.fee_percentage = policy.fee_percentage;
    form.fee_amount = policy.fee_amount;
    form.is_compounding = policy.is_compounding;
    form.compounding_frequency = policy.compounding_frequency;
    form.max_fee_cap = policy.max_fee_cap;
    form.is_active = policy.is_active;
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    editingPolicy.value = null;
    form.reset();
};

const submitForm = () => {
    if (editingPolicy.value) {
        form.put(route('finances.late-fee-policies.update', editingPolicy.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                editingPolicy.value = null;
                form.reset();
            },
        });
    } else {
        form.post(route('finances.late-fee-policies.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    }
};

const togglePolicyStatus = (policy) => {
    router.post(route('finances.late-fee-policies.toggle', policy.id), {}, {
        preserveScroll: true,
    });
};

const confirmDelete = (policy) => {
    policyToDelete.value = policy;
    showDeleteConfirm.value = true;
};

const deletePolicy = () => {
    if (!policyToDelete.value) return;
    router.delete(route('finances.late-fee-policies.destroy', policyToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            policyToDelete.value = null;
        },
    });
};

</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 rounded-lg">
                        <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Active Policies</p>
                        <p class="text-lg font-semibold text-gray-900">{{ stats.active_policies || 0 }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-100 rounded-lg">
                        <ExclamationTriangleIcon class="w-5 h-5 text-amber-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Fees This Month</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.fees_this_month) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <ClockIcon class="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Applied</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.total_fees_applied) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <XCircleIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Waived</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.total_fees_waived) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Late Fee Policies</h3>
                    <p class="text-xs text-gray-500 mt-1">Configure automatic late fee rules for overdue invoices</p>
                </div>
                <button
                    @click="openCreateForm"
                    class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-4 h-4" />
                    Add Policy
                </button>
            </div>

            <div v-if="showForm" class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Policy Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g., Default Late Fee"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Property (Optional)</label>
                            <select
                                v-model="form.property_id"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option :value="null">All Properties (Default)</option>
                                <option v-for="property in properties" :key="property.id" :value="property.id">
                                    {{ property.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Building (Optional)</label>
                            <select
                                v-model="form.building_id"
                                :disabled="!form.property_id"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100"
                            >
                                <option :value="null">All Buildings</option>
                                <option v-for="building in filteredBuildings" :key="building.id" :value="building.id">
                                    {{ building.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Grace Period (days) *</label>
                            <input
                                v-model.number="form.grace_period_days"
                                type="number"
                                min="0"
                                max="60"
                                required
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">Days after due date before fee applies</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fee Type *</label>
                            <select
                                v-model="form.fee_type"
                                required
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="percentage">Percentage (%)</option>
                                <option value="flat_amount">Flat Amount ({{ currencySymbol }})</option>
                            </select>
                        </div>
                        <div v-if="form.fee_type === 'percentage'">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fee Percentage *</label>
                            <div class="relative">
                                <input
                                    v-model.number="form.fee_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    required
                                    class="w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                <span class="absolute right-3 top-2 text-gray-400">%</span>
                            </div>
                        </div>
                        <div v-else>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fee Amount *</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                <input
                                    v-model.number="form.fee_amount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                    class="w-full px-3 py-2 pl-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Max Fee Cap (Optional)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                <input
                                    v-model.number="form.max_fee_cap"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="No limit"
                                    class="w-full px-3 py-2 pl-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_compounding"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Compounding (apply fee multiple times)</span>
                        </label>

                        <div v-if="form.is_compounding" class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Frequency:</label>
                            <select
                                v-model="form.compounding_frequency"
                                class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="cancelForm"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {{ form.processing ? 'Saving...' : (editingPolicy ? 'Update Policy' : 'Create Policy') }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="policies.length === 0" class="px-6 py-12 text-center">
                <ExclamationTriangleIcon class="mx-auto h-12 w-12 text-gray-300" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No late fee policies</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a late fee policy.</p>
                <button
                    @click="openCreateForm"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                >
                    <PlusIcon class="w-4 h-4" />
                    Add Your First Policy
                </button>
            </div>

            <div v-else class="divide-y divide-gray-200">
                <div
                    v-for="policy in policies"
                    :key="policy.id"
                    class="px-6 py-4 flex items-center justify-between hover:bg-gray-50"
                >
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h4 class="text-sm font-medium text-gray-900">{{ policy.name }}</h4>
                            <span
                                :class="[
                                    'px-2 py-0.5 text-xs font-medium rounded-full',
                                    policy.is_active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                {{ policy.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            <span>{{ policy.scope_label }}</span>
                            <span>|</span>
                            <span>{{ policy.grace_period_days }} day grace period</span>
                            <span>|</span>
                            <span class="font-medium text-gray-700">{{ policy.fee_description }}</span>
                            <span v-if="policy.is_compounding">| Compounds {{ policy.compounding_frequency }}</span>
                            <span v-if="policy.max_fee_cap">| Max {{ formatCurrency(policy.max_fee_cap) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="togglePolicyStatus(policy)"
                            :class="[
                                'p-2 rounded-lg transition-colors',
                                policy.is_active
                                    ? 'text-amber-600 hover:bg-amber-50'
                                    : 'text-emerald-600 hover:bg-emerald-50'
                            ]"
                            :title="policy.is_active ? 'Deactivate' : 'Activate'"
                        >
                            <component :is="policy.is_active ? XCircleIcon : CheckCircleIcon" class="w-5 h-5" />
                        </button>
                        <button
                            @click="openEditForm(policy)"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="Edit"
                        >
                            <PencilSquareIcon class="w-5 h-5" />
                        </button>
                        <button
                            @click="confirmDelete(policy)"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Delete"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showDeleteConfirm = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Delete Policy</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Are you sure you want to delete "{{ policyToDelete?.name }}"? This action cannot be undone.
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deletePolicy"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
