<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import EmptyState from '@/Components/EmptyState.vue';
import {
    MagnifyingGlassIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

interface ActivityRow {
    id: number;
    type: string;
    type_label: string;
    type_color: string;
    description: string | null;
    tenant: { id: number; name: string } | null;
    performer: { id: number; name: string } | null;
    created_at: string;
    created_at_human: string;
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
    activities?: Paginator<ActivityRow>;
    activityTypes?: { value: string; label: string }[];
    stats?: Record<string, number>;
    filters?: { search?: string; type?: string; date_from?: string; date_to?: string };
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();

const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');

const applyFilters = () => {
    router.get(route('archive.hub', { tab: 'activity' }), {
        search: search.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    type.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => !!(search.value || type.value));
</script>

<template>
    <div>
        <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_activities ?? 0 }}</p>
                <p class="text-sm text-gray-500">{{ t('archive_activity.stats.total') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.today ?? 0 }}</p>
                <p class="text-sm text-gray-500">{{ t('archive_activity.stats.today') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.this_week ?? 0 }}</p>
                <p class="text-sm text-gray-500">{{ t('archive_activity.stats.this_week') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ stats.this_month ?? 0 }}</p>
                <p class="text-sm text-gray-500">{{ t('archive_activity.stats.this_month') }}</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            :placeholder="t('archive_activity.search_placeholder')"
                            :aria-label="t('archive_activity.search_placeholder')"
                            class="w-full ps-10 border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                        />
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-2.5" />
                    </div>
                </div>
                <select
                    v-model="type"
                    @change="applyFilters"
                    :aria-label="t('archive_activity.all_types')"
                    class="border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                >
                    <option value="">{{ t('archive_activity.all_types') }}</option>
                    <option v-for="type in activityTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </div>
            <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
                <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ t('archive_activity.clear_filters') }}</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <ul v-if="activities?.data?.length" class="divide-y divide-gray-100">
                <li v-for="activity in activities.data" :key="activity.id" class="px-6 py-4 flex items-start gap-3">
                    <span :class="[activity.type_color, 'mt-0.5 px-2 py-0.5 text-xs font-medium rounded-full whitespace-nowrap']">
                        {{ activity.type_label }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900">{{ activity.description }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span v-if="activity.tenant">{{ activity.tenant.name }}</span>
                            <span v-if="activity.performer">{{ t('archive_activity.by_performer', { name: activity.performer.name }) }}</span>
                        </p>
                    </div>
                    <div class="text-end whitespace-nowrap">
                        <p class="text-xs text-gray-500">{{ activity.created_at_human }}</p>
                        <p class="text-xs text-gray-400">{{ formatDate(activity.created_at) }}</p>
                    </div>
                </li>
            </ul>

            <EmptyState
                v-else
                :icon="ClockIcon"
                :title="t('archive_activity.empty.title')"
                :description="hasActiveFilters ? t('archive_activity.empty.filtered') : t('archive_activity.empty.default')"
            />

            <div v-if="activities?.data?.length && activities.last_page > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">{{ t('archive_activity.pagination.showing', { from: activities.from, to: activities.to, total: activities.total }) }}</div>
                    <div class="flex space-x-2">
                        <Link
                            v-for="link in activities.links"
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
