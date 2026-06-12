<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import {
    HomeIcon,
    CreditCardIcon,
    ClockIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    BanknotesIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import OverviewTab from './tabs/OverviewTab.vue';
import CollectionTab from './tabs/CollectionTab.vue';
import TransactionsTab from './tabs/TransactionsTab.vue';
import AnalyticsTab from './tabs/AnalyticsTab.vue';
import SettingsTab from './tabs/SettingsTab.vue';

// ── Shared types ──────────────────────────────────────────────────────────────

interface TabConfig {
    id: string;
    name: string;
    route: string;
    icon: string;
}

interface SetupProgress {
    payment_methods: boolean;
    payout_account: boolean;
    first_payment: boolean;
}

// ── Overview tab ──────────────────────────────────────────────────────────────

interface OverviewStats {
    total_collected: number;
    this_month: number;
    pending_amount: number;
    payment_count: number;
    collection_rate: number;
}

interface RecentPayment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    tenant_name: string;
    unit: string;
    building: string;
    reference: string;
}

interface PayoutAccountSummary {
    has_accounts: boolean;
    has_verified: boolean;
    primary_account: { id: number; bank_name: string; masked_account_number: string; status: string } | null;
    account_count: number;
}

interface QuickAction {
    id: string;
    label: string;
    description: string;
    route: string;
    icon: string;
}

// ── Collection tab ────────────────────────────────────────────────────────────

interface PaymentConfig {
    accepted_payment_methods: string[];
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_branch: string | null;
    mpesa_paybill: string | null;
    mpesa_account_name: string | null;
    paystack_enabled: boolean;
}

interface PayoutAccount {
    id: number;
    provider: string;
    provider_label: string;
    account_type: string;
    account_name: string;
    masked_account_number: string;
    bank_name: string;
    business_name: string;
    verification_status: string;
    status_label: string;
    status_color: string;
    is_primary: boolean;
    is_active: boolean;
    can_receive_payments: boolean;
    created_at: string;
}

interface BillingSettings {
    transaction_fee_percentage: number;
    minimum_fee: number;
    billing_model: string;
}

// ── Transactions tab ──────────────────────────────────────────────────────────

interface PlatformFee {
    fee_amount: number;
    net_amount: number;
}

interface TransactionPayment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    reference: string;
    invoice: { invoice_number: string; total_due: number } | null;
    lease: {
        tenant: { name: string; email: string };
        unit: { unit_number: string; building: { name: string } };
    } | null;
    platform_fee: PlatformFee | null;
}

interface PaginatedPayments {
    data: TransactionPayment[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Building {
    id: number;
    name: string;
}

interface TransactionFilters {
    search?: string | null;
    method?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    building_id?: number | null;
}

// ── Analytics tab ─────────────────────────────────────────────────────────────

interface RevenueData {
    total: number;
    previous_total: number;
    trend: number;
    trend_direction: 'up' | 'down';
}

interface CollectionRates {
    current_month: number;
    previous_month: number;
    trend: 'up' | 'down';
}

interface PaymentMethodBreakdownItem {
    method: string;
    label: string;
    count: number;
    total: number;
}

interface MonthlyTrendItem {
    month: string;
    short_month: string;
    amount: number;
}

interface TopPayingUnit {
    unit: string;
    building: string;
    total_paid: number;
    payment_count: number;
}

interface PlatformFees {
    this_month: number;
    total_fees: number;
    total_gross: number;
    total_net: number;
}

// ── Settings tab ──────────────────────────────────────────────────────────────

interface Preferences {
    default_payment_terms_days: number;
    auto_send_invoices: boolean;
    invoice_footer: string;
}

interface InvoiceSettings {
    include_water_charges: boolean;
    include_arrears: boolean;
    auto_generate_monthly: boolean;
}

interface ReminderSettings {
    reminder_days_before_due: number;
    overdue_reminder_frequency: string;
    reminder_channels: string[];
}

// ── Page props ────────────────────────────────────────────────────────────────

interface Props {
    activeTab: string;
    setupComplete: boolean;
    setupProgress: SetupProgress;
    tabs: TabConfig[];
    // Overview tab
    stats?: OverviewStats;
    recentPayments?: RecentPayment[];
    pendingInvoices?: number;
    collectionStatus?: 'excellent' | 'good' | 'needs_attention' | 'critical';
    payoutAccountSummary?: PayoutAccountSummary;
    quickActions?: QuickAction[];
    // Collection tab
    paymentMethods?: Record<string, string>;
    paymentConfig?: PaymentConfig | null;
    payoutAccounts?: PayoutAccount[];
    billingSettings?: BillingSettings;
    // Transactions tab
    payments?: PaginatedPayments;
    paymentMethodOptions?: PaymentMethodOption[];
    buildings?: Building[];
    filters?: TransactionFilters;
    // Analytics tab
    period?: string;
    revenueData?: RevenueData;
    collectionRates?: CollectionRates;
    paymentMethodBreakdown?: PaymentMethodBreakdownItem[];
    monthlyTrend?: MonthlyTrendItem[];
    topPayingUnits?: TopPayingUnit[];
    platformFees?: PlatformFees;
    // Settings tab
    preferences?: Preferences;
    invoiceSettings?: InvoiceSettings;
    reminderSettings?: ReminderSettings;
}

const props = withDefaults(defineProps<Props>(), {
    recentPayments: () => [],
    pendingInvoices: 0,
    quickActions: () => [],
    payoutAccounts: () => [],
    paymentMethodBreakdown: () => [],
    monthlyTrend: () => [],
    topPayingUnits: () => [],
    buildings: () => [],
    paymentMethodOptions: () => [],
    filters: () => ({}),
    period: 'month',
});

const { t } = useI18n();

const tabIconMap: Record<string, unknown> = {
    HomeIcon,
    CreditCardIcon,
    ClockIcon,
    ChartBarIcon,
    Cog6ToothIcon,
};

const breadcrumbItems = [
    { label: t('payments_hub.page_title'), href: route('payments-hub.overview') },
    { label: props.tabs.find(tab => tab.id === props.activeTab)?.name ?? '' },
];

const setupSteps = [
    { key: 'payment_methods' as const, label: 'Payment Methods' },
    { key: 'payout_account' as const, label: 'Payout Account' },
    { key: 'first_payment' as const, label: 'First Payment' },
];
</script>

<template>
    <Head :title="t('payments_hub.page_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/40 rounded-lg">
                    <BanknotesIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ t('payments_hub.page_title') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('payments_hub.page_subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <!-- Setup progress banner -->
                <div
                    v-if="!setupComplete"
                    class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4"
                >
                    <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-3">
                        {{ t('payments_hub.setup_banner_title') }}
                    </h3>
                    <div class="flex flex-wrap gap-4">
                        <div
                            v-for="step in setupSteps"
                            :key="step.key"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircleIcon
                                :class="[
                                    'w-5 h-5',
                                    setupProgress[step.key]
                                        ? 'text-green-500'
                                        : 'text-amber-300 dark:text-amber-600',
                                ]"
                            />
                            <span
                                :class="
                                    setupProgress[step.key]
                                        ? 'text-gray-700 dark:text-gray-300 line-through'
                                        : 'text-amber-800 dark:text-amber-300'
                                "
                            >
                                {{ step.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <!-- Tab bar -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Payments Hub tabs">
                            <Link
                                v-for="tab in tabs"
                                :key="tab.id"
                                :href="route(tab.route)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    tab.id === activeTab
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-500',
                                ]"
                            >
                                <component
                                    :is="tabIconMap[tab.icon]"
                                    :class="[
                                        'w-5 h-5',
                                        tab.id === activeTab
                                            ? 'text-indigo-500 dark:text-indigo-400'
                                            : 'text-gray-400 dark:text-gray-500',
                                    ]"
                                />
                                {{ tab.name }}
                            </Link>
                        </nav>
                    </div>

                    <!-- Tab content -->
                    <div class="p-6">
                        <OverviewTab
                            v-if="activeTab === 'overview'"
                            :stats="stats"
                            :recent-payments="recentPayments"
                            :pending-invoices="pendingInvoices"
                            :collection-status="collectionStatus"
                            :payout-account-summary="payoutAccountSummary"
                            :quick-actions="quickActions"
                        />

                        <CollectionTab
                            v-else-if="activeTab === 'collection'"
                            :payment-methods="paymentMethods ?? {}"
                            :payment-config="paymentConfig ?? null"
                            :payout-accounts="payoutAccounts"
                            :billing-settings="billingSettings"
                        />

                        <TransactionsTab
                            v-else-if="activeTab === 'transactions'"
                            :payments="payments"
                            :payment-methods="paymentMethodOptions"
                            :buildings="buildings"
                            :filters="filters ?? {}"
                        />

                        <AnalyticsTab
                            v-else-if="activeTab === 'analytics'"
                            :period="period"
                            :revenue-data="revenueData"
                            :collection-rates="collectionRates"
                            :payment-method-breakdown="paymentMethodBreakdown"
                            :monthly-trend="monthlyTrend"
                            :top-paying-units="topPayingUnits"
                            :platform-fees="platformFees"
                        />

                        <SettingsTab
                            v-else-if="activeTab === 'settings'"
                            :preferences="preferences"
                            :invoice-settings="invoiceSettings"
                            :reminder-settings="reminderSettings"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
