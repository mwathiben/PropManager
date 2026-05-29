<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { CubeIcon, BoltIcon, BanknotesIcon, TrashIcon } from '@heroicons/vue/24/outline';

interface PriceRow {
    cost_per_unit_cents: number;
    source: string;
    effective_at: string | null;
}

interface SupplierRow {
    id: number;
    vendor_id: number;
    vendor_name: string | null;
    unit_cost_cents: number;
    lead_time_days: number;
    min_order_qty: number;
    is_cheapest: boolean;
    is_fastest: boolean;
}

interface PartRow {
    id: number;
    name: string;
    sku: string | null;
    category: string | null;
    cost_per_unit_cents: number;
    qty_available: number;
    reorder_threshold: number;
    price_history: PriceRow[];
    suppliers: SupplierRow[];
}

interface VendorOption {
    id: number;
    name: string;
}

const props = defineProps<{
    parts: PartRow[];
    vendors: VendorOption[];
}>();

const { formatMoney } = useFormatters();
const { t } = useI18n();

const expanded = ref<number | null>(null);
const draft = reactive({ vendor_id: '', unit_cost: '', lead_time_days: '7', min_order_qty: '1' });

const money = (cents: number): string => formatMoney(cents / 100);

function resetDraft(): void {
    draft.vendor_id = '';
    draft.unit_cost = '';
    draft.lead_time_days = '7';
    draft.min_order_qty = '1';
}

function toggle(id: number): void {
    expanded.value = expanded.value === id ? null : id;
    resetDraft();
}

function sparkPoints(history: PriceRow[]): string {
    if (history.length < 2) return '';
    const costs = [...history].reverse().map((h) => h.cost_per_unit_cents);
    const min = Math.min(...costs);
    const max = Math.max(...costs);
    const span = max - min || 1;
    const step = 100 / (costs.length - 1);
    return costs
        .map((c, i) => `${(i * step).toFixed(1)},${(28 - ((c - min) / span) * 24 - 2).toFixed(1)}`)
        .join(' ');
}

function addSupplier(partId: number): void {
    router.post(
        route('parts.suppliers.store', partId),
        {
            vendor_id: draft.vendor_id,
            unit_cost_cents: Math.round(Number(draft.unit_cost) * 100),
            lead_time_days: draft.lead_time_days,
            min_order_qty: draft.min_order_qty,
        },
        {
            preserveScroll: true,
            onSuccess: resetDraft,
        },
    );
}

function removeSupplier(partId: number, supplierId: number): void {
    router.delete(route('parts.suppliers.destroy', [partId, supplierId]), { preserveScroll: true });
}

const hasParts = computed(() => props.parts.length > 0);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('parts.pricing.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <CubeIcon class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('parts.pricing.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('parts.pricing.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-3" data-testid="parts-pricing">
            <div v-if="!hasParts" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('parts.pricing.empty') }}
            </div>

            <div
                v-for="part in parts"
                :key="part.id"
                data-testid="part-pricing-row"
                class="rounded-lg bg-white shadow"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-4 py-3 text-start hover:bg-gray-50"
                    @click="toggle(part.id)"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-900">{{ part.name }}</p>
                        <p class="text-xs text-gray-500">
                            <span v-if="part.sku">{{ part.sku }} · </span>{{ part.category || '—' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-6 text-end">
                        <div>
                            <p class="text-xs text-gray-400">{{ t('parts.pricing.current_cost') }}</p>
                            <p class="text-sm font-semibold text-gray-900">{{ money(part.cost_per_unit_cents) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">{{ t('parts.pricing.in_stock') }}</p>
                            <p class="text-sm text-gray-700">{{ part.qty_available }}</p>
                        </div>
                        <svg
                            v-if="part.price_history.length >= 2"
                            viewBox="0 0 100 28"
                            class="h-7 w-24 text-orange-500"
                            preserveAspectRatio="none"
                        >
                            <polyline
                                :points="sparkPoints(part.price_history)"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                    </div>
                </button>

                <div v-if="expanded === part.id" class="border-t border-gray-100 px-4 py-4 space-y-5">
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase text-gray-500">{{ t('parts.pricing.history_title') }}</h3>
                        <p v-if="part.price_history.length === 0" class="text-sm text-gray-400">{{ t('parts.pricing.history_empty') }}</p>
                        <ul v-else class="divide-y divide-gray-50 text-sm">
                            <li v-for="(row, i) in part.price_history" :key="i" class="flex items-center justify-between py-1.5">
                                <span class="text-gray-900">{{ money(row.cost_per_unit_cents) }}</span>
                                <span class="text-xs text-gray-400">
                                    {{ t('parts.pricing.source.' + row.source) }} · {{ row.effective_at?.slice(0, 10) }}
                                </span>
                            </li>
                        </ul>
                    </section>

                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase text-gray-500">{{ t('parts.pricing.suppliers_title') }}</h3>
                        <p v-if="part.suppliers.length === 0" class="text-sm text-gray-400">{{ t('parts.pricing.suppliers_empty') }}</p>
                        <table v-else class="min-w-full text-sm">
                            <thead class="text-xs font-medium uppercase text-gray-400">
                                <tr>
                                    <th class="py-1.5 text-start">{{ t('parts.pricing.col_supplier') }}</th>
                                    <th class="py-1.5 text-end">{{ t('parts.pricing.col_unit_cost') }}</th>
                                    <th class="py-1.5 text-end">{{ t('parts.pricing.col_lead_time') }}</th>
                                    <th class="py-1.5 text-end">{{ t('parts.pricing.col_min_order') }}</th>
                                    <th class="py-1.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="s in part.suppliers" :key="s.id">
                                    <td class="py-1.5 text-gray-900">
                                        {{ s.vendor_name }}
                                        <span v-if="s.is_cheapest" class="ms-1 inline-flex items-center gap-0.5 rounded bg-emerald-50 px-1 text-xs text-emerald-700">
                                            <BanknotesIcon class="h-3 w-3" />{{ t('parts.pricing.cheapest') }}
                                        </span>
                                        <span v-if="s.is_fastest" class="ms-1 inline-flex items-center gap-0.5 rounded bg-sky-50 px-1 text-xs text-sky-700">
                                            <BoltIcon class="h-3 w-3" />{{ t('parts.pricing.fastest') }}
                                        </span>
                                    </td>
                                    <td class="py-1.5 text-end text-gray-900">{{ money(s.unit_cost_cents) }}</td>
                                    <td class="py-1.5 text-end text-gray-600">{{ t('parts.pricing.days', { count: s.lead_time_days }) }}</td>
                                    <td class="py-1.5 text-end text-gray-600">{{ s.min_order_qty }}</td>
                                    <td class="py-1.5 text-end">
                                        <button type="button" class="text-gray-300 hover:text-rose-600" @click="removeSupplier(part.id, s.id)">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="addSupplier(part.id)">
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('parts.pricing.col_supplier') }}
                            <select v-model="draft.vendor_id" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                <option value="" disabled>{{ t('parts.pricing.select_vendor') }}</option>
                                <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('parts.pricing.unit_cost_label') }}
                            <input v-model="draft.unit_cost" type="number" min="0" step="0.01" required class="mt-0.5 w-28 rounded-md border-gray-300 text-sm" />
                        </label>
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('parts.pricing.lead_time_label') }}
                            <input v-model="draft.lead_time_days" type="number" min="0" required class="mt-0.5 w-24 rounded-md border-gray-300 text-sm" />
                        </label>
                        <label class="flex flex-col text-xs text-gray-500">
                            {{ t('parts.pricing.min_order_label') }}
                            <input v-model="draft.min_order_qty" type="number" min="1" required class="mt-0.5 w-24 rounded-md border-gray-300 text-sm" />
                        </label>
                        <button type="submit" class="rounded-md bg-orange-600 px-3 py-2 text-sm font-medium text-white hover:bg-orange-700">
                            {{ t('parts.pricing.save') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
