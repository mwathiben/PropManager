<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters, usePayments, useTabFilters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { FilterBar, DataTable, AmountDisplay, MetricCard, InvoiceStatusBadge } from '@/Components/Finances';
import {
    ExclamationTriangleIcon,
    UsersIcon,
    ClockIcon,
    BellIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';
import type { Building } from '@/types/finances';

interface ArrearsItem {
    id: number;
    invoice_number: string;
    tenant?: { name: string; email?: string; phone?: string };
    unit: string;
    building: string;
    total_due: number;
    amount_paid: number;
    balance: number;
    days_overdue: number;
    due_date: string;
}

interface ArrearsStats {
    total_arrears: number;
    tenants_in_arrears: number;
    average_days_overdue: number;
}

interface Props {
    arrears?: ArrearsItem[];
    filters?: Record<string, unknown>;
    stats?: ArrearsStats;
    buildings?: Building[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    arrears: () => [],
    filters: () => ({}),
    buildings: () => [],
    loading: false,
});

const { t } = useI18n();
const { formatDate, formatMoney } = useFormatters();
const { sendReminder: sendInvoiceReminder, isProcessing } = usePayments();

const { localFilters, applyFilters, clearFilters } = useTabFilters({
    routeName: 'finances.arrears',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        buildingId: { urlKey: 'building_id', default: null },
    },
});

const columns = computed(() => [
    { key: 'invoice_number', label: t('finances_arrears.columns.invoice'), sortable: false },
    { key: 'tenant', label: t('finances_arrears.columns.tenant'), sortable: false },
    { key: 'balance', label: t('finances_arrears.columns.balance'), align: 'right', sortable: true },
    { key: 'days_overdue', label: t('finances_arrears.columns.days_overdue'), align: 'center', sortable: true },
    { key: 'due_date', label: t('finances_arrears.columns.due_date'), sortable: true },
    { key: 'actions', label: '', align: 'right' },
]);

const tableData = computed(() => {
    if (!props.arrears) return [];
    return props.arrears.map(item => ({
        id: item.id,
        invoice_number: item.invoice_number,
        tenant: item.tenant?.name || 'Unknown',
        tenant_email: item.tenant?.email,
        tenant_phone: item.tenant?.phone,
        unit: item.unit || 'N/A',
        building: item.building || '',
        total_due: item.total_due,
        amount_paid: item.amount_paid,
        balance: item.balance,
        days_overdue: item.days_overdue,
        due_date: item.due_date,
    }));
});

const getDaysOverdueClass = (days) => {
    if (days <= 30) return 'text-yellow-600 bg-yellow-100';
    if (days <= 60) return 'text-orange-600 bg-orange-100';
    return 'text-red-600 bg-red-100';
};


const viewInvoice = (item) => {
    router.visit(route('invoices.show', item.id));
};

const sendReminder = async (item) => {
    await sendInvoiceReminder(item.id);
};
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <MetricCard
                :title="t('finances_arrears.metric.total_arrears')"
                :value="stats?.total_arrears"
                format="currency"
                :icon="ExclamationTriangleIcon"
                color="red"
            />
            <MetricCard
                :title="t('finances_arrears.metric.tenants_in_arrears')"
                :value="stats?.tenants_in_arrears"
                format="number"
                :icon="UsersIcon"
                color="orange"
            />
            <MetricCard
                :title="t('finances_arrears.metric.overdue_invoices')"
                :value="stats?.overdue_count"
                format="number"
                :icon="ClockIcon"
                color="yellow"
            />
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm font-medium text-gray-500 mb-3">{{ t('finances_arrears.aging.title') }}</p>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ t('finances_arrears.aging.0_30') }}</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['0_30'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ t('finances_arrears.aging.31_60') }}</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['31_60'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ t('finances_arrears.aging.61_90') }}</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['61_90'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-red-600">{{ t('finances_arrears.aging.90_plus') }}</span>
                        <span class="font-medium text-red-600">{{ formatMoney(stats?.age_groups?.['90_plus'] || 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <FilterBar
            v-model="localFilters"
            :buildings="buildings"
            :show-status="false"
            :show-payment-method="false"
            :show-date-range="false"
            :search-placeholder="t('finances_arrears.search_placeholder')"
            @filter="applyFilters"
            @clear="clearFilters"
        >
            <template #actions>
                <Link
                    :href="route('finances.notifications.arrears')"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                >
                    <BellIcon class="h-4 w-4" />
                    {{ t('finances_arrears.send_notices') }}
                </Link>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="ExclamationTriangleIcon"
            :empty-title="t('finances_arrears.empty.title')"
            :empty-description="t('finances_arrears.empty.description')"
            @row-click="viewInvoice"
        >
            <template #cell-tenant="{ row }">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ row.tenant }}</p>
                    <p class="text-xs text-gray-500">{{ row.unit }} - {{ row.building }}</p>
                </div>
            </template>

            <template #cell-balance="{ row }">
                <AmountDisplay :amount="row.balance" size="sm" class="text-red-600" />
            </template>

            <template #cell-days_overdue="{ row }">
                <span :class="['inline-flex px-2 py-1 text-xs font-medium rounded-full', getDaysOverdueClass(row.days_overdue)]">
                    {{ t('finances_arrears.days_count', { count: row.days_overdue }) }}
                </span>
            </template>

            <template #cell-due_date="{ row }">
                <span class="text-sm text-gray-600">{{ formatDate(row.due_date) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <button
                    @click.stop="sendReminder(row)"
                    :disabled="isProcessing"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-orange-700 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors disabled:opacity-50"
                    :title="t('finances_arrears.reminder_title')"
                >
                    <EnvelopeIcon class="h-3.5 w-3.5" />
                    {{ isProcessing ? t('finances_arrears.sending') : t('finances_arrears.remind') }}
                </button>
            </template>
        </DataTable>
    </div>
</template>
