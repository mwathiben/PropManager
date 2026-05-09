<script setup lang="ts">
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import Pagination from '@/Components/Pagination.vue';
import { useFormatters } from '@/composables';
import type { PaymentVerificationsIndexPageProps } from '@/types/tenants';
import {
    ShieldCheckIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentTextIcon,
    MagnifyingGlassIcon,
    UserIcon,
    HomeIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<PaymentVerificationsIndexPageProps>();

const { formatMoney: formatCurrency, formatDate } = useFormatters();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'payment_submitted', label: 'Awaiting Review' },
    { value: 'pending_payment', label: 'Pending Payment' },
    { value: 'payment_verified', label: 'Verified' },
    { value: 'rejected', label: 'Rejected' },
];

const applyFilters = () => {
    router.get(route('payment-verifications.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        building_id: props.filters?.building_id || undefined,
        wing_id: props.filters?.wing_id || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const onBuildingFilterChange = (buildingId, wingId) => {
    router.get(route('payment-verifications.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        building_id: buildingId || undefined,
        wing_id: wingId || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusBadge = (statusValue) => {
    const badges = {
        pending_payment: { class: 'bg-yellow-100 text-yellow-800', label: 'Pending Payment' },
        payment_submitted: { class: 'bg-blue-100 text-blue-800', label: 'Awaiting Review' },
        payment_verified: { class: 'bg-green-100 text-green-800', label: 'Verified' },
        rejected: { class: 'bg-red-100 text-red-800', label: 'Rejected' },
    };
    return badges[statusValue] || { class: 'bg-gray-100 text-gray-800', label: statusValue };
};

const awaitingReviewCount = computed(() => {
    return props.verifications.data.filter(v => v.status === 'payment_submitted').length;
});
</script>

<template>
    <Head title="Payment Verifications" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <ShieldCheckIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Payment Verifications</h1>
                        <p class="text-sm text-gray-500">Review and approve new tenant payments</p>
                    </div>
                </div>
                <div v-if="awaitingReviewCount > 0" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    {{ awaitingReviewCount }} awaiting review
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1 relative">
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by tenant name..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="status"
                            @change="applyFilters"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>

                        <!-- Building Filter -->
                        <BuildingWingFilter
                            v-if="buildings?.length > 0"
                            :buildings="buildings"
                            :initial-building-id="filters?.building_id"
                            :initial-wing-id="filters?.wing_id"
                            @change="onBuildingFilterChange"
                        />
                    </div>
                </div>

                <!-- Verifications Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tenant
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unit
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Required
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Submitted
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Documents
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="verification in verifications.data"
                                    :key="verification.id"
                                    :class="verification.status === 'payment_submitted' ? 'bg-blue-50/50' : ''"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                <UserIcon class="w-4 h-4 text-gray-500" />
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ verification.lease?.tenant?.name || 'Unknown' }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ verification.lease?.tenant?.email }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <HomeIcon class="w-4 h-4 mr-1 text-gray-400" />
                                            {{ verification.lease?.unit?.unit_number }}
                                            <span class="text-gray-400 mx-1">•</span>
                                            {{ verification.lease?.unit?.building?.name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ formatCurrency(verification.total_required) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                getStatusBadge(verification.status).class
                                            ]"
                                        >
                                            {{ getStatusBadge(verification.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(verification.submitted_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="verification.documents?.length > 0" class="flex items-center text-sm text-gray-600">
                                            <DocumentTextIcon class="w-4 h-4 mr-1 text-gray-400" />
                                            {{ verification.documents.length }}
                                        </div>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            :href="route('payment-verifications.show', verification.id)"
                                            class="inline-flex items-center px-3 py-1.5 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg transition-colors"
                                        >
                                            <EyeIcon class="w-4 h-4 mr-1" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="verifications.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <ShieldCheckIcon class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                        <p class="text-gray-500">No payment verifications found</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="verifications.links?.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <Pagination :links="verifications.links" color="indigo" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
