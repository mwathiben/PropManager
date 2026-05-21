<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { MagnifyingGlassIcon, EnvelopeIcon, EyeIcon } from '@heroicons/vue/24/outline';

interface Invitation {
    id: number;
    email: string;
    tenant_name?: string | null;
    status: string;
    expires_at?: string | null;
    unit?: { unit_number?: string; building?: { name?: string } } | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number; last_page: number }

const props = defineProps<{
    invitations?: Paginator<Invitation>;
    stats?: Record<string, number>;
    filters?: { search?: string; status?: string };
}>();

const { formatDate } = useFormatters();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('tenants.hub', { tab: 'onboarding' }), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => { search.value = ''; status.value = ''; applyFilters(); };
const hasActiveFilters = computed(() => !!(search.value || status.value));

const statusColor = (s: string): string => ({
    pending: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    expired: 'bg-gray-100 text-gray-700',
}[s] || 'bg-gray-100 text-gray-700');
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-3 gap-4 mb-6 max-w-xl">
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.pending ?? 0 }}</p><p class="text-sm text-gray-500">Pending</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.accepted ?? 0 }}</p><p class="text-sm text-gray-500">Accepted</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.expired ?? 0 }}</p><p class="text-sm text-gray-500">Expired</p></div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Search invitations..."
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select v-model="status" @change="applyFilters" class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">Clear filters</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="invitations?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Invitee</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="inv in invitations.data" :key="inv.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ inv.tenant_name || inv.email }}</div>
                            <div class="text-xs text-gray-500">{{ inv.email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ inv.unit?.unit_number ? `Unit ${inv.unit.unit_number}` : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(inv.expires_at) }}</td>
                        <td class="px-6 py-4 text-center"><span :class="[statusColor(inv.status), 'px-2 py-1 text-xs font-medium rounded-full']">{{ inv.status }}</span></td>
                        <td class="px-6 py-4 text-end">
                            <Link :href="route('tenant-invitations.show', inv.id)" class="text-gray-600 hover:text-gray-900" title="View"><EyeIcon class="w-5 h-5" /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="EnvelopeIcon" title="No invitations"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'Tenant invitations will appear here.'" />

            <div v-if="invitations?.data?.length && invitations.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">Showing {{ invitations.from }} to {{ invitations.to }} of {{ invitations.total }} results</div>
                    <div class="flex space-x-2">
                        <Link v-for="link in invitations.links" :key="link.label" :href="link.url || '#'"
                            :class="[link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50', 'px-3 py-1 text-sm border rounded-md']">
                            <PaginatorLink :label="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
