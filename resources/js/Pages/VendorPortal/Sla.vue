<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import VendorPortalLayout from '@/Layouts/VendorPortalLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

interface Props {
    vendor: { id: number; name: string };
    window: number;
    metrics: {
        window_days: number;
        total_resolved: number;
        with_due: number;
        within_sla: number;
        breached: number;
        within_sla_pct: number | null;
        avg_resolution_hours: number | null;
        open_overdue: number;
    };
}

const props = defineProps<Props>();
const { t } = useI18n();

const pct = computed(() => props.metrics.within_sla_pct);
const ringColor = computed(() => {
    const p = pct.value;
    if (p === null) return 'text-gray-300';
    if (p >= 90) return 'text-emerald-500';
    if (p >= 70) return 'text-amber-500';
    return 'text-rose-500';
});
const dash = computed(() => `${pct.value ?? 0}, 100`);

const windows = [30, 90, 365];
const setWindow = (w: number) => router.get('/v/portal/sla', { window: w }, { preserveState: true });
</script>

<template>
    <Head :title="t('vendor_portal_sla.title')" />
    <VendorPortalLayout :vendor-name="vendor.name">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900">{{ t('vendor_portal_sla.title') }}</h1>
            <div class="flex gap-1" data-testid="vendor-sla-window">
                <button
                    v-for="w in windows"
                    :key="w"
                    @click="setWindow(w)"
                    :class="['rounded-lg px-3 py-1.5 text-sm', w === window ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']"
                >
                    {{ t(`vendor_portal_sla.window_${w}`, String(w)) }}
                </button>
            </div>
        </div>

        <div v-if="metrics.total_resolved === 0" class="mt-6 rounded-2xl bg-white p-10 text-center text-gray-500 ring-1 ring-gray-100">
            {{ t('vendor_portal_sla.no_data') }}
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100" data-testid="vendor-sla-within">
                <svg viewBox="0 0 36 36" class="h-20 w-20 -rotate-90">
                    <!-- i18n-ignore: svg attributes -->
                    <path class="text-gray-100" stroke="currentColor" stroke-width="3.5" fill="none"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <!-- i18n-ignore: svg attributes -->
                    <path :class="ringColor" stroke="currentColor" stroke-width="3.5" fill="none" stroke-linecap="round"
                        :stroke-dasharray="dash"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <div>
                    <p class="text-3xl font-bold text-gray-900">{{ pct === null ? '—' : pct + '%' }}</p>
                    <p class="text-sm text-gray-500">{{ t('vendor_portal_sla.within_sla') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <p class="text-2xl font-bold text-rose-600">{{ metrics.breached }}</p>
                    <p class="text-sm text-gray-500">{{ t('vendor_portal_sla.breached') }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <p class="text-2xl font-bold text-amber-600">{{ metrics.open_overdue }}</p>
                    <p class="text-sm text-gray-500">{{ t('vendor_portal_sla.open_overdue') }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <p class="text-2xl font-bold text-gray-900">{{ metrics.total_resolved }}</p>
                    <p class="text-sm text-gray-500">{{ t('vendor_portal_sla.resolved_total') }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <p class="text-2xl font-bold text-gray-900">
                        {{ metrics.avg_resolution_hours === null ? '—' : metrics.avg_resolution_hours + t('vendor_portal_sla.hours') }}
                    </p>
                    <p class="text-sm text-gray-500">{{ t('vendor_portal_sla.avg_resolution') }}</p>
                </div>
            </div>
        </div>
    </VendorPortalLayout>
</template>
