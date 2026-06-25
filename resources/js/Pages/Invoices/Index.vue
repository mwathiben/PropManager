<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { computed, ref } from 'vue';
import {
    DocumentTextIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables';
import type { InvoicesPaginated, InvoiceFilters, Building } from '@/types';

const props = withDefaults(defineProps<{
    invoices: InvoicesPaginated;
    buildings?: Building[];
    filters?: InvoiceFilters;
}>(), {
    buildings: () => [],
    filters: () => ({}),
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const buildingId = ref(props.filters.building_id || '');
const showGenerateModal = ref(false);

const currentDate = new Date();
const generateForm = useForm({
    month: currentDate.getMonth() + 1,
    year: currentDate.getFullYear(),
});

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
});

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'partial', label: 'Partial' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
];

const applyFilters = () => {
    router.get(route('invoices.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        building_id: buildingId.value || undefined,
    }, { preserveState: true });
};

const { formatMoney: formatCurrency } = useFormatters();

const statusColor = (status) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        partial: 'bg-yellow-100 text-yellow-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
        voided: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const submitGenerate = () => {
    generateForm.post(route('invoices.generate'), {
        onSuccess: () => {
            showGenerateModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Invoices" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">Invoices</h1>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex flex-wrap gap-4 items-center">
                            <div class="flex-1 min-w-[200px]">
                                <div class="relative">
                                    <MagnifyingGlassIcon class="absolute start-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                                    <input
                                        v-model="search"
                                        id="inv-search"
                                        type="text"
                                        placeholder="Search invoices..."
                                        class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500"
                                        aria-label="Search invoices"
                                        @keyup.enter="applyFilters"
                                    />
                                </div>
                            </div>

                            <select
                                v-model="status"
                                aria-label="Filter by status"
                                class="border border-gray-300 rounded-md px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500"
                                @change="applyFilters"
                            >
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

                            <select
                                v-if="buildings.length > 0"
                                v-model="buildingId"
                                aria-label="Filter by building"
                                class="border border-gray-300 rounded-md px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500"
                                @change="applyFilters"
                            >
                                <option value="">All Buildings</option>
                                <option v-for="building in buildings" :key="building.id" :value="building.id">
                                    {{ building.name }}
                                </option>
                            </select>

                            <button
                                @click="showGenerateModal = true"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <PlusIcon class="w-5 h-5 me-2" />
                                Generate Invoices
                            </button>

                            <Link
                                :href="route('finances.invoices')"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700"
                            >
                                Go to Finance Hub
                            </Link>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ invoice.invoice_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ invoice.lease?.tenant?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ invoice.lease?.unit?.unit_number || 'N/A' }}
                                        <span v-if="invoice.lease?.unit?.building" class="text-gray-400">
                                            ({{ invoice.lease.unit.building.name }})
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatCurrency(invoice.total_due) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[statusColor(invoice.status), 'px-2 py-1 text-xs font-medium rounded-full']">
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ invoice.due_date }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                                        <Link
                                            :href="route('invoices.show', invoice.id)"
                                            class="text-emerald-600 hover:text-emerald-900"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                                <!-- Phase-20 FRONT-UX-9: EmptyState component. -->
                                <tr v-if="!invoices.data?.length">
                                    <td colspan="7" class="px-6">
                                        <EmptyState
                                            :icon="DocumentTextIcon"
                                            title="No invoices found"
                                            description="Adjust your filters above. Invoices are generated automatically from active leases."
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showGenerateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40" role="button" tabindex="0" @click="showGenerateModal = false" @keydown.enter="showGenerateModal = false" @keydown.space.prevent="showGenerateModal = false"></div>
                    <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate Invoices</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Generate invoices for all active leases for the selected billing period.
                        </p>

                        <form @submit.prevent="submitGenerate">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="inv-gen-month" class="block text-sm font-medium text-gray-700">Month</label>
                                    <select
                                        id="inv-gen-month"
                                        v-model="generateForm.month"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option v-for="m in months" :key="m.value" :value="m.value">
                                            {{ m.label }}
                                        </option>
                                    </select>
                                    <p v-if="generateForm.errors.month" class="mt-1 text-sm text-red-600">
                                        {{ generateForm.errors.month }}
                                    </p>
                                </div>

                                <div>
                                    <label for="inv-gen-year" class="block text-sm font-medium text-gray-700">Year</label>
                                    <select
                                        id="inv-gen-year"
                                        v-model="generateForm.year"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option v-for="y in years" :key="y" :value="y">
                                            {{ y }}
                                        </option>
                                    </select>
                                    <p v-if="generateForm.errors.year" class="mt-1 text-sm text-red-600">
                                        {{ generateForm.errors.year }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="showGenerateModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="generateForm.processing"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Generate Invoices
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
