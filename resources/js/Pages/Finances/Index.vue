<script setup>
import { computed, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useFinancesStore } from '@/stores/finances';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
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
import InvoiceDetailModal from './modals/InvoiceDetailModal.vue';
import PaymentDetailModal from './modals/PaymentDetailModal.vue';
import RecordPaymentModal from './modals/RecordPaymentModal.vue';
import RefundModal from './modals/RefundModal.vue';
import MatchPaymentModal from './modals/MatchPaymentModal.vue';
import RefundDepositModal from './modals/RefundDepositModal.vue';
import ForfeitDepositModal from './modals/ForfeitDepositModal.vue';
import SendRemindersModal from './modals/SendRemindersModal.vue';
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

const props = defineProps({
    activeTab: { type: String, default: 'overview' },
    buildings: { type: Array, default: () => [] },
    tabs: { type: Array, default: () => [] },
    stats: Object,
    recentPayments: Array,
    recentInvoices: Array,
    collectionStatus: String,
    monthlyTrend: Array,
    invoices: Object,
    payments: Object,
    refunds: Object,
    unmatchedPayments: Array,
    pendingReconciliation: Number,
    deposits: Object,
    arrears: Array,
    paymentConfig: Object,
    paymentMethods: Object,
    invoiceSettings: Object,
    reminderSettings: Object,
    filters: Object,
    statusOptions: Array,
    paymentMethodOptions: Array,
    policies: Array,
    properties: Array,
    expenses: Object,
    categories: Array,
    vendors: Array,
    revenueData: Array,
    collectionRate: Array,
    occupancyData: Object,
    arrearsAging: Object,
    expensesByCategory: Array,
});

const store = useFinancesStore();

const tabIcons = {
    overview: ChartBarIcon,
    invoices: DocumentTextIcon,
    payments: CreditCardIcon,
    expenses: ReceiptPercentIcon,
    refunds: ArrowUturnLeftIcon,
    reconciliation: ArrowPathIcon,
    deposits: BanknotesIcon,
    arrears: ExclamationTriangleIcon,
    'late-fees': ClockIcon,
    reports: ChartPieIcon,
    settings: Cog6ToothIcon,
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

const currentTabComponent = computed(() => tabComponents[store.activeTab] || OverviewTab);

onMounted(() => {
    store.initFromProps({
        buildings: props.buildings,
        activeTab: props.activeTab,
    });
});

watch(() => props.activeTab, (newTab) => {
    store.setTab(newTab);
});

const navigateToTab = (tab) => {
    router.visit(route(`finances.${tab.id}`), {
        preserveState: true,
        preserveScroll: true,
    });
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

const pageTitle = computed(() => {
    return `Finance Hub - ${tabNames[store.activeTab] || 'Overview'}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: tabNames[store.activeTab] || 'Overview' },
]);

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
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="navigateToTab(tab)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    store.activeTab === tab.id
                                        ? 'border-emerald-500 text-emerald-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component
                                    :is="tabIcons[tab.id]"
                                    :class="[
                                        'w-5 h-5',
                                        store.activeTab === tab.id ? 'text-emerald-500' : 'text-gray-400'
                                    ]"
                                />
                                {{ tab.name }}
                            </button>
                        </nav>
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
