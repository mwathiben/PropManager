<script setup lang="ts">
import { computed, onMounted, watch, defineAsyncComponent, type Component } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useFinancesStore } from '@/stores/finances';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { ModalLoadingPlaceholder } from '@/Components/Finances';
import OverviewTab from './tabs/OverviewTab.vue';
import InvoicesTab from './tabs/InvoicesTab.vue';
import PaymentsTab from './tabs/PaymentsTab.vue';
import ExpensesTab from './tabs/ExpensesTab.vue';
import RefundsTab from './tabs/RefundsTab.vue';
import ReconciliationTab from './tabs/ReconciliationTab.vue';
import DepositsTab from './tabs/DepositsTab.vue';
import ArrearsTab from './tabs/ArrearsTab.vue';
import LateFeeSettingsTab from './tabs/LateFeeSettingsTab.vue';
import SettingsTab from './tabs/SettingsTab.vue';
import ReportsTab from './tabs/ReportsTab.vue';

const InvoiceDetailModal = defineAsyncComponent({
    loader: () => import('./modals/InvoiceDetailModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const PaymentDetailModal = defineAsyncComponent({
    loader: () => import('./modals/PaymentDetailModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const RecordPaymentModal = defineAsyncComponent({
    loader: () => import('./modals/RecordPaymentModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const RefundModal = defineAsyncComponent({
    loader: () => import('./modals/RefundModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const MatchPaymentModal = defineAsyncComponent({
    loader: () => import('./modals/MatchPaymentModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const RefundDepositModal = defineAsyncComponent({
    loader: () => import('./modals/RefundDepositModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const ForfeitDepositModal = defineAsyncComponent({
    loader: () => import('./modals/ForfeitDepositModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});

const SendRemindersModal = defineAsyncComponent({
    loader: () => import('./modals/SendRemindersModal.vue'),
    loadingComponent: ModalLoadingPlaceholder,
    delay: 100,
});
import {
    ChartBarIcon,
    ChartPieIcon,
    DocumentTextIcon,
    CreditCardIcon,
    ArrowPathIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    Cog6ToothIcon,
    ArrowUturnLeftIcon,
    ClockIcon,
    ReceiptPercentIcon,
} from '@heroicons/vue/24/outline';
import type {
    PaginatedResponse,
    Invoice,
    Payment,
    Refund,
    Deposit,
    Expense,
    Building,
    Property,
    FinanceStats,
    TrendDataPoint,
} from '@/types/finances';

interface TabConfig {
    id: string;
    name: string;
    route: string;
    subtabs?: TabConfig[];
}

interface GroupConfig {
    name: string;
    icon: Component;
}

interface ArrearsEntry {
    id: number;
    tenant_name: string;
    unit_number: string;
    amount: number;
    days_overdue: number;
}

interface StatusOption {
    value: string;
    label: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Props {
    activeTab?: string;
    activeGroup?: string | null;
    buildings?: Building[];
    tabs?: TabConfig[];
    stats?: FinanceStats;
    recentPayments?: Payment[];
    recentInvoices?: Invoice[];
    collectionStatus?: string;
    monthlyTrend?: TrendDataPoint[];
    invoices?: PaginatedResponse<Invoice>;
    payments?: PaginatedResponse<Payment>;
    refunds?: PaginatedResponse<Refund>;
    unmatchedPayments?: Payment[];
    pendingReconciliation?: number;
    deposits?: PaginatedResponse<Deposit>;
    arrears?: ArrearsEntry[];
    paymentConfig?: Record<string, unknown>;
    paymentMethods?: Record<string, unknown>;
    invoiceSettings?: Record<string, unknown>;
    reminderSettings?: Record<string, unknown>;
    filters?: Record<string, unknown>;
    statusOptions?: StatusOption[];
    paymentMethodOptions?: PaymentMethodOption[];
    policies?: Record<string, unknown>[];
    properties?: Property[];
    expenses?: PaginatedResponse<Expense>;
    categories?: Record<string, unknown>[];
    vendors?: Record<string, unknown>[];
    revenueData?: Record<string, unknown>[];
    collectionRate?: Record<string, unknown>[];
    occupancyData?: Record<string, unknown>;
    arrearsAging?: Record<string, unknown>;
    expensesByCategory?: Record<string, unknown>[];
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'overview',
    activeGroup: null,
    buildings: () => [],
    tabs: () => [],
    recentPayments: () => [],
    recentInvoices: () => [],
    monthlyTrend: () => [],
    arrears: () => [],
    statusOptions: () => [],
    paymentMethodOptions: () => [],
    policies: () => [],
    properties: () => [],
    categories: () => [],
    vendors: () => [],
    revenueData: () => [],
    collectionRate: () => [],
    expensesByCategory: () => [],
    filters: () => ({}),
});

const store = useFinancesStore();

const groupConfig: Record<string, GroupConfig> = {
    overview: { name: 'Overview', icon: ChartBarIcon },
    billing: { name: 'Billing', icon: DocumentTextIcon },
    expenses: { name: 'Expenses', icon: ReceiptPercentIcon },
    collections: { name: 'Collections', icon: ExclamationTriangleIcon },
    reconciliation: { name: 'Reconciliation', icon: ArrowPathIcon },
    reports: { name: 'Reports', icon: ChartPieIcon },
    settings: { name: 'Settings', icon: Cog6ToothIcon },
};

const tabComponents = {
    overview: OverviewTab,
    invoices: InvoicesTab,
    payments: PaymentsTab,
    expenses: ExpensesTab,
    refunds: RefundsTab,
    reconciliation: ReconciliationTab,
    deposits: DepositsTab,
    arrears: ArrearsTab,
    'late-fees': LateFeeSettingsTab,
    reports: ReportsTab,
    settings: SettingsTab,
};

const tabNames = {
    overview: 'Overview',
    invoices: 'Invoices',
    payments: 'Payments',
    expenses: 'Expenses',
    refunds: 'Refunds',
    reconciliation: 'Reconciliation',
    deposits: 'Deposits',
    arrears: 'Arrears',
    'late-fees': 'Late Fees',
    reports: 'Reports',
    settings: 'Settings',
};

const currentTabComponent = computed(() => tabComponents[store.activeTab] || OverviewTab);

const effectiveGroup = computed(() => props.activeGroup || props.activeTab);

const activeSubtabs = computed(() => {
    const group = props.tabs.find(t => t.id === effectiveGroup.value);
    return group?.subtabs || null;
});

onMounted(() => {
    store.initFromProps({
        buildings: props.buildings,
        activeTab: props.activeTab,
        activeGroup: props.activeGroup,
    });
});

watch(() => props.activeTab, (newTab) => {
    store.setTab(newTab);
});

watch(() => props.activeGroup, (newGroup) => {
    store.setGroup(newGroup);
});

const navigateToTab = (tab) => {
    router.visit(route(tab.route), {
        preserveState: true,
        preserveScroll: true,
    });
};

const pageTitle = computed(() => {
    return `Finance Hub - ${tabNames[store.activeTab] || 'Overview'}`;
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Finance Hub', href: route('finances.index') }];

    if (props.activeGroup) {
        const group = props.tabs.find(t => t.id === props.activeGroup);
        if (group) {
            items.push({ label: group.name, href: route(group.route) });
        }
        items.push({ label: tabNames[store.activeTab] || 'Overview' });
    } else {
        items.push({ label: tabNames[store.activeTab] || 'Overview' });
    }

    return items;
});

const invoicesForModal = computed(() => {
    if (!props.invoices?.data) return [];
    return props.invoices.data
        .filter(inv => inv.status !== 'paid')
        .map(inv => ({
            id: inv.id,
            invoice_number: inv.invoice_number,
            tenant_name: inv.lease?.tenant?.name || 'Unknown',
            balance: (inv.total_due || 0) - (inv.amount_paid || 0),
        }));
});

const paymentsForModal = computed(() => {
    if (!props.payments?.data) return [];
    return props.payments.data.map(pmt => ({
        id: pmt.id,
        payment_date: pmt.payment_date,
        tenant_name: pmt.lease?.tenant?.name || pmt.tenant_name || 'Unknown',
        amount: pmt.amount,
        refund_status: pmt.refund_status || null,
        payment_method: pmt.payment_method,
    }));
});

const unmatchedPaymentsForModal = computed(() => {
    if (!props.unmatchedPayments) return [];
    return props.unmatchedPayments.map(pmt => ({
        id: pmt.id,
        payment_date: pmt.payment_date,
        tenant_name: pmt.tenant_name || 'Unknown',
        amount: pmt.amount,
        payment_method: pmt.payment_method,
    }));
});
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <BanknotesIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Finance Hub</h1>
                    <p class="text-sm text-gray-500">Manage invoices, payments, and financial operations</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Main Tab Groups -->
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="navigateToTab(tab)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    effectiveGroup === tab.id
                                        ? 'border-emerald-500 text-emerald-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component
                                    :is="groupConfig[tab.id]?.icon"
                                    :class="[
                                        'w-5 h-5',
                                        effectiveGroup === tab.id ? 'text-emerald-500' : 'text-gray-400'
                                    ]"
                                />
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>

                    <!-- Sub-tabs (when group has subtabs) -->
                    <div v-if="activeSubtabs" class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                        <div class="flex gap-2">
                            <button
                                v-for="subtab in activeSubtabs"
                                :key="subtab.id"
                                @click="navigateToTab(subtab)"
                                :class="[
                                    'px-3 py-1.5 text-sm font-medium rounded-full transition-colors',
                                    store.activeTab === subtab.id
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'text-gray-600 hover:bg-gray-100'
                                ]"
                            >
                                {{ subtab.name }}
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <component
                            :is="currentTabComponent"
                            :stats="stats"
                            :recent-payments="recentPayments"
                            :recent-invoices="recentInvoices"
                            :collection-status="collectionStatus"
                            :monthly-trend="monthlyTrend"
                            :invoices="invoices"
                            :payments="payments"
                            :refunds="refunds"
                            :unmatched-payments="unmatchedPayments"
                            :pending-reconciliation="pendingReconciliation"
                            :deposits="deposits"
                            :arrears="arrears"
                            :payment-config="paymentConfig"
                            :payment-methods="paymentMethods"
                            :invoice-settings="invoiceSettings"
                            :reminder-settings="reminderSettings"
                            :filters="filters"
                            :status-options="statusOptions"
                            :payment-method-options="paymentMethodOptions"
                            :buildings="buildings"
                            :policies="policies"
                            :properties="properties"
                            :expenses="expenses"
                            :categories="categories"
                            :vendors="vendors"
                            :revenue-data="revenueData"
                            :collection-rate="collectionRate"
                            :occupancy-data="occupancyData"
                            :arrears-aging="arrearsAging"
                            :expenses-by-category="expensesByCategory"
                        />
                    </div>
                </div>
            </div>
        </div>

        <InvoiceDetailModal />
        <PaymentDetailModal />
        <RecordPaymentModal :invoices="invoicesForModal" />
        <RefundModal :payments="paymentsForModal" />
        <MatchPaymentModal :invoices="invoicesForModal" :payments="unmatchedPaymentsForModal" />
        <RefundDepositModal />
        <ForfeitDepositModal />
        <SendRemindersModal />
    </AuthenticatedLayout>
</template>
