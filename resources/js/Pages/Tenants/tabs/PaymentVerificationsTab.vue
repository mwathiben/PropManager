<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { MagnifyingGlassIcon, BanknotesIcon, EyeIcon } from '@heroicons/vue/24/outline';

interface PaymentVerification {
    id: number;
    status: string;
    amount?: number | null;
    created_at: string;
    tenant?: { name?: string; email?: string } | null;
    unit?: { unit_number?: string } | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number; last_page: number }

const props = defineProps<{
    paymentVerifications?: Paginator<PaymentVerification>;
    filters?: { search?: string; status?: string };
}>();

const { formatDate, formatCurrency } = useFormatters();
const { t } = useI18n();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('tenants.hub', { tab: 'payment-verifications' }), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => { search.value = ''; status.value = ''; applyFilters(); };
const hasActiveFilters = computed(() => !!(search.value || status.value));

const statusColor = (s: string): string => ({
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
}[s] || 'bg-gray-100 text-gray-700');

const statusLabel = (s: string): string => t(`tenants_payment_verifications_tab.status.${s}`, s ?? '');
</script>

<template>
    <div>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" :placeholder="t('tenants_payment_verifications_tab.filters.search_placeholder')"
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select v-model="status" @change="applyFilters" class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">{{ t('tenants_payment_verifications_tab.filters.all_status') }}</option>
                    <option value="pending">{{ t('tenants_payment_verifications_tab.status.pending') }}</option>
                    <option value="approved">{{ t('tenants_payment_verifications_tab.status.approved') }}</option>
                    <option value="rejected">{{ t('tenants_payment_verifications_tab.status.rejected') }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ t('tenants_payment_verifications_tab.filters.clear') }}</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="paymentVerifications?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_payment_verifications_tab.table.tenant') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_payment_verifications_tab.table.unit') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_payment_verifications_tab.table.amount') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_payment_verifications_tab.table.status') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_payment_verifications_tab.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="pv in paymentVerifications.data" :key="pv.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ pv.tenant?.name || t('tenants_payment_verifications_tab.unknown') }}</div>
                            <div class="text-xs text-gray-500">{{ pv.tenant?.email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ pv.unit?.unit_number ? t('tenants_payment_verifications_tab.unit_prefix', { number: pv.unit.unit_number }) : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-end">{{ pv.amount != null ? formatCurrency(pv.amount) : '—' }}</td>
                        <td class="px-6 py-4 text-center"><span :class="[statusColor(pv.status), 'px-2 py-1 text-xs font-medium rounded-full']">{{ statusLabel(pv.status) }}</span></td>
                        <td class="px-6 py-4 text-end">
                            <Link :href="route('payment-verifications.show', pv.id)" class="text-gray-600 hover:text-gray-900" :title="t('tenants_payment_verifications_tab.actions.view')"><EyeIcon class="w-5 h-5" /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="BanknotesIcon" :title="t('tenants_payment_verifications_tab.empty.title')" :description="hasActiveFilters ? t('tenants_payment_verifications_tab.empty.description_filtered') : t('tenants_payment_verifications_tab.empty.description_default')" />

            <div v-if="paymentVerifications?.data?.length && paymentVerifications.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ t('tenants_payment_verifications_tab.pagination.showing', { from: paymentVerifications.from, to: paymentVerifications.to, total: paymentVerifications.total }) }}</div>
                    <div class="flex space-x-2">
                        <Link v-for="link in paymentVerifications.links" :key="link.label" :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']">
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
