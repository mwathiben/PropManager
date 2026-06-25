<script setup lang="ts">
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import Pagination from '@/Components/Pagination.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
const { t } = useI18n();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const statusOptions = computed(() => [
    { value: '', label: t('payment_verifications_index.status_options.all') },
    { value: 'payment_submitted', label: t('payment_verifications_index.status_options.awaiting_review') },
    { value: 'pending_payment', label: t('payment_verifications_index.status_options.pending_payment') },
    { value: 'payment_verified', label: t('payment_verifications_index.status_options.verified') },
    { value: 'rejected', label: t('payment_verifications_index.status_options.rejected') },
]);

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
        pending_payment: { class: 'bg-yellow-100 text-yellow-800', label: t('payment_verifications_index.status_options.pending_payment') },
        payment_submitted: { class: 'bg-blue-100 text-blue-800', label: t('payment_verifications_index.status_options.awaiting_review') },
        payment_verified: { class: 'bg-green-100 text-green-800', label: t('payment_verifications_index.status_options.verified') },
        rejected: { class: 'bg-red-100 text-red-800', label: t('payment_verifications_index.status_options.rejected') },
    };
    return badges[statusValue] || { class: 'bg-gray-100 text-gray-800', label: statusValue };
};

const awaitingReviewCount = computed(() => {
    return props.verifications.data.filter(v => v.status === 'payment_submitted').length;
});
</script>

<template>
    <Head :title="t('payment_verifications_index.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <ShieldCheckIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ t('payment_verifications_index.header.title') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('payment_verifications_index.header.subtitle') }}</p>
                    </div>
                </div>
                <div v-if="awaitingReviewCount > 0" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    {{ t('payment_verifications_index.header.awaiting_review_badge', { count: awaitingReviewCount }) }}
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
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute start-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="t('payment_verifications_index.filters.search_placeholder')"
                                :aria-label="t('payment_verifications_index.filters.search_placeholder')"
                                class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="status"
                            @change="applyFilters"
                            :aria-label="t('payment_verifications_index.table.status')"
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
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.tenant') }}
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.unit') }}
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.total_required') }}
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.status') }}
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.submitted') }}
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.documents') }}
                                    </th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ t('payment_verifications_index.table.actions') }}
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
                                            <div class="ms-3">
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ verification.lease?.tenant?.name || t('payment_verifications_index.unknown_tenant') }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ verification.lease?.tenant?.email }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <HomeIcon class="w-4 h-4 me-1 text-gray-400" />
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
                                            <DocumentTextIcon class="w-4 h-4 me-1 text-gray-400" />
                                            {{ verification.documents.length }}
                                        </div>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <Link
                                            :href="route('payment-verifications.show', verification.id)"
                                            class="inline-flex items-center px-3 py-1.5 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg transition-colors"
                                        >
                                            <EyeIcon class="w-4 h-4 me-1" />
                                            {{ t('payment_verifications_index.actions.view') }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="verifications.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <ShieldCheckIcon class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                        <p class="text-gray-500">{{ t('payment_verifications_index.empty') }}</p>
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
