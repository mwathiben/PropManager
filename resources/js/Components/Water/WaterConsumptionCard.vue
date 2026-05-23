<script setup lang="ts">
/**
 * Phase-93: shared consumption view — summary KPIs + a 12-month consumption chart
 * (reuses Components/Dashboard/ChartCard) + a recent-readings table. Data-only
 * props, no role logic, so the Phase-94+ water-client dashboard reuses it as-is.
 */
import ChartCard from '@/Components/Dashboard/ChartCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';

interface Point { label: string; value: number }
interface Summary { latest_consumption: number | null; latest_date: string | null; avg_monthly: number; ytd_consumption: number }
interface Reading { id: number; reading_date: string; consumption: number | string; cost: number | string }

const { t } = useI18n();
const { formatNumber, formatCurrency, formatDate } = useFormatters();

withDefaults(defineProps<{ history?: Point[]; summary?: Summary | null; readings?: Reading[] }>(), {
    history: () => [],
    summary: null,
    readings: () => [],
});
</script>

<template>
    <div class="space-y-6" data-testid="water-consumption-card">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">{{ t('water.account.summary_latest') }}</div>
                <div class="mt-1 text-xl font-semibold text-gray-900">{{ summary?.latest_consumption === null || summary?.latest_consumption === undefined ? '—' : formatNumber(summary.latest_consumption) }}</div>
                <div v-if="summary?.latest_date" class="mt-0.5 text-xs text-gray-400">{{ formatDate(summary.latest_date) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">{{ t('water.account.summary_avg') }}</div>
                <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatNumber(summary?.avg_monthly ?? 0) }}</div>
                <div class="mt-0.5 text-xs text-gray-400">{{ t('water.account.units') }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">{{ t('water.account.summary_ytd') }}</div>
                <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatNumber(summary?.ytd_consumption ?? 0) }}</div>
                <div class="mt-0.5 text-xs text-gray-400">{{ t('water.account.units') }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ t('water.account.history_title') }}</h3>
            <p class="mt-0.5 text-xs text-gray-500">{{ t('water.account.history_hint') }}</p>
            <ChartCard :card="{ points: history }" />
        </div>

        <div v-if="readings.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">{{ t('water.account.readings_title') }}</div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-start">{{ t('water.tenant.date') }}</th>
                        <th class="px-4 py-2 text-end">{{ t('water.tenant.consumption') }}</th>
                        <th class="px-4 py-2 text-end">{{ t('water.tenant.cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in readings" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-900">{{ formatDate(r.reading_date) }}</td>
                        <td class="px-4 py-3 text-end text-gray-700">{{ formatNumber(Number(r.consumption)) }}</td>
                        <td class="px-4 py-3 text-end font-medium text-gray-900">{{ formatCurrency(Number(r.cost)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
