<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { UserGroupIcon } from '@heroicons/vue/24/outline';

interface CaretakerRow {
    caretaker_id: number;
    name: string;
    resolved_count: number;
    within_sla_pct: number | null;
    avg_resolution_hours: number | null;
    avg_first_response_hours: number | null;
    open_count: number;
    open_overdue: number;
    water_readings_recorded: number;
    escalations_raised: number;
}

const props = defineProps<{
    caretakers: CaretakerRow[];
    window: number;
    windows: number[];
}>();

const { t } = useI18n();

const sortKey = ref<keyof CaretakerRow>('within_sla_pct');
const sortDir = ref<'asc' | 'desc'>('desc');

const sorted = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...props.caretakers].sort((a, b) => {
        const av = a[sortKey.value];
        const bv = b[sortKey.value];
        if (av === bv) return 0;
        if (av === null) return 1;
        if (bv === null) return -1;
        return (Number(av) - Number(bv)) * dir;
    });
});

function setSort(key: keyof CaretakerRow): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}

function setWindow(w: number): void {
    router.get(route('maintenance.caretaker-performance'), { window: w }, { preserveScroll: true });
}

const pct = (v: number | null): string => (v === null ? '—' : `${v}%`);
const hours = (v: number | null): string => (v === null ? '—' : `${v}h`);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('maintenance.caretaker_perf.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <UserGroupIcon class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('maintenance.caretaker_perf.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('maintenance.caretaker_perf.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8 space-y-4" data-testid="caretaker-performance">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ t('maintenance.caretaker_perf.window') }}:</span>
                <button
                    v-for="w in windows"
                    :key="w"
                    type="button"
                    class="rounded-md px-3 py-1 text-xs font-medium"
                    :class="w === window ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'"
                    @click="setWindow(w)"
                >
                    {{ t('maintenance.caretaker_perf.days', { count: w }) }}
                </button>
            </div>

            <div v-if="caretakers.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('maintenance.caretaker_perf.empty') }}
            </div>

            <table v-else class="min-w-full overflow-hidden rounded-lg bg-white shadow text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('maintenance.caretaker_perf.col_caretaker') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('within_sla_pct')">{{ t('maintenance.caretaker_perf.col_within_sla') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('avg_first_response_hours')">{{ t('maintenance.caretaker_perf.col_first_response') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('avg_resolution_hours')">{{ t('maintenance.caretaker_perf.col_avg_resolution') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('resolved_count')">{{ t('maintenance.caretaker_perf.col_resolved') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('open_overdue')">{{ t('maintenance.caretaker_perf.col_overdue') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('water_readings_recorded')">{{ t('maintenance.caretaker_perf.col_water') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-end" @click="setSort('escalations_raised')">{{ t('maintenance.caretaker_perf.col_escalations') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="c in sorted" :key="c.caretaker_id" data-testid="caretaker-perf-row" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ c.name }}</td>
                        <td class="px-4 py-3 text-end" :class="c.within_sla_pct !== null && c.within_sla_pct < 85 ? 'text-rose-600' : 'text-emerald-700'">{{ pct(c.within_sla_pct) }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ hours(c.avg_first_response_hours) }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ hours(c.avg_resolution_hours) }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ c.resolved_count }}</td>
                        <td class="px-4 py-3 text-end" :class="c.open_overdue > 0 ? 'text-rose-600 font-medium' : 'text-gray-600'">{{ c.open_overdue }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ c.water_readings_recorded }}</td>
                        <td class="px-4 py-3 text-end text-gray-600">{{ c.escalations_raised }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
