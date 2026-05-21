<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { MagnifyingGlassIcon, ClockIcon, EyeIcon } from '@heroicons/vue/24/outline';

interface LastLease {
    unit_number?: string | null;
    building_name?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    duration_months?: number | null;
    move_out?: { reason?: string | null } | null;
}
interface PastTenant { id: number; name: string; email: string; last_lease?: LastLease | null }
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number; last_page: number }

const props = defineProps<{
    pastTenants?: Paginator<PastTenant>;
    stats?: Record<string, number>;
    buildings?: { id: number; name: string }[];
    filters?: { search?: string; building_id?: string };
}>();

const { formatDate } = useFormatters();

const search = ref(props.filters?.search || '');
const buildingId = ref(props.filters?.building_id || '');

const applyFilters = () => {
    router.get(route('tenants.hub', { tab: 'history' }), {
        search: search.value || undefined,
        building_id: buildingId.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => { search.value = ''; buildingId.value = ''; applyFilters(); };
const hasActiveFilters = computed(() => !!(search.value || buildingId.value));
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-2 gap-4 mb-6 max-w-md">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_past_tenants ?? 0 }}</p>
                <p class="text-sm text-gray-500">Past tenants</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Search past tenants..."
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select v-model="buildingId" @change="applyFilters" class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">All Buildings</option>
                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">Clear filters</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="pastTenants?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Last Unit</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="t in pastTenants.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ t.name }}</div>
                            <div class="text-xs text-gray-500">{{ t.email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">Unit {{ t.last_lease?.unit_number || '—' }}</div>
                            <div class="text-xs text-gray-500">{{ t.last_lease?.building_name }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div>{{ formatDate(t.last_lease?.start_date) }} – {{ formatDate(t.last_lease?.end_date) }}</div>
                            <div v-if="t.last_lease?.move_out?.reason" class="text-xs">{{ t.last_lease.move_out.reason }}</div>
                        </td>
                        <td class="px-6 py-4 text-end">
                            <Link :href="route('tenants.show', t.id)" class="text-gray-600 hover:text-gray-900" title="View"><EyeIcon class="w-5 h-5" /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="ClockIcon" title="No past tenants"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'Former tenants will appear here.'" />

            <div v-if="pastTenants?.data?.length && pastTenants.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">Showing {{ pastTenants.from }} to {{ pastTenants.to }} of {{ pastTenants.total }} results</div>
                    <div class="flex space-x-2">
                        <Link v-for="link in pastTenants.links" :key="link.label" :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']">
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
