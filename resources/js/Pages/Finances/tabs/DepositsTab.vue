<script setup lang="ts">
import { ref, computed } from 'vue';
import { useFormatters, useTabFilters } from '@/composables';
import { useFinancesStore } from '@/stores/finances';
import { FilterBar, DataTable, AmountDisplay, MetricCard, Pagination, ExportDropdown } from '@/Components/Finances';
import BanknotesIcon from '@heroicons/vue/24/outline/BanknotesIcon';
import ShieldCheckIcon from '@heroicons/vue/24/outline/ShieldCheckIcon';
import ArrowUturnLeftIcon from '@heroicons/vue/24/outline/ArrowUturnLeftIcon';
import XCircleIcon from '@heroicons/vue/24/outline/XCircleIcon';
import EllipsisVerticalIcon from '@heroicons/vue/24/outline/EllipsisVerticalIcon';
import ChevronDownIcon from '@heroicons/vue/24/outline/ChevronDownIcon';
import ChevronRightIcon from '@heroicons/vue/24/outline/ChevronRightIcon';
import type { PaginatedResponse, Deposit, Building } from '@/types/finances';

interface DepositData {
    id: number;
    tenant_name: string;
    unit_number: string;
    building_name: string;
    amount: number;
    status: string;
    refund_amount?: number;
    deductions?: number;
    deduction_reason?: string;
    processed_at?: string;
    start_date: string;
    end_date?: string;
    is_active: boolean;
    transactions?: unknown[];
}

interface DepositStats {
    total_held: number;
    total_refunded: number;
    total_forfeited: number;
    pending_refunds: number;
}

interface Props {
    deposits?: PaginatedResponse<DepositData>;
    filters?: Record<string, unknown>;
    stats?: DepositStats;
    buildings?: Building[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    buildings: () => [],
    loading: false,
});

const store = useFinancesStore();
const { formatDate, formatMoney } = useFormatters();

const { localFilters, applyFilters, clearFilters, getExportParams } = useTabFilters({
    routeName: 'finances.deposits',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        status: { default: '' },
        buildingId: { urlKey: 'building_id', default: null },
    },
});

const statusOptions = [
    { value: 'held', label: 'Held' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'forfeited', label: 'Forfeited' },
    { value: 'partial_refund', label: 'Partial Refund' },
];

const columns = [
    { key: 'tenant', label: 'Tenant', sortable: false },
    { key: 'unit', label: 'Unit', sortable: false },
    { key: 'amount', label: 'Amount', align: 'right', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Collected', sortable: true },
    { key: 'actions', label: '', align: 'right', sortable: false },
];

const tableData = computed(() => {
    if (!props.deposits?.data) return [];
    return props.deposits.data.map(deposit => ({
        id: deposit.id,
        tenant_name: deposit.tenant_name || 'Unknown',
        unit_number: deposit.unit_number || 'N/A',
        building_name: deposit.building_name || '',
        amount: deposit.amount,
        status: deposit.status,
        refund_amount: deposit.refund_amount,
        deductions: deposit.deductions,
        deduction_reason: deposit.deduction_reason,
        processed_at: deposit.processed_at,
        start_date: deposit.start_date,
        end_date: deposit.end_date,
        is_active: deposit.is_active,
        transactions: deposit.transactions || [],
    }));
});

const openRefundModal = (deposit) => {
    store.openModal('refundDeposit', {
        leaseId: deposit.id,
        deposit: {
            id: deposit.id,
            amount: deposit.amount,
            tenant_name: deposit.tenant_name,
            unit_number: deposit.unit_number,
        },
    });
};

const openForfeitModal = (deposit) => {
    store.openModal('forfeitDeposit', {
        leaseId: deposit.id,
        deposit: {
            id: deposit.id,
            amount: deposit.amount,
            tenant_name: deposit.tenant_name,
            unit_number: deposit.unit_number,
        },
    });
};

const activeDropdown = ref(null);
const expandedRows = ref(new Set());

const toggleDropdown = (id) => {
    activeDropdown.value = activeDropdown.value === id ? null : id;
};

const closeDropdown = () => {
    activeDropdown.value = null;
};

const toggleRow = (id) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

const exportData = (format) => {
    const params = getExportParams(format);
    window.location.href = route('finances.deposits.export') + '?' + params.toString();
};

const statusColors = {
    held: 'bg-blue-100 text-blue-800',
    refunded: 'bg-emerald-100 text-emerald-800',
    forfeited: 'bg-red-100 text-red-800',
    partial_refund: 'bg-yellow-100 text-yellow-800',
};

const statusLabels = {
    held: 'Held',
    refunded: 'Refunded',
    forfeited: 'Forfeited',
    partial_refund: 'Partial',
};

</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <MetricCard
                title="Total Deposits"
                :value="stats?.total"
                format="currency"
                :icon="BanknotesIcon"
                color="blue"
            />
            <MetricCard
                title="Currently Held"
                :value="stats?.held"
                format="currency"
                :icon="ShieldCheckIcon"
                color="emerald"
            />
            <MetricCard
                title="Refunded"
                :value="stats?.refunded"
                format="currency"
                :icon="ArrowUturnLeftIcon"
                color="gray"
            />
            <MetricCard
                title="Forfeited"
                :value="stats?.forfeited"
                format="currency"
                :icon="XCircleIcon"
                color="red"
            />
        </div>

        <div class="flex items-center justify-between gap-4">
            <FilterBar
                v-model="localFilters"
                :status-options="statusOptions"
                :buildings="buildings"
                :show-payment-method="false"
                :show-date-range="false"
                search-placeholder="Search deposits..."
                @filter="applyFilters"
                @clear="clearFilters"
                class="flex-1"
            />
            <ExportDropdown @export="exportData" />
        </div>

        <DataTable
            :columns="columns"
            :data="tableData"
            :loading="loading"
            row-key="id"
            :empty-icon="BanknotesIcon"
            empty-title="No deposits found"
            empty-description="Security deposits will appear here"
        >
            <template #cell-tenant="{ row }">
                <div class="flex items-center gap-2">
                    <button
                        v-if="row.transactions.length > 0"
                        @click.stop="toggleRow(row.id)"
                        class="p-0.5 text-gray-400 hover:text-gray-600 rounded"
                    >
                        <ChevronDownIcon v-if="expandedRows.has(row.id)" class="w-4 h-4" />
                        <ChevronRightIcon v-else class="w-4 h-4" />
                    </button>
                    <div v-else class="w-5" />
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ row.tenant_name }}</p>
                        <p class="text-xs text-gray-500">{{ row.building_name }}</p>
                    </div>
                </div>
            </template>

            <template #cell-unit="{ row }">
                <span class="text-sm text-gray-900">{{ row.unit_number }}</span>
            </template>

            <template #cell-amount="{ row }">
                <div>
                    <AmountDisplay :amount="row.amount" size="sm" />
                    <p v-if="row.refund_amount && row.status !== 'held'" class="text-xs text-gray-500 mt-0.5">
                        Refunded: {{ formatMoney(row.refund_amount) }}
                    </p>
                </div>
            </template>

            <template #cell-status="{ row }">
                <span :class="['inline-flex px-2 py-1 text-xs font-medium rounded-full', statusColors[row.status] || statusColors.held]">
                    {{ statusLabels[row.status] || row.status }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <div>
                    <span class="text-sm text-gray-600">{{ formatDate(row.start_date) }}</span>
                    <p v-if="row.processed_at" class="text-xs text-gray-400">
                        Processed: {{ formatDate(row.processed_at) }}
                    </p>
                </div>
            </template>

            <template #cell-actions="{ row }">
                <div v-if="row.status === 'held'" class="relative">
                    <button
                        @click.stop="toggleDropdown(row.id)"
                        class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <EllipsisVerticalIcon class="w-5 h-5" />
                    </button>

                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="activeDropdown === row.id"
                            class="absolute right-0 z-10 mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
                            @click.stop
                        >
                            <button
                                @click="openRefundModal(row); closeDropdown()"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                <ArrowUturnLeftIcon class="w-4 h-4 text-emerald-500" />
                                Refund Deposit
                            </button>
                            <button
                                @click="openForfeitModal(row); closeDropdown()"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                <XCircleIcon class="w-4 h-4 text-red-500" />
                                Forfeit Deposit
                            </button>
                        </div>
                    </Transition>
                </div>
                <div v-else class="text-xs text-gray-400">
                    {{ row.deduction_reason ? row.deduction_reason : '-' }}
                </div>
            </template>

            <template #row-expansion="{ row }">
                <tr v-if="expandedRows.has(row.id) && row.transactions.length > 0">
                    <td colspan="6" class="bg-gray-50 px-6 py-3">
                        <div class="text-xs font-medium text-gray-500 mb-2">Transaction History</div>
                        <div class="space-y-2">
                            <div
                                v-for="transaction in row.transactions"
                                :key="transaction.id"
                                class="flex items-center justify-between py-2 px-3 bg-white rounded-lg border border-gray-100"
                            >
                                <div class="flex items-center gap-3">
                                    <span :class="[
                                        'inline-flex px-2 py-0.5 text-xs font-medium rounded',
                                        transaction.type === 'received' ? 'bg-blue-100 text-blue-700' :
                                        transaction.type === 'full_refund' ? 'bg-emerald-100 text-emerald-700' :
                                        transaction.type === 'partial_refund' ? 'bg-yellow-100 text-yellow-700' :
                                        transaction.type === 'deduction' ? 'bg-orange-100 text-orange-700' :
                                        'bg-red-100 text-red-700'
                                    ]">
                                        {{ transaction.type_label }}
                                    </span>
                                    <span class="text-sm text-gray-600">{{ transaction.reason || '-' }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ formatMoney(transaction.amount) }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ transaction.created_at }}
                                    </span>
                                    <span v-if="transaction.processed_by" class="text-xs text-gray-400">
                                        by {{ transaction.processed_by }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>
        </DataTable>

        <div v-if="expandedRows.size > 0" class="space-y-2">
            <template v-for="row in tableData" :key="row.id">
                <div
                    v-if="expandedRows.has(row.id) && row.transactions.length > 0"
                    class="bg-white border border-gray-200 rounded-lg p-4"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-900">
                            Transaction History - {{ row.tenant_name }} ({{ row.unit_number }})
                        </h4>
                        <button
                            @click="toggleRow(row.id)"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <XCircleIcon class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="transaction in row.transactions"
                            :key="transaction.id"
                            class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg"
                        >
                            <div class="flex items-center gap-3">
                                <span :class="[
                                    'inline-flex px-2 py-0.5 text-xs font-medium rounded',
                                    transaction.type === 'received' ? 'bg-blue-100 text-blue-700' :
                                    transaction.type === 'full_refund' ? 'bg-emerald-100 text-emerald-700' :
                                    transaction.type === 'partial_refund' ? 'bg-yellow-100 text-yellow-700' :
                                    transaction.type === 'deduction' ? 'bg-orange-100 text-orange-700' :
                                    'bg-red-100 text-red-700'
                                ]">
                                    {{ transaction.type_label }}
                                </span>
                                <span class="text-sm text-gray-600">{{ transaction.reason || '-' }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm font-medium text-gray-900">
                                    {{ formatMoney(transaction.amount) }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ transaction.created_at }}
                                </span>
                                <span v-if="transaction.processed_by" class="text-xs text-gray-400">
                                    by {{ transaction.processed_by }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <Pagination :links="deposits?.links" />
    </div>
</template>
