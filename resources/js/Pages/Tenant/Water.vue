<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WaterDisconnectionBanner from '@/Components/Water/WaterDisconnectionBanner.vue';
import WaterUsageAlert from '@/Components/Water/WaterUsageAlert.vue';
import WaterConsumptionCard from '@/Components/Water/WaterConsumptionCard.vue';
import WaterChargesCard from '@/Components/Water/WaterChargesCard.vue';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon } from '@heroicons/vue/24/outline';

interface Reading { id: number; reading_date: string; consumption: number | string; cost: number | string; status: string }
interface Point { label: string; value: number }
interface Summary { latest_consumption: number | null; latest_date: string | null; avg_monthly: number; ytd_consumption: number }
interface Alert { consumption: number; reading_date: string | null }
interface Charge { period: string | null; water_due: number; paid: boolean; status: string }

withDefaults(defineProps<{
    hasUnit: boolean;
    readings: Reading[];
    history?: Point[];
    summary?: Summary | null;
    alert?: Alert | null;
    charges?: Charge[];
    meterDisconnected?: boolean;
    disconnectReason?: string | null;
    payUrl?: string | null;
}>(), {
    history: () => [],
    summary: null,
    alert: null,
    charges: () => [],
    meterDisconnected: false,
    disconnectReason: null,
    payUrl: null,
});

const { t } = useI18n();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('water.tenant.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-100 rounded-lg">
                    <BeakerIcon class="w-6 h-6 text-cyan-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('water.tenant.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('water.tenant.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8" data-testid="tenant-water">
            <WaterDisconnectionBanner :disconnected="meterDisconnected" :reason="disconnectReason" :pay-url="payUrl" />
            <WaterUsageAlert :alert="alert" />

            <p v-if="!hasUnit" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('water.tenant.empty') }}
            </p>

            <div v-else class="space-y-6">
                <WaterConsumptionCard :history="history" :summary="summary" :readings="readings" />
                <WaterChargesCard :charges="charges" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
