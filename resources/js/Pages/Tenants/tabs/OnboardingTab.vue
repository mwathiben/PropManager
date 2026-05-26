<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
const { t } = useI18n();

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

const statusLabel = (s: string): string => t(`tenants_onboarding_tab.status_label.${s}`, s ?? '');
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-3 gap-4 mb-6 max-w-xl">
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.pending ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_onboarding_tab.stats.pending') }}</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.accepted ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_onboarding_tab.stats.accepted') }}</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats.expired ?? 0 }}</p><p class="text-sm text-gray-500">{{ t('tenants_onboarding_tab.stats.expired') }}</p></div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" :placeholder="t('tenants_onboarding_tab.search_placeholder')"
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select v-model="status" @change="applyFilters" class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">{{ t('tenants_onboarding_tab.status_filter.all') }}</option>
                    <option value="pending">{{ t('tenants_onboarding_tab.status_filter.pending') }}</option>
                    <option value="accepted">{{ t('tenants_onboarding_tab.status_filter.accepted') }}</option>
                    <option value="expired">{{ t('tenants_onboarding_tab.status_filter.expired') }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ t('tenants_onboarding_tab.clear_filters') }}</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table v-if="invitations?.data?.length" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_onboarding_tab.table.invitee') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_onboarding_tab.table.unit') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_onboarding_tab.table.expires') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_onboarding_tab.table.status') }}</th>
                        <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('tenants_onboarding_tab.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="inv in invitations.data" :key="inv.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ inv.tenant_name || inv.email }}</div>
                            <div class="text-xs text-gray-500">{{ inv.email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ inv.unit?.unit_number ? `${t('tenants_onboarding_tab.unit_prefix')} ${inv.unit.unit_number}` : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(inv.expires_at) }}</td>
                        <td class="px-6 py-4 text-center"><span :class="[statusColor(inv.status), 'px-2 py-1 text-xs font-medium rounded-full']">{{ statusLabel(inv.status) }}</span></td>
                        <td class="px-6 py-4 text-end">
                            <Link :href="route('tenant-invitations.show', inv.id)" class="text-gray-600 hover:text-gray-900" :title="t('tenants_onboarding_tab.view')"><EyeIcon class="w-5 h-5" /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState v-else :icon="EnvelopeIcon" :title="t('tenants_onboarding_tab.empty.title')" :description="hasActiveFilters ? t('tenants_onboarding_tab.empty.filtered') : t('tenants_onboarding_tab.empty.default')" />

            <div v-if="invitations?.data?.length && invitations.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ t('tenants_onboarding_tab.showing_results', { from: invitations.from, to: invitations.to, total: invitations.total }) }}</div>
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
