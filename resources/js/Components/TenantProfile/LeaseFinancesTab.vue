<script setup lang="ts">
import FinancialSummaryCard from '@/Components/FinancialSummaryCard.vue';
import { useFormatters } from '@/composables';
import type { TenantLeaseFinancesTabProps } from '@/types/tenants';

const props = defineProps<TenantLeaseFinancesTabProps>();
const { formatDate, formatMoney: formatCurrency } = useFormatters();

const leaseStatusClass = (lease) => {
    if (lease.is_active) return 'bg-green-100 text-green-800';
    return 'bg-gray-100 text-gray-800';
};

const leaseDuration = (lease) => {
    if (!lease.start_date) return 'N/A';
    const start = new Date(lease.start_date);
    const end = lease.end_date ? new Date(lease.end_date) : new Date();
    const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30));
    return `${months} month${months !== 1 ? 's' : ''}`;
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="activeLease" class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3">
                <h3 class="text-sm font-medium text-gray-900">Current Lease</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-gray-500">Unit</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ activeLease.unit?.unit_number || 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ activeLease.unit?.building?.name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Monthly Rent</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatCurrency(activeLease.rent_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Start Date</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(activeLease.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Deposit</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatCurrency(activeLease.deposit_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Wallet Balance</p>
                        <p :class="['text-sm font-medium', (activeLease.wallet_balance || 0) > 0 ? 'text-green-600' : 'text-gray-900']">
                            {{ formatCurrency(activeLease.wallet_balance) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Arrears</p>
                        <p :class="['text-sm font-medium', (activeLease.arrears || 0) > 0 ? 'text-red-600' : 'text-gray-900']">
                            {{ formatCurrency(activeLease.arrears) }}
                        </p>
                    </div>
                </div>

                <div v-if="activeLease.rent_history?.length" class="mt-4 pt-4 border-t">
                    <h4 class="text-xs font-medium text-gray-500 uppercase mb-2">Rent History</h4>
                    <div class="space-y-2">
                        <div
                            v-for="history in activeLease.rent_history.slice(0, 5)"
                            :key="history.id"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-gray-600">{{ formatDate(history.effective_date) }}</span>
                            <span class="font-medium">
                                {{ formatCurrency(history.old_amount) }} → {{ formatCurrency(history.new_amount) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
            <p class="text-sm text-yellow-800">No active lease found for this tenant.</p>
        </div>

        <FinancialSummaryCard :summary="financialSummary" />

        <div v-if="pastLeases?.length" class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3">
                <h3 class="text-sm font-medium text-gray-900">Past Leases</h3>
            </div>
            <ul class="divide-y">
                <li v-for="lease in pastLeases" :key="lease.id" class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ lease.unit?.unit_number || 'Unknown Unit' }}
                                <span class="text-gray-500 font-normal">
                                    at {{ lease.unit?.building?.name || 'Unknown Building' }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ formatDate(lease.start_date) }} - {{ formatDate(lease.end_date) }}
                                ({{ leaseDuration(lease) }})
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-medium text-gray-900">{{ formatCurrency(lease.rent_amount) }}/mo</p>
                            <span :class="[leaseStatusClass(lease), 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-1']">
                                {{ lease.is_active ? 'Active' : 'Ended' }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
