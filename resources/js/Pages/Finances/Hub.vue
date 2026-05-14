<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { useFormatters } from '@/composables';
import { MetricCard } from '@/Components/Finances';
import {
    BanknotesIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    HomeModernIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ExclamationTriangleIcon,
    Cog6ToothIcon,
    DocumentPlusIcon,
    DocumentDuplicateIcon,
    PlusCircleIcon,
} from '@heroicons/vue/24/outline';
import type { Building, Property } from '@/types/finances';

interface HubStats {
    revenue_mtd?: number;
    month_trend?: number;
    outstanding_balance?: number;
    collection_rate?: number;
    active_leases?: number;
    invoices_pending?: number;
    payments_this_month?: number;
    deposits_held?: number;
    expenses_this_month?: number;
    expenses_count?: number;
    refunds_pending?: number;
    total_arrears?: number;
    tenants_in_arrears?: number;
    unreconciled_count?: number;
}

interface Props {
    stats?: HubStats;
    buildings?: Building[];
    properties?: Property[];
}

const props = withDefaults(defineProps<Props>(), {
    buildings: () => [],
    properties: () => [],
});

const { formatMoney } = useFormatters();

const generateInvoices = () => {
    router.post(route('invoices.generate'));
};
</script>

<template>
    <Head title="Finance Hub" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                Finance Hub
            </h1>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-4">
                    <Breadcrumb :items="[{ label: 'Finance Hub' }]" />
                </div>

                <!-- Page Header -->
                <div class="mb-6">
                    <p class="text-gray-500">Overview of your financial health</p>
                </div>

                <!-- Hero KPIs (4 cards) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <MetricCard
                        title="Revenue (MTD)"
                        :value="stats?.revenue_mtd"
                        format="currency"
                        :trend="stats?.month_trend ? { direction: stats.month_trend >= 0 ? 'up' : 'down', value: `${Math.abs(stats.month_trend)}%` } : null"
                        :icon="BanknotesIcon"
                        color="emerald"
                    />
                    <MetricCard
                        title="Outstanding"
                        :value="stats?.outstanding_balance"
                        format="currency"
                        :icon="CurrencyDollarIcon"
                        color="yellow"
                    />
                    <MetricCard
                        title="Collection Rate"
                        :value="stats?.collection_rate"
                        format="percent"
                        :icon="ChartBarIcon"
                        color="blue"
                    />
                    <MetricCard
                        title="Active Leases"
                        :value="stats?.active_leases"
                        format="number"
                        :icon="HomeModernIcon"
                        color="indigo"
                    />
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-8">
                    <h2 class="text-sm font-medium text-gray-500 mb-3">Quick Actions</h2>
                    <div class="flex flex-wrap gap-3">
                        <button
                            @click="generateInvoices"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            <DocumentPlusIcon class="h-5 w-5" />
                            Generate Invoices
                        </button>
                        <Link
                            :href="route('finances.payments.record')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <PlusCircleIcon class="h-5 w-5" />
                            Record Payment
                        </Link>
                        <Link
                            :href="route('finances.expenses')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <PlusCircleIcon class="h-5 w-5" />
                            Add Expense
                        </Link>
                        <Link
                            :href="route('finances.templates')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <DocumentDuplicateIcon class="h-5 w-5" />
                            Manage Templates
                        </Link>
                    </div>
                </div>

                <!-- Section Cards (2x2 grid) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Money In Card -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-emerald-100 rounded-lg">
                                <ArrowTrendingUpIcon class="h-5 w-5 text-emerald-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Money In</h3>
                        </div>
                        <div class="space-y-3 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Pending Invoices</span>
                                <span class="font-medium">{{ stats?.invoices_pending ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Payments This Month</span>
                                <span class="font-medium">{{ stats?.payments_this_month ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Deposits Held</span>
                                <span class="font-medium">{{ formatMoney(stats?.deposits_held) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-100">
                            <Link :href="route('finances.invoices')" class="text-sm text-emerald-600 hover:underline">Invoices</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.payments')" class="text-sm text-emerald-600 hover:underline">Payments</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.deposits')" class="text-sm text-emerald-600 hover:underline">Deposits</Link>
                        </div>
                    </div>

                    <!-- Money Out Card -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-red-100 rounded-lg">
                                <ArrowTrendingDownIcon class="h-5 w-5 text-red-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Money Out</h3>
                        </div>
                        <div class="space-y-3 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Expenses This Month</span>
                                <span class="font-medium">{{ formatMoney(stats?.expenses_this_month) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Expense Count</span>
                                <span class="font-medium">{{ stats?.expenses_count ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Pending Refunds</span>
                                <span class="font-medium">{{ stats?.refunds_pending ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-100">
                            <Link :href="route('finances.expenses')" class="text-sm text-red-600 hover:underline">Expenses</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.refunds')" class="text-sm text-red-600 hover:underline">Refunds</Link>
                        </div>
                    </div>

                    <!-- Collections Card -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-amber-100 rounded-lg">
                                <ExclamationTriangleIcon class="h-5 w-5 text-amber-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Collections</h3>
                        </div>
                        <div class="space-y-3 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Total Arrears</span>
                                <span class="font-medium text-red-600">{{ formatMoney(stats?.total_arrears) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Tenants in Arrears</span>
                                <span class="font-medium">{{ stats?.tenants_in_arrears ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Unreconciled</span>
                                <span class="font-medium">{{ stats?.unreconciled_count ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-100">
                            <Link :href="route('finances.arrears')" class="text-sm text-amber-600 hover:underline">Arrears</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.late-fees')" class="text-sm text-amber-600 hover:underline">Late Fees</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.reconciliation')" class="text-sm text-amber-600 hover:underline">Reconciliation</Link>
                        </div>
                    </div>

                    <!-- Reports & Settings Card -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <Cog6ToothIcon class="h-5 w-5 text-blue-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Reports & Settings</h3>
                        </div>
                        <p class="text-sm text-gray-500 mb-4">
                            View financial reports and configure payment settings.
                        </p>
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-100">
                            <Link :href="route('finances.reports')" class="text-sm text-blue-600 hover:underline">Reports</Link>
                            <span class="text-gray-300">|</span>
                            <Link :href="route('finances.settings')" class="text-sm text-blue-600 hover:underline">Settings</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
