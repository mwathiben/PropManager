<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ArrowRightOnRectangleIcon, EyeIcon } from '@heroicons/vue/24/outline';

interface MoveOut {
    id: number;
    status: string;
    created_at: string;
    lease?: {
        tenant?: { name?: string; email?: string } | null;
        unit?: { unit_number?: string; building?: { name?: string } } | null;
    } | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number; last_page: number }

const props = defineProps<{
    moveOuts?: Paginator<MoveOut>;
    stats?: Record<string, number>;
    filters?: { status?: string };
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();

const status = ref(props.filters?.status || 'active');

const applyFilters = () => {
    router.get(route('tenants.hub', { tab: 'move-outs' }), {
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};

const statusColor = (s: string): string => ({
    notice_given: 'bg-blue-100 text-blue-800',
    inspection_pending: 'bg-orange-100 text-orange-800',
    inspection_complete: 'bg-indigo-100 text-indigo-800',
    settlement_pending: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    settled: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-600',
}[s] || 'bg-gray-100 text-gray-700');

const humanStatus = (s: string): string => t(`tenants_move_outs_tab.status.${s}`, s.replace(/_/g, ' '));
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.active ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_move_outs_tab.stats.active') }}</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.inspection_pending ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_move_outs_tab.stats.inspection_pending') }}</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.settlement_pending ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_move_outs_tab.stats.settlement_pending') }}</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.completed_this_month ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_move_outs_tab.stats.completed_this_month') }}</p></div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <select v-model="status" @change="applyFilters" class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" :aria-label="t('tenants_move_outs_tab.table.status')">
                    <option value="active">{{ t('tenants_move_outs_tab.filter.active') }}</option>
                    <option value="completed">{{ t('tenants_move_outs_tab.filter.completed') }}</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="moveOuts?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_move_outs_tab.table.tenant') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_move_outs_tab.table.unit') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_move_outs_tab.table.initiated') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_move_outs_tab.table.status') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_move_outs_tab.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="mo in moveOuts.data" :key="mo.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ mo.lease?.tenant?.name || t('tenants_move_outs_tab.unknown') }}</div>
                            <div class="text-xs text-gray-500">{{ mo.lease?.tenant?.email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ t('tenants_move_outs_tab.unit_prefix', { number: mo.lease?.unit?.unit_number || '—' }) }}</div>
                            <div class="text-xs text-gray-500">{{ mo.lease?.unit?.building?.name }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(mo.created_at) }}</td>
                        <td class="px-6 py-4 text-center"><span :class="[statusColor(mo.status), 'px-2 py-1 text-xs font-medium rounded-full capitalize']">{{ humanStatus(mo.status) }}</span></td>
                        <td class="px-6 py-4 text-end">
                            <Link :href="route('move-outs.show', mo.id)" class="text-gray-600 hover:text-gray-900" :title="t('tenants_move_outs_tab.actions.view')"><EyeIcon class="w-5 h-5" /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="ArrowRightOnRectangleIcon" :title="t('tenants_move_outs_tab.empty.title')" :description="t('tenants_move_outs_tab.empty.description')" />

            <div v-if="moveOuts?.data?.length && moveOuts.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ t('tenants_move_outs_tab.pagination.showing', { from: moveOuts.from, to: moveOuts.to, total: moveOuts.total }) }}</div>
                    <div class="flex space-x-2">
                        <Link v-for="link in moveOuts.links" :key="link.label" :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']">
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
