<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    summary: {
        mrr_total_kes_today: number;
        mrr_delta_30d_pct: number;
        monthly_churn_rate: number;
        active_incident_count: number;
        last_24h_alert_count: number;
        unresolved_alert_count: number;
    };
}>();

function formatKes(n: number): string {
    return new Intl.NumberFormat('en-KE', { maximumFractionDigits: 0 }).format(n);
}

function pct(n: number): string {
    return `${(n * 100).toFixed(2)}%`;
}
</script>

<template>
    <Head title="Ops dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Operations dashboard</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">MRR (today)</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">KES {{ formatKes(summary.mrr_total_kes_today) }}</p>
                        <p class="mt-1 text-sm" :class="summary.mrr_delta_30d_pct >= 0 ? 'text-green-700' : 'text-red-700'">
                            {{ summary.mrr_delta_30d_pct >= 0 ? '+' : '' }}{{ summary.mrr_delta_30d_pct.toFixed(2) }}% vs 30d ago
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Monthly churn rate</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ pct(summary.monthly_churn_rate) }}</p>
                        <p class="mt-1 text-sm" :class="summary.monthly_churn_rate <= 0.05 ? 'text-green-700' : 'text-red-700'">
                            target ≤ 5.00%
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active incidents</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.active_incident_count }}</p>
                        <p class="mt-1 text-sm text-gray-600">open + investigating + mitigated</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Alerts fired (24h)</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.last_24h_alert_count }}</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Unresolved alerts</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.unresolved_alert_count }}</p>
                    </div>
                </div>

                <div class="mt-8 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Drill-downs</h2>
                    <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <li><Link :href="route('ops.mrr.trend')" class="text-sm text-indigo-700 hover:underline">MRR trend (90 days)</Link></li>
                        <li><Link :href="route('ops.landlord-cost.top-n')" class="text-sm text-indigo-700 hover:underline">Top-N landlord cost</Link></li>
                        <li><Link :href="route('ops.incidents.index')" class="text-sm text-indigo-700 hover:underline">Operational incidents</Link></li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
