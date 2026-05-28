<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { BuildingsIndexPageProps } from '@/types/finances';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    Bars3BottomLeftIcon,
    BuildingOffice2Icon,
    MapPinIcon,
    HomeModernIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AddBuildingModal from '@/Components/Modals/AddBuildingModal.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<BuildingsIndexPageProps>();

const { t } = useI18n();

const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const selectedSort = ref(props.filters?.sort || 'name_asc');
const showAddModal = ref(false);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
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

const totalBuildings = computed(() => {
    return props.buildingGroups.reduce((sum, group) => sum + group.buildings.length, 0);
});

const sortOptions = computed(() => [
    { value: 'name_asc', label: t('buildings_index.sort.name_asc') },
    { value: 'name_desc', label: t('buildings_index.sort.name_desc') },
    { value: 'occupancy_high', label: t('buildings_index.sort.occupancy_high') },
    { value: 'occupancy_low', label: t('buildings_index.sort.occupancy_low') },
    { value: 'updated', label: t('buildings_index.sort.updated') },
]);

const getOccupancyColor = (rate: number) => {
    if (rate >= 80) return 'bg-green-500';
    if (rate >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
};

const getOccupancyTextColor = (rate: number) => {
    if (rate >= 80) return 'text-green-600';
    if (rate >= 50) return 'text-yellow-600';
    return 'text-red-600';
};

const getBuildingTypeColor = (type: string) => {
    const colors: Record<string, string> = {
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
    <Head :title="t('buildings_index.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ t('buildings_index.heading') }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ t('buildings_index.subheading') }}</p>
                </div>
                <button
                    @click="showAddModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 transition shadow-sm"
                >
                    <PlusIcon class="w-5 h-5 me-2" />
                    {{ t('buildings_index.add_building') }}
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
                            <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="t('buildings_index.search_placeholder')"
                                class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <!-- Type Filter -->
                        <div class="relative">
                            <FunnelIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <select
                                v-model="selectedType"
                                @change="applyFilters"
                                :aria-label="t('buildings_index.filter_type_aria')"
                                class="ps-10 pe-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none bg-white min-w-[180px]"
                            >
                                <option value="">{{ t('buildings_index.all_types') }}</option>
                                <option v-for="(label, key) in buildingTypes" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Sort -->
                        <div class="relative">
                            <Bars3BottomLeftIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <select
                                v-model="selectedSort"
                                @change="applyFilters"
                                :aria-label="t('buildings_index.sort_aria')"
                                class="ps-10 pe-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none bg-white min-w-[160px]"
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
                            <XMarkIcon class="w-4 h-4 me-1" />
                            {{ t('buildings_index.clear') }}
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="totalBuildings === 0" class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <EmptyState
                        :icon="BuildingOffice2Icon"
                        :title="t('buildings_index.empty_title')"
                        :description="t('buildings_index.empty_description')"
                        :action-label="t('buildings_index.empty_action')"
                        size="lg"
                        @action="showAddModal = true"
                    />
                </div>

                <!-- Grouped Buildings -->
                <div v-else class="space-y-8">
                    <div v-for="group in buildingGroups" :key="group.parent_name ?? 'standalone'">
                        <!-- Section Header (for parent buildings with wings) -->
                        <div v-if="group.parent_name" class="mb-4">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-semibold text-gray-900">{{ group.parent_name }}</h3>
                                <span
                                    v-if="group.parent_type_label"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getBuildingTypeColor(group.parent_building_type ?? '')"
                                >
                                    {{ group.parent_type_label }}
                                </span>
                            </div>
                            <div v-if="group.parent_address" class="mt-1 flex items-center text-sm text-gray-500">
                                <MapPinIcon class="w-4 h-4 me-1 shrink-0" />
                                <span>{{ group.parent_address }}</span>
                            </div>
                        </div>

                        <!-- Building Cards Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <Link
                                v-for="building in group.buildings"
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
                                    <div class="absolute top-3 start-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                            :class="getBuildingTypeColor(building.building_type ?? '')"
                                        >
                                            {{ building.type_label }}
                                        </span>
                                    </div>

                                    <!-- Occupancy Badge -->
                                    <div class="absolute top-3 end-3">
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
                                        <MapPinIcon class="w-4 h-4 me-1 shrink-0" />
                                        <span class="truncate">{{ building.address }}</span>
                                    </div>
                                    <div v-else class="mt-1 text-sm text-gray-400 italic">{{ t('buildings_index.no_address') }}</div>

                                    <!-- Stats -->
                                    <div class="mt-3 flex items-center justify-between text-sm">
                                        <div class="text-gray-500">
                                            <span class="font-medium text-gray-900">{{ building.units_count }}</span> {{ t('buildings_index.units') }}
                                        </div>
                                        <div class="text-gray-500">
                                            <span class="font-medium text-green-600">{{ building.occupied_units_count }}</span> {{ t('buildings_index.occupied') }}
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
                    </div>
                </div>

                <!-- Results Count -->
                <div v-if="totalBuildings > 0" class="mt-6 text-center text-sm text-gray-500">
                    {{ t('buildings_index.showing_count', totalBuildings, { count: totalBuildings }) }}
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
