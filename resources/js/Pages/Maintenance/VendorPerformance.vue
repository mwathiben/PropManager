<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';

interface VendorRow {
    vendor_id: number;
    name: string;
    resolved_count: number;
    within_sla_pct: number | null;
    avg_resolution_hours: number | null;
    open_overdue: number;
    cost_total_cents: number;
    cost_per_ticket_cents: number | null;
}

const props = defineProps<{
    vendors: VendorRow[];
    window: number;
    windows: number[];
}>();

const { formatMoney } = useFormatters();
const { t } = useI18n();

const sortKey = ref<keyof VendorRow>('within_sla_pct');
const sortDir = ref<'asc' | 'desc'>('desc');

const sorted = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...props.vendors].sort((a, b) => {
        const av = a[sortKey.value];
        const bv = b[sortKey.value];
        if (av === bv) return 0;
        if (av === null) return 1;
        if (bv === null) return -1;
        return (Number(av) - Number(bv)) * dir;
    });
});

function setSort(key: keyof VendorRow): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

function setWindow(w: number): void {
    router.get(route('maintenance.vendor-performance'), { window: w }, { preserveScroll: true });
}

const money = (cents: number | null): string => (cents === null ? '—' : formatMoney(cents / 100));
const pct = (v: number | null): string => (v === null ? '—' : `${v}%`);
const hours = (v: number | null): string => (v === null ? '—' : `${v}h`);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('vendors.performance.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <WrenchScrewdriverIcon class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('vendors.performance.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('vendors.performance.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8 space-y-4" data-testid="vendor-performance">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ t('vendors.performance.window') }}:</span>
                <button
                    v-for="w in windows"
                    :key="w"
                    type="button"
                    class="rounded-md px-3 py-1 text-xs font-medium"
                    :class="w === window ? 'bg-orange-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'"
                    @click="setWindow(w)"
                >
                    {{ t('vendors.performance.days', { count: w }) }}
                </button>
            </div>

            <div v-if="vendors.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('vendors.performance.empty') }}
            </div>

            <table v-else class="min-w-full overflow-hidden rounded-lg bg-white shadow text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('vendors.performance.col_vendor') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('within_sla_pct')">{{ t('vendors.performance.col_within_sla') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('avg_resolution_hours')">{{ t('vendors.performance.col_avg_resolution') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('resolved_count')">{{ t('vendors.performance.col_resolved') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('open_overdue')">{{ t('vendors.performance.col_overdue') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('cost_per_ticket_cents')">{{ t('vendors.performance.col_cost_per_ticket') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="v in sorted" :key="v.vendor_id" data-testid="vendor-perf-row" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ v.name }}</td>
                        <td class="px-4 py-3 text-end" :class="v.within_sla_pct !== null && v.within_sla_pct < 85 ? 'text-rose-600' : 'text-emerald-700'">{{ pct(v.within_sla_pct) }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ hours(v.avg_resolution_hours) }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ v.resolved_count }}</td>
                        <td class="px-4 py-3 text-end" :class="v.open_overdue > 0 ? 'text-rose-600 font-medium' : 'text-gray-600'">{{ v.open_overdue }}</td>
                        <td class="px-4 py-3 text-end text-gray-900">{{ money(v.cost_per_ticket_cents) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
