<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import VendorPortalLayout from '@/Layouts/VendorPortalLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { ClockIcon, WrenchScrewdriverIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

interface Props {
    vendor: { id: number; name: string };
    stats: { pending: number; open: number; overdue: number };
}

const props = defineProps<Props>();
const { t } = useI18n();

const cards = [
    { key: 'pending', icon: ClockIcon, value: props.stats.pending, tone: 'text-amber-600', testid: 'stat-pending' },
    { key: 'open', icon: WrenchScrewdriverIcon, value: props.stats.open, tone: 'text-indigo-600', testid: 'stat-open' },
    { key: 'overdue', icon: ExclamationTriangleIcon, value: props.stats.overdue, tone: 'text-rose-600', testid: 'stat-overdue' },
];
</script>

<template>
    <Head :title="t('vendor_portal.nav.dashboard')" />
    <VendorPortalLayout :vendor-name="vendor.name">
        <h1 class="text-xl font-semibold text-gray-900" data-testid="vendor-dashboard">
            {{ t('vendor_portal.dashboard.title', { name: vendor.name }) }}
        </h1>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.key"
                class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                :data-testid="card.testid"
            >
                <component :is="card.icon" class="h-6 w-6" :class="card.tone" />
                <p class="mt-3 text-3xl font-bold text-gray-900">{{ card.value }}</p>
                <p class="text-sm text-gray-500">{{ t('vendor_portal.dashboard.' + card.key) }}</p>
            </div>
        </div>
    </VendorPortalLayout>
</template>
