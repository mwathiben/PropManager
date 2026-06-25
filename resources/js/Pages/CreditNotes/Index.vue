<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import Pagination from '@/Components/Pagination.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { CreditNotesIndexPageProps } from '@/types/templates';
import {
    DocumentTextIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<CreditNotesIndexPageProps>();
const { formatMoney, formatDate } = useFormatters();
const { t } = useI18n();

const breadcrumbItems = computed(() => [
    { label: t('credit_notes_index.breadcrumb.finance_hub'), href: route('finances.index') },
    { label: t('credit_notes_index.breadcrumb.credit_notes') },
]);

const statusBadgeClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        applied: 'bg-green-100 text-green-800',
        voided: 'bg-gray-100 text-gray-500',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const search = (e) => {
    router.get(route('credit-notes.index'), {
        search: e.target.value,
        status: props.filters?.status,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const filterByStatus = (status) => {
    router.get(route('credit-notes.index'), {
        search: props.filters?.search,
        status: status || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('credit_notes_index.page_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <DocumentTextIcon class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('credit_notes_index.header_title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('credit_notes_index.header_subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                        <p class="text-sm text-gray-500">{{ t('credit_notes_index.stats.total') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-yellow-200">
                        <p class="text-sm text-yellow-600">{{ t('credit_notes_index.stats.pending') }}</p>
                        <p class="text-2xl font-semibold text-yellow-700">{{ stats.pending }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-blue-200">
                        <p class="text-sm text-blue-600">{{ t('credit_notes_index.stats.approved') }}</p>
                        <p class="text-2xl font-semibold text-blue-700">{{ stats.approved }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-green-200">
                        <p class="text-sm text-green-600">{{ t('credit_notes_index.stats.applied') }}</p>
                        <p class="text-2xl font-semibold text-green-700">{{ stats.applied }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-purple-200">
                        <p class="text-sm text-purple-600">{{ t('credit_notes_index.stats.total_amount') }}</p>
                        <p class="text-lg font-semibold text-purple-700">{{ formatMoney(stats.total_amount) }}</p>
                    </div>
                </div>

                <!-- Filters & Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="p-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                        <div class="flex flex-col sm:flex-row gap-3 flex-1">
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute start-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    :aria-label="t('credit_notes_index.filters.search_placeholder')"
                                    :placeholder="t('credit_notes_index.filters.search_placeholder')"
                                    :value="filters?.search"
                                    @input="search"
                                    class="ps-10 pe-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500 w-full sm:w-64"
                                />
                            </div>
                            <select
                                :aria-label="t('credit_notes_index.table.status')"
                                :value="filters?.status || ''"
                                @change="filterByStatus($event.target.value)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                            >
                                <option value="">{{ t('credit_notes_index.filters.all_statuses') }}</option>
                                <option value="pending">{{ t('credit_notes_index.status.pending') }}</option>
                                <option value="approved">{{ t('credit_notes_index.status.approved') }}</option>
                                <option value="applied">{{ t('credit_notes_index.status.applied') }}</option>
                                <option value="voided">{{ t('credit_notes_index.status.voided') }}</option>
                            </select>
                        </div>
                        <Link
                            :href="route('credit-notes.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition"
                        >
                            <PlusIcon class="w-5 h-5" />
                            {{ t('credit_notes_index.actions.issue') }}
                        </Link>
                    </div>
                </div>

                <!-- Credit Notes Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.credit_number') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.tenant') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.unit') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.amount') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.reason') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.status') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.date') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('credit_notes_index.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="cn in creditNotes.data" :key="cn.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ cn.credit_number }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ cn.tenant?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <template v-if="cn.lease?.unit">
                                            {{ cn.lease.unit.unit_number }}
                                            <span class="text-gray-400">/ {{ cn.lease.unit.building?.name }}</span>
                                        </template>
                                        <template v-else>-</template>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ formatMoney(cn.amount) }}
                                        <div v-if="cn.applied_amount > 0" class="text-xs text-gray-500">
                                            {{ t('credit_notes_index.table.applied_amount', { amount: formatMoney(cn.applied_amount) }) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ reasonOptions[cn.reason] || cn.reason }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusBadgeClass(cn.status)]">
                                            {{ t(`credit_notes_index.status.${cn.status}`, cn.status ?? '') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ formatDate(cn.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('credit-notes.show', cn.id)"
                                            class="text-purple-600 hover:text-purple-800 text-sm font-medium"
                                        >
                                            {{ t('credit_notes_index.actions.view') }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="creditNotes.data.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center">
                                        <DocumentTextIcon class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                        <p class="text-gray-500">{{ t('credit_notes_index.empty.title') }}</p>
                                        <p class="text-sm text-gray-400 mt-1">{{ t('credit_notes_index.empty.subtitle') }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="creditNotes.data.length > 0" class="px-4 py-3 border-t border-gray-200">
                        <Pagination :links="creditNotes.links" color="indigo" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
