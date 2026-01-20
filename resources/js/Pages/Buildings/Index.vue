<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    Bars3BottomLeftIcon,
    BuildingOffice2Icon,
    MapPinIcon,
    HomeModernIcon,
    ChartBarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AddBuildingModal from '@/Components/Modals/AddBuildingModal.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    buildings: Array,
    buildingTypes: Object,
    amenityOptions: Object,
    filters: Object,
});

// Local filter state
const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const selectedSort = ref(props.filters?.sort || 'name_asc');
const showAddModal = ref(false);

// Debounced search
let searchTimeout = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

const applyFilters = () => {
    router.get(route('buildings.index'), {
        search: search.value || undefined,
        type: selectedType.value || undefined,
        sort: selectedSort.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    selectedType.value = '';
    selectedSort.value = 'name_asc';
    router.get(route('buildings.index'), {}, {
        preserveState: true,
    });
};

const hasActiveFilters = computed(() => {
    return search.value || selectedType.value || selectedSort.value !== 'name_asc';
});

const sortOptions = [
    { value: 'name_asc', label: 'Name (A-Z)' },
    { value: 'name_desc', label: 'Name (Z-A)' },
    { value: 'occupancy_high', label: 'Occupancy (High-Low)' },
    { value: 'occupancy_low', label: 'Occupancy (Low-High)' },
    { value: 'updated', label: 'Recently Updated' },
];

const getOccupancyColor = (rate) => {
    if (rate >= 80) return 'bg-green-500';
    if (rate >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
};

const getOccupancyTextColor = (rate) => {
    if (rate >= 80) return 'text-green-600';
    if (rate >= 50) return 'text-yellow-600';
    return 'text-red-600';
};

const getBuildingTypeColor = (type) => {
    const colors = {
        'residential_apartment': 'bg-blue-100 text-blue-700',
        'office_block': 'bg-purple-100 text-purple-700',
        'warehouse': 'bg-amber-100 text-amber-700',
        'go_down': 'bg-orange-100 text-orange-700',
        'maisonette': 'bg-teal-100 text-teal-700',
        'bungalow': 'bg-green-100 text-green-700',
        'single_unit_rental': 'bg-indigo-100 text-indigo-700',
        'mixed_use': 'bg-pink-100 text-pink-700',
        'commercial_plaza': 'bg-rose-100 text-rose-700',
        'townhouse': 'bg-cyan-100 text-cyan-700',
        'bedsitter_block': 'bg-lime-100 text-lime-700',
        'hostel': 'bg-violet-100 text-violet-700',
    };
    return colors[type] || 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Buildings" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Buildings</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage all your properties in one place</p>
                </div>
                <button
                    @click="showAddModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 transition shadow-sm"
                >
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Add Building
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters Bar -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Search -->
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search buildings..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <!-- Type Filter -->
                        <div class="relative">
                            <FunnelIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <select
                                v-model="selectedType"
                                @change="applyFilters"
                                class="pl-10 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none bg-white min-w-[180px]"
                            >
                                <option value="">All Types</option>
                                <option v-for="(label, key) in buildingTypes" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Sort -->
                        <div class="relative">
                            <Bars3BottomLeftIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <select
                                v-model="selectedSort"
                                @change="applyFilters"
                                class="pl-10 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none bg-white min-w-[160px]"
                            >
                                <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Clear Filters -->
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                        >
                            <XMarkIcon class="w-4 h-4 mr-1" />
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="buildings.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <EmptyState
                        :icon="BuildingOffice2Icon"
                        title="No buildings yet"
                        description="Get started by adding your first building. You can manage apartments, offices, warehouses, and more."
                        action-label="Add Your First Building"
                        size="lg"
                        @action="showAddModal = true"
                    />
                </div>

                <!-- Buildings Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link
                        v-for="building in buildings"
                        :key="building.id"
                        :href="route('buildings.show', building.id)"
                        class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-indigo-200 transition-all duration-200"
                    >
                        <!-- Photo / Placeholder -->
                        <div class="aspect-video bg-gradient-to-br from-gray-100 to-gray-200 relative overflow-hidden">
                            <img
                                v-if="building.primary_photo"
                                :src="building.primary_photo"
                                :alt="building.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />
                            <div v-else class="absolute inset-0 flex items-center justify-center">
                                <HomeModernIcon class="w-16 h-16 text-gray-300" />
                            </div>

                            <!-- Type Badge -->
                            <div class="absolute top-3 left-3">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                    :class="getBuildingTypeColor(building.building_type)"
                                >
                                    {{ building.type_label }}
                                </span>
                            </div>

                            <!-- Occupancy Badge -->
                            <div class="absolute top-3 right-3">
                                <div class="bg-white/90 backdrop-blur-sm rounded-full px-2.5 py-1 flex items-center gap-1.5 shadow-sm">
                                    <div class="w-2 h-2 rounded-full" :class="getOccupancyColor(building.occupancy_rate)"></div>
                                    <span class="text-xs font-semibold" :class="getOccupancyTextColor(building.occupancy_rate)">
                                        {{ building.occupancy_rate }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors truncate">
                                {{ building.name }}
                            </h3>

                            <!-- Address -->
                            <div v-if="building.address" class="mt-1 flex items-center text-sm text-gray-500">
                                <MapPinIcon class="w-4 h-4 mr-1 shrink-0" />
                                <span class="truncate">{{ building.address }}</span>
                            </div>
                            <div v-else class="mt-1 text-sm text-gray-400 italic">No address set</div>

                            <!-- Stats -->
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <div class="text-gray-500">
                                    <span class="font-medium text-gray-900">{{ building.units_count }}</span> units
                                </div>
                                <div class="text-gray-500">
                                    <span class="font-medium text-green-600">{{ building.occupied_units_count }}</span> occupied
                                </div>
                            </div>

                            <!-- Occupancy Bar -->
                            <div class="mt-3">
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="getOccupancyColor(building.occupancy_rate)"
                                        :style="{ width: `${building.occupancy_rate}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Results Count -->
                <div v-if="buildings.length > 0" class="mt-6 text-center text-sm text-gray-500">
                    Showing {{ buildings.length }} building{{ buildings.length === 1 ? '' : 's' }}
                </div>
            </div>
        </div>

        <!-- Add Building Modal -->
        <AddBuildingModal
            v-if="showAddModal"
            :show="showAddModal"
            :building-types="buildingTypes"
            :amenity-options="amenityOptions"
            @close="showAddModal = false"
        />
    </AuthenticatedLayout>
</template>
