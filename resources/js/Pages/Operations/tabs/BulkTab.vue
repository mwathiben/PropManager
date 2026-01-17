<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpTrayIcon,
    CurrencyDollarIcon,
    HomeIcon,
    DocumentTextIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    bulkStats: Object,
    buildings: Array,
});

const operations = [
    {
        id: 'rent-adjustment',
        name: 'Rent Adjustment',
        description: 'Increase or decrease rent for multiple units at once',
        icon: CurrencyDollarIcon,
        color: 'bg-green-100 text-green-600',
        route: 'bulk.index',
        params: { tab: 'rent' },
    },
    {
        id: 'unit-status',
        name: 'Unit Status Update',
        description: 'Update status for multiple units (vacant, maintenance, etc.)',
        icon: HomeIcon,
        color: 'bg-blue-100 text-blue-600',
        route: 'bulk.index',
        params: { tab: 'status' },
    },
    {
        id: 'lease-management',
        name: 'Lease Management',
        description: 'Extend or terminate multiple leases at once',
        icon: DocumentTextIcon,
        color: 'bg-purple-100 text-purple-600',
        route: 'bulk.index',
        params: { tab: 'leases' },
    },
    {
        id: 'target-rent',
        name: 'Target Rent Update',
        description: 'Update market rent values for multiple units',
        icon: ChartBarIcon,
        color: 'bg-orange-100 text-orange-600',
        route: 'bulk.index',
        params: { tab: 'target' },
    },
];
</script>

<template>
    <div>
        <!-- Stats -->
        <div v-if="bulkStats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-gray-900">{{ bulkStats.total_units || 0 }}</div>
                <div class="text-sm text-gray-500">Total Units</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-green-600">{{ bulkStats.occupied_units || 0 }}</div>
                <div class="text-sm text-gray-500">Occupied</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-yellow-600">{{ bulkStats.active_leases || 0 }}</div>
                <div class="text-sm text-gray-500">Active Leases</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="text-2xl font-bold text-blue-600">{{ buildings?.length || 0 }}</div>
                <div class="text-sm text-gray-500">Buildings</div>
            </div>
        </div>

        <!-- Operations Grid -->
        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-for="op in operations"
                :key="op.id"
                :href="route(op.route, op.params)"
                class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md hover:border-gray-300 transition-all group"
            >
                <div class="flex items-start gap-4">
                    <div :class="op.color" class="p-3 rounded-lg group-hover:scale-110 transition-transform">
                        <component :is="op.icon" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 group-hover:text-purple-600 transition-colors">
                            {{ op.name }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">{{ op.description }}</p>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8">
            <h3 class="font-semibold text-gray-900 mb-4">Quick Select by Building</h3>
            <div v-if="buildings?.length > 0" class="flex flex-wrap gap-2">
                <Link
                    v-for="building in buildings"
                    :key="building.id"
                    :href="route('bulk.index', { building_id: building.id })"
                    class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-full text-sm hover:bg-purple-100 hover:text-purple-700 transition-colors"
                >
                    {{ building.name }}
                    <span class="text-gray-400 ml-1">({{ building.units_count || 0 }})</span>
                </Link>
            </div>
            <p v-else class="text-sm text-gray-500">No buildings available.</p>
        </div>
    </div>
</template>
