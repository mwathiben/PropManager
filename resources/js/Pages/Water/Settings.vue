<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import {
    Cog6ToothIcon,
    BeakerIcon,
    HomeModernIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    buildings: Array,
    globalSettings: Object,
});

const { formatCurrency } = useFormatters();

// Form for global settings
const form = useForm({
    water_billing_type: props.globalSettings.water_billing_type || 'consumption',
    water_unit_rate: props.globalSettings.water_unit_rate || 150,
    flat_water_rate: props.globalSettings.flat_water_rate || 0,
    building_overrides: props.buildings.map(b => ({
        id: b.id,
        water_billing_type: b.water_billing_type || 'inherit',
        water_unit_rate: b.water_unit_rate || '',
        water_flat_rate: b.water_flat_rate || '',
    })),
});

// Submit the form
const submit = () => {
    form.put(route('water.settings.update'), {
        preserveScroll: true,
    });
};

// Get billing type label
const getBillingTypeLabel = (type) => {
    switch (type) {
        case 'consumption':
            return 'Per Unit Consumption';
        case 'flat_rate':
            return 'Flat Rate';
        case 'none':
            return 'No Water Billing';
        case 'inherit':
            return 'Use Global Settings';
        default:
            return type;
    }
};

// Get building override index
const getBuildingOverrideIndex = (buildingId) => {
    return form.building_overrides.findIndex(b => b.id === buildingId);
};
</script>

<template>
    <Head title="Water Settings" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Water Settings</h1>
                    <p class="text-gray-600 mt-1">Configure water billing rates and methods</p>
                </div>

                <form @submit.prevent="submit">
                    <!-- Global Settings Card -->
                    <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div class="flex items-center mb-4">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <Cog6ToothIcon class="w-6 h-6 text-blue-600" />
                            </div>
                            <h2 class="ml-3 text-lg font-semibold text-gray-900">Global Water Billing Settings</h2>
                        </div>
                        <p class="text-sm text-gray-500 mb-6">
                            These settings apply to all buildings unless overridden at the building level.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Billing Type -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Billing Method</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label
                                        class="relative flex cursor-pointer rounded-lg border p-4 focus:outline-none"
                                        :class="form.water_billing_type === 'consumption' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.water_billing_type"
                                            value="consumption"
                                            class="sr-only"
                                        >
                                        <div class="flex flex-1">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">Per Unit Consumption</span>
                                                <span class="mt-1 flex items-center text-sm text-gray-500">Charge based on meter readings</span>
                                            </div>
                                        </div>
                                        <CheckIcon
                                            v-if="form.water_billing_type === 'consumption'"
                                            class="h-5 w-5 text-indigo-600"
                                        />
                                    </label>

                                    <label
                                        class="relative flex cursor-pointer rounded-lg border p-4 focus:outline-none"
                                        :class="form.water_billing_type === 'flat_rate' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.water_billing_type"
                                            value="flat_rate"
                                            class="sr-only"
                                        >
                                        <div class="flex flex-1">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">Flat Rate</span>
                                                <span class="mt-1 flex items-center text-sm text-gray-500">Fixed monthly charge</span>
                                            </div>
                                        </div>
                                        <CheckIcon
                                            v-if="form.water_billing_type === 'flat_rate'"
                                            class="h-5 w-5 text-indigo-600"
                                        />
                                    </label>

                                    <label
                                        class="relative flex cursor-pointer rounded-lg border p-4 focus:outline-none"
                                        :class="form.water_billing_type === 'none' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.water_billing_type"
                                            value="none"
                                            class="sr-only"
                                        >
                                        <div class="flex flex-1">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">No Water Billing</span>
                                                <span class="mt-1 flex items-center text-sm text-gray-500">Water included in rent</span>
                                            </div>
                                        </div>
                                        <CheckIcon
                                            v-if="form.water_billing_type === 'none'"
                                            class="h-5 w-5 text-indigo-600"
                                        />
                                    </label>
                                </div>
                            </div>

                            <!-- Rate per Unit (for consumption billing) -->
                            <div v-if="form.water_billing_type === 'consumption'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rate per Unit (KES)</label>
                                <input
                                    v-model="form.water_unit_rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="150"
                                >
                                <p class="mt-1 text-xs text-gray-500">Amount charged per unit of water consumed</p>
                            </div>

                            <!-- Flat Rate Amount (for flat rate billing) -->
                            <div v-if="form.water_billing_type === 'flat_rate'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Flat Rate (KES)</label>
                                <input
                                    v-model="form.flat_water_rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="500"
                                >
                                <p class="mt-1 text-xs text-gray-500">Fixed amount charged monthly to each tenant</p>
                            </div>
                        </div>
                    </div>

                    <!-- Per-Building Overrides -->
                    <div class="bg-white shadow-sm rounded-lg p-6 mb-6" v-if="buildings.length > 0">
                        <div class="flex items-center mb-4">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <HomeModernIcon class="w-6 h-6 text-green-600" />
                            </div>
                            <h2 class="ml-3 text-lg font-semibold text-gray-900">Building-Specific Settings</h2>
                        </div>
                        <p class="text-sm text-gray-500 mb-6">
                            Override global settings for specific buildings. Set to "Use Global Settings" to inherit from above.
                        </p>

                        <div class="space-y-4">
                            <div
                                v-for="building in buildings"
                                :key="building.id"
                                class="border border-gray-200 rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="font-medium text-gray-900">{{ building.name }}</h3>
                                        <p class="text-sm text-gray-500">{{ building.units_count }} units</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type"
                                            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                        >
                                            <option value="inherit">Use Global Settings</option>
                                            <option value="consumption">Per Unit Consumption</option>
                                            <option value="flat_rate">Flat Rate</option>
                                            <option value="none">No Water Billing</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Building-specific rate inputs -->
                                <div
                                    v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'consumption'"
                                    class="mt-3 pl-4 border-l-2 border-indigo-200"
                                >
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rate per Unit (KES)</label>
                                    <input
                                        v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_unit_rate"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                        :placeholder="form.water_unit_rate || '150'"
                                    >
                                </div>

                                <div
                                    v-if="form.building_overrides[getBuildingOverrideIndex(building.id)].water_billing_type === 'flat_rate'"
                                    class="mt-3 pl-4 border-l-2 border-indigo-200"
                                >
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Flat Rate (KES)</label>
                                    <input
                                        v-model="form.building_overrides[getBuildingOverrideIndex(building.id)].water_flat_rate"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                        :placeholder="form.flat_water_rate || '500'"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="buildings.length === 0" class="bg-white shadow-sm rounded-lg mb-6">
                        <EmptyState
                            :icon="BeakerIcon"
                            title="No buildings found"
                            description="Add buildings to configure per-building water settings."
                            size="sm"
                        />
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
