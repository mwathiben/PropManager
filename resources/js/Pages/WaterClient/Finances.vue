<script setup lang="ts">
/**
 * Phase-97 WATER-CLIENT-BILLING: the water client's own charges + outstanding
 * balance. The destination of the dashboard "pay" link. Read-only — a neighbour
 * settles with the supplier, who records the payment.
 */
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WaterChargesCard from '@/Components/Water/WaterChargesCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

interface Charge { period: string | null; water_due: number; paid: boolean; status: string }
interface Line { id: number; identifier: string; status: string; outstanding: number; charges: Charge[] }

const props = withDefaults(defineProps<{ lines?: Line[]; totalOutstanding?: number; supplierName?: string | null }>(), {
    lines: () => [],
    totalOutstanding: 0,
    supplierName: null,
});

const { t } = useI18n();
const { formatMoney } = useFormatters();

const howToPay = props.supplierName
    ? t('water.client_finances.how_to_pay', { supplier: props.supplierName })
    : t('water.client_finances.how_to_pay_generic');
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('water.client_finances.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-cyan-100 p-2"><BanknotesIcon class="h-6 w-6 text-cyan-600" /></div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('water.client_finances.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('water.client_finances.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-testid="water-client-finances">
            <div
                :class="['rounded-xl border p-4', totalOutstanding > 0 ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50']"
            >
                <p class="text-xs font-medium" :class="totalOutstanding > 0 ? 'text-amber-700' : 'text-emerald-700'">
                    {{ t('water.client_finances.total_outstanding') }}
                </p>
                <p class="mt-1 text-2xl font-semibold" :class="totalOutstanding > 0 ? 'text-amber-900' : 'text-emerald-900'">
                    {{ formatMoney(totalOutstanding) }}
                </p>
                <p v-if="totalOutstanding > 0" class="mt-2 text-xs text-amber-800">{{ howToPay }}</p>
                <p v-else class="mt-2 text-xs text-emerald-800">{{ t('water.client_finances.all_settled') }}</p>
            </div>

            <p v-if="!lines.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('water.client_finances.no_charges') }}
            </p>

            <section v-for="line in lines" :key="line.id" class="space-y-3" :data-testid="`water-line-finances-${line.id}`">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4">
                    <p class="font-semibold text-gray-900">{{ line.identifier }}</p>
                    <span class="text-sm text-gray-600">
                        {{ t('water.client_finances.outstanding') }}: <span class="font-semibold text-gray-900">{{ formatMoney(line.outstanding) }}</span>
                    </span>
                </div>
                <WaterChargesCard :charges="line.charges" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
