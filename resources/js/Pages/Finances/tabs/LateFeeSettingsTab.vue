<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useAuth } from '@/composables/useAuth';
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
const { can } = useAuth();
const { t } = useI18n();

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

// Phase-81 LATE-FEE-DEPTH-1: apply eligible late fees on demand.
const applyNow = () => {
    router.post(route('finances.late-fees.apply-now'), {}, { preserveScroll: true });
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
        <!-- Phase-81 LATE-FEE-DEPTH-1: apply late fees to eligible overdue invoices now. -->
        <div class="flex justify-end">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                @click="applyNow"
            >
                {{ t('finances_latefee.apply_now') }}
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 rounded-lg">
                        <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ t('finances_latefee.stats.active_policies') }}</p>
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
                        <p class="text-xs text-gray-500">{{ t('finances_latefee.stats.fees_this_month') }}</p>
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
                        <p class="text-xs text-gray-500">{{ t('finances_latefee.stats.total_applied') }}</p>
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
                        <p class="text-xs text-gray-500">{{ t('finances_latefee.stats.total_waived') }}</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.total_fees_waived) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_latefee.policies.title') }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ t('finances_latefee.policies.subtitle') }}</p>
                </div>
                <button
                    @click="openCreateForm"
                    class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    <PlusIcon class="w-4 h-4" />
                    {{ t('finances_latefee.policies.add') }}
                </button>
            </div>

            <div v-if="showForm" class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="lf-name" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.name') }}</label>
                            <input
                                id="lf-name"
                                v-model="form.name"
                                type="text"
                                required
                                :placeholder="t('finances_latefee.form.name_placeholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label for="lf-property" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.property') }}</label>
                            <select
                                id="lf-property"
                                v-model="form.property_id"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option :value="null">{{ t('finances_latefee.form.property_all') }}</option>
                                <option v-for="property in properties" :key="property.id" :value="property.id">
                                    {{ property.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="lf-building" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.building') }}</label>
                            <select
                                id="lf-building"
                                v-model="form.building_id"
                                :disabled="!form.property_id"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100"
                            >
                                <option :value="null">{{ t('finances_latefee.form.building_all') }}</option>
                                <option v-for="building in filteredBuildings" :key="building.id" :value="building.id">
                                    {{ building.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="lf-grace-period" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.grace_period') }}</label>
                            <input
                                id="lf-grace-period"
                                v-model.number="form.grace_period_days"
                                type="number"
                                min="0"
                                max="60"
                                required
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('finances_latefee.form.grace_period_hint') }}</p>
                        </div>
                        <div>
                            <label for="lf-fee-type" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.fee_type') }}</label>
                            <select
                                id="lf-fee-type"
                                v-model="form.fee_type"
                                required
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="percentage">{{ t('finances_latefee.form.fee_type_percentage') }}</option>
                                <option value="fixed">{{ t('finances_latefee.form.fee_type_fixed', { currency: currencySymbol }) }}</option>
                            </select>
                        </div>
                        <div v-if="form.fee_type === 'percentage'">
                            <label for="lf-fee-percentage" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.fee_percentage') }}</label>
                            <div class="relative">
                                <input
                                    id="lf-fee-percentage"
                                    v-model.number="form.fee_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    required
                                    class="w-full px-3 py-2 pe-8 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                                <span class="absolute end-3 top-2 text-gray-400">%</span>
                            </div>
                        </div>
                        <div v-else>
                            <label for="lf-fee-amount" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.fee_amount') }}</label>
                            <div class="relative">
                                <span class="absolute start-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                <input
                                    id="lf-fee-amount"
                                    v-model.number="form.fee_amount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                    class="w-full px-3 py-2 ps-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label for="lf-max-fee-cap" class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_latefee.form.max_fee_cap') }}</label>
                            <div class="relative">
                                <span class="absolute start-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                <input
                                    id="lf-max-fee-cap"
                                    v-model.number="form.max_fee_cap"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :placeholder="t('finances_latefee.form.max_fee_cap_placeholder')"
                                    class="w-full px-3 py-2 ps-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                            <span class="text-sm text-gray-700">{{ t('finances_latefee.form.compounding') }}</span>
                        </label>

                        <div v-if="form.is_compounding" class="flex items-center gap-2">
                            <label for="lf-compounding-frequency" class="text-sm text-gray-700">{{ t('finances_latefee.form.frequency') }}</label>
                            <select
                                id="lf-compounding-frequency"
                                v-model="form.compounding_frequency"
                                class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="daily">{{ t('finances_latefee.form.frequency_daily') }}</option>
                                <option value="weekly">{{ t('finances_latefee.form.frequency_weekly') }}</option>
                                <option value="monthly">{{ t('finances_latefee.form.frequency_monthly') }}</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                            />
                            <span class="text-sm text-gray-700">{{ t('finances_latefee.form.active') }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="cancelForm"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            {{ t('finances_latefee.form.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {{ form.processing ? t('finances_latefee.form.saving') : (editingPolicy ? t('finances_latefee.form.update') : t('finances_latefee.form.create')) }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="policies.length === 0" class="px-6 py-12 text-center">
                <ExclamationTriangleIcon class="mx-auto h-12 w-12 text-gray-300" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('finances_latefee.empty.title') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ t('finances_latefee.empty.subtitle') }}</p>
                <button
                    @click="openCreateForm"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                >
                    <PlusIcon class="w-4 h-4" />
                    {{ t('finances_latefee.empty.add_first') }}
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
                                    /* i18n-ignore */ 'px-2 py-0.5 text-xs font-medium rounded-full',
                                    policy.is_active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                {{ policy.is_active ? t('finances_latefee.list.status_active') : t('finances_latefee.list.status_inactive') }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            <span>{{ policy.scope_label }}</span>
                            <span>|</span>
                            <span>{{ t('finances_latefee.list.grace_period', { days: policy.grace_period_days }) }}</span>
                            <span>|</span>
                            <span class="font-medium text-gray-700">{{ policy.fee_description }}</span>
                            <span v-if="policy.is_compounding">{{ t('finances_latefee.list.compounds', { frequency: policy.compounding_frequency }) }}</span>
                            <span v-if="policy.max_fee_cap">{{ t('finances_latefee.list.max', { amount: formatCurrency(policy.max_fee_cap) }) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="togglePolicyStatus(policy)"
                            :class="[
                                /* i18n-ignore */ 'p-2 rounded-lg transition-colors',
                                policy.is_active
                                    ? 'text-amber-600 hover:bg-amber-50'
                                    : 'text-emerald-600 hover:bg-emerald-50'
                            ]"
                            :title="policy.is_active ? t('finances_latefee.list.deactivate') : t('finances_latefee.list.activate')"
                        >
                            <component :is="policy.is_active ? XCircleIcon : CheckCircleIcon" class="w-5 h-5" />
                        </button>
                        <button
                            @click="openEditForm(policy)"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            :title="t('finances_latefee.list.edit')"
                        >
                            <PencilSquareIcon class="w-5 h-5" />
                        </button>
                        <button
                            v-if="can('finances:manage')"
                            @click="confirmDelete(policy)"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            :title="t('finances_latefee.list.delete')"
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
                    <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_latefee.delete.title') }}</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ t('finances_latefee.delete.confirm', { name: policyToDelete?.name }) }}
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            {{ t('finances_latefee.delete.cancel') }}
                        </button>
                        <button
                            @click="deletePolicy"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        >
                            {{ t('finances_latefee.delete.confirm_btn') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
