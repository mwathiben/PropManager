<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import RentAdjustmentTab from './RentAdjustmentTab.vue';
import UnitStatusTab from './UnitStatusTab.vue';
import LeaseManagementTab from './LeaseManagementTab.vue';
import TargetRentTab from './TargetRentTab.vue';
import BuildingWingFilter from '@/Components/BuildingWingFilter.vue';
import { useI18n } from '@/composables/useI18n';
import type { BulkOperationsIndexPageProps } from '@/types';

const { t } = useI18n();

const props = withDefaults(defineProps<BulkOperationsIndexPageProps>(), {
    properties: () => [],
    buildings: () => [],
    units: () => [],
    tenants: () => [],
});

// Active tab
const activeTab = ref('rent');

// Filters
const selectedPropertyId = ref('');
const selectedBuildingId = ref(null);
const selectedWingId = ref(null);
const selectedStatus = ref('');

// Get filtered buildings from property (for property-based filtering)
const filteredBuildings = computed(() => {
    if (!selectedPropertyId.value) return [];
    const property = props.properties.find(p => p.id === parseInt(selectedPropertyId.value));
    return property?.buildings || [];
});

// Get wing IDs for a building (includes the building itself and all its wings)
const getBuildingIds = (buildingId, wingId) => {
    if (wingId) return [wingId];
    if (!buildingId) return [];

    const building = props.buildings?.find(b => b.id === buildingId);
    if (!building) return [buildingId];

    const wingIds = building.wings?.map(w => w.id) || [];
    return [buildingId, ...wingIds];
};

// Get filtered units
const filteredUnits = computed(() => {
    let units = [...props.units];

    if (selectedPropertyId.value) {
        units = units.filter(u => u.building?.property_id === parseInt(selectedPropertyId.value));
    }

    // Building/Wing filter (more specific than property)
    if (selectedBuildingId.value || selectedWingId.value) {
        const buildingIds = getBuildingIds(selectedBuildingId.value, selectedWingId.value);
        units = units.filter(u => buildingIds.includes(u.building_id));
    }

    if (selectedStatus.value) {
        units = units.filter(u => u.status === selectedStatus.value);
    }

    return units;
});

// Handle building filter change
const onBuildingFilterChange = () => {
    // Clear property filter when building filter is used
    if (selectedBuildingId.value) {
        selectedPropertyId.value = '';
    }
};

// Get units with active leases
const unitsWithLeases = computed(() => {
    return filteredUnits.value.filter(u => u.active_lease);
});

// Selection state
const selectedUnitIds = ref([]);
const selectedLeaseIds = ref([]);

// Reset selections when filters change
watch([selectedPropertyId, selectedBuildingId, selectedWingId, selectedStatus], () => {
    selectedUnitIds.value = [];
    selectedLeaseIds.value = [];
});

// Handle success from tab components
const handleSuccess = () => {
    selectedUnitIds.value = [];
    selectedLeaseIds.value = [];
};

const tabs = computed(() => [
    { key: 'rent', label: t('bulk_operations.tabs.rent') },
    { key: 'status', label: t('bulk_operations.tabs.status') },
    { key: 'lease', label: t('bulk_operations.tabs.lease') },
    { key: 'target', label: t('bulk_operations.tabs.target') }
]);
</script>

<template>
    <Head :title="t('bulk_operations.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('bulk_operations.title') }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ t('bulk_operations.subtitle') }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">{{ t('bulk_operations.filters.heading') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Building/Wing Filter -->
                            <div v-if="buildings?.length > 0" role="group" aria-labelledby="filter-building-wing-label">
                                <span id="filter-building-wing-label" class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_operations.filters.building_wing') }}</span>
                                <BuildingWingFilter
                                    :buildings="buildings"
                                    v-model:buildingId="selectedBuildingId"
                                    v-model:wingId="selectedWingId"
                                    :showBadge="false"
                                    :buildingPlaceholder="t('bulk_operations.filters.all_buildings')"
                                    :wingPlaceholder="t('bulk_operations.filters.all_wings')"
                                    @change="onBuildingFilterChange"
                                />
                            </div>

                            <div>
                                <label for="filter-property" class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_operations.filters.property') }}</label>
                                <select id="filter-property" v-model="selectedPropertyId" class="w-full border-gray-300 rounded-md" :disabled="!!selectedBuildingId">
                                    <option value="">{{ t('bulk_operations.filters.all_properties') }}</option>
                                    <option v-for="property in properties" :key="property.id" :value="property.id">
                                        {{ property.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="filter-status" class="block text-sm font-medium text-gray-700 mb-1">{{ t('bulk_operations.filters.status') }}</label>
                                <select id="filter-status" v-model="selectedStatus" class="w-full border-gray-300 rounded-md">
                                    <option value="">{{ t('bulk_operations.filters.all_statuses') }}</option>
                                    <option value="vacant">{{ t('bulk_operations.status.vacant') }}</option>
                                    <option value="occupied">{{ t('bulk_operations.status.occupied') }}</option>
                                    <option value="maintenance">{{ t('bulk_operations.status.maintenance') }}</option>
                                    <option value="arrears">{{ t('bulk_operations.status.arrears') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-4">
                            <div class="text-sm text-gray-600">
                                {{ t('bulk_operations.filters.found_label') }} <strong>{{ filteredUnits.length }}</strong> {{ t('bulk_operations.filters.units_suffix') }}
                                <strong>{{ unitsWithLeases.length }}</strong> {{ t('bulk_operations.filters.with_active_leases') }}
                            </div>
                            <div v-if="selectedBuildingId || selectedWingId" class="text-xs px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full">
                                {{ t('bulk_operations.filters.strict_mode') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="['px-6 py-4 text-sm font-medium border-b-2 transition-colors', activeTab === tab.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <RentAdjustmentTab
                            v-if="activeTab === 'rent'"
                            :units-with-leases="unitsWithLeases"
                            :building-id="selectedBuildingId"
                            :wing-id="selectedWingId"
                            v-model:selected-lease-ids="selectedLeaseIds"
                            @success="handleSuccess"
                        />

                        <UnitStatusTab
                            v-if="activeTab === 'status'"
                            :filtered-units="filteredUnits"
                            :building-id="selectedBuildingId"
                            :wing-id="selectedWingId"
                            v-model:selected-unit-ids="selectedUnitIds"
                            @success="handleSuccess"
                        />

                        <LeaseManagementTab
                            v-if="activeTab === 'lease'"
                            :units-with-leases="unitsWithLeases"
                            :building-id="selectedBuildingId"
                            :wing-id="selectedWingId"
                            v-model:selected-lease-ids="selectedLeaseIds"
                            @success="handleSuccess"
                        />

                        <TargetRentTab
                            v-if="activeTab === 'target'"
                            :filtered-units="filteredUnits"
                            :building-id="selectedBuildingId"
                            :wing-id="selectedWingId"
                            v-model:selected-unit-ids="selectedUnitIds"
                            @success="handleSuccess"
                        />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
