<script setup lang="ts">
import { useFormatters } from '@/composables';
import type { FinancialSummary } from '@/types';

const props = withDefaults(defineProps<{
    summary: FinancialSummary;
    compact?: boolean;
}>(), {
    summary: () => ({
        total_paid: 0,
        total_invoiced: 0,
        outstanding: 0,
        wallet_balance: 0,
        deposit_held: 0
    }),
    compact: false,
});

const { formatMoney: formatCurrency } = useFormatters();

const paymentProgress = () => {
    if (!props.summary.total_invoiced) return 100;
    return Math.min(100, Math.round((props.summary.total_paid / props.summary.total_invoiced) * 100));
};
</script>

<template>
    <div :class="compact ? 'space-y-2' : 'bg-gray-50 rounded-lg p-4 space-y-3'">
        <div v-if="!compact" class="flex items-center justify-between">
            <h4 class="text-sm font-medium text-gray-900">Financial Summary</h4>
            <span class="text-xs text-gray-500">{{ paymentProgress() }}% collected</span>
        </div>

        <div class="w-full bg-gray-200 rounded-full h-2">
            <div
                class="h-2 rounded-full transition-all"
                :class="summary.outstanding > 0 ? 'bg-yellow-500' : 'bg-green-500'"
                :style="{ width: paymentProgress() + '%' }"
            ></div>
        </div>

        <div :class="compact ? 'grid grid-cols-2 gap-2' : 'grid grid-cols-2 md:grid-cols-4 gap-3'">
            <div class="text-center p-2 bg-white rounded border">
                <p class="text-xs text-gray-500">Total Paid</p>
                <p class="text-sm font-semibold text-green-600">{{ formatCurrency(summary.total_paid) }}</p>
            </div>
            <div class="text-center p-2 bg-white rounded border">
                <p class="text-xs text-gray-500">Outstanding</p>
                <p :class="['text-sm font-semibold', summary.outstanding > 0 ? 'text-red-600' : 'text-gray-600']">
                    {{ formatCurrency(summary.outstanding) }}
                </p>
            </div>
            <div class="text-center p-2 bg-white rounded border">
                <p class="text-xs text-gray-500">Wallet Balance</p>
                <p class="text-sm font-semibold text-blue-600">{{ formatCurrency(summary.wallet_balance) }}</p>
            </div>
            <div class="text-center p-2 bg-white rounded border">
                <p class="text-xs text-gray-500">Deposit Held</p>
                <p class="text-sm font-semibold text-gray-700">{{ formatCurrency(summary.deposit_held) }}</p>
            </div>
        </div>
    </div>
</template>
