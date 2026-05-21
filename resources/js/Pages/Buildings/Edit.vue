<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BuildingMap from '@/Components/BuildingMap.vue';
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useFormatters, useCurrency } from '@/composables';
import type { BuildingsEditPageProps, BuildingEditUnit } from '@/types/finances';

const { formatMoney } = useFormatters();
const { currencyCode, currencySymbol } = useCurrency();
import HomeIcon from '@heroicons/vue/24/outline/HomeIcon';
import BuildingOfficeIcon from '@heroicons/vue/24/outline/BuildingOfficeIcon';
import Cog6ToothIcon from '@heroicons/vue/24/outline/Cog6ToothIcon';
import TrashIcon from '@heroicons/vue/24/outline/TrashIcon';
import CurrencyDollarIcon from '@heroicons/vue/24/outline/CurrencyDollarIcon';
import CheckIcon from '@heroicons/vue/24/outline/CheckIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import MapPinIcon from '@heroicons/vue/24/outline/MapPinIcon';
import WifiIcon from '@heroicons/vue/24/outline/WifiIcon';
import ShieldCheckIcon from '@heroicons/vue/24/outline/ShieldCheckIcon';
import TruckIcon from '@heroicons/vue/24/outline/TruckIcon';
import SparklesIcon from '@heroicons/vue/24/outline/SparklesIcon';
import HomeModernIcon from '@heroicons/vue/24/outline/HomeModernIcon';
import MapIcon from '@heroicons/vue/24/outline/MapIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import ChevronDownIcon from '@heroicons/vue/24/outline/ChevronDownIcon';
import ChevronUpIcon from '@heroicons/vue/24/outline/ChevronUpIcon';
import BoltIcon from '@heroicons/vue/24/outline/BoltIcon';
import FireIcon from '@heroicons/vue/24/outline/FireIcon';
import SignalIcon from '@heroicons/vue/24/outline/SignalIcon';
import VideoCameraIcon from '@heroicons/vue/24/outline/VideoCameraIcon';
import LockClosedIcon from '@heroicons/vue/24/outline/LockClosedIcon';
import FingerPrintIcon from '@heroicons/vue/24/outline/FingerPrintIcon';
import BellAlertIcon from '@heroicons/vue/24/outline/BellAlertIcon';
import BuildingStorefrontIcon from '@heroicons/vue/24/outline/BuildingStorefrontIcon';
import UserGroupIcon from '@heroicons/vue/24/outline/UserGroupIcon';
import AcademicCapIcon from '@heroicons/vue/24/outline/AcademicCapIcon';
import HeartIcon from '@heroicons/vue/24/outline/HeartIcon';
import ShoppingBagIcon from '@heroicons/vue/24/outline/ShoppingBagIcon';
import DeliveryIcon from '@heroicons/vue/24/outline/TruckIcon';
import SpeakerWaveIcon from '@heroicons/vue/24/outline/SpeakerWaveIcon';
import SunIcon from '@heroicons/vue/24/outline/SunIcon';
import ArrowPathIcon from '@heroicons/vue/24/outline/ArrowPathIcon';
import EyeIcon from '@heroicons/vue/24/outline/EyeIcon';
import TagIcon from '@heroicons/vue/24/outline/TagIcon';
import ArrowsPointingOutIcon from '@heroicons/vue/24/outline/ArrowsPointingOutIcon';
import Square2StackIcon from '@heroicons/vue/24/outline/Square2StackIcon';
import BriefcaseIcon from '@heroicons/vue/24/outline/BriefcaseIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import ClockIcon from '@heroicons/vue/24/outline/ClockIcon';
import EnvelopeIcon from '@heroicons/vue/24/outline/EnvelopeIcon';
import CalendarDaysIcon from '@heroicons/vue/24/outline/CalendarDaysIcon';
import InformationCircleIcon from '@heroicons/vue/24/outline/InformationCircleIcon';
import CheckCircleIcon from '@heroicons/vue/24/solid/CheckCircleIcon';
import StarIcon from '@heroicons/vue/24/solid/StarIcon';

const props = defineProps<BuildingsEditPageProps>();

// Feature access from subscription
const page = usePage();
const canAccessWater = computed(() => page.props.featureAccess?.water_billing ?? false);

// --- TABS ---
const activeTab = ref('units');
const tabs = [
    { id: 'units', name: 'Units', icon: Square2StackIcon },
    { id: 'settings', name: 'Details', icon: BuildingOfficeIcon },
    { id: 'amenities', name: 'Amenities', icon: SparklesIcon },
    { id: 'location', name: 'Location', icon: MapPinIcon },
    { id: 'automation', name: 'Automation', icon: ClockIcon },
    { id: 'deductions', name: 'Deductions', icon: DocumentTextIcon },
];

// --- STATUS STYLES (static classes for Tailwind JIT safety) ---
const statusStyles = {
    occupied: 'bg-green-50 border-green-200 text-green-700',
    arrears: 'bg-red-50 border-red-200 text-red-700',
    maintenance: 'bg-amber-50 border-amber-200 text-amber-700',
    vacant: 'bg-gray-50 border-gray-200 text-gray-500',
};

const statusDotStyles = {
    occupied: 'bg-green-400',
    arrears: 'bg-red-400',
    maintenance: 'bg-amber-400',
    vacant: 'bg-gray-300',
};

const categoryStyles = {
    utilities: { bg: 'bg-amber-100', text: 'text-amber-600', badge: 'bg-amber-100 text-amber-700', selectedBorder: 'border-amber-400 bg-amber-50', selectedBg: 'bg-amber-500 text-white', selectedText: 'text-amber-700' },
    security: { bg: 'bg-red-100', text: 'text-red-600', badge: 'bg-red-100 text-red-700', selectedBorder: 'border-red-400 bg-red-50', selectedBg: 'bg-red-500 text-white', selectedText: 'text-red-700' },
    parking: { bg: 'bg-blue-100', text: 'text-blue-600', badge: 'bg-blue-100 text-blue-700', selectedBorder: 'border-blue-400 bg-blue-50', selectedBg: 'bg-blue-500 text-white', selectedText: 'text-blue-700' },
    common_amenities: { bg: 'bg-purple-100', text: 'text-purple-600', badge: 'bg-purple-100 text-purple-700', selectedBorder: 'border-purple-400 bg-purple-50', selectedBg: 'bg-purple-500 text-white', selectedText: 'text-purple-700' },
    unit_features: { bg: 'bg-emerald-100', text: 'text-emerald-600', badge: 'bg-emerald-100 text-emerald-700', selectedBorder: 'border-emerald-400 bg-emerald-50', selectedBg: 'bg-emerald-500 text-white', selectedText: 'text-emerald-700' },
    neighborhood: { bg: 'bg-cyan-100', text: 'text-cyan-600', badge: 'bg-cyan-100 text-cyan-700', selectedBorder: 'border-cyan-400 bg-cyan-50', selectedBg: 'bg-cyan-500 text-white', selectedText: 'text-cyan-700' },
};

// --- AMENITY CATEGORIES CONFIG ---
const categoryConfig = {
    utilities: { icon: BoltIcon, label: 'Utilities & Power' },
    security: { icon: ShieldCheckIcon, label: 'Security Features' },
    parking: { icon: TruckIcon, label: 'Parking & Transport' },
    common_amenities: { icon: SparklesIcon, label: 'Common Amenities' },
    unit_features: { icon: HomeModernIcon, label: 'Unit Features' },
    neighborhood: { icon: MapIcon, label: 'Neighborhood' },
};

const amenityIcons = {
    wifi: WifiIcon, hot_water: FireIcon, generator: BoltIcon, solar: SunIcon,
    borehole: ArrowPathIcon, water_tank: ArrowPathIcon, fiber_ready: SignalIcon,
    cctv: VideoCameraIcon, security_guard: ShieldCheckIcon, intercom: SpeakerWaveIcon,
    electric_fence: BoltIcon, gated: LockClosedIcon, biometric_access: FingerPrintIcon,
    security_alarm: BellAlertIcon, parking: TruckIcon, covered_parking: TruckIcon,
    motorcycle_parking: TruckIcon, visitor_parking: TruckIcon, parking_per_unit: TruckIcon,
    elevator: ArrowsPointingOutIcon, gym: HeartIcon, swimming_pool: SparklesIcon,
    playground: UserGroupIcon, laundry: ArrowPathIcon, rooftop: SunIcon,
    bbq_area: FireIcon, clubhouse: BuildingStorefrontIcon, meeting_room: UserGroupIcon,
    balcony: SunIcon, garden: SparklesIcon, pets_allowed: HeartIcon,
    furnished: HomeModernIcon, air_conditioning: SparklesIcon, washer_hookup: ArrowPathIcon,
    built_in_wardrobes: Square2StackIcon, en_suite: HomeIcon, near_schools: AcademicCapIcon,
    near_hospital: HeartIcon, near_shopping: ShoppingBagIcon, public_transport: DeliveryIcon,
    quiet_area: SpeakerWaveIcon, main_road_access: MapIcon,
};

// --- SETTINGS FORM ---
const settingsForm = useForm({
    name: props.building.name,
    building_type: props.building.building_type || 'residential',
    currency: props.building.currency || '',
    amenities: props.building.amenities || { selected: [], custom: [] },
    // Phase-78 AMENITY-DEPTH-3: per-amenity detail keyed by amenity key.
    amenity_details: props.amenityDetails || {},
    coordinates: props.building.coordinates || null
});

// Phase-78 AMENITY-DEPTH-3: flat [{key,label}] of selected predefined amenities
// for the detail editor.
const selectedAmenityList = computed(() => {
    const out = [];
    for (const items of Object.values(props.amenityOptions || {})) {
        for (const [key, label] of Object.entries(items)) {
            if ((settingsForm.amenities.selected || []).includes(key)) {
                out.push({ key, label });
            }
        }
    }
    return out;
});

// Pure getter — never mutates during render (the detail object is seeded on
// selection + mount below, so v-model always has a live target).
function amenityDetailFor(key) {
    return settingsForm.amenity_details[key] || {};
}

function ensureDetail(key) {
    if (!settingsForm.amenity_details[key]) {
        settingsForm.amenity_details = { ...settingsForm.amenity_details, [key]: {} };
    }
}

// Seed detail objects for the already-selected amenities once on mount.
onMounted(() => {
    (settingsForm.amenities.selected || []).forEach(ensureDetail);
});

const buildingTypes = [
    { value: 'residential_apartment', label: 'Residential Apartment', icon: HomeIcon },
    { value: 'office_block', label: 'Commercial/Office', icon: BuildingOfficeIcon },
    { value: 'mixed_use', label: 'Mixed Use', icon: BuildingStorefrontIcon },
    { value: 'single_unit_rental', label: 'Single Unit/House', icon: HomeModernIcon },
    { value: 'warehouse', label: 'Warehouse/Industrial', icon: TruckIcon },
];

const submitSettings = () => {
    settingsForm.put(route('buildings.update-settings', props.building.id), {
        preserveScroll: true,
    });
};

// --- AUTOMATION FORM ---
const automationForm = useForm({
    auto_generate_invoices: props.building.auto_generate_invoices || false,
    invoice_generation_day: props.building.invoice_generation_day || 1,
    auto_send_invoices: props.building.auto_send_invoices || false
});

const submitAutomation = () => {
    automationForm.put(route('buildings.automation-settings.update', props.building.id), {
        preserveScroll: true,
    });
};

const dayOptions = Array.from({ length: 28 }, (_, i) => i + 1);

const toggleAmenity = (key) => {
    const selected = settingsForm.amenities.selected || [];
    const index = selected.indexOf(key);
    if (index > -1) {
        selected.splice(index, 1);
    } else {
        selected.push(key);
        ensureDetail(key);
    }
    settingsForm.amenities = { ...settingsForm.amenities, selected };
};

const isAmenitySelected = (key) => {
    return (settingsForm.amenities.selected || []).includes(key);
};

const customAmenityInput = ref('');
const addCustomAmenity = () => {
    if (customAmenityInput.value.trim()) {
        const custom = settingsForm.amenities.custom || [];
        custom.push(customAmenityInput.value.trim());
        settingsForm.amenities = { ...settingsForm.amenities, custom };
        customAmenityInput.value = '';
    }
};

const removeCustomAmenity = (index) => {
    const custom = [...(settingsForm.amenities.custom || [])];
    custom.splice(index, 1);
    settingsForm.amenities = { ...settingsForm.amenities, custom };
};

const collapsedCategories = ref({});
const toggleCategory = (cat) => {
    collapsedCategories.value[cat] = !collapsedCategories.value[cat];
};

const getSelectedCount = (category, items) => {
    return Object.keys(items).filter(key => isAmenitySelected(key)).length;
};

// --- UNIT MANAGEMENT STATE ---
const selectedIds = ref([]);
const showActionModal = ref(false);
const showAddUnitModal = ref(false);
const modalType = ref('');
const hoveredUnit = ref(null);

// Group units by floor (descending - top floors first)
const unitsByFloor = computed(() => {
    const grouped = {};
    props.units.forEach(unit => {
        const floor = unit.floor_number || 1;
        if (!grouped[floor]) grouped[floor] = [];
        grouped[floor].push(unit);
    });
    return Object.entries(grouped)
        .map(([floor, units]) => ({
            floor: parseInt(floor),
            units: units.sort((a, b) => String(a.unit_number).localeCompare(
                String(b.unit_number), undefined, { numeric: true }
            ))
        }))
        .sort((a, b) => b.floor - a.floor);
});

const floorGridStyle = computed(() => ({
    gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))'
}));

const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(item => item !== id);
    } else {
        selectedIds.value.push(id);
    }
};

const selectFloor = (floor) => {
    const floorUnitIds = props.units
        .filter(u => u.floor_number === floor)
        .map(u => u.id);

    const allSelected = floorUnitIds.every(id => selectedIds.value.includes(id));

    if (allSelected) {
        selectedIds.value = selectedIds.value.filter(id => !floorUnitIds.includes(id));
    } else {
        selectedIds.value = [...new Set([...selectedIds.value, ...floorUnitIds])];
    }
};

const isFloorSelected = (floor) => {
    const floorUnitIds = props.units.filter(u => u.floor_number === floor).map(u => u.id);
    return floorUnitIds.length > 0 && floorUnitIds.every(id => selectedIds.value.includes(id));
};

const selectAll = () => {
    if (selectedIds.value.length === props.units.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.units.map(u => u.id);
    }
};

const clearSelection = () => {
    selectedIds.value = [];
};

// --- ACTION FORMS ---
const actionForm = useForm({
    selectedUnitIds: [],
    action: '',
    value: ''
});

const openActionModal = (type) => {
    modalType.value = type;
    actionForm.reset();

    if (type === 'delete') {
        showDeleteConfirm.value = true;
    } else {
        showActionModal.value = true;
    }
};

const showDeleteConfirm = ref(false);

const confirmDelete = () => {
    actionForm.selectedUnitIds = selectedIds.value;
    actionForm.action = 'delete';

    actionForm.post(route('buildings.update-units', props.building.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            showDeleteConfirm.value = false;
            actionForm.reset();
        }
    });
};

const submitAction = () => {
    actionForm.selectedUnitIds = selectedIds.value;
    actionForm.action = modalType.value;

    actionForm.post(route('buildings.update-units', props.building.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            showActionModal.value = false;
            actionForm.reset();
        }
    });
};

// --- ADD UNIT FORM ---
const addUnitForm = useForm({
    floor_number: '',
    unit_number: '',
    target_rent: '',
    unit_type: 'residential'
});

const openAddUnitModal = (floor = null) => {
    addUnitForm.reset();
    if (floor) {
        addUnitForm.floor_number = floor;
        const floorUnits = props.units.filter(u => u.floor_number === floor);
        const prefix = props.building.unit_prefix || '';
        let nextNum;
        if (floorUnits.length > 0) {
            const numericParts = floorUnits
                .map(u => parseInt(String(u.unit_number).replace(/^\D+/, ''), 10))
                .filter(n => !isNaN(n));
            nextNum = numericParts.length > 0 ? Math.max(...numericParts) + 1 : (floor * 100) + 1;
        } else {
            nextNum = (floor * 100) + 1;
        }
        addUnitForm.unit_number = prefix + nextNum;
    }
    showAddUnitModal.value = true;
};

const submitAddUnit = () => {
    addUnitForm.post(route('buildings.add-unit', props.building.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddUnitModal.value = false;
            addUnitForm.reset();
        }
    });
};

const getUnitClasses = (unit, isSelected) => {
    if (isSelected) {
        return 'bg-indigo-50 border-indigo-300 text-indigo-700 ring-2 ring-indigo-500 ring-offset-1';
    }
    if (unit.unit_type === 'commercial') {
        return 'bg-purple-50 border-purple-200 text-purple-700 hover:scale-105';
    }
    return (statusStyles[unit.status] || statusStyles.vacant) + ' hover:scale-105';
};

const updateCoordinates = (coords) => {
    settingsForm.coordinates = coords;
};

// --- BUILDING DELETION ---
const showDeleteBuildingModal = ref(false);
const deletingBuilding = ref(false);

const deleteTarget = computed(() => {
    if (props.building.is_wing && props.parentBuilding) {
        return props.parentBuilding;
    }
    return { id: props.building.id, name: props.building.name };
});

const confirmDeleteBuilding = () => {
    deletingBuilding.value = true;
    router.delete(route('buildings.destroy', deleteTarget.value.id), {
        onFinish: () => {
            deletingBuilding.value = false;
            showDeleteBuildingModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Configure Building" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <Cog6ToothIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ building.name }}</h1>
                        <p class="text-sm text-gray-500">{{ building.property?.name }} &middot; {{ units.length }} units</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('buildings.dashboard', building.id)"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <EyeIcon class="w-4 h-4 me-2" />
                        View Dashboard
                    </Link>
                    <button @click="showDeleteBuildingModal = true"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <TrashIcon class="w-4 h-4 me-2" />
                        Delete
                    </button>
                    <button @click="submitSettings"
                        :disabled="settingsForm.processing"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        <CheckIcon class="w-4 h-4 me-2" />
                        {{ settingsForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- WING NAVIGATION -->
                <div v-if="buildings.length > 1" class="flex items-center gap-2 overflow-x-auto pb-4 mb-4">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider me-2">Wings:</span>
                    <Link
                        v-for="b in buildings"
                        :key="b.id"
                        :href="route('buildings.edit', b.id)"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                            building.id === b.id
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
                        ]"
                        preserve-state>
                        {{ b.name }}
                    </Link>
                </div>

                <!-- Shared settings banner for multi-wing buildings -->
                <div v-if="buildings.length > 1"
                    class="flex items-center gap-2 px-4 py-2.5 mb-4 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-100 text-sm">
                    <InformationCircleIcon class="w-4 h-4 shrink-0" />
                    <span>Settings, amenities, and automation changes apply to all wings.</span>
                </div>

                <!-- TABS (underline style matching Hub pattern) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    'flex items-center gap-2 px-4 py-4 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    activeTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]">
                                <component :is="tab.icon"
                                    :class="[
                                        'w-5 h-5',
                                        activeTab === tab.id ? 'text-indigo-500' : 'text-gray-400'
                                    ]" />
                                <span class="hidden sm:inline">{{ tab.name }}</span>
                            </button>
                        </nav>
                    </div>

                    <!-- TAB CONTENT -->
                    <div class="p-4 sm:p-6">

                        <!-- === TAB: UNITS === -->
                        <div v-show="activeTab === 'units'" class="space-y-4">
                            <!-- Toolbar -->
                            <div class="flex flex-wrap gap-3 items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div v-if="selectedIds.length > 0"
                                        class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-200">
                                        <CheckCircleIcon class="w-4 h-4" />
                                        <span class="text-sm font-semibold">{{ selectedIds.length }} selected</span>
                                        <button @click="clearSelection" class="ms-1 p-0.5 hover:bg-indigo-100 rounded">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <button @click="openActionModal('update_rent')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'text-emerald-700 hover:bg-emerald-50' : 'text-gray-400'">
                                        <CurrencyDollarIcon class="w-4 h-4 me-1.5" />
                                        Set Rent
                                    </button>
                                    <button @click="openActionModal('update_type')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'text-purple-700 hover:bg-purple-50' : 'text-gray-400'">
                                        <TagIcon class="w-4 h-4 me-1.5" />
                                        Set Type
                                    </button>
                                    <button @click="openActionModal('delete')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'text-red-600 hover:bg-red-50' : 'text-gray-400'">
                                        <TrashIcon class="w-4 h-4 me-1.5" />
                                        Delete
                                    </button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button @click="selectAll"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                                        {{ selectedIds.length === units.length ? 'Deselect All' : 'Select All' }}
                                    </button>
                                    <button @click="openAddUnitModal()"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                        <PlusIcon class="w-4 h-4 me-1.5" />
                                        Add Unit
                                    </button>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-green-400"></div>
                                    Occupied
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-gray-300"></div>
                                    Vacant
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
                                    Maintenance
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
                                    Arrears
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full bg-purple-400"></div>
                                    Commercial
                                </div>
                            </div>

                            <!-- Floor-grouped Grid -->
                            <div class="max-h-150 overflow-y-auto rounded-lg border border-gray-200">
                                <div v-for="floorGroup in unitsByFloor" :key="floorGroup.floor" class="border-b border-gray-100 last:border-b-0">
                                    <!-- Floor Header -->
                                    <div class="px-4 sm:px-6 py-2 bg-gray-50 sticky top-0 z-10 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <button @click="selectFloor(floorGroup.floor)"
                                                :class="[
                                                    'px-2 py-1 rounded text-xs font-bold transition-colors',
                                                    isFloorSelected(floorGroup.floor) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'
                                                ]">
                                                F{{ floorGroup.floor }}
                                            </button>
                                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Floor {{ floorGroup.floor }}</span>
                                            <span class="text-xs text-gray-400">{{ floorGroup.units.length }} units</span>
                                        </div>
                                        <button @click="openAddUnitModal(floorGroup.floor)"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors">
                                            <PlusIcon class="w-3.5 h-3.5" />
                                            Add
                                        </button>
                                    </div>

                                    <!-- Units Grid -->
                                    <div class="p-4 sm:p-6 pt-3">
                                        <div class="grid gap-2 sm:gap-3" :style="floorGridStyle">
                                            <button
                                                v-for="unit in floorGroup.units"
                                                :key="unit.id"
                                                @click="toggleSelection(unit.id)"
                                                @mouseenter="hoveredUnit = unit.id"
                                                @mouseleave="hoveredUnit = null"
                                                :class="getUnitClasses(unit, selectedIds.includes(unit.id))"
                                                class="relative aspect-square rounded-lg border p-2 flex flex-col items-center justify-center transition-all text-center cursor-pointer">
                                                <!-- Selection check -->
                                                <div v-if="selectedIds.includes(unit.id)"
                                                    class="absolute top-1 end-1 w-4 h-4 bg-indigo-500 rounded-full flex items-center justify-center">
                                                    <CheckIcon class="w-2.5 h-2.5 text-white" />
                                                </div>
                                                <span class="text-sm font-bold leading-tight">{{ unit.unit_number }}</span>
                                                <span class="text-[10px] uppercase tracking-wide mt-0.5">
                                                    {{ unit.status === 'occupied' ? 'Occ' : unit.status === 'arrears' ? 'Late' : unit.status === 'maintenance' ? 'Mnt' : 'Vac' }}
                                                </span>
                                                <span class="text-[10px] mt-0.5 opacity-75">{{ formatMoney(unit.target_rent) }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="unitsByFloor.length === 0" class="p-12 text-center text-gray-400">
                                    <Square2StackIcon class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                    <p class="text-sm font-medium">No units yet</p>
                                    <p class="text-xs mt-1">Click "Add Unit" to create your first unit</p>
                                </div>
                            </div>
                        </div>

                        <!-- === TAB: SETTINGS === -->
                        <div v-show="activeTab === 'settings'" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Building Details -->
                                <div class="space-y-5">
                                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                        <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                        Building Information
                                    </h3>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Building Name</label>
                                        <input v-model="settingsForm.name" type="text"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                            placeholder="e.g., Sunrise Apartments">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Property Type</label>
                                        <select v-model="settingsForm.building_type"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                            <option v-for="type in buildingTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency Override</label>
                                        <select v-model="settingsForm.currency"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                            <option value="">Inherit from default</option>
                                            <option value="KES">Kenyan Shilling (KES)</option>
                                            <option value="USD">US Dollar (USD)</option>
                                            <option value="EUR">Euro (EUR)</option>
                                            <option value="GBP">British Pound (GBP)</option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">Leave as "Inherit" to use your default currency setting.</p>
                                    </div>
                                </div>

                                <!-- Structure Overview -->
                                <div class="space-y-5">
                                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                        <Square2StackIcon class="w-5 h-5 text-gray-400" />
                                        Structure Overview
                                    </h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                                            <div class="text-2xl font-bold text-gray-900">{{ building.total_floors }}</div>
                                            <div class="text-sm text-gray-500">Floors</div>
                                        </div>
                                        <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                                            <div class="text-2xl font-bold text-gray-900">{{ units.length }}</div>
                                            <div class="text-sm text-gray-500">Units</div>
                                        </div>
                                        <div class="p-4 rounded-xl border border-green-200 bg-green-50">
                                            <div class="text-2xl font-bold text-green-700">
                                                {{ units.filter(u => u.status === 'occupied').length }}
                                            </div>
                                            <div class="text-sm text-gray-500">Occupied</div>
                                        </div>
                                        <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                                            <div class="text-2xl font-bold text-gray-500">
                                                {{ units.filter(u => u.status === 'vacant').length }}
                                            </div>
                                            <div class="text-sm text-gray-500">Vacant</div>
                                        </div>
                                    </div>

                                    <div class="pt-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between text-sm mb-2">
                                            <span class="text-gray-500">Occupancy Rate</span>
                                            <span class="font-semibold text-gray-900">
                                                {{ units.length > 0 ? Math.round((units.filter(u => u.status === 'occupied').length / units.length) * 100) : 0 }}%
                                            </span>
                                        </div>
                                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-500 rounded-full transition-all duration-500"
                                                :style="`width: ${units.length > 0 ? (units.filter(u => u.status === 'occupied').length / units.length) * 100 : 0}%`"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- === TAB: AMENITIES === -->
                        <div v-show="activeTab === 'amenities'" class="space-y-4">
                            <div v-for="(items, category) in amenityOptions" :key="category"
                                class="border border-gray-200 rounded-xl overflow-hidden">
                                <!-- Category Header -->
                                <button @click="toggleCategory(category)"
                                    class="w-full px-4 sm:px-6 py-4 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div :class="[
                                            'w-10 h-10 rounded-xl flex items-center justify-center',
                                            categoryStyles[category]?.bg || 'bg-gray-100'
                                        ]">
                                            <component :is="categoryConfig[category]?.icon || SparklesIcon"
                                                :class="['w-5 h-5', categoryStyles[category]?.text || 'text-gray-600']" />
                                        </div>
                                        <div class="text-start">
                                            <h3 class="font-semibold text-gray-900">{{ categoryConfig[category]?.label || category }}</h3>
                                            <p class="text-xs text-gray-500">
                                                {{ getSelectedCount(category, items) }} of {{ Object.keys(items).length }} selected
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div v-if="getSelectedCount(category, items) > 0"
                                            :class="[
                                                'px-2 py-1 rounded-full text-xs font-semibold',
                                                categoryStyles[category]?.badge || 'bg-gray-100 text-gray-700'
                                            ]">
                                            {{ getSelectedCount(category, items) }}
                                        </div>
                                        <ChevronDownIcon :class="[
                                            'w-5 h-5 text-gray-400 transition-transform duration-200',
                                            collapsedCategories[category] ? '' : 'rotate-180'
                                        ]" />
                                    </div>
                                </button>

                                <!-- Category Items -->
                                <div v-show="!collapsedCategories[category]" class="px-4 sm:px-6 pb-4 sm:pb-6">
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                        <button v-for="(label, key) in items" :key="key"
                                            @click="toggleAmenity(key)"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-xl border-2 text-start transition-colors',
                                                isAmenitySelected(key)
                                                    ? (categoryStyles[category]?.selectedBorder || 'border-indigo-400 bg-indigo-50')
                                                    : 'border-gray-200 hover:border-gray-300 bg-white hover:bg-gray-50'
                                            ]">
                                            <div :class="[
                                                'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                                                isAmenitySelected(key)
                                                    ? (categoryStyles[category]?.selectedBg || 'bg-indigo-500 text-white')
                                                    : 'bg-gray-100 text-gray-400'
                                            ]">
                                                <component :is="amenityIcons[key] || SparklesIcon" class="w-4 h-4" />
                                            </div>
                                            <span :class="[
                                                'text-sm font-medium truncate',
                                                isAmenitySelected(key)
                                                    ? (categoryStyles[category]?.selectedText || 'text-indigo-700')
                                                    : 'text-gray-700'
                                            ]">{{ label }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Amenities -->
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
                                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                        <StarIcon class="w-5 h-5 text-amber-400" />
                                        Custom Amenities
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">Add unique features specific to your building</p>
                                </div>
                                <div class="p-4 sm:p-6">
                                    <div class="flex gap-2 mb-4">
                                        <input v-model="customAmenityInput" type="text"
                                            placeholder="e.g., Rooftop Garden, EV Charging"
                                            @keyup.enter="addCustomAmenity"
                                            class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <button @click="addCustomAmenity"
                                            :disabled="!customAmenityInput.trim()"
                                            class="px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            <PlusIcon class="w-5 h-5" />
                                        </button>
                                    </div>

                                    <div v-if="settingsForm.amenities.custom?.length" class="flex flex-wrap gap-2">
                                        <div v-for="(item, index) in settingsForm.amenities.custom" :key="index"
                                            class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-200">
                                            <StarIcon class="w-4 h-4 text-indigo-500" />
                                            <span class="text-sm font-medium">{{ item }}</span>
                                            <button @click="removeCustomAmenity(index)" class="p-0.5 hover:bg-indigo-100 rounded">
                                                <XMarkIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-400 text-center py-4">No custom amenities added yet</p>
                                </div>
                            </div>

                            <!-- Phase-78 AMENITY-DEPTH-3: per-amenity detail -->
                            <div v-if="selectedAmenityList.length" class="border border-gray-200 rounded-xl overflow-hidden" data-testid="amenity-details">
                                <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
                                    <h3 class="font-semibold text-gray-900">{{ $t('building.amenity_detail.title') }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ $t('building.amenity_detail.subtitle') }}</p>
                                </div>
                                <div class="p-4 sm:p-6 space-y-3">
                                    <div v-for="a in selectedAmenityList" :key="a.key" class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-center">
                                        <span class="text-sm font-medium text-gray-700">{{ a.label }}</span>
                                        <input v-model.number="amenityDetailFor(a.key).quantity" type="number" min="0" :placeholder="$t('building.amenity_detail.quantity')" class="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                                        <input v-model="amenityDetailFor(a.key).provider" type="text" :placeholder="$t('building.amenity_detail.provider')" class="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                                        <input v-model="amenityDetailFor(a.key).account_ref" type="text" :placeholder="$t('building.amenity_detail.account_ref')" class="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                                        <input v-model.number="amenityDetailFor(a.key).monthly_cost" type="number" min="0" step="0.01" :placeholder="$t('building.amenity_detail.monthly_cost')" class="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- === TAB: LOCATION === -->
                        <div v-show="activeTab === 'location'" class="space-y-6">
                            <div>
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2 mb-4">
                                    <MapPinIcon class="w-5 h-5 text-gray-400" />
                                    Building Location
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">Click on the map or drag the marker to set location</p>
                                <BuildingMap
                                    :coordinates="settingsForm.coordinates"
                                    :address="building.property?.address"
                                    :editable="true"
                                    height="400px"
                                    @update:coordinates="updateCoordinates"
                                />

                                <div v-if="settingsForm.coordinates?.lat" class="mt-4 flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                            <MapPinIcon class="w-5 h-5 text-indigo-600" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">Coordinates Set</div>
                                            <div class="text-xs text-gray-500">
                                                {{ settingsForm.coordinates.lat.toFixed(6) }}, {{ settingsForm.coordinates.lng.toFixed(6) }}
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="settingsForm.coordinates = null"
                                        class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Water Configuration Link -->
                            <div v-if="canAccessWater" class="p-6 rounded-xl border border-blue-200 bg-blue-50">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                        <Cog6ToothIcon class="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900">Water Billing Configuration</h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Set up water meters, billing rates, and consumption tracking.
                                        </p>
                                        <Link :href="route('buildings.water-settings', building.id)"
                                            class="inline-flex items-center mt-3 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                            Configure Water Settings
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- === TAB: AUTOMATION === -->
                        <div v-show="activeTab === 'automation'" class="space-y-6">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                                Invoice Automation
                            </h3>

                            <!-- Enable Automation Toggle -->
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                        <ClockIcon class="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">Auto-Generate Invoices</div>
                                        <div class="text-sm text-gray-500">Automatically create monthly invoices for active leases</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="automationForm.auto_generate_invoices" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <div v-if="automationForm.auto_generate_invoices" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <CalendarDaysIcon class="w-4 h-4 inline me-1 text-gray-400" />
                                        Generation Day of Month
                                    </label>
                                    <select v-model="automationForm.invoice_generation_day"
                                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                        <option v-for="day in dayOptions" :key="day" :value="day">
                                            {{ day }}{{ day === 1 ? 'st' : day === 2 ? 'nd' : day === 3 ? 'rd' : 'th' }} of each month
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Invoices will be generated at 6:00 AM on this day each month</p>
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                            <EnvelopeIcon class="w-5 h-5 text-green-600" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">Auto-Send via Email</div>
                                            <div class="text-sm text-gray-500">Automatically email invoices to tenants when generated</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="automationForm.auto_send_invoices" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <div class="flex gap-3">
                                    <DocumentTextIcon class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                                    <div class="text-sm text-blue-700">
                                        <p class="font-medium mb-1">What gets included in automated invoices:</p>
                                        <ul class="list-disc list-inside space-y-1 text-blue-600">
                                            <li>Base rent from unit configuration</li>
                                            <li>Water charges (if water billing is active)</li>
                                            <li>Previous balance/arrears automatically</li>
                                            <li>Late payment fees (if applicable)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-gray-100">
                                <button @click="submitAutomation"
                                    :disabled="automationForm.processing"
                                    class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                    <CheckIcon class="w-4 h-4 me-2" />
                                    {{ automationForm.processing ? 'Saving...' : 'Save Automation Settings' }}
                                </button>
                            </div>
                        </div>

                        <!-- === TAB: DEDUCTIONS === -->
                        <div v-show="activeTab === 'deductions'" class="space-y-6">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                                Move-Out Deduction Defaults
                            </h3>
                            <p class="text-sm text-gray-500">Configure deductions that automatically apply during move-out inspections.</p>

                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <div class="flex gap-3">
                                    <DocumentTextIcon class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                                    <div class="text-sm text-blue-700">
                                        <p class="font-medium mb-1">About Deduction Categories:</p>
                                        <ul class="list-disc list-inside space-y-1 text-blue-600">
                                            <li>Categories marked "Always Apply" are auto-added when inspections start</li>
                                            <li>You can create building-specific or global categories</li>
                                            <li>Default amounts can be adjusted during each inspection</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-center py-4">
                                <Link
                                    :href="route('move-out-categories.index')"
                                    class="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-xl hover:bg-indigo-100 transition-colors">
                                    <Cog6ToothIcon class="w-5 h-5" />
                                    Manage Deduction Categories
                                </Link>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- ==================== MODALS ==================== -->

        <!-- Action Modal (Set Rent / Set Type) -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showActionModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showActionModal = false"></div>

                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-100">
                                    <div class="flex items-center gap-3">
                                        <div :class="[
                                            'w-10 h-10 rounded-xl flex items-center justify-center',
                                            modalType === 'update_rent' ? 'bg-emerald-100' : 'bg-purple-100'
                                        ]">
                                            <CurrencyDollarIcon v-if="modalType === 'update_rent'" class="w-5 h-5 text-emerald-600" />
                                            <TagIcon v-else class="w-5 h-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">
                                                {{ modalType === 'update_rent' ? 'Set Rent Amount' : 'Set Unit Type' }}
                                            </h3>
                                            <p class="text-xs text-gray-500">Applying to {{ selectedIds.length }} units</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6">
                                    <div v-if="modalType === 'update_rent'">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Rent Amount ({{ currencyCode }})</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 start-0 ps-4 flex items-center pointer-events-none">
                                                <span class="text-gray-400 font-medium">{{ currencySymbol }}</span>
                                            </div>
                                            <input v-model="actionForm.value" type="number"
                                                class="w-full ps-14 pe-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-semibold"
                                                placeholder="25,000">
                                        </div>
                                    </div>

                                    <div v-if="modalType === 'update_type'">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Unit Type</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label :class="[
                                                'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-colors',
                                                actionForm.value === 'residential'
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            ]">
                                                <input type="radio" v-model="actionForm.value" value="residential" class="sr-only">
                                                <HomeIcon :class="['w-8 h-8', actionForm.value === 'residential' ? 'text-indigo-600' : 'text-gray-400']" />
                                                <span :class="['font-medium', actionForm.value === 'residential' ? 'text-indigo-700' : 'text-gray-700']">Residential</span>
                                            </label>
                                            <label :class="[
                                                'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-colors',
                                                actionForm.value === 'commercial'
                                                    ? 'border-purple-500 bg-purple-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            ]">
                                                <input type="radio" v-model="actionForm.value" value="commercial" class="sr-only">
                                                <BriefcaseIcon :class="['w-8 h-8', actionForm.value === 'commercial' ? 'text-purple-600' : 'text-gray-400']" />
                                                <span :class="['font-medium', actionForm.value === 'commercial' ? 'text-purple-700' : 'text-gray-700']">Commercial</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                                    <button @click="showActionModal = false"
                                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </button>
                                    <button @click="submitAction"
                                        :disabled="!actionForm.value || actionForm.processing"
                                        :class="[
                                            'px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors disabled:opacity-50',
                                            modalType === 'update_rent' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-purple-600 hover:bg-purple-700'
                                        ]">
                                        {{ actionForm.processing ? 'Applying...' : 'Apply Changes' }}
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>

                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                            <div class="p-6 text-center">
                                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                                    <TrashIcon class="w-8 h-8 text-red-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Units?</h3>
                                <p class="text-sm text-gray-500">
                                    You are about to delete <span class="font-semibold">{{ selectedIds.length }}</span> units.
                                    This action cannot be undone.
                                </p>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 flex gap-3">
                                <button @click="showDeleteConfirm = false"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button @click="confirmDelete"
                                    :disabled="actionForm.processing"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50">
                                    {{ actionForm.processing ? 'Deleting...' : 'Delete' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Add Unit Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showAddUnitModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showAddUnitModal = false"></div>

                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                                        <PlusIcon class="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Add New Unit</h3>
                                        <p class="text-xs text-gray-500">Create a new unit in the building</p>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="submitAddUnit" class="p-6 space-y-5">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                                        <input v-model="addUnitForm.floor_number" type="number"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="1">
                                        <p v-if="addUnitForm.errors.floor_number" class="text-red-500 text-xs mt-1">{{ addUnitForm.errors.floor_number }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit Number</label>
                                        <input v-model="addUnitForm.unit_number" type="text"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="101">
                                        <p v-if="addUnitForm.errors.unit_number" class="text-red-500 text-xs mt-1">{{ addUnitForm.errors.unit_number }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Rent ({{ currencyCode }})</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 start-0 ps-4 flex items-center pointer-events-none">
                                            <span class="text-gray-400 font-medium">{{ currencySymbol }}</span>
                                        </div>
                                        <input v-model="addUnitForm.target_rent" type="number"
                                            class="w-full ps-14 pe-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="25,000">
                                    </div>
                                    <p v-if="addUnitForm.errors.target_rent" class="text-red-500 text-xs mt-1">{{ addUnitForm.errors.target_rent }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Type</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label :class="[
                                            'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                                            addUnitForm.unit_type === 'residential'
                                                ? 'border-indigo-500 bg-indigo-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]">
                                            <input type="radio" v-model="addUnitForm.unit_type" value="residential" class="sr-only">
                                            <HomeIcon :class="['w-5 h-5', addUnitForm.unit_type === 'residential' ? 'text-indigo-600' : 'text-gray-400']" />
                                            <span :class="addUnitForm.unit_type === 'residential' ? 'text-indigo-700' : 'text-gray-700'">Residential</span>
                                        </label>
                                        <label :class="[
                                            'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                                            addUnitForm.unit_type === 'commercial'
                                                ? 'border-purple-500 bg-purple-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]">
                                            <input type="radio" v-model="addUnitForm.unit_type" value="commercial" class="sr-only">
                                            <BriefcaseIcon :class="['w-5 h-5', addUnitForm.unit_type === 'commercial' ? 'text-purple-600' : 'text-gray-400']" />
                                            <span :class="addUnitForm.unit_type === 'commercial' ? 'text-purple-700' : 'text-gray-700'">Commercial</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                    <button type="button" @click="showAddUnitModal = false"
                                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        :disabled="addUnitForm.processing"
                                        class="px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                        {{ addUnitForm.processing ? 'Creating...' : 'Create Unit' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Building Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showDeleteBuildingModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showDeleteBuildingModal = false"></div>

                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                            <div class="p-6 text-center">
                                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                                    <TrashIcon class="w-8 h-8 text-red-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Building?</h3>
                                <p class="text-sm text-gray-500">
                                    <template v-if="building.is_wing && parentBuilding">
                                        This will permanently delete <span class="font-semibold">{{ parentBuilding.name }}</span>,
                                        all {{ buildings.length }} wings, and their units. This action cannot be undone.
                                    </template>
                                    <template v-else>
                                        This will permanently delete <span class="font-semibold">{{ building.name }}</span>
                                        and all {{ units.length }} units. This action cannot be undone.
                                    </template>
                                </p>
                                <p v-if="$page.props.errors?.building" class="mt-3 text-sm text-red-600 font-medium">
                                    {{ $page.props.errors.building }}
                                </p>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 flex gap-3">
                                <button @click="showDeleteBuildingModal = false"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button @click="confirmDeleteBuilding"
                                    :disabled="deletingBuilding"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50">
                                    {{ deletingBuilding ? 'Deleting...' : 'Delete Building' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>
