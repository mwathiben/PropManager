<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

interface Discrepancy {
    type: string;
    reference: string;
    local_amount: number | null;
    remote_amount: number | null;
    currency: string | null;
    remote_status: string | null;
}

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
    error_message: string | null;
    reconciled_at: string | null;
    discrepancies: Discrepancy[];
}

defineProps<{ report: Report }>();

const { formatDate, formatMoney } = useFormatters();

const typeClass = (type: string) => ({
    missing_locally: 'bg-amber-100 text-amber-800',
    missing_remotely: 'bg-red-100 text-red-800',
    amount_mismatch: 'bg-orange-100 text-orange-800',
}[type] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head :title="$t('gateway_reconciliation.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link :href="route('gateway-reconciliation.index')" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                    <ArrowLeftIcon class="w-4 h-4" /> {{ $t('gateway_reconciliation.back') }}
                </Link>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h1 class="text-xl font-semibold text-gray-900 capitalize">{{ report.provider }} · {{ report.period_from ? formatDate(report.period_from) : '—' }} → {{ report.period_to ? formatDate(report.period_to) : '—' }}</h1>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div><div class="text-gray-500">{{ $t('gateway_reconciliation.local') }}</div><div class="font-semibold">{{ report.local_count }}</div></div>
                        <div><div class="text-gray-500">{{ $t('gateway_reconciliation.remote') }}</div><div class="font-semibold">{{ report.remote_count }}</div></div>
                        <div><div class="text-gray-500">{{ $t('gateway_reconciliation.matched') }}</div><div class="font-semibold text-emerald-600">{{ report.matched_count }}</div></div>
                        <div><div class="text-gray-500">{{ $t('gateway_reconciliation.discrepancies') }}</div><div class="font-semibold" :class="report.discrepancy_count > 0 ? 'text-red-600' : 'text-emerald-600'">{{ report.discrepancy_count }}</div></div>
                    </div>
                    <p v-if="report.error_message" class="mt-3 text-sm text-red-600">{{ report.error_message }}</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table v-if="report.discrepancies.length" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.kind') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.reference') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.local_amount') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ $t('gateway_reconciliation.remote_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="(d, i) in report.discrepancies" :key="i">
                                <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', typeClass(d.type)]">{{ $t('gateway_reconciliation.type_' + d.type) }}</span></td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ d.reference }}</td>
                                <td class="px-4 py-3 text-end">{{ d.local_amount !== null ? formatMoney(d.local_amount) : '—' }}</td>
                                <td class="px-4 py-3 text-end">{{ d.remote_amount !== null ? formatMoney(d.remote_amount) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="p-8 text-center text-emerald-600">{{ $t('gateway_reconciliation.no_discrepancies') }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
