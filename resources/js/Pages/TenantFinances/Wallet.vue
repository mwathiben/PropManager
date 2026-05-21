<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { WalletIcon } from '@heroicons/vue/24/outline';

interface Balance {
    currency: string;
    balance: number;
}
interface LedgerRow {
    id: number;
    type: string;
    amount: number;
    currency: string;
    reason: string | null;
    balance_after: number;
    created_at: string | null;
}
interface InvoiceRow {
    id: number;
    invoice_number: string;
    currency: string;
    outstanding: number;
    due_date: string | null;
}

defineProps<{
    hasLease: boolean;
    balances: Balance[];
    ledger: LedgerRow[];
    invoices: InvoiceRow[];
}>();

const { t } = useI18n();

const selected = ref<number | ''>('');
const amount = ref('');

function apply(): void {
    if (!selected.value) return;
    router.post(
        route('tenant.wallet.apply'),
        { invoice_id: selected.value, amount: amount.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = '';
                amount.value = '';
            },
        },
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('tenant.wallet.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <WalletIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('tenant.wallet.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('tenant.wallet.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8 space-y-5" data-testid="tenant-wallet">
            <p v-if="!hasLease" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('tenant.wallet.no_lease') }}
            </p>

            <template v-else>
                <section class="rounded-lg bg-white p-5 shadow">
                    <h2 class="mb-3 text-xs font-semibold uppercase text-gray-500">{{ t('tenant.wallet.balance_heading') }}</h2>
                    <p v-if="balances.length === 0" class="text-sm text-gray-400">{{ t('tenant.wallet.no_balance') }}</p>
                    <div v-else class="flex flex-wrap gap-4">
                        <div v-for="b in balances" :key="b.currency" class="rounded-lg bg-emerald-50 px-4 py-3">
                            <p class="text-xs text-emerald-700">{{ b.currency }}</p>
                            <p class="text-xl font-semibold text-emerald-900">{{ b.balance.toFixed(2) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow">
                    <h2 class="mb-3 text-xs font-semibold uppercase text-gray-500">{{ t('tenant.wallet.apply_heading') }}</h2>
                    <p v-if="invoices.length === 0" class="text-sm text-gray-400">{{ t('tenant.wallet.no_invoices') }}</p>
                    <form v-else class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('tenant.wallet.apply_heading') }}
                            <select v-model="selected" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                <option value="" disabled>—</option>
                                <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                                    {{ inv.invoice_number }} · {{ inv.currency }} {{ inv.outstanding.toFixed(2) }}
                                </option>
                            </select>
                        </label>
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('tenant.wallet.apply_amount') }}
                            <input v-model="amount" type="number" min="0.01" step="0.01" class="mt-0.5 w-32 rounded-md border-gray-300 text-sm" />
                        </label>
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            {{ t('tenant.wallet.apply_button') }}
                        </button>
                    </form>
                </section>

                <section class="rounded-lg bg-white p-5 shadow">
                    <h2 class="mb-3 text-xs font-semibold uppercase text-gray-500">{{ t('tenant.wallet.ledger_heading') }}</h2>
                    <p v-if="ledger.length === 0" class="text-sm text-gray-400">{{ t('tenant.wallet.no_ledger') }}</p>
                    <table v-else class="min-w-full text-sm">
                        <thead class="text-xs font-medium uppercase text-gray-400">
                            <tr>
                                <th class="py-1.5 text-start">{{ t('tenant.wallet.col_date') }}</th>
                                <th class="py-1.5 text-start">{{ t('tenant.wallet.col_type') }}</th>
                                <th class="py-1.5 text-end">{{ t('tenant.wallet.col_amount') }}</th>
                                <th class="py-1.5 text-start">{{ t('tenant.wallet.col_reason') }}</th>
                                <th class="py-1.5 text-end">{{ t('tenant.wallet.col_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="row in ledger" :key="row.id">
                                <td class="py-1.5 text-gray-600">{{ row.created_at }}</td>
                                <td class="py-1.5">
                                    <span :class="row.type === 'credit' ? 'text-emerald-700' : 'text-gray-700'">
                                        {{ row.type === 'credit' ? t('tenant.wallet.type_credit') : t('tenant.wallet.type_debit') }}
                                    </span>
                                </td>
                                <td class="py-1.5 text-end text-gray-900">{{ row.currency }} {{ row.amount.toFixed(2) }}</td>
                                <td class="py-1.5 text-gray-500">{{ row.reason }}</td>
                                <td class="py-1.5 text-end text-gray-600">{{ row.balance_after.toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
