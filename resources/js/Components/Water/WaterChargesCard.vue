<script setup lang="ts">
/**
 * Phase-93: shared water-charge history (per-period water_due + settled status).
 * Water-only, so it is identical for a tenant and a Phase-94+ water client.
 */
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';

interface Charge { period: string | null; water_due: number; paid: boolean; status: string }

const { t } = useI18n();
const { formatCurrency } = useFormatters();

withDefaults(defineProps<{ charges?: Charge[] }>(), { charges: () => [] });
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white" data-testid="water-charges-card">
        <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">{{ t('water.account.charges_title') }}</div>
        <p v-if="charges.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">{{ t('water.account.no_charges') }}</p>
        <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                <tr>
                    <th class="px-4 py-2 text-start">{{ t('water.account.charges_period') }}</th>
                    <th class="px-4 py-2 text-end">{{ t('water.account.charges_amount') }}</th>
                    <th class="px-4 py-2 text-end">{{ t('water.account.charges_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <tr v-for="(c, idx) in charges" :key="idx" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-900">{{ c.period }}</td>
                    <td class="px-4 py-3 text-end font-medium text-gray-900">{{ formatCurrency(c.water_due) }}</td>
                    <td class="px-4 py-3 text-end">
                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', c.paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800']">
                            {{ c.paid ? t('water.account.charges_paid') : t('water.account.charges_unpaid') }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
