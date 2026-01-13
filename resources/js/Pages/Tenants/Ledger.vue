<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { useFormatters } from '@/composables';
import {
    ArrowLeftIcon,
    ArrowDownTrayIcon,
    EnvelopeIcon,
    FunnelIcon,
    DocumentTextIcon,
    CreditCardIcon,
    ArrowUturnLeftIcon,
    CalendarIcon,
    BanknotesIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    tenant: Object,
    activeLease: Object,
    transactions: Array,
    summary: Object,
    filters: Object,
});

const { formatMoney, formatDate } = useFormatters();

const showFilters = ref(false);
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const isExporting = ref(false);
const isEmailing = ref(false);

const breadcrumbItems = computed(() => [
    { label: 'Tenants', href: route('tenants.index') },
    { label: props.tenant.name, href: route('tenants.show', props.tenant.id) },
    { label: 'Statement' },
]);

const applyFilters = () => {
    router.get(route('tenants.ledger', props.tenant.id), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    dateFrom.value = '';
    dateTo.value = '';
    router.get(route('tenants.ledger', props.tenant.id), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const hasFilters = computed(() => {
    return props.filters?.date_from || props.filters?.date_to;
});

const downloadPdf = () => {
    isExporting.value = true;
    const params = new URLSearchParams();
    if (dateFrom.value) params.append('date_from', dateFrom.value);
    if (dateTo.value) params.append('date_to', dateTo.value);

    window.location.href = route('tenants.ledger.pdf', props.tenant.id) + '?' + params.toString();
    setTimeout(() => isExporting.value = false, 2000);
};

const emailStatement = () => {
    isEmailing.value = true;
    router.post(route('tenants.ledger.email', props.tenant.id), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveScroll: true,
        onFinish: () => isEmailing.value = false,
    });
};

const getTypeIcon = (type) => {
    switch (type) {
        case 'invoice': return DocumentTextIcon;
        case 'payment': return CreditCardIcon;
        case 'refund': return ArrowUturnLeftIcon;
        default: return BanknotesIcon;
    }
};

const getTypeColor = (type) => {
    switch (type) {
        case 'invoice': return 'text-amber-600 bg-amber-50';
        case 'payment': return 'text-emerald-600 bg-emerald-50';
        case 'refund': return 'text-red-600 bg-red-50';
        default: return 'text-gray-600 bg-gray-50';
    }
};

const getTypeLabel = (type) => {
    switch (type) {
        case 'invoice': return 'Invoice';
        case 'payment': return 'Payment';
        case 'refund': return 'Refund';
        default: return type;
    }
};
</script>

<template>
    <Head :title="`Statement - ${tenant.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tenants.show', tenant.id)" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                    <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Account Statement</h1>
                    <p class="text-sm text-gray-500">{{ tenant.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Total Invoiced</p>
                        <p class="text-xl font-semibold text-gray-900">{{ formatMoney(summary.total_invoiced) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Total Paid</p>
                        <p class="text-xl font-semibold text-emerald-600">{{ formatMoney(summary.total_paid) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Refunds</p>
                        <p class="text-xl font-semibold text-red-600">{{ formatMoney(summary.total_refunds) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Current Balance</p>
                        <p :class="['text-xl font-semibold', summary.current_balance > 0 ? 'text-red-600' : 'text-emerald-600']">
                            {{ formatMoney(Math.abs(summary.current_balance)) }}
                            <span v-if="summary.current_balance > 0" class="text-xs font-normal">owed</span>
                            <span v-else-if="summary.current_balance < 0" class="text-xs font-normal">credit</span>
                        </p>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="p-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <button
                                @click="showFilters = !showFilters"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                                    hasFilters
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : 'text-gray-700 hover:bg-gray-100 border border-gray-200'
                                ]"
                            >
                                <FunnelIcon class="w-4 h-4" />
                                Filters
                                <span v-if="hasFilters" class="ml-1 px-1.5 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded-full">
                                    Active
                                </span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="downloadPdf"
                                :disabled="isExporting"
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                            >
                                <ArrowDownTrayIcon class="w-4 h-4" />
                                {{ isExporting ? 'Generating...' : 'Download PDF' }}
                            </button>
                            <button
                                @click="emailStatement"
                                :disabled="isEmailing"
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                            >
                                <EnvelopeIcon class="w-4 h-4" />
                                {{ isEmailing ? 'Sending...' : 'Email Statement' }}
                            </button>
                        </div>
                    </div>

                    <!-- Filter Panel -->
                    <div v-if="showFilters" class="border-t border-gray-200 p-4 bg-gray-50">
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                                <input
                                    v-model="dateTo"
                                    type="date"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <button
                                @click="applyFilters"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                            >
                                Apply
                            </button>
                            <button
                                v-if="hasFilters"
                                @click="clearFilters"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="txn in transactions" :key="`${txn.type}-${txn.id}`" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(txn.date) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span :class="['inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full', getTypeColor(txn.type)]">
                                            <component :is="getTypeIcon(txn.type)" class="w-3 h-3" />
                                            {{ getTypeLabel(txn.type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ txn.description }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        {{ txn.reference }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        <span v-if="txn.debit > 0" class="text-red-600 font-medium">
                                            {{ formatMoney(txn.debit) }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        <span v-if="txn.credit > 0" class="text-emerald-600 font-medium">
                                            {{ formatMoney(txn.credit) }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                        <span :class="txn.running_balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                                            {{ formatMoney(Math.abs(txn.running_balance)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="transactions.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        <BanknotesIcon class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                        <p class="text-sm">No transactions found</p>
                                        <p v-if="hasFilters" class="text-xs mt-1">Try adjusting your date filters</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Info -->
                <div v-if="activeLease" class="mt-6 bg-gray-50 rounded-lg border border-gray-200 p-4">
                    <div class="flex flex-wrap gap-6 text-sm">
                        <div>
                            <span class="text-gray-500">Unit:</span>
                            <span class="ml-2 font-medium text-gray-900">
                                {{ activeLease.unit?.unit_number }} - {{ activeLease.unit?.building?.name }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">Deposit Held:</span>
                            <span class="ml-2 font-medium text-gray-900">{{ formatMoney(summary.deposit_held) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Wallet Balance:</span>
                            <span class="ml-2 font-medium text-emerald-600">{{ formatMoney(summary.wallet_balance) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
