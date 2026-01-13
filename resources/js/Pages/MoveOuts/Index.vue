<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

const props = defineProps({
    moveOuts: Object,
    status: String,
    stats: Object,
});

const currentStatus = ref(props.status);

const filterByStatus = (status) => {
    currentStatus.value = status;
    router.get(route('move-outs.index'), { status }, { preserveState: true });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
    }).format(amount || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'notice_given':
            return { color: 'bg-blue-100 text-blue-800', label: 'Notice Given' };
        case 'inspection_pending':
            return { color: 'bg-yellow-100 text-yellow-800', label: 'Inspection Pending' };
        case 'inspection_complete':
            return { color: 'bg-purple-100 text-purple-800', label: 'Inspection Complete' };
        case 'settlement_pending':
            return { color: 'bg-orange-100 text-orange-800', label: 'Settlement Pending' };
        case 'completed':
            return { color: 'bg-green-100 text-green-800', label: 'Completed' };
        case 'cancelled':
            return { color: 'bg-gray-100 text-gray-800', label: 'Cancelled' };
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
    <Head title="Move-Outs" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Move-Outs</h1>
                    <p class="text-sm text-gray-500">Manage tenant move-outs and deposit settlements</p>
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
                                <div class="text-xs text-gray-500">Active Move-Outs</div>
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
                                <div class="text-xs text-gray-500">Inspection Pending</div>
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
                                <div class="text-xs text-gray-500">Settlement Pending</div>
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
                                <div class="text-xs text-gray-500">Completed This Month</div>
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
                                Active
                            </button>
                            <button
                                @click="filterByStatus('completed')"
                                :class="currentStatus === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="flex-1 sm:flex-none px-6 py-4 border-b-2 font-medium text-sm transition-colors"
                            >
                                Completed
                            </button>
                        </nav>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Move-Out Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="moveOut in moveOuts.data" :key="moveOut.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                                                {{ moveOut.lease?.tenant?.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">{{ moveOut.lease?.tenant?.name }}</div>
                                                <div class="text-xs text-gray-500">{{ moveOut.lease?.tenant?.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Unit {{ moveOut.lease?.unit?.unit_number }}</div>
                                        <div class="text-xs text-gray-500">{{ moveOut.lease?.unit?.building?.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ formatDate(moveOut.actual_move_out_date || moveOut.intended_move_out_date) }}</div>
                                        <div v-if="!moveOut.actual_move_out_date" class="text-xs text-gray-500">(Intended)</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ formatCurrency(moveOut.deposit_held) }}</div>
                                        <div v-if="moveOut.refund_amount !== null" class="text-xs" :class="moveOut.refund_amount > 0 ? 'text-green-600' : 'text-red-600'">
                                            Refund: {{ formatCurrency(moveOut.refund_amount) }}
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <Link
                                            :href="route('move-outs.show', moveOut.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg"
                                        >
                                            <EyeIcon class="w-4 h-4" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!moveOuts.data?.length" class="text-center py-12">
                        <ArrowRightOnRectangleIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No move-outs</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ currentStatus === 'active' ? 'No active move-outs at the moment.' : 'No completed move-outs to display.' }}
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="moveOuts.data?.length && moveOuts.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="text-sm text-gray-700">
                            Page {{ moveOuts.current_page }} of {{ moveOuts.last_page }}
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="goToPage(moveOuts.prev_page_url)"
                                :disabled="!moveOuts.prev_page_url"
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                            >
                                Previous
                            </button>
                            <button
                                @click="goToPage(moveOuts.next_page_url)"
                                :disabled="!moveOuts.next_page_url"
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
