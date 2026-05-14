<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import type { ReadingsHistoryPageProps, WaterReading } from '@/types';

const props = defineProps<ReadingsHistoryPageProps>();
const { formatDate, formatMoney } = useFormatters();
const { can } = useAuth();

const filterForm = useForm({
    building_id: props.filters.building_id || '',
    unit_id: props.filters.unit_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    invoiced: props.filters.invoiced || ''
});

const applyFilters = () => {
    router.get(route('readings.history'), filterForm.data(), {
        preserveState: true,
        replace: true
    });
};

const clearFilters = () => {
    filterForm.reset();
    applyFilters();
};

// Get units for selected building
const filteredUnits = computed(() => {
    if (!filterForm.building_id) return [];
    const building = props.buildings.find(b => b.id === parseInt(filterForm.building_id));
    return building ? building.units : [];
});

// Edit/Delete functionality
const editingReading = ref(null);
const editForm = useForm({
    current_reading: null,
    reading_date: null
});

const startEdit = (reading) => {
    editingReading.value = reading.id;
    editForm.current_reading = reading.current_reading;
    editForm.reading_date = reading.reading_date.split('T')[0];
};

const cancelEdit = () => {
    editingReading.value = null;
    editForm.reset();
};

const saveEdit = (readingId) => {
    editForm.put(route('readings.update', readingId), {
        preserveState: true,
        onSuccess: () => {
            editingReading.value = null;
            editForm.reset();
        }
    });
};

const deleteReading = (readingId) => {
    if (confirm('Are you sure you want to delete this reading?')) {
        router.delete(route('readings.destroy', readingId), {
            preserveState: true
        });
    }
};
</script>

<template>
    <Head title="Water Reading History" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">

                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-xl font-bold text-gray-800">Water Reading History</h1>
                            <Link :href="route('readings.index')" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Add Readings
                            </Link>
                        </div>

                        <!-- Filters -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h3 class="font-semibold text-gray-700 mb-3">Filters</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Building</label>
                                    <select v-model="filterForm.building_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">All Buildings</option>
                                        <option v-for="building in buildings" :key="building.id" :value="building.id">
                                            {{ building.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                                    <select v-model="filterForm.unit_id" :disabled="!filterForm.building_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100">
                                        <option value="">All Units</option>
                                        <option v-for="unit in filteredUnits" :key="unit.id" :value="unit.id">
                                            {{ unit.unit_number }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                                    <input type="date" v-model="filterForm.date_from" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                                    <input type="date" v-model="filterForm.date_to" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select v-model="filterForm.invoiced" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">All</option>
                                        <option value="false">Not Invoiced</option>
                                        <option value="true">Invoiced</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    Apply Filters
                                </button>
                                <button @click="clearFilters" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Readings Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Previous</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Consumption</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="reading in readings.data" :key="reading.id" class="hover:bg-gray-50">
                                        <template v-if="editingReading === reading.id">
                                            <td class="px-4 py-3">
                                                <input type="date" v-model="editForm.reading_date" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ reading.unit.unit_number }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-900 font-mono">{{ reading.previous_reading }}</td>
                                            <td class="px-4 py-3">
                                                <input type="number" v-model="editForm.current_reading" class="w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-right font-mono text-sm">
                                            </td>
                                            <td colspan="4" class="px-4 py-3 text-center">
                                                <button @click="saveEdit(reading.id)" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 mr-2">
                                                    Save
                                                </button>
                                                <button @click="cancelEdit" class="px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm hover:bg-gray-400">
                                                    Cancel
                                                </button>
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatDate(reading.reading_date) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ reading.unit.unit_number }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right font-mono">
                                                {{ reading.previous_reading }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right font-mono">
                                                {{ reading.current_reading }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right font-mono">
                                                {{ reading.consumption }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right font-mono">
                                                {{ reading.cost ? formatMoney(reading.cost) : 'N/A' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <span v-if="reading.is_invoiced" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Invoiced
                                                </span>
                                                <span v-else class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm">
                                                <button v-if="!reading.is_invoiced" @click="startEdit(reading)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    Edit
                                                </button>
                                                <button v-if="can('finances:manage') && !reading.is_invoiced" @click="deleteReading(reading.id)" class="text-red-600 hover:text-red-900">
                                                    Delete
                                                </button>
                                                <span v-if="reading.is_invoiced" class="text-gray-400 text-xs">Locked</span>
                                            </td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="readings.data.length > 0" class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-700">
                                Showing {{ readings.from }} to {{ readings.to }} of {{ readings.total }} readings
                            </div>
                            <div class="flex gap-2">
                                <Link v-for="link in readings.links" :key="link.label" :href="link.url || '#'"
                                    :class="[
                                        'px-3 py-1 rounded border',
                                        link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                    :disabled="!link.url">
                                    <PaginatorLink :label="link.label" />
                                </Link>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <EmptyState
                            v-if="readings.data.length === 0"
                            :icon="ClipboardDocumentListIcon"
                            title="No readings found"
                            description="Try adjusting your filters or add new readings."
                        />

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
