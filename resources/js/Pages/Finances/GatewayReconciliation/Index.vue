<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

interface Report {
    id: number;
    provider: string;
    status: string;
    period_from: string | null;
    period_to: string | null;
    local_count: number;
    remote_count: number;
    matched_count: number;
    discrepancy_count: number;
    reconciled_at: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Dispute { id: number; gateway: string; amount: number; currency: string; reason: string | null; status: string; opened_at: string | null }
interface FailedRefund { id: number; amount: number; payment_method: string; retry_count: number; needs_review: boolean }

withDefaults(defineProps<{ reports: Paginator<Report>; disputes?: Dispute[]; failedRefunds?: FailedRefund[] }>(), {
    disputes: () => [],
    failedRefunds: () => [],
});

const { formatDate, formatMoney } = useFormatters();
</script>

<template>
    <Head :title="$t('gateway_reconciliation.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-blue-100">
                        <ArrowsRightLeftIcon class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $t('gateway_reconciliation.title') }}</h1>
                        <p class="text-gray-600">{{ $t('gateway_reconciliation.subtitle') }}</p>
                    </div>
                </div>

                <!-- Disputes needing attention -->
                <div v-if="disputes.length" class="bg-white rounded-xl shadow-sm border border-red-200 p-4">
                    <h2 class="text-sm font-semibold text-red-800 mb-3">{{ $t('payment_dispute.open_title') }}</h2>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="d in disputes" :key="d.id" class="py-2 flex items-center justify-between">
                            <span class="text-gray-800 capitalize">{{ d.gateway }} · {{ d.reason || '—' }}</span>
                            <span class="text-red-700 font-medium">{{ formatMoney(d.amount) }} {{ d.currency }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Failed refunds needing attention -->
                <div v-if="failedRefunds.length" class="bg-white rounded-xl shadow-sm border border-amber-200 p-4">
                    <h2 class="text-sm font-semibold text-amber-800 mb-3">{{ $t('refund.retry.failed_title') }}</h2>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="r in failedRefunds" :key="r.id" class="py-2 flex items-center justify-between">
                            <span class="text-gray-800">{{ formatMoney(r.amount) }} · {{ r.payment_method }}</span>
                            <span :class="r.needs_review ? 'text-red-700' : 'text-amber-700'" class="text-xs">
                                {{ r.needs_review ? $t('refund.retry.needs_review') : $t('refund.retry.retrying', { count: r.retry_count }) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table v-if="reports.data.length" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.gateway') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.period') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.matched') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.discrepancies') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.run') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="r in reports.data" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900 capitalize">{{ r.provider }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ r.period_from ? formatDate(r.period_from) : '—' }} → {{ r.period_to ? formatDate(r.period_to) : '—' }}</td>
                                <td class="px-4 py-3 text-end text-gray-600">{{ r.matched_count }} / {{ r.remote_count }}</td>
                                <td class="px-4 py-3 text-end">
                                    <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', r.discrepancy_count > 0 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800']">
                                        {{ r.discrepancy_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ r.reconciled_at ? formatDate(r.reconciled_at) : '—' }}</td>
                                <td class="px-4 py-3 text-end">
                                    <Link :href="route('gateway-reconciliation.show', r.id)" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ $t('gateway_reconciliation.view') }}</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="p-8 text-center text-gray-500">{{ $t('gateway_reconciliation.empty') }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
