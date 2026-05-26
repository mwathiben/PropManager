<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ClockIcon } from '@heroicons/vue/24/outline';

interface ReadingRow {
    id: number;
    current_reading: number;
    previous_reading?: number | null;
    consumption?: number | null;
    reading_date: string;
    is_approved: boolean;
    is_invoiced: boolean;
    unit?: { unit_number: string; building?: { name: string } } | null;
}
interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
}

const props = defineProps<{
    readings?: Paginator<ReadingRow> | ReadingRow[];
    buildingsList?: { id: number; name: string }[];
    filters?: { building_id?: string; status?: string };
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();

const buildingId = ref(props.filters?.building_id || '');
const status = ref(props.filters?.status || '');

const rows = computed<ReadingRow[]>(() => {
    const r = props.readings as Paginator<ReadingRow> | undefined;
    return Array.isArray(props.readings) ? (props.readings as ReadingRow[]) : (r?.data ?? []);
});
const paginator = computed<Paginator<ReadingRow> | null>(() =>
    Array.isArray(props.readings) ? null : ((props.readings as Paginator<ReadingRow>) ?? null),
);

const applyFilters = () => {
    router.get(route('water.hub', { tab: 'history' }), {
        building_id: buildingId.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    buildingId.value = '';
    status.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => !!(buildingId.value || status.value));

function statusBadge(r: ReadingRow): { label: string; cls: string } {
    if (r.is_invoiced) return { label: t('water_history.status.invoiced'), cls: 'bg-blue-100 text-blue-800' };
    if (r.is_approved) return { label: t('water_history.status.approved'), cls: 'bg-green-100 text-green-800' };
    return { label: t('water_history.status.pending'), cls: 'bg-yellow-100 text-yellow-800' };
}
</script>

<template>
    <div>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <select
                    v-model="buildingId"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm"
                >
                    <option value="">{{ t('water_history.filters.all_buildings') }}</option>
                    <option v-for="b in buildingsList" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm"
                >
                    <option value="">{{ t('water_history.filters.all_status') }}</option>
                    <option value="pending">{{ t('water_history.filters.pending') }}</option>
                    <option value="approved">{{ t('water_history.filters.approved') }}</option>
                    <option value="invoiced">{{ t('water_history.filters.invoiced') }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ t('water_history.filters.clear') }}</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="rows.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_history.table.unit') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_history.table.reading') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_history.table.date') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('water_history.table.status') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ t('water_history.unit_prefix', { number: r.unit?.unit_number ?? '' }) }}</div>
                            <div class="text-xs text-gray-500">{{ r.unit?.building?.name }}</div>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-900 text-end">{{ r.current_reading }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500 text-end">{{ formatDate(r.reading_date) }}</td>
                        <td class="px-6 py-3 text-center">
                            <span :class="[statusBadge(r).cls, 'px-2 py-1 text-xs font-medium rounded-full']">{{ statusBadge(r).label }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                :icon="ClockIcon"
                :title="t('water_history.empty.title')"
                :description="hasActiveFilters ? t('water_history.empty.description_filtered') : t('water_history.empty.description_default')"
            />

            <div v-if="paginator && rows.length && paginator.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ t('water_history.pagination.showing', { from: paginator.from ?? 0, to: paginator.to ?? 0, total: paginator.total }) }}</div>
                    <div class="flex space-x-2">
                        <Link
                            v-for="link in paginator.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']"
                        >
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
