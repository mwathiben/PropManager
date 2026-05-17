<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    window_days: number;
    landlords: Array<{
        landlord_id: number;
        cost_kes: number;
        metrics: Record<string, number>;
    }>;
}>();

function formatKes(n: number): string {
    return new Intl.NumberFormat('en-KE', { maximumFractionDigits: 2 }).format(n);
}
</script>

<template>
    <Head title="Top-N landlord cost" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">
                Top-N landlord cost — last {{ window_days }} days
            </h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold uppercase tracking-wide text-gray-600">Landlord ID</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold uppercase tracking-wide text-gray-600">Estimated cost (KES)</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold uppercase tracking-wide text-gray-600">Metric breakdown</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in landlords" :key="row.landlord_id">
                                <td class="px-4 py-2 text-sm text-gray-900">#{{ row.landlord_id }}</td>
                                <td class="px-4 py-2 text-end text-sm font-medium text-gray-900">{{ formatKes(row.cost_kes) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    <span v-for="(value, metric) in row.metrics" :key="metric" class="me-3 inline-block">
                                        {{ metric }}: <span class="font-medium">{{ value }}</span>
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!landlords.length">
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No usage recorded in the window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
