<script setup lang="ts">
/**
 * Phase-103 OWNER-PAYOUTS: the landlord/PM's owner-detail page — set the management fee,
 * see the owner's running balance, record a payout, and review/void payout history.
 */
import { ref } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { ArrowLeftIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

interface Owner {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    has_login: boolean;
    management_fee_type: string;
    management_fee_value: number;
}
interface PayoutRow { id: number; amount: number; paid_on: string | null; method: string; reference: string | null; notes: string | null; voided: boolean }
interface Summary {
    lifetime_collected: number;
    lifetime_expenses: number;
    lifetime_management_fee: number;
    lifetime_net: number;
    total_paid_out: number;
    balance_due: number;
}
interface PropertyRow { id: number; name: string }

const props = withDefaults(defineProps<{ owner: Owner; summary?: Summary; payouts?: PayoutRow[]; properties?: PropertyRow[]; currencySymbol?: string }>(), {
    summary: () => ({ lifetime_collected: 0, lifetime_expenses: 0, lifetime_management_fee: 0, lifetime_net: 0, total_paid_out: 0, balance_due: 0 }),
    payouts: () => [],
    properties: () => [],
    currencySymbol: '',
});

const { t } = useI18n();
const { formatMoney } = useFormatters();
const page = usePage();

const methods = ['bank_transfer', 'mpesa', 'cheque', 'cash', 'other'];

// Only the fee fields here — name is required by the update request; is_active/id_number/
// notes are intentionally omitted so a fee save can't silently flip the owner's other state.
const feeForm = useForm({
    name: props.owner.name,
    management_fee_type: props.owner.management_fee_type || 'none',
    management_fee_value: props.owner.management_fee_value || 0,
});

const saveFee = () => {
    feeForm.put(route('finances.owners.update', props.owner.id), { preserveScroll: true });
};

const payoutForm = useForm({
    amount: '' as number | string,
    paid_on: new Date().toISOString().slice(0, 10),
    method: 'bank_transfer',
    reference: '',
    notes: '',
});

const recordPayout = () => {
    payoutForm.post(route('finances.owners.payouts.store', props.owner.id), {
        preserveScroll: true,
        onSuccess: () => payoutForm.reset('amount', 'reference', 'notes'),
    });
};

const voidPayout = (payout: PayoutRow) => {
    if (confirm(t('owners.payouts.void_confirm'))) {
        router.post(route('finances.owners.payouts.void', { owner: props.owner.id, payout: payout.id }), {}, { preserveScroll: true });
    }
};

const showPayoutForm = ref(false);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="owner.name" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('finances.owners.index')" class="rounded p-1.5 text-gray-500 hover:bg-gray-100"><ArrowLeftIcon class="h-5 w-5" /></Link>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ owner.name }}</h1>
                        <p class="text-sm text-gray-500">{{ owner.email || '—' }}</p>
                    </div>
                </div>
                <a
                    :href="route('finances.owners.statement', { owner: owner.id, period: '12' })"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <DocumentArrowDownIcon class="h-4 w-4" />
                    {{ t('owners.actions.download_statement') }}
                </a>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-testid="owner-show">
            <div v-if="(page.props.flash as any)?.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700" data-testid="flash-success">{{ (page.props.flash as any).success }}</div>
            <div v-if="(page.props.flash as any)?.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700" data-testid="flash-error">{{ (page.props.flash as any).error }}</div>

            <!-- Balance summary -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.lifetime_net') }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatMoney(summary.lifetime_net) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.management_fee') }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatMoney(summary.lifetime_management_fee) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.paid_out') }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatMoney(summary.total_paid_out) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase text-gray-400">{{ t('owners.payouts.balance') }}</p>
                    <p class="mt-1 text-lg font-semibold" :class="summary.balance_due < 0 ? 'text-amber-600' : 'text-emerald-700'">{{ formatMoney(summary.balance_due) }}</p>
                </div>
            </div>

            <p v-if="summary.balance_due < 0" class="rounded-md bg-amber-50 p-3 text-sm text-amber-700">
                {{ t('owners.payouts.advance_note_landlord') }}
            </p>

            <!-- Management fee editor -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('owners.payouts.management_fee') }}</h2>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveFee">
                    <div>
                        <label class="block text-xs font-medium text-gray-600" for="fee-type">{{ t('owners.payouts.fee_type') }}</label>
                        <select id="fee-type" v-model="feeForm.management_fee_type" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="none">{{ t('owners.payouts.fee_none') }}</option>
                            <option value="percentage">{{ t('owners.payouts.fee_percentage') }}</option>
                            <option value="flat">{{ t('owners.payouts.fee_flat') }}</option>
                        </select>
                    </div>
                    <div v-if="feeForm.management_fee_type !== 'none'">
                        <label class="block text-xs font-medium text-gray-600" for="fee-value">{{ feeForm.management_fee_type === 'percentage' ? t('owners.payouts.fee_value_pct') : t('owners.payouts.fee_value_flat') }}</label>
                        <input id="fee-value" v-model="feeForm.management_fee_value" type="number" step="0.01" min="0" class="mt-1 w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="feeForm.errors.management_fee_value" class="mt-1 text-xs text-rose-600">{{ feeForm.errors.management_fee_value }}</p>
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" :disabled="feeForm.processing">{{ t('owners.actions.save') }}</button>
                </form>
            </div>

            <!-- Record payout -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ t('owners.payouts.record') }}</h2>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-700" data-testid="payout-toggle" @click="showPayoutForm = !showPayoutForm">
                        {{ showPayoutForm ? t('owners.actions.cancel') : t('owners.payouts.record') }}
                    </button>
                </div>
                <form v-if="showPayoutForm" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="recordPayout">
                    <div>
                        <label class="block text-xs font-medium text-gray-600" for="payout-amount">{{ t('owners.payouts.amount') }}</label>
                        <input id="payout-amount" v-model="payoutForm.amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required />
                        <p v-if="payoutForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ payoutForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600" for="payout-date">{{ t('owners.payouts.paid_on') }}</label>
                        <input id="payout-date" v-model="payoutForm.paid_on" type="date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600" for="payout-method">{{ t('owners.payouts.method') }}</label>
                        <select id="payout-method" v-model="payoutForm.method" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="m in methods" :key="m" :value="m">{{ t(`owners.payouts.methods.${m}`) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600" for="payout-ref">{{ t('owners.payouts.reference') }}</label>
                        <input id="payout-ref" v-model="payoutForm.reference" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600" for="payout-notes">{{ t('owners.payouts.notes') }}</label>
                        <textarea id="payout-notes" v-model="payoutForm.notes" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" :disabled="payoutForm.processing" data-testid="payout-submit">{{ t('owners.payouts.record') }}</button>
                    </div>
                </form>
            </div>

            <!-- Payout history -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">{{ t('owners.payouts.history') }}</h2>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ t('owners.payouts.paid_on') }}</th>
                            <th class="px-4 py-2">{{ t('owners.payouts.method') }}</th>
                            <th class="px-4 py-2">{{ t('owners.payouts.reference') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('owners.payouts.amount') }}</th>
                            <th class="px-4 py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in payouts" :key="p.id" :class="p.voided ? 'opacity-50' : ''" :data-testid="`payout-row-${p.id}`">
                            <td class="px-4 py-2 text-gray-700">{{ p.paid_on }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ t(`owners.payouts.methods.${p.method}`) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ p.reference || '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium" :class="p.voided ? 'text-gray-400 line-through' : 'text-gray-900'">{{ formatMoney(p.amount) }}</td>
                            <td class="px-4 py-2 text-right">
                                <span v-if="p.voided" class="text-xs text-gray-400">{{ t('owners.payouts.voided') }}</span>
                                <button v-else type="button" class="text-xs font-medium text-rose-600 hover:text-rose-700" @click="voidPayout(p)">{{ t('owners.payouts.void') }}</button>
                            </td>
                        </tr>
                        <tr v-if="!payouts.length">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ t('owners.payouts.none') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Properties -->
            <div v-if="properties.length" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ t('owners.fields.properties') }}</h2>
                <ul class="space-y-1 text-sm text-gray-700">
                    <li v-for="prop in properties" :key="prop.id">{{ prop.name }}</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
