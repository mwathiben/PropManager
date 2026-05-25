<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { MoveOutsIndexPageProps } from '@/types/finances';
import {
    ArrowRightOnRectangleIcon,
    ClipboardDocumentCheckIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    EyeIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<MoveOutsIndexPageProps>();
const { formatMoney: formatCurrency, formatDate } = useFormatters();
const { t } = useI18n();

const currentStatus = ref(props.status);

const filterByStatus = (status) => {
    currentStatus.value = status;
    router.get(route('move-outs.index'), { status }, { preserveState: true });
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'notice_given':
            return { color: 'bg-blue-100 text-blue-800', label: t('moveouts.index.status_label.notice_given') };
        case 'inspection_pending':
            return { color: 'bg-yellow-100 text-yellow-800', label: t('moveouts.index.status_label.inspection_pending') };
        case 'inspection_complete':
            return { color: 'bg-purple-100 text-purple-800', label: t('moveouts.index.status_label.inspection_complete') };
        case 'settlement_pending':
            return { color: 'bg-orange-100 text-orange-800', label: t('moveouts.index.status_label.settlement_pending') };
        case 'completed':
            return { color: 'bg-green-100 text-green-800', label: t('moveouts.index.status_label.completed') };
        case 'cancelled':
            return { color: 'bg-gray-100 text-gray-800', label: t('moveouts.index.status_label.cancelled') };
        default:
            return { color: 'bg-gray-100 text-gray-800', label: status };
    }
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('moveouts.index.head_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('moveouts.index.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('moveouts.index.subtitle') }}</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <ArrowRightOnRectangleIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.active }}</div>
                                <div class="text-xs text-gray-500">{{ t('moveouts.index.stats.active') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <ClipboardDocumentCheckIcon class="w-5 h-5 text-yellow-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-yellow-600">{{ stats.inspection_pending }}</div>
                                <div class="text-xs text-gray-500">{{ t('moveouts.index.stats.inspection_pending') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <BanknotesIcon class="w-5 h-5 text-orange-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-orange-600">{{ stats.settlement_pending }}</div>
                                <div class="text-xs text-gray-500">{{ t('moveouts.index.stats.settlement_pending') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <CheckCircleIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600">{{ stats.completed_this_month }}</div>
                                <div class="text-xs text-gray-500">{{ t('moveouts.index.stats.completed_this_month') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                @click="filterByStatus('active')"
                                :class="currentStatus === 'active' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="flex-1 sm:flex-none px-6 py-4 border-b-2 font-medium text-sm transition-colors"
                            >
                                {{ t('moveouts.index.tabs.active') }}
                            </button>
                            <button
                                @click="filterByStatus('completed')"
                                :class="currentStatus === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="flex-1 sm:flex-none px-6 py-4 border-b-2 font-medium text-sm transition-colors"
                            >
                                {{ t('moveouts.index.tabs.completed') }}
                            </button>
                        </nav>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.tenant') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.unit') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.move_out_date') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.deposit') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.status') }}</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('moveouts.index.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="moveOut in moveOuts.data" :key="moveOut.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                                                {{ moveOut.lease?.tenant?.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div class="ms-3">
                                                <div class="text-sm font-medium text-gray-900">{{ moveOut.lease?.tenant?.name }}</div>
                                                <div class="text-xs text-gray-500">{{ moveOut.lease?.tenant?.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ t('moveouts.index.unit_prefix') }} {{ moveOut.lease?.unit?.unit_number }}</div>
                                        <div class="text-xs text-gray-500">{{ moveOut.lease?.unit?.building?.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ formatDate(moveOut.actual_move_out_date || moveOut.intended_move_out_date) }}</div>
                                        <div v-if="!moveOut.actual_move_out_date" class="text-xs text-gray-500">{{ t('moveouts.index.intended') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ formatCurrency(moveOut.deposit_held) }}</div>
                                        <div v-if="moveOut.refund_amount !== null" class="text-xs" :class="moveOut.refund_amount > 0 ? 'text-green-600' : 'text-red-600'">
                                            {{ t('moveouts.index.refund', { amount: formatCurrency(moveOut.refund_amount) }) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="getStatusBadge(moveOut.status).color"
                                            class="px-2.5 py-1 rounded-full text-xs font-medium"
                                        >
                                            {{ getStatusBadge(moveOut.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end">
                                        <Link
                                            :href="route('move-outs.show', moveOut.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg"
                                        >
                                            <EyeIcon class="w-4 h-4" />
                                            {{ t('moveouts.index.view') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <EmptyState
                        v-if="!moveOuts.data?.length"
                        :icon="ArrowRightOnRectangleIcon"
                        :title="t('moveouts.index.empty.title')"
                        :description="currentStatus === 'active' ? t('moveouts.index.empty.active_description') : t('moveouts.index.empty.completed_description')"
                    />

                    <!-- Pagination -->
                    <div v-if="moveOuts.data?.length && moveOuts.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="text-sm text-gray-700">
                            {{ t('moveouts.index.pagination.page_of', { current: moveOuts.current_page, last: moveOuts.last_page }) }}
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="goToPage(moveOuts.prev_page_url)"
                                :disabled="!moveOuts.prev_page_url"
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                            >
                                {{ t('moveouts.index.pagination.previous') }}
                            </button>
                            <button
                                @click="goToPage(moveOuts.next_page_url)"
                                :disabled="!moveOuts.next_page_url"
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                            >
                                {{ t('moveouts.index.pagination.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
