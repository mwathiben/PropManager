<script setup lang="ts">
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import VendorPortalLayout from '@/Layouts/VendorPortalLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

interface Line { amount_cents: number }
interface Props {
    vendor: { id: number; name: string };
    period: { from: string; to: string };
    statement: {
        ticket_costs: Array<{ ticket_id: number; title: string; amount_cents: number; recorded_at: string | null }>;
        expenses: Array<{ id: number; description: string | null; amount_cents: number; expense_date: string | null }>;
        ticket_costs_total_cents: number;
        expenses_total_cents: number;
        total_cents: number;
    };
}

const props = defineProps<Props>();
const { t } = useI18n();
const { formatMoney, formatDate } = useFormatters();

const kes = (cents: number) => formatMoney(cents / 100);

const filter = reactive({ from: props.period.from, to: props.period.to });
const apply = () => router.get('/v/portal/statement', { from: filter.from, to: filter.to }, { preserveState: true });

const exportUrl = `/v/portal/statement/export?from=${props.period.from}&to=${props.period.to}`;
const isEmpty = (props.statement.ticket_costs.length + props.statement.expenses.length) === 0;
</script>

<template>
    <Head :title="t('vendor_portal.statement.title')" />
    <VendorPortalLayout :vendor-name="vendor.name">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900">{{ t('vendor_portal.statement.title') }}</h1>
            <a
                v-if="!isEmpty"
                :href="exportUrl"
                class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
                data-testid="vendor-statement-export"
            >
                <ArrowDownTrayIcon class="h-4 w-4" /> {{ t('vendor_portal.statement.export') }}
            </a>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-gray-600">{{ t('vendor_portal.statement.from') }}
                <input v-model="filter.from" type="date" class="mt-1 block rounded-lg border-gray-200 text-sm" />
            </label>
            <label class="text-sm text-gray-600">{{ t('vendor_portal.statement.to') }}
                <input v-model="filter.to" type="date" class="mt-1 block rounded-lg border-gray-200 text-sm" />
            </label>
            <button type="button" @click="apply" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
                {{ t('vendor_portal.statement.apply') }}
            </button>
        </div>

        <div v-if="isEmpty" class="mt-6 rounded-2xl bg-white p-10 text-center text-gray-500 ring-1 ring-gray-100">
            {{ t('vendor_portal.statement.empty') }}
        </div>

        <div v-else class="mt-6 space-y-6" data-testid="vendor-statement">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                <h2 class="font-medium text-gray-900">{{ t('vendor_portal.statement.ticket_costs') }}</h2>
                <table class="mt-3 min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in statement.ticket_costs" :key="'tc' + row.ticket_id + row.recorded_at">
                            <td class="py-2 text-gray-700">{{ row.title }} (#{{ row.ticket_id }})</td>
                            <td class="py-2 text-gray-400">{{ formatDate(row.recorded_at) }}</td>
                            <td class="py-2 text-end font-medium text-gray-900">{{ kes(row.amount_cents) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-end text-sm text-gray-600">{{ t('vendor_portal.statement.total') }}: {{ kes(statement.ticket_costs_total_cents) }}</p>
            </section>

            <section v-if="statement.expenses.length" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                <h2 class="font-medium text-gray-900">{{ t('vendor_portal.statement.expenses') }}</h2>
                <table class="mt-3 min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in statement.expenses" :key="'ex' + row.id">
                            <td class="py-2 text-gray-700">{{ row.description }}</td>
                            <td class="py-2 text-gray-400">{{ formatDate(row.expense_date) }}</td>
                            <td class="py-2 text-end font-medium text-gray-900">{{ kes(row.amount_cents) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-end text-sm text-gray-600">{{ t('vendor_portal.statement.total') }}: {{ kes(statement.expenses_total_cents) }}</p>
            </section>

            <div class="rounded-2xl bg-indigo-600 p-5 text-white">
                <p class="text-sm">{{ t('vendor_portal.statement.total') }}</p>
                <p class="text-3xl font-bold">{{ kes(statement.total_cents) }}</p>
            </div>
        </div>
    </VendorPortalLayout>
</template>
