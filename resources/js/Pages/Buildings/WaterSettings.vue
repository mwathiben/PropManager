<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useCurrency } from '@/composables';
import type { BuildingsWaterSettingsPageProps } from '@/types/water';

const props = defineProps<BuildingsWaterSettingsPageProps>();

const { currencyCode, currencySymbol } = useCurrency();

const form = useForm({
    water_billing_type: props.building.water_billing_type || null,
    water_flat_rate: props.building.water_flat_rate || '',
    water_unit_rate: props.building.water_unit_rate || '',
});

const billingOptions = [
    { value: null, label: 'Disabled', description: 'Water billing is not enabled for this building' },
    { value: 'consumption', label: 'Consumption-Based', description: 'Charge based on actual meter readings' },
    { value: 'flat_rate', label: 'Flat Rate', description: 'Fixed monthly charge per unit' },
];

const showFlatRateInput = computed(() => form.water_billing_type === 'flat_rate');
const showUnitRateInput = computed(() => form.water_billing_type === 'consumption');

const submit = () => {
    form.put(route('buildings.water-settings.update', props.building.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Water Settings - ${building.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold leading-tight text-gray-800">
                        Water Settings
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Configure water billing for {{ building.name }}
                    </p>
                </div>
                <Link
                    :href="route('buildings.edit', building.id)"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    Back to Building
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Billing Type Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    Water Billing Type
                                </label>
                                <div class="space-y-3">
                                    <label
                                        v-for="option in billingOptions"
                                        :key="option.value"
                                        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                                        :class="form.water_billing_type === option.value
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <input
                                            type="radio"
                                            :value="option.value"
                                            v-model="form.water_billing_type"
                                            class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ option.label }}
                                            </span>
                                            <span class="block text-sm text-gray-500">
                                                {{ option.description }}
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                <p v-if="form.errors.water_billing_type" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.water_billing_type }}
                                </p>
                            </div>

                            <!-- Flat Rate Input -->
                            <div v-if="showFlatRateInput" class="transition-all">
                                <label for="water_flat_rate" class="block text-sm font-medium text-gray-700">
                                    Monthly Flat Rate ({{ currencyCode }})
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ currencySymbol }}</span>
                                    </div>
                                    <input
                                        type="number"
                                        id="water_flat_rate"
                                        v-model="form.water_flat_rate"
                                        step="0.01"
                                        min="0"
                                        class="block w-full pl-12 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="0.00"
                                    />
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    This amount will be charged to each unit every billing cycle.
                                </p>
                                <p v-if="form.errors.water_flat_rate" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.water_flat_rate }}
                                </p>
                            </div>

                            <!-- Unit Rate Override Input -->
                            <div v-if="showUnitRateInput" class="transition-all">
                                <label for="water_unit_rate" class="block text-sm font-medium text-gray-700">
                                    Rate per Unit ({{ currencyCode }}/m³) - Optional Override
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ currencySymbol }}</span>
                                    </div>
                                    <input
                                        type="number"
                                        id="water_unit_rate"
                                        v-model="form.water_unit_rate"
                                        step="0.01"
                                        min="0"
                                        class="block w-full pl-12 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Leave empty to use global rate"
                                    />
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    Override the global water rate for this building. Leave empty to use the landlord's default rate.
                                </p>
                                <p v-if="form.errors.water_unit_rate" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.water_unit_rate }}
                                </p>
                            </div>

                            <!-- Info Box -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">
                                            How water billing works
                                        </h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <ul class="list-disc list-inside space-y-1">
                                                <li><strong>Consumption-Based:</strong> Water charges are calculated from meter readings recorded by caretakers.</li>
                                                <li><strong>Flat Rate:</strong> A fixed amount is added to each invoice regardless of usage.</li>
                                                <li><strong>Disabled:</strong> No water charges will be included in invoices.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-end pt-4 border-t">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Save Settings
                                </button>
                            </div>

                            <!-- Success Message -->
                            <div v-if="form.recentlySuccessful" class="text-sm text-green-600">
                                Settings saved successfully.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
