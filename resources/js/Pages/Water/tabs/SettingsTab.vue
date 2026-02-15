<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormatters, useCurrency } from '@/composables';
import type { WaterSettingsTabProps } from '@/types';
import {
    Cog6ToothIcon,
    CurrencyDollarIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<WaterSettingsTabProps>();

const { formatDate, formatCurrency } = useFormatters();
const { currencySymbol } = useCurrency();

const form = useForm({
    water_rate: props.settings?.water_rate || '',
    billing_day: props.settings?.billing_day || 1,
    include_in_invoice: props.settings?.include_in_invoice ?? true,
});

const submit = () => {
    form.put(route('water.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="max-w-2xl">
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Water Rate -->
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <CurrencyDollarIcon class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">Water Rate</h3>
                        <p class="text-sm text-gray-500 mb-4">Set the cost per unit of water consumed</p>

                        <div class="flex items-center gap-2">
                            <span class="text-gray-500">{{ currencySymbol }}</span>
                            <input
                                v-model.number="form.water_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-32 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                            <span class="text-gray-500">per unit</span>
                        </div>
                        <p v-if="form.errors.water_rate" class="mt-1 text-sm text-red-600">
                            {{ form.errors.water_rate }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Billing Day -->
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <ClockIcon class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">Billing Day</h3>
                        <p class="text-sm text-gray-500 mb-4">Day of month when water charges are added to invoices</p>

                        <select
                            v-model.number="form.billing_day"
                            class="w-32 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option v-for="day in 28" :key="day" :value="day">{{ day }}</option>
                        </select>
                        <p v-if="form.errors.billing_day" class="mt-1 text-sm text-red-600">
                            {{ form.errors.billing_day }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Include in Invoice -->
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <Cog6ToothIcon class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">Invoice Integration</h3>
                        <p class="text-sm text-gray-500 mb-4">Automatically include water charges in tenant invoices</p>

                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.include_in_invoice"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-700">Include water charges in monthly invoices</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Settings</span>
                </button>
            </div>
        </form>

        <!-- Rate History -->
        <div v-if="rateHistory?.length > 0" class="mt-8">
            <h3 class="font-semibold text-gray-900 mb-4">Rate History</h3>
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Effective Date
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                Rate
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="rate in rateHistory" :key="rate.id">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ formatDate(rate.effective_date) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                {{ formatCurrency(rate.rate) }}/unit
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
