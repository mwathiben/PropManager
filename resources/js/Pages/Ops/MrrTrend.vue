<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    window_days: number;
    days: Array<{
        day: string;
        mrr_kes_total: number;
        active_subscriptions_total: number;
        new_mrr_kes_total: number;
        expansion_mrr_kes_total: number;
        contraction_mrr_kes_total: number;
        churned_mrr_kes_total: number;
        by_plan: Array<{ plan_slug: string | null; plan_name: string | null; mrr_kes: number; active_subscriptions: number }>;
    }>;
}>();

function formatKes(n: number): string {
    return new Intl.NumberFormat('en-KE', { maximumFractionDigits: 0 }).format(n);
}
</script>

<template>
    <Head title="MRR trend" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">
                MRR trend — last {{ window_days }} days
            </h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Day</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">MRR (KES)</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Active subs</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-green-700">+ New</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-green-700">+ Expansion</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-red-700">− Contraction</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-red-700">− Churned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in days" :key="row.day">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ row.day }}</td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-900">{{ formatKes(row.mrr_kes_total) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ row.active_subscriptions_total }}</td>
                                <td class="px-4 py-2 text-right text-sm text-green-700">{{ formatKes(row.new_mrr_kes_total) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-green-700">{{ formatKes(row.expansion_mrr_kes_total) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-red-700">{{ formatKes(row.contraction_mrr_kes_total) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-red-700">{{ formatKes(row.churned_mrr_kes_total) }}</td>
                            </tr>
                            <tr v-if="!days.length">
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No snapshots in the window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
