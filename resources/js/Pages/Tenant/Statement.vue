<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    ArrowDownTrayIcon,
    EnvelopeIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';

interface StatementRow {
    date: string;
    description: string;
    reference: string | null;
    charge: number;
    payment: number;
    running_balance: number;
    kind: 'opening' | 'invoice' | 'payment' | 'closing';
}

const props = defineProps<{
    period: string;
    allowedPeriods: string[];
    from: string;
    to: string;
    rows: StatementRow[];
}>();

const changePeriod = (period: string) => {
    router.get(route('tenant.statement.index'), { period }, { preserveScroll: true });
};

const emailMe = () => {
    router.post(route('tenant.statement.email'), { period: props.period }, { preserveScroll: true });
};

const periodLabel = (key: string) => {
    const labels: Record<string, string> = {
        current_month: 'Current month',
        last_month: 'Last month',
        last_3_months: 'Last 3 months',
        year_to_date: 'Year to date',
    };
    return labels[key] ?? key;
};

const formatMoney = (value: number) => {
    if (value === 0) return '';
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatBalance = (value: number) => {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const totalCharges = computed(() =>
    props.rows.filter(r => r.kind === 'invoice').reduce((sum, r) => sum + r.charge, 0),
);
const totalPayments = computed(() =>
    props.rows.filter(r => r.kind === 'payment').reduce((sum, r) => sum + r.payment, 0),
);
const closingBalance = computed(() => {
    const closing = props.rows.find(r => r.kind === 'closing');
    return closing?.running_balance ?? 0;
});
</script>

<template>
    <Head title="Statement" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">My Statement</h1>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Controls -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-center gap-3 justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="key in props.allowedPeriods"
                            :key="key"
                            type="button"
                            @click="changePeriod(key)"
                            :class="[
                                'px-3 py-2 text-sm rounded-md border',
                                key === props.period
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-200 text-gray-700 hover:bg-gray-50',
                            ]"
                        >
                            {{ periodLabel(key) }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('tenant.statement.pdf', { period: props.period })"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50"
                        >
                            <DocumentTextIcon class="w-4 h-4" />
                            PDF
                        </Link>
                        <Link
                            :href="route('tenant.statement.xlsx', { period: props.period })"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Excel
                        </Link>
                        <PrimaryButton type="button" @click="emailMe">
                            <EnvelopeIcon class="w-4 h-4 me-2" />
                            Email me
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Summary -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-xs uppercase text-gray-500">Total charges</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">KES {{ formatBalance(totalCharges) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-xs uppercase text-gray-500">Total payments</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">KES {{ formatBalance(totalPayments) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-xs uppercase text-gray-500">Closing balance</p>
                        <p
                            class="mt-1 text-xl font-semibold"
                            :class="closingBalance > 0 ? 'text-red-600' : 'text-emerald-600'"
                        >KES {{ formatBalance(closingBalance) }}</p>
                    </div>
                </div>

                <!-- Statement table -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <caption class="sr-only">Tenant statement from {{ props.from }} to {{ props.to }}</caption>
                        <thead class="bg-gray-900">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-white">Date</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-white">Description</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-white">Reference</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-white">Charge</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-white">Payment</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-white">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(row, idx) in props.rows"
                                :key="idx"
                                :class="['opening', 'closing'].includes(row.kind) ? 'bg-gray-50 font-medium' : ''"
                            >
                                <td class="px-4 py-2 text-sm text-gray-700">{{ row.date }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ row.description }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ row.reference ?? '' }}</td>
                                <td class="px-4 py-2 text-sm text-end tabular-nums text-gray-700">{{ formatMoney(row.charge) }}</td>
                                <td class="px-4 py-2 text-sm text-end tabular-nums text-emerald-700">{{ formatMoney(row.payment) }}</td>
                                <td class="px-4 py-2 text-sm text-end tabular-nums text-gray-900">{{ formatBalance(row.running_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
