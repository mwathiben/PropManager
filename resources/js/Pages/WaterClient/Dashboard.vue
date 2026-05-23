<script setup lang="ts">
/**
 * Phase-96 WATER-CLIENT-DASHBOARD: the water client's own dashboard. Each "water
 * line" (WaterConnection) renders the SAME shared Components/Water/* the tenant
 * self-service uses (Phase 93) — consumption history, usage/leak alert, charges —
 * keyed off the connection's meter instead of a lease. Charges arrive in Phase 97.
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WaterDisconnectionBanner from '@/Components/Water/WaterDisconnectionBanner.vue';
import WaterUsageAlert from '@/Components/Water/WaterUsageAlert.vue';
import WaterConsumptionCard from '@/Components/Water/WaterConsumptionCard.vue';
import WaterChargesCard from '@/Components/Water/WaterChargesCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon } from '@heroicons/vue/24/outline';

interface Point { label: string; value: number }
interface Summary { latest_consumption: number | null; latest_date: string | null; avg_monthly: number; ytd_consumption: number }
interface Alert { consumption: number; reading_date: string | null }
interface Charge { period: string | null; water_due: number; paid: boolean; status: string }
interface Disconnection { disconnected: boolean; reason: string | null }
interface Connection {
    id: number;
    identifier: string;
    status: string;
    billing_mode: string;
    meter: string | null;
    has_meter: boolean;
    effective_rate: number | null;
    history: Point[];
    summary: Summary | null;
    alert: Alert | null;
    charges: Charge[];
    disconnection: Disconnection;
}

withDefaults(defineProps<{ connections?: Connection[]; onboardingComplete?: boolean }>(), {
    connections: () => [],
    onboardingComplete: true,
});

const { t } = useI18n();
const { formatMoney } = useFormatters();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('water.client_dash.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-cyan-100 p-2"><BeakerIcon class="h-6 w-6 text-cyan-600" /></div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('water.client_dash.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('water.client_dash.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8" data-testid="water-client-dashboard">
            <Link
                v-if="!onboardingComplete"
                :href="route('onboarding.index')"
                class="mb-6 block rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-800 hover:bg-amber-100"
            >{{ t('water.client_dash.finish_onboarding') }}</Link>

            <div v-if="connections.length" class="space-y-8">
                <section v-for="c in connections" :key="c.id" class="space-y-4" :data-testid="`water-line-${c.id}`">
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-900">{{ c.identifier }}</p>
                            <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', c.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600']">{{ t(`water.clients.status_${c.status}`) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ t(`water.clients.mode_${c.billing_mode}`) }}</span>
                            <span v-if="c.meter">· {{ c.meter }}</span>
                            <span>· {{ t('water.client_dash.rate_label') }}:
                                <template v-if="c.effective_rate !== null">{{ formatMoney(c.effective_rate) }}<template v-if="c.billing_mode === 'metered'"> {{ t('water.client_dash.per_unit') }}</template></template>
                                <template v-else>{{ t('water.client_dash.rate_not_set') }}</template>
                            </span>
                        </div>
                    </div>

                    <template v-if="c.has_meter">
                        <WaterDisconnectionBanner :disconnected="c.disconnection.disconnected" :reason="c.disconnection.reason" :pay-url="null" />
                        <WaterUsageAlert :alert="c.alert" />
                        <WaterConsumptionCard :history="c.history" :summary="c.summary" />
                        <WaterChargesCard :charges="c.charges" />
                    </template>
                    <p v-else class="rounded-lg bg-white p-4 text-sm text-gray-500 shadow">
                        {{ c.billing_mode === 'flat_rate' ? t('water.client_dash.flat_rate_note') : t('water.client_dash.metering_pending') }}
                    </p>
                </section>
            </div>
            <p v-else class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">{{ t('water.client_dash.no_connection') }}</p>
        </div>
    </AuthenticatedLayout>
</template>
