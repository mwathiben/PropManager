<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import BuildingMap from '@/Components/BuildingMap.vue';
import type { BuildingsShowPageProps } from '@/types/water';
import { useI18n } from '@/composables/useI18n';
import {
    BuildingOfficeIcon,
    MapPinIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    CheckIcon,
    PlusIcon,
    XMarkIcon,
    PencilIcon,
    ArrowLeftIcon,
    HomeModernIcon,
    WifiIcon,
    ShieldCheckIcon,
    TruckIcon,
    SparklesIcon,
    PhotoIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<BuildingsShowPageProps>();
const { t } = useI18n();

// Edit mode state
const isEditing = ref(false);
const showMapModal = ref(false);

// Form for editing building details
const form = useForm({
    name: props.building.name,
    building_type: props.building.building_type || 'residential_apartment',
    address: props.building.address || '',
    description: props.building.description || '',
    currency: props.building.currency || '',
    amenities: {
        selected: props.building.amenities?.selected || [],
        custom: props.building.amenities?.custom || [],
    },
    coordinates: props.building.coordinates || { lat: null, lng: null },
    photos: props.building.photos || [],
});

// Custom amenity input
const newCustomAmenity = ref('');

const addCustomAmenity = () => {
    if (newCustomAmenity.value.trim() && !form.amenities.custom.includes(newCustomAmenity.value.trim())) {
        form.amenities.custom.push(newCustomAmenity.value.trim());
        newCustomAmenity.value = '';
    }
};

const removeCustomAmenity = (index) => {
    form.amenities.custom.splice(index, 1);
};

const toggleAmenity = (key) => {
    const index = form.amenities.selected.indexOf(key);
    if (index === -1) {
        form.amenities.selected.push(key);
    } else {
        form.amenities.selected.splice(index, 1);
    }
};

const isAmenitySelected = (key) => {
    return form.amenities.selected.includes(key);
};

const otherBuildings = computed(() => props.siblingBuildings?.filter(b => b.id !== props.building.id) ?? []);

const saveChanges = () => {
    form.put(route('buildings.update-settings', props.building.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

const cancelEdit = () => {
    form.reset();
    form.name = props.building.name;
    form.building_type = props.building.building_type || 'residential_apartment';
    form.address = props.building.address || '';
    form.description = props.building.description || '';
    form.currency = props.building.currency || '';
    form.amenities = {
        selected: props.building.amenities?.selected || [],
        custom: props.building.amenities?.custom || [],
    };
    form.coordinates = props.building.coordinates || { lat: null, lng: null };
    isEditing.value = false;
};

// Get category icon
const getCategoryIcon = (category) => {
    switch(category) {
        case 'utilities': return WifiIcon;
        case 'security': return ShieldCheckIcon;
        case 'parking': return TruckIcon;
        case 'common_amenities': return SparklesIcon;
        case 'unit_features': return HomeModernIcon;
        case 'neighborhood': return GlobeAltIcon;
        // Legacy category names for backward compatibility
        case 'amenities': return SparklesIcon;
        case 'features': return HomeModernIcon;
        default: return CheckIcon;
    }
};

// Get category color
const getCategoryColor = (category) => {
    switch(category) {
        case 'utilities': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'security': return 'bg-red-50 text-red-700 border-red-200';
        case 'parking': return 'bg-yellow-50 text-yellow-700 border-yellow-200';
        case 'common_amenities': return 'bg-green-50 text-green-700 border-green-200';
        case 'unit_features': return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'neighborhood': return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'custom': return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        // Legacy category names
        case 'amenities': return 'bg-green-50 text-green-700 border-green-200';
        case 'features': return 'bg-purple-50 text-purple-700 border-purple-200';
        default: return 'bg-gray-50 text-gray-700 border-gray-200';
    }
};

// Update coordinates from map
const updateCoordinates = (coords) => {
    form.coordinates = coords;
};

const occupancyColor = computed(() => {
    const rate = props.unitStats.occupancy_rate;
    if (rate >= 80) return 'text-green-600';
    if (rate >= 50) return 'text-yellow-600';
    return 'text-red-600';
});
</script>

<template>
    <Head :title="building.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('buildings.index')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                            <Link :href="route('buildings.index')" class="hover:text-indigo-600">{{ t('buildings_show.breadcrumb_buildings') }}</Link>
                            <span>/</span>
                            <span class="text-gray-900 font-medium">{{ building.name }}</span>
                        </div>
                        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ t('buildings_show.page_title') }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('buildings.dashboard', building.id)"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 transition shadow-sm"
                    >
                        <ChartBarIcon class="w-4 h-4 me-2" />
                        {{ t('buildings_show.view_dashboard') }}
                    </Link>
                    <button
                        v-if="!isEditing"
                        @click="isEditing = true"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50 transition"
                    >
                        <PencilIcon class="w-4 h-4 me-2" />
                        {{ t('buildings_show.edit_details') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Building Info Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">{{ t('buildings_show.building_information') }}</h3>
                                <div v-if="isEditing" class="flex items-center gap-2">
                                    <button @click="cancelEdit" class="text-sm text-gray-500 hover:text-gray-700">{{ t('buildings_show.cancel') }}</button>
                                    <button
                                        @click="saveChanges"
                                        :disabled="form.processing"
                                        class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ t('buildings_show.save_changes') }}
                                    </button>
                                </div>
                            </div>

                            <div class="p-6 space-y-6">
                                <!-- Basic Details -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="building-name" class="block text-sm font-medium text-gray-500 mb-1">{{ t('buildings_show.building_name') }}</label>
                                        <input
                                            v-if="isEditing"
                                            id="building-name"
                                            v-model="form.name"
                                            type="text"
                                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                        <p v-else class="text-lg font-semibold text-gray-900">{{ building.name }}</p>
                                    </div>
                                    <div>
                                        <label for="building-type" class="block text-sm font-medium text-gray-500 mb-1">{{ t('buildings_show.building_type') }}</label>
                                        <select
                                            v-if="isEditing"
                                            id="building-type"
                                            v-model="form.building_type"
                                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option v-for="(label, key) in buildingTypes" :key="key" :value="key">
                                                {{ label }}
                                            </option>
                                        </select>
                                        <p v-else class="text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                                                {{ buildingTypes[building.building_type] || building.building_type }}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Currency Override -->
                                <div>
                                    <label for="building-currency" class="block text-sm font-medium text-gray-500 mb-1">{{ t('buildings_show.currency') }}</label>
                                    <select
                                        v-if="isEditing"
                                        id="building-currency"
                                        v-model="form.currency"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="">{{ t('buildings_show.currency_inherit_option') }}</option>
                                        <option value="KES">{{ t('buildings_show.currency_kes') }}</option>
                                        <option value="USD">{{ t('buildings_show.currency_usd') }}</option>
                                        <option value="EUR">{{ t('buildings_show.currency_eur') }}</option>
                                        <option value="GBP">{{ t('buildings_show.currency_gbp') }}</option>
                                    </select>
                                    <p v-else class="text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium bg-emerald-50 text-emerald-700">
                                            {{ building.currency || t('buildings_show.currency_inherited_display') }}
                                        </span>
                                    </p>
                                </div>

                                <!-- Address -->
                                <div>
                                    <label for="building-address" class="block text-sm font-medium text-gray-500 mb-1">{{ t('buildings_show.address_location') }}</label>
                                    <div v-if="isEditing" class="flex gap-2">
                                        <input
                                            id="building-address"
                                            v-model="form.address"
                                            type="text"
                                            :placeholder="t('buildings_show.enter_address_placeholder')"
                                            class="flex-1 border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                        <button
                                            @click="showMapModal = true"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50"
                                        >
                                            <MapPinIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <p v-else class="text-gray-900 flex items-center gap-2">
                                        <MapPinIcon class="w-4 h-4 text-gray-400" />
                                        {{ building.address || t('buildings_show.no_address_set') }}
                                    </p>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label for="building-description" class="block text-sm font-medium text-gray-500 mb-1">{{ t('buildings_show.description') }}</label>
                                    <textarea
                                        v-if="isEditing"
                                        id="building-description"
                                        v-model="form.description"
                                        rows="3"
                                        :placeholder="t('buildings_show.describe_building_placeholder')"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    ></textarea>
                                    <p v-else class="text-gray-600">{{ building.description || t('buildings_show.no_description_provided') }}</p>
                                </div>

                                <!-- Location Map Preview -->
                                <div v-if="!isEditing">
                                    <label class="block text-sm font-medium text-gray-500 mb-2">{{ t('buildings_show.location_on_map') }}</label>
                                    <BuildingMap
                                        :coordinates="building.coordinates"
                                        :address="building.address"
                                        :editable="false"
                                        height="200px"
                                    />
                                </div>

                                <!-- Editable Map -->
                                <div v-if="isEditing">
                                    <label class="block text-sm font-medium text-gray-500 mb-2">{{ t('buildings_show.set_location') }}</label>
                                    <BuildingMap
                                        :coordinates="form.coordinates"
                                        :address="form.address"
                                        :editable="true"
                                        height="250px"
                                        @update:coordinates="updateCoordinates"
                                    />
                                    <p class="mt-2 text-xs text-gray-500">{{ t('buildings_show.map_click_hint') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="font-semibold text-gray-900">{{ t('buildings_show.amenities_and_features') }}</h3>
                            </div>

                            <div class="p-6">
                                <!-- View Mode - Show active amenities -->
                                <div v-if="!isEditing">
                                    <div v-if="activeAmenities.length === 0" class="text-center py-8 text-gray-500">
                                        <SparklesIcon class="w-8 h-8 mx-auto text-gray-300" />
                                        <p class="mt-2">{{ t('buildings_show.no_amenities_configured') }}</p>
                                    </div>
                                    <div v-else class="flex flex-wrap gap-2">
                                        <span
                                            v-for="amenity in activeAmenities"
                                            :key="amenity.key + amenity.label"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border"
                                            :class="getCategoryColor(amenity.category)"
                                        >
                                            <component :is="getCategoryIcon(amenity.category)" class="w-4 h-4" />
                                            {{ amenity.label }}
                                            <!-- Phase-78 AMENITY-DEPTH-2: surface detail -->
                                            <span v-if="amenity.detail" class="ms-1 text-xs opacity-75">
                                                <template v-if="amenity.detail.quantity">×{{ amenity.detail.quantity }}</template>
                                                <template v-if="amenity.detail.provider"> · {{ amenity.detail.provider }}</template>
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Edit Mode - Show all amenity options -->
                                <div v-else class="space-y-6">
                                    <!-- Predefined Amenities by Category -->
                                    <div v-for="(items, category) in amenityOptions" :key="category">
                                        <h4 class="text-sm font-semibold text-gray-700 capitalize mb-3 flex items-center gap-2">
                                            <component :is="getCategoryIcon(category)" class="w-4 h-4" />
                                            {{ category }}
                                        </h4>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="(label, key) in items"
                                                :key="key"
                                                @click="toggleAmenity(key)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all"
                                                :class="isAmenitySelected(key)
                                                    ? getCategoryColor(category)
                                                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                                            >
                                                <CheckIcon v-if="isAmenitySelected(key)" class="w-4 h-4" />
                                                {{ label }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Custom Amenities -->
                                    <div class="pt-4 border-t border-gray-200">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ t('buildings_show.custom_amenities') }}</h4>
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            <span
                                                v-for="(custom, index) in form.amenities.custom"
                                                :key="index"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700 border border-indigo-200"
                                            >
                                                {{ custom }}
                                                <button @click="removeCustomAmenity(index)" class="hover:text-indigo-900">
                                                    <XMarkIcon class="w-4 h-4" />
                                                </button>
                                            </span>
                                        </div>
                                        <div class="flex gap-2">
                                            <input
                                                v-model="newCustomAmenity"
                                                type="text"
                                                :placeholder="t('buildings_show.add_custom_amenity_placeholder')"
                                                :aria-label="t('buildings_show.add_custom_amenity_placeholder')"
                                                class="flex-1 border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                @keydown.enter.prevent="addCustomAmenity"
                                            >
                                            <button
                                                @click="addCustomAmenity"
                                                class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                            >
                                                <PlusIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Unit Stats Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="font-semibold text-gray-900">{{ t('buildings_show.unit_statistics') }}</h3>
                            </div>
                            <div class="p-6">
                                <div class="text-center mb-6">
                                    <div class="text-4xl font-bold" :class="occupancyColor">
                                        {{ unitStats.occupancy_rate }}%
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('buildings_show.occupancy_rate') }}</div>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="text-gray-600">{{ t('buildings_show.total_units') }}</span>
                                        <span class="font-semibold text-gray-900">{{ unitStats.total }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="text-gray-600">{{ t('buildings_show.occupied') }}</span>
                                        <span class="font-semibold text-green-600">{{ unitStats.occupied }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="text-gray-600">{{ t('buildings_show.vacant') }}</span>
                                        <span class="font-semibold text-gray-500">{{ unitStats.vacant }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-gray-600">{{ t('buildings_show.maintenance') }}</span>
                                        <span class="font-semibold text-orange-500">{{ unitStats.maintenance }}</span>
                                    </div>
                                </div>

                                <Link
                                    :href="route('buildings.edit', building.id)"
                                    class="mt-6 block w-full text-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium text-sm hover:bg-gray-50 transition"
                                >
                                    <Cog6ToothIcon class="w-4 h-4 inline me-2" />
                                    {{ t('buildings_show.configure_units') }}
                                </Link>
                            </div>
                        </div>

                        <!-- Other Buildings in Property -->
                        <div v-if="otherBuildings.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="font-semibold text-gray-900">{{ t('buildings_show.other_buildings') }}</h3>
                            </div>
                            <div class="p-4 space-y-2">
                                <Link
                                    v-for="sibling in otherBuildings"
                                    :key="sibling.id"
                                    :href="route('buildings.show', sibling.id)"
                                    class="block p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/50 transition"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-900">{{ sibling.name }}</span>
                                        <span class="text-xs text-gray-500">{{ t('buildings_show.occupancy_short', { rate: sibling.occupancy_rate }) }}</span>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('buildings_show.units_count', { count: sibling.units_count }) }}</div>
                                </Link>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h3 class="font-semibold text-gray-900">{{ t('buildings_show.quick_actions') }}</h3>
                            </div>
                            <div class="p-4 space-y-2">
                                <Link
                                    :href="route('buildings.dashboard', building.id)"
                                    class="block w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg font-medium text-sm text-center hover:bg-indigo-700 transition"
                                >
                                    {{ t('buildings_show.view_dashboard') }}
                                </Link>
                                <Link
                                    :href="route('buildings.edit', building.id)"
                                    class="block w-full px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm text-center hover:bg-gray-50 transition"
                                >
                                    {{ t('buildings_show.configure_units_architect') }}
                                </Link>
                                <Link
                                    :href="route('buildings.water-settings', building.id)"
                                    class="block w-full px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm text-center hover:bg-gray-50 transition"
                                >
                                    {{ t('buildings_show.water_settings') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Modal -->
        <Modal :show="showMapModal" @close="showMapModal = false" max-width="2xl">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ t('buildings_show.set_location_on_map') }}</h2>
                <BuildingMap
                    :coordinates="form.coordinates"
                    :address="form.address"
                    :editable="true"
                    height="400px"
                    @update:coordinates="updateCoordinates"
                />
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label for="map-latitude" class="block text-sm font-medium text-gray-700 mb-1">{{ t('buildings_show.latitude') }}</label>
                        <input
                            id="map-latitude"
                            v-model.number="form.coordinates.lat"
                            type="number"
                            step="any"
                            class="w-full border-gray-300 rounded-lg"
                            placeholder="-1.2921"
                        >
                    </div>
                    <div>
                        <label for="map-longitude" class="block text-sm font-medium text-gray-700 mb-1">{{ t('buildings_show.longitude') }}</label>
                        <input
                            id="map-longitude"
                            v-model.number="form.coordinates.lng"
                            type="number"
                            step="any"
                            class="w-full border-gray-300 rounded-lg"
                            placeholder="36.8219"
                        >
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showMapModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        {{ t('buildings_show.cancel') }}
                    </button>
                    <button @click="showMapModal = false" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        {{ t('buildings_show.save_location') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
