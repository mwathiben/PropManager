<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { MagnifyingGlassIcon, UsersIcon, EyeIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

interface Lease { unit?: { unit_number?: string; building?: { name?: string } } | null }
interface TenantRow { id: number; name: string; email: string; mobile_number?: string | null; leases?: Lease[] }
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number; last_page: number }

const props = defineProps<{
    tenants?: Paginator<TenantRow>;
    stats?: Record<string, number>;
    buildings?: { id: number; name: string }[];
    filters?: { search?: string; building_id?: string };
}>();

const search = ref(props.filters?.search || '');
const buildingId = ref(props.filters?.building_id || '');

const applyFilters = () => {
    router.get(route('tenants.hub', { tab: 'directory' }), {
        search: search.value || undefined,
        building_id: buildingId.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => { search.value = ''; buildingId.value = ''; applyFilters(); };
const hasActiveFilters = computed(() => !!(search.value || buildingId.value));
const firstLease = (t: TenantRow) => t.leases?.[0];
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-2 gap-4 mb-6 max-w-md">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.active_tenants ?? 0 }}</p>
                <p class="text-sm text-gray-500">Active tenants</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Search tenants..."
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
            <table v-if="tenants?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="t in tenants.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ t.name }}</div>
                            <div class="text-xs text-gray-500">{{ t.email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">Unit {{ firstLease(t)?.unit?.unit_number || '—' }}</div>
                            <div class="text-xs text-gray-500">{{ firstLease(t)?.unit?.building?.name }}</div>
                        </td>
                        <td class="px-6 py-4 text-end">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="route('tenants.show', t.id)" class="text-gray-600 hover:text-gray-900" title="View"><EyeIcon class="w-5 h-5" /></Link>
                                <Link :href="route('tenants.ledger', t.id)" class="text-gray-600 hover:text-gray-900" title="Ledger"><DocumentTextIcon class="w-5 h-5" /></Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="UsersIcon" title="No tenants found"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'Active tenants will appear here.'" />

            <div v-if="tenants?.data?.length && tenants.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">Showing {{ tenants.from }} to {{ tenants.to }} of {{ tenants.total }} results</div>
                    <div class="flex space-x-2">
                        <Link v-for="link in tenants.links" :key="link.label" :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']">
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
