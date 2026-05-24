<script setup lang="ts">
/**
 * Phase-102 OWNER-PORTAL: the owner's consolidated statement — totals, per-property
 * breakdown, expenses by category, a period picker, and a PDF download.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

interface PropertyRow { name: string; collected: number; expenses: number; net: number }
interface Statement {
    owner: { id: number; name: string };
    period: { start: string; end: string };
    collected: number;
    expenses: { category: string; amount: number }[];
    total_expenses: number;
    net: number;
    properties: PropertyRow[];
}

const props = withDefaults(defineProps<{ statement: Statement; currencySymbol?: string; period?: string }>(), {
    currencySymbol: '',
    period: '12',
});

const { t } = useI18n();
const { formatMoney } = useFormatters();

const period = ref(props.period);
const periodOptions = [
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: '3', label: 'Last 3 Months' },
    { value: '6', label: 'Last 6 Months' },
    { value: '12', label: 'Last 12 Months' },
];

const applyPeriod = () => {
    router.get(route('owner-portal.statements'), { period: period.value }, { preserveState: false, preserveScroll: true });
};

const download = () => {
    window.location.href = route('owner-portal.statements.download', { period: period.value });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('owners.portal.statements_title')" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.portal.statements_title') }}</h1>
                <div class="flex items-center gap-2">
                    <select v-model="period" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm" @change="applyPeriod">
                        <option v-for="o in periodOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        data-testid="owner-statement-download"
                        @click="download"
                    >
                        <DocumentArrowDownIcon class="h-4 w-4" />
                        {{ t('owners.portal.download') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6 lg:px-8" data-testid="owner-statements">
            <p class="text-sm text-gray-500">{{ statement.period.start }} – {{ statement.period.end }}</p>

            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.collected') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ formatMoney(statement.collected) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.expenses') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ formatMoney(statement.total_expenses) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.net') }}</p>
                    <p class="mt-1 text-xl font-semibold" :class="statement.net < 0 ? 'text-rose-600' : 'text-emerald-700'">{{ formatMoney(statement.net) }}</p>
                </div>
            </div>

            <div v-if="statement.properties.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ t('owners.fields.properties') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('owners.portal.collected') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('owners.portal.expenses') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('owners.portal.net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in statement.properties" :key="p.name">
                            <td class="px-4 py-2 text-gray-900">{{ p.name }}</td>
                            <td class="px-4 py-2 text-right">{{ formatMoney(p.collected) }}</td>
                            <td class="px-4 py-2 text-right">{{ formatMoney(p.expenses) }}</td>
                            <td class="px-4 py-2 text-right" :class="p.net < 0 ? 'text-rose-600' : ''">{{ formatMoney(p.net) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
