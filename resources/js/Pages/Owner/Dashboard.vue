<script setup lang="ts">
/**
 * Phase-102 OWNER-PORTAL: the owner's read-only view of the properties a PM manages
 * for them — occupancy + rent-roll at a glance, plus a link to their statements.
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BuildingOffice2Icon, DocumentTextIcon } from '@heroicons/vue/24/outline';

interface PropertyMetrics {
    property_id: number;
    name: string;
    unit_count: number;
    occupied_count: number;
    occupancy_pct: number;
    monthly_rent_roll: number;
    outstanding_arrears: number;
}

withDefaults(defineProps<{ owner?: { name: string }; properties?: PropertyMetrics[] }>(), {
    owner: () => ({ name: '' }),
    properties: () => [],
});

const { t } = useI18n();
const { formatMoney } = useFormatters();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('owners.portal.dashboard_title')" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-100 p-2"><BuildingOffice2Icon class="h-6 w-6 text-indigo-600" /></div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.portal.dashboard_title') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('owners.portal.dashboard_subtitle') }}</p>
                    </div>
                </div>
                <Link
                    :href="route('owner-portal.statements')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <DocumentTextIcon class="h-4 w-4" />
                    {{ t('owners.portal.statements_title') }}
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8" data-testid="owner-dashboard">
            <p v-if="!properties.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('owners.portal.no_properties') }}
            </p>

            <div v-for="p in properties" :key="p.property_id" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ p.name }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.occupancy') }}</p>
                        <p class="mt-1 text-lg font-semibold" :class="p.occupancy_pct < 70 ? 'text-rose-600' : 'text-emerald-700'">{{ p.occupancy_pct }}%</p>
                        <p class="text-xs text-gray-500">{{ p.occupied_count }}/{{ p.unit_count }} {{ t('owners.portal.units') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.rent_roll') }}</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatMoney(p.monthly_rent_roll) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">{{ t('owners.portal.arrears') }}</p>
                        <p class="mt-1 text-lg font-semibold" :class="p.outstanding_arrears > 0 ? 'text-rose-600' : 'text-gray-500'">{{ formatMoney(p.outstanding_arrears) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
