<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import type { LandlordHomePageProps } from '@/types/tenants';
import {
    HomeModernIcon,
    PlusIcon,
    BuildingOfficeIcon,
    MapPinIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    UsersIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<LandlordHomePageProps>();

const showAddPropertyModal = ref(false);

// Compute total stats across all properties
const totalStats = computed(() => {
    let totalUnits = 0;
    let occupiedUnits = 0;
    let buildings = 0;

    props.properties.forEach(property => {
        property.buildings.forEach(building => {
            buildings++;
            totalUnits += building.units_count || 0;
            occupiedUnits += building.occupied_units_count || 0;
        });
    });

    return {
        properties: props.properties.length,
        buildings,
        totalUnits,
        occupiedUnits,
        occupancyRate: totalUnits > 0 ? Math.round((occupiedUnits / totalUnits) * 100) : 0
    };
});

const form = useForm({
    name: '',
    type: 'residential',
    address: '',
    building_name: 'Main Building',
    building_type: 'residential_apartment',
    floors: 1,
    units_per_floor: 1,
});

const submit = () => {
    form.post(route('properties.store'), {
        onSuccess: () => {
            showAddPropertyModal.value = false;
            form.reset();
        },
    });
};

const getOccupancyColor = (rate) => {
    if (rate >= 80) return 'text-green-600 bg-green-50';
    if (rate >= 50) return 'text-yellow-600 bg-yellow-50';
    return 'text-red-600 bg-red-50';
};
</script>

<template>
    <Head title="My Properties" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="font-semibold text-xl text-gray-800 leading-tight">My Properties</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage all your buildings and properties</p>
                </div>
                <button
                    @click="showAddPropertyModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm"
                >
                    <PlusIcon class="w-5 h-5 me-2" />
                    Add New Property
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-50 rounded-lg">
                                <HomeModernIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ totalStats.properties }}</p>
                                <p class="text-xs text-gray-500">Properties</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-50 rounded-lg">
                                <BuildingOfficeIcon class="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ totalStats.buildings }}</p>
                                <p class="text-xs text-gray-500">Buildings</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-50 rounded-lg">
                                <UsersIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ totalStats.occupiedUnits }}/{{ totalStats.totalUnits }}</p>
                                <p class="text-xs text-gray-500">Units Occupied</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-purple-50 rounded-lg">
                                <ChartBarIcon class="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ totalStats.occupancyRate }}%</p>
                                <p class="text-xs text-gray-500">Overall Occupancy</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="properties.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <EmptyState
                        :icon="HomeModernIcon"
                        title="No properties yet"
                        description="Get started by adding your first property. You can add multiple buildings/wings to each property."
                        action-label="Add Your First Property"
                        size="lg"
                        @action="showAddPropertyModal = true"
                    />
                </div>

                <!-- Properties List -->
                <div v-else class="space-y-6">
                    <div v-for="property in properties" :key="property.id" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Property Header -->
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-indigo-100 rounded-lg">
                                        <HomeModernIcon class="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ property.name }}</h3>
                                        <p v-if="property.address" class="text-sm text-gray-500 flex items-center gap-1">
                                            <MapPinIcon class="h-3.5 w-3.5" />
                                            {{ property.address }}
                                        </p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-indigo-50 text-indigo-700 capitalize">
                                    {{ property.type }}
                                </span>
                            </div>
                        </div>

                        <!-- Buildings Grid -->
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-medium text-gray-700">Buildings / Wings</h4>
                                <Link
                                    :href="route('buildings.store', property.id)"
                                    method="post"
                                    as="button"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    @click.prevent="() => {/* TODO: Add wing modal */}"
                                >
                                    + Add Wing
                                </Link>
                            </div>

                            <div v-if="property.buildings.length === 0" class="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg">
                                <BuildingOfficeIcon class="mx-auto h-8 w-8 text-gray-300" />
                                <p class="mt-2 text-sm text-gray-500">No buildings added yet</p>
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <Link
                                    v-for="building in property.buildings"
                                    :key="building.id"
                                    :href="route('buildings.show', building.id)"
                                    class="group block p-4 rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-md transition-all duration-200 bg-white"
                                >
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <BuildingOfficeIcon class="h-5 w-5 text-gray-400 group-hover:text-indigo-500 transition-colors" />
                                            <span class="font-medium text-gray-900 group-hover:text-indigo-700 transition-colors">{{ building.name }}</span>
                                        </div>
                                        <ArrowRightIcon class="h-4 w-4 text-gray-300 group-hover:text-indigo-500 group-hover:translate-x-1 transition-all" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-500">
                                            <span class="font-medium text-gray-700">{{ building.occupied_units_count || 0 }}</span> / {{ building.units_count || 0 }} units
                                        </div>
                                        <span
                                            class="px-2 py-0.5 text-xs font-medium rounded-full"
                                            :class="getOccupancyColor(building.occupancy_rate)"
                                        >
                                            {{ building.occupancy_rate || 0 }}% Occ.
                                        </span>
                                    </div>

                                    <!-- Quick Actions (visible on hover) -->
                                    <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link
                                            :href="route('buildings.dashboard', building.id)"
                                            class="flex-1 text-center text-xs py-1.5 px-2 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-medium"
                                            @click.stop
                                        >
                                            Dashboard
                                        </Link>
                                        <Link
                                            :href="route('buildings.edit', building.id)"
                                            class="flex-1 text-center text-xs py-1.5 px-2 rounded bg-gray-50 text-gray-700 hover:bg-gray-100 font-medium"
                                            @click.stop
                                        >
                                            Configure
                                        </Link>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Property Modal -->
        <Modal :show="showAddPropertyModal" @close="showAddPropertyModal = false" max-width="lg">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-1">Add New Property</h2>
                <p class="text-sm text-gray-500 mb-6">Create a new property with its first building</p>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Property Details -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Property Details</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Property Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Sunrise Apartments"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                required
                            >
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                                <select v-model="form.type" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="residential">Residential</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="industrial">Industrial</option>
                                    <option value="mixed">Mixed Use</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <input
                                    v-model="form.address"
                                    type="text"
                                    placeholder="e.g. Westlands, Nairobi"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Building Configuration -->
                    <div class="pt-4 border-t border-gray-200 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">First Building</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Building Name *</label>
                                <input
                                    v-model="form.building_name"
                                    type="text"
                                    placeholder="e.g. Block A"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Building Type</label>
                                <select v-model="form.building_type" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option v-for="(label, key) in buildingTypes" :key="key" :value="key">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of Floors *</label>
                                <input
                                    v-model="form.floors"
                                    type="number"
                                    min="1"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Units per Floor *</label>
                                <input
                                    v-model="form.units_per_floor"
                                    type="number"
                                    min="1"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                    required
                                >
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                            This will create <span class="font-semibold text-gray-900">{{ form.floors * form.units_per_floor }}</span> units total.
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showAddPropertyModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold text-sm shadow-sm disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Property</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
