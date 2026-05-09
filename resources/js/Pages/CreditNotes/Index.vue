<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import Pagination from '@/Components/Pagination.vue';
import { useFormatters } from '@/composables';
import type { CreditNotesIndexPageProps } from '@/types/templates';
import {
    DocumentTextIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<CreditNotesIndexPageProps>();
const { formatMoney, formatDate } = useFormatters();

const breadcrumbItems = [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Credit Notes' },
];

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
    <Head title="Credit Notes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <DocumentTextIcon class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Credit Notes</h1>
                    <p class="text-sm text-gray-500">Issue and manage tenant account credits</p>
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
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-yellow-200">
                        <p class="text-sm text-yellow-600">Pending</p>
                        <p class="text-2xl font-semibold text-yellow-700">{{ stats.pending }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-blue-200">
                        <p class="text-sm text-blue-600">Approved</p>
                        <p class="text-2xl font-semibold text-blue-700">{{ stats.approved }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-green-200">
                        <p class="text-sm text-green-600">Applied</p>
                        <p class="text-2xl font-semibold text-green-700">{{ stats.applied }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-purple-200">
                        <p class="text-sm text-purple-600">Total Amount</p>
                        <p class="text-lg font-semibold text-purple-700">{{ formatMoney(stats.total_amount) }}</p>
                    </div>
                </div>

                <!-- Filters & Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="p-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                        <div class="flex flex-col sm:flex-row gap-3 flex-1">
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search credit notes..."
                                    :value="filters?.search"
                                    @input="search"
                                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500 w-full sm:w-64"
                                />
                            </div>
                            <select
                                :value="filters?.status || ''"
                                @change="filterByStatus($event.target.value)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="applied">Applied</option>
                                <option value="voided">Voided</option>
                            </select>
                        </div>
                        <Link
                            :href="route('credit-notes.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition"
                        >
                            <PlusIcon class="w-5 h-5" />
                            Issue Credit Note
                        </Link>
                    </div>
                </div>

                <!-- Credit Notes Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Credit #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
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
                                            Applied: {{ formatMoney(cn.applied_amount) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ reasonOptions[cn.reason] || cn.reason }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusBadgeClass(cn.status)]">
                                            {{ cn.status }}
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
                                            View
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="creditNotes.data.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center">
                                        <DocumentTextIcon class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                        <p class="text-gray-500">No credit notes found</p>
                                        <p class="text-sm text-gray-400 mt-1">Issue a credit note to adjust tenant balances</p>
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
