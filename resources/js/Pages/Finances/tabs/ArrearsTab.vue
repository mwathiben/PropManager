<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters, usePayments, useTabFilters } from '@/composables';
import { FilterBar, DataTable, AmountDisplay, MetricCard, InvoiceStatusBadge } from '@/Components/Finances';
import {
    ExclamationTriangleIcon,
    UsersIcon,
    ClockIcon,
    BellIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    arrears: Array,
    filters: Object,
    stats: Object,
    buildings: Array,
});

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

const columns = [
    { key: 'invoice_number', label: 'Invoice', sortable: false },
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'balance', label: 'Balance', align: 'right', sortable: true },
    { key: 'days_overdue', label: 'Days Overdue', align: 'center', sortable: true },
    { key: 'due_date', label: 'Due Date', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

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
                title="Total Arrears"
                :value="stats?.total_arrears"
                format="currency"
                :icon="ExclamationTriangleIcon"
                color="red"
            />
            <MetricCard
                title="Tenants in Arrears"
                :value="stats?.tenants_in_arrears"
                format="number"
                :icon="UsersIcon"
                color="orange"
            />
            <MetricCard
                title="Overdue Invoices"
                :value="stats?.overdue_count"
                format="number"
                :icon="ClockIcon"
                color="yellow"
            />
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm font-medium text-gray-500 mb-3">Aging Breakdown</p>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">0-30 days</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['0_30'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">31-60 days</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['31_60'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">61-90 days</span>
                        <span class="font-medium">{{ formatMoney(stats?.age_groups?.['61_90'] || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-red-600">90+ days</span>
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
            search-placeholder="Search tenants..."
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
                    Send Arrears Notices
                </Link>
            </template>
        </FilterBar>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="false"
            row-key="id"
            :empty-icon="ExclamationTriangleIcon"
            empty-title="No arrears"
            empty-description="Great news! No overdue invoices."
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
                    {{ row.days_overdue }} days
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
                    title="Send Reminder"
                >
                    <EnvelopeIcon class="h-3.5 w-3.5" />
                    {{ isProcessing ? 'Sending...' : 'Remind' }}
                </button>
            </template>
        </DataTable>
    </div>
</template>
