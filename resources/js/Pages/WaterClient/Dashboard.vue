<script setup lang="ts">
/**
 * Phase-95 WATER-CLIENT-ONBOARDING: the landing for a water client. A minimal
 * shell — their water line(s) + an onboarding nudge. Phase 96 enriches it with the
 * shared Components/Water/* (consumption history, charges, leak alert).
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon } from '@heroicons/vue/24/outline';

interface Connection { id: number; identifier: string; status: string; billing_mode: string; meter: string | null }

withDefaults(defineProps<{ connections?: Connection[]; onboardingComplete?: boolean }>(), {
    connections: () => [],
    onboardingComplete: true,
});

const { t } = useI18n();
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

            <div v-if="connections.length" class="space-y-3">
                <div v-for="c in connections" :key="c.id" class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-gray-900">{{ c.identifier }}</p>
                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', c.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600']">{{ t(`water.clients.status_${c.status}`) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t(`water.clients.mode_${c.billing_mode}`) }}
                        <template v-if="c.meter"> · {{ c.meter }}</template>
                    </p>
                </div>
            </div>
            <p v-else class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">{{ t('water.client_dash.no_connection') }}</p>

            <p class="mt-6 text-center text-xs text-gray-400">{{ t('water.client_dash.more_soon') }}</p>
        </div>
    </AuthenticatedLayout>
</template>
