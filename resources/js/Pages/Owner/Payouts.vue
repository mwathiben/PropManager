<script setup lang="ts">
/**
 * Phase-103 OWNER-PAYOUTS: the owner's read-only view of remittances received + their
 * running balance (lifetime net earned − paid out). Scoped to the authed owner server-side.
 */
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

interface PayoutRow { id: number; amount: number; paid_on: string | null; method: string; reference: string | null }
interface Summary {
    lifetime_collected: number;
    lifetime_expenses: number;
    lifetime_management_fee: number;
    lifetime_net: number;
    total_paid_out: number;
    balance_due: number;
}

withDefaults(defineProps<{ summary?: Summary; payouts?: PayoutRow[]; currencySymbol?: string }>(), {
    summary: () => ({ lifetime_collected: 0, lifetime_expenses: 0, lifetime_management_fee: 0, lifetime_net: 0, total_paid_out: 0, balance_due: 0 }),
    payouts: () => [],
    currencySymbol: '',
});

const { t } = useI18n();
const { formatMoney } = useFormatters();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('owners.portal.payouts_title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-100 p-2"><BanknotesIcon class="h-6 w-6 text-indigo-600" /></div>
                <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.portal.payouts_title') }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6 lg:px-8" data-testid="owner-payouts">
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.lifetime_net') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ formatMoney(summary.lifetime_net) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.paid_out') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ formatMoney(summary.total_paid_out) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.balance') }}</p>
                    <p class="mt-1 text-xl font-semibold" :class="summary.balance_due < 0 ? 'text-amber-600' : 'text-emerald-700'">{{ formatMoney(summary.balance_due) }}</p>
                </div>
            </div>

            <p v-if="summary.balance_due < 0" class="rounded-md bg-amber-50 p-3 text-sm text-amber-700">
                {{ t('owners.payouts.advance_note') }}
            </p>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ t('owners.payouts.paid_on') }}</th>
                            <th class="px-4 py-2">{{ t('owners.payouts.method') }}</th>
                            <th class="px-4 py-2">{{ t('owners.payouts.reference') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('owners.payouts.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in payouts" :key="p.id">
                            <td class="px-4 py-2 text-gray-700">{{ p.paid_on }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ t(`owners.payouts.methods.${p.method}`) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ p.reference || '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium text-gray-900">{{ formatMoney(p.amount) }}</td>
                        </tr>
                        <tr v-if="!payouts.length">
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ t('owners.payouts.none') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
