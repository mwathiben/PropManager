<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BuildingMap from '@/Components/BuildingMap.vue';
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    HomeIcon,
    BuildingOfficeIcon,
    Cog6ToothIcon,
    TrashIcon,
    CurrencyDollarIcon,
    CheckIcon,
    PlusIcon,
    MapPinIcon,
    WifiIcon,
    ShieldCheckIcon,
    TruckIcon,
    SparklesIcon,
    HomeModernIcon,
    MapIcon,
    XMarkIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    BoltIcon,
    FireIcon,
    SignalIcon,
    VideoCameraIcon,
    LockClosedIcon,
    FingerPrintIcon,
    BellAlertIcon,
    BuildingStorefrontIcon,
    UserGroupIcon,
    AcademicCapIcon,
    HeartIcon,
    ShoppingBagIcon,
    TruckIcon as DeliveryIcon,
    SpeakerWaveIcon,
    SunIcon,
    ArrowPathIcon,
    EyeIcon,
    TagIcon,
    ArrowsPointingOutIcon,
    Square2StackIcon,
    BriefcaseIcon,
    DocumentTextIcon,
    ClockIcon,
    EnvelopeIcon,
    CalendarDaysIcon
} from '@heroicons/vue/24/outline';
import { CheckCircleIcon, StarIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    building: Object,
    buildings: Array,
    units: Array,
    amenityOptions: Object
});

// Feature access from subscription
const page = usePage();
const canAccessWater = computed(() => page.props.featureAccess?.water_billing ?? false);

// --- TABS ---
const activeTab = ref('units');
const tabs = [
    { id: 'units', name: 'Units', icon: Square2StackIcon, description: 'Manage unit configuration' },
    { id: 'settings', name: 'Details', icon: BuildingOfficeIcon, description: 'Building information' },
    { id: 'amenities', name: 'Amenities', icon: SparklesIcon, description: 'Features & facilities' },
    { id: 'location', name: 'Location', icon: MapPinIcon, description: 'Map & coordinates' },
    { id: 'automation', name: 'Automation', icon: ClockIcon, description: 'Invoice automation' },
];

// --- AMENITY CATEGORIES CONFIG ---
const categoryConfig = {
    utilities: {
        icon: BoltIcon,
        color: 'amber',
        label: 'Utilities & Power'
    },
    security: {
        icon: ShieldCheckIcon,
        color: 'red',
        label: 'Security Features'
    },
    parking: {
        icon: TruckIcon,
        color: 'blue',
        label: 'Parking & Transport'
    },
    common_amenities: {
        icon: SparklesIcon,
        color: 'purple',
        label: 'Common Amenities'
    },
    unit_features: {
        icon: HomeModernIcon,
        color: 'emerald',
        label: 'Unit Features'
    },
    neighborhood: {
        icon: MapIcon,
        color: 'cyan',
        label: 'Neighborhood'
    }
};

// Amenity icons mapping
const amenityIcons = {
    wifi: WifiIcon,
    hot_water: FireIcon,
    generator: BoltIcon,
    solar: SunIcon,
    borehole: ArrowPathIcon,
    water_tank: ArrowPathIcon,
    fiber_ready: SignalIcon,
    cctv: VideoCameraIcon,
    security_guard: ShieldCheckIcon,
    intercom: SpeakerWaveIcon,
    electric_fence: BoltIcon,
    gated: LockClosedIcon,
    biometric_access: FingerPrintIcon,
    security_alarm: BellAlertIcon,
    parking: TruckIcon,
    covered_parking: TruckIcon,
    motorcycle_parking: TruckIcon,
    visitor_parking: TruckIcon,
    parking_per_unit: TruckIcon,
    elevator: ArrowsPointingOutIcon,
    gym: HeartIcon,
    swimming_pool: SparklesIcon,
    playground: UserGroupIcon,
    laundry: ArrowPathIcon,
    rooftop: SunIcon,
    bbq_area: FireIcon,
    clubhouse: BuildingStorefrontIcon,
    meeting_room: UserGroupIcon,
    balcony: SunIcon,
    garden: SparklesIcon,
    pets_allowed: HeartIcon,
    furnished: HomeModernIcon,
    air_conditioning: SparklesIcon,
    washer_hookup: ArrowPathIcon,
    built_in_wardrobes: Square2StackIcon,
    en_suite: HomeIcon,
    near_schools: AcademicCapIcon,
    near_hospital: HeartIcon,
    near_shopping: ShoppingBagIcon,
    public_transport: DeliveryIcon,
    quiet_area: SpeakerWaveIcon,
    main_road_access: MapIcon
};

// --- SETTINGS FORM ---
const settingsForm = useForm({
    name: props.building.name,
    building_type: props.building.building_type || 'residential',
    amenities: props.building.amenities || { selected: [], custom: [] },
    coordinates: props.building.coordinates || null
});

const buildingTypes = [
    { value: 'residential', label: 'Residential Apartment', icon: HomeIcon },
    { value: 'commercial', label: 'Commercial/Office', icon: BuildingOfficeIcon },
    { value: 'mixed', label: 'Mixed Use', icon: BuildingStorefrontIcon },
    { value: 'single_unit', label: 'Single Unit/House', icon: HomeModernIcon },
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
    }
    settingsForm.amenities = { ...settingsForm.amenities, selected };
};

const isAmenitySelected = (key) => {
    return (settingsForm.amenities.selected || []).includes(key);
};

// Custom amenity handling
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

// Collapsed categories state
const collapsedCategories = ref({});
const toggleCategory = (cat) => {
    collapsedCategories.value[cat] = !collapsedCategories.value[cat];
};

// Count selected amenities per category
const getSelectedCount = (category, items) => {
    return Object.keys(items).filter(key => isAmenitySelected(key)).length;
};

// --- UNIT MANAGEMENT STATE ---
const selectedIds = ref([]);
const showActionModal = ref(false);
const showAddUnitModal = ref(false);
const modalType = ref('');
const hoveredUnit = ref(null);

// Computed: Floors in reverse order
const floors = computed(() => {
    return [...Array(props.building.total_floors).keys()].map(i => i + 1).reverse();
});

// Helper to find unit
const findUnit = (floor, col) => {
    const targetNumber = String((floor * 100) + col);
    return props.units.find(u => String(u.unit_number) === targetNumber && u.floor_number === floor);
};

const getUnitsOnFloor = (floor) => {
    return props.units.filter(u => u.floor_number === floor);
};

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
        let nextNum;
        if (floorUnits.length > 0) {
             const maxUnitNum = Math.max(...floorUnits.map(u => parseInt(u.unit_number)));
             nextNum = maxUnitNum + 1;
        } else {
             nextNum = (floor * 100) + 1;
        }
        addUnitForm.unit_number = nextNum;
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

// Unit status colors
const getUnitStatusColor = (unit) => {
    if (unit.status === 'occupied') return 'emerald';
    if (unit.status === 'maintenance') return 'amber';
    if (unit.status === 'arrears') return 'red';
    return 'gray';
};

const getUnitClasses = (unit, isSelected, isHovered) => {
    const status = getUnitStatusColor(unit);
    const isCommercial = unit.unit_type === 'commercial';

    let base = "relative flex flex-col justify-between p-3 h-28 rounded-xl border-2 transition-all duration-200 cursor-pointer ";

    if (isSelected) {
        return base + "border-indigo-500 bg-indigo-50 ring-2 ring-indigo-300 shadow-lg transform scale-[1.02] z-10";
    }

    if (isHovered) {
        base += "shadow-lg transform scale-[1.01] ";
    } else {
        base += "shadow-sm hover:shadow-md ";
    }

    if (isCommercial) {
        return base + `border-purple-200 bg-gradient-to-br from-purple-50 to-white`;
    }

    return base + `border-${status}-200 bg-gradient-to-br from-${status}-50/50 to-white`;
};

// Map coordinates update
const updateCoordinates = (coords) => {
    settingsForm.coordinates = coords;
};
</script>

<template>
    <Head title="Configure Building" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <Cog6ToothIcon class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h2 class="font-bold text-2xl text-gray-900">
                            {{ building.name }}
                        </h2>
                        <p class="text-sm text-gray-500">Building Configuration</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Link :href="route('buildings.dashboard', building.id)"
                        class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
                        <EyeIcon class="w-4 h-4 mr-2" />
                        View Dashboard
                    </Link>
                    <button @click="submitSettings"
                        :disabled="settingsForm.processing"
                        class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-md disabled:opacity-50">
                        <CheckIcon class="w-4 h-4 mr-2" />
                        {{ settingsForm.processing ? 'Saving...' : 'Save All Changes' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- WING NAVIGATION (if multiple buildings) -->
                <div v-if="buildings.length > 1" class="flex items-center gap-2 overflow-x-auto pb-2">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider mr-2">Wings:</span>
                    <Link
                        v-for="b in buildings"
                        :key="b.id"
                        :href="route('buildings.edit', b.id)"
                        :class="[
                            'px-4 py-2 rounded-xl text-sm font-medium transition-all whitespace-nowrap',
                            building.id === b.id
                                ? 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-md'
                                : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200 hover:border-gray-300'
                        ]"
                        preserve-state>
                        {{ b.name }}
                    </Link>
                </div>

                <!-- MODERN TABS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5">
                    <nav class="flex gap-1" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'flex-1 group flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-medium text-sm transition-all duration-200',
                                activeTab === tab.id
                                    ? 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-md'
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                            ]">
                            <component :is="tab.icon"
                                :class="[
                                    'w-5 h-5 transition-colors',
                                    activeTab === tab.id ? 'text-white' : 'text-gray-400 group-hover:text-gray-500'
                                ]" />
                            <span class="hidden sm:inline">{{ tab.name }}</span>
                        </button>
                    </nav>
                </div>

                <!-- === TAB: UNITS === -->
                <div v-show="activeTab === 'units'" class="space-y-4">
                    <!-- MODERN TOOLBAR -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sticky top-4 z-20">
                        <div class="flex flex-wrap gap-3 items-center justify-between">
                            <div class="flex items-center gap-2">
                                <!-- Selection indicator -->
                                <div v-if="selectedIds.length > 0"
                                    class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-200">
                                    <CheckCircleIcon class="w-4 h-4" />
                                    <span class="text-sm font-semibold">{{ selectedIds.length }} selected</span>
                                    <button @click="clearSelection" class="ml-1 p-0.5 hover:bg-indigo-100 rounded">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </div>

                                <!-- Bulk actions -->
                                <div class="flex items-center gap-2">
                                    <button @click="openActionModal('update_rent')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200' : 'bg-gray-50 text-gray-400 border border-gray-200'">
                                        <CurrencyDollarIcon class="w-4 h-4 mr-1.5" />
                                        Set Rent
                                    </button>
                                    <button @click="openActionModal('update_type')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200' : 'bg-gray-50 text-gray-400 border border-gray-200'">
                                        <TagIcon class="w-4 h-4 mr-1.5" />
                                        Set Type
                                    </button>
                                    <button @click="openActionModal('delete')"
                                        :disabled="selectedIds.length === 0"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                        :class="selectedIds.length > 0 ? 'bg-red-50 text-red-600 hover:bg-red-100 border border-red-200' : 'bg-gray-50 text-gray-400 border border-gray-200'">
                                        <TrashIcon class="w-4 h-4 mr-1.5" />
                                        Delete
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button @click="selectAll"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition-all">
                                    {{ selectedIds.length === units.length ? 'Deselect All' : 'Select All' }}
                                </button>
                                <button @click="openAddUnitModal()"
                                    class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all shadow-md">
                                    <PlusIcon class="w-4 h-4 mr-1.5" />
                                    Add Unit
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- VISUAL BUILDING GRID -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 overflow-x-auto">
                        <!-- Building Visualization Header -->
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <BuildingOfficeIcon class="w-5 h-5 text-gray-500" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Building Layout</h3>
                                    <p class="text-xs text-gray-500">{{ building.total_floors }} floors &middot; {{ units.length }} units</p>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                                    <span class="text-gray-500">Occupied</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                                    <span class="text-gray-500">Vacant</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-purple-400"></div>
                                    <span class="text-gray-500">Commercial</span>
                                </div>
                            </div>
                        </div>

                        <!-- Grid -->
                        <div class="inline-grid gap-3" :style="`grid-template-columns: 80px repeat(${building.units_per_floor}, minmax(120px, 1fr)) 50px`">
                            <template v-for="floor in floors" :key="floor">
                                <!-- FLOOR LABEL -->
                                <div
                                    @click="selectFloor(floor)"
                                    :class="[
                                        'h-28 flex flex-col items-center justify-center rounded-xl cursor-pointer transition-all group',
                                        isFloorSelected(floor)
                                            ? 'bg-indigo-100 border-2 border-indigo-300'
                                            : 'bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-transparent hover:border-indigo-200'
                                    ]">
                                    <span class="text-2xl font-bold text-gray-300 group-hover:text-indigo-500 transition-colors">{{ floor }}</span>
                                    <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Floor</span>
                                    <span class="text-[10px] text-gray-400 mt-1">{{ getUnitsOnFloor(floor).length }} units</span>
                                </div>

                                <!-- UNITS -->
                                <div v-for="col in building.units_per_floor" :key="`${floor}-${col}`">
                                    <div v-if="findUnit(floor, col)"
                                         @click="toggleSelection(findUnit(floor, col).id)"
                                         @mouseenter="hoveredUnit = findUnit(floor, col).id"
                                         @mouseleave="hoveredUnit = null"
                                         :class="getUnitClasses(findUnit(floor, col), selectedIds.includes(findUnit(floor, col).id), hoveredUnit === findUnit(floor, col).id)">

                                        <!-- Top row: Unit number + Selection check -->
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-lg font-bold text-gray-800">{{ findUnit(floor, col).unit_number }}</span>
                                                <span v-if="findUnit(floor, col).unit_type === 'commercial'"
                                                    class="px-1.5 py-0.5 text-[9px] font-bold uppercase bg-purple-100 text-purple-600 rounded">
                                                    COM
                                                </span>
                                            </div>
                                            <div :class="[
                                                'w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all',
                                                selectedIds.includes(findUnit(floor, col).id)
                                                    ? 'bg-indigo-500 border-indigo-500'
                                                    : 'border-gray-300 bg-white'
                                            ]">
                                                <CheckIcon v-if="selectedIds.includes(findUnit(floor, col).id)" class="w-3 h-3 text-white" />
                                            </div>
                                        </div>

                                        <!-- Center: Status indicator -->
                                        <div class="flex items-center justify-center flex-grow">
                                            <div :class="[
                                                'w-2 h-2 rounded-full',
                                                `bg-${getUnitStatusColor(findUnit(floor, col))}-400`
                                            ]"></div>
                                        </div>

                                        <!-- Bottom: Rent -->
                                        <div class="text-right">
                                            <span class="text-sm font-semibold text-gray-700">
                                                KES {{ Number(findUnit(floor, col).target_rent).toLocaleString() }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Empty slot -->
                                    <div v-else
                                        class="h-28 border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center bg-gray-50/50 group hover:border-indigo-300 hover:bg-indigo-50/50 transition-all cursor-pointer"
                                        @click="openAddUnitModal(floor)">
                                        <PlusIcon class="w-5 h-5 text-gray-300 group-hover:text-indigo-400 transition-colors" />
                                    </div>
                                </div>

                                <!-- Add unit button at end of row -->
                                <div class="h-28 flex items-center justify-center">
                                    <button @click="openAddUnitModal(floor)"
                                        class="p-2 rounded-full bg-gray-100 hover:bg-indigo-100 text-gray-400 hover:text-indigo-600 transition-all"
                                        title="Add unit to this floor">
                                        <PlusIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- === TAB: SETTINGS === -->
                <div v-show="activeTab === 'settings'" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Building Details Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                    Building Information
                                </h3>
                            </div>
                            <div class="p-6 space-y-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Building Name</label>
                                    <input v-model="settingsForm.name" type="text"
                                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                        placeholder="e.g., Sunrise Apartments">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Property Type</label>
                                    <div class="grid grid-cols-1 gap-2">
                                        <label v-for="type in buildingTypes" :key="type.value"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all',
                                                settingsForm.building_type === type.value
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                            ]">
                                            <input type="radio" v-model="settingsForm.building_type" :value="type.value" class="sr-only">
                                            <div :class="[
                                                'w-10 h-10 rounded-lg flex items-center justify-center',
                                                settingsForm.building_type === type.value
                                                    ? 'bg-indigo-500 text-white'
                                                    : 'bg-gray-100 text-gray-400'
                                            ]">
                                                <component :is="type.icon" class="w-5 h-5" />
                                            </div>
                                            <span :class="[
                                                'font-medium',
                                                settingsForm.building_type === type.value ? 'text-indigo-700' : 'text-gray-700'
                                            ]">{{ type.label }}</span>
                                            <CheckCircleIcon v-if="settingsForm.building_type === type.value"
                                                class="w-5 h-5 text-indigo-500 ml-auto" />
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Structure Info Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <Square2StackIcon class="w-5 h-5 text-gray-400" />
                                    Structure Overview
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gradient-to-br from-indigo-50 to-white p-4 rounded-xl border border-indigo-100">
                                        <div class="text-3xl font-bold text-indigo-600">{{ building.total_floors }}</div>
                                        <div class="text-sm text-gray-500">Total Floors</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-purple-50 to-white p-4 rounded-xl border border-purple-100">
                                        <div class="text-3xl font-bold text-purple-600">{{ units.length }}</div>
                                        <div class="text-sm text-gray-500">Total Units</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-emerald-50 to-white p-4 rounded-xl border border-emerald-100">
                                        <div class="text-3xl font-bold text-emerald-600">
                                            {{ units.filter(u => u.status === 'occupied').length }}
                                        </div>
                                        <div class="text-sm text-gray-500">Occupied</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-50 to-white p-4 rounded-xl border border-gray-200">
                                        <div class="text-3xl font-bold text-gray-600">
                                            {{ units.filter(u => u.status === 'vacant').length }}
                                        </div>
                                        <div class="text-sm text-gray-500">Vacant</div>
                                    </div>
                                </div>

                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-500">Occupancy Rate</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ units.length > 0 ? Math.round((units.filter(u => u.status === 'occupied').length / units.length) * 100) : 0 }}%
                                        </span>
                                    </div>
                                    <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 rounded-full transition-all duration-500"
                                            :style="`width: ${units.length > 0 ? (units.filter(u => u.status === 'occupied').length / units.length) * 100 : 0}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- === TAB: AMENITIES === -->
                <div v-show="activeTab === 'amenities'" class="space-y-4">
                    <!-- Amenity Categories -->
                    <div v-for="(items, category) in amenityOptions" :key="category"
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Category Header -->
                        <button @click="toggleCategory(category)"
                            class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 hover:to-gray-50 transition-all">
                            <div class="flex items-center gap-3">
                                <div :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center',
                                    `bg-${categoryConfig[category]?.color || 'gray'}-100`
                                ]">
                                    <component :is="categoryConfig[category]?.icon || SparklesIcon"
                                        :class="[
                                            'w-5 h-5',
                                            `text-${categoryConfig[category]?.color || 'gray'}-600`
                                        ]" />
                                </div>
                                <div class="text-left">
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
                                        `bg-${categoryConfig[category]?.color || 'gray'}-100 text-${categoryConfig[category]?.color || 'gray'}-700`
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
                        <div v-show="!collapsedCategories[category]" class="px-6 pb-6">
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <button v-for="(label, key) in items" :key="key"
                                    @click="toggleAmenity(key)"
                                    :class="[
                                        'flex items-center gap-3 p-3 rounded-xl border-2 text-left transition-all',
                                        isAmenitySelected(key)
                                            ? `border-${categoryConfig[category]?.color || 'indigo'}-400 bg-${categoryConfig[category]?.color || 'indigo'}-50`
                                            : 'border-gray-200 hover:border-gray-300 bg-white hover:bg-gray-50'
                                    ]">
                                    <div :class="[
                                        'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
                                        isAmenitySelected(key)
                                            ? `bg-${categoryConfig[category]?.color || 'indigo'}-500 text-white`
                                            : 'bg-gray-100 text-gray-400'
                                    ]">
                                        <component :is="amenityIcons[key] || SparklesIcon" class="w-4 h-4" />
                                    </div>
                                    <span :class="[
                                        'text-sm font-medium truncate',
                                        isAmenitySelected(key) ? `text-${categoryConfig[category]?.color || 'indigo'}-700` : 'text-gray-700'
                                    ]">{{ label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Amenities -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                <StarIcon class="w-5 h-5 text-amber-400" />
                                Custom Amenities
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Add unique features specific to your building</p>
                        </div>
                        <div class="p-6">
                            <div class="flex gap-2 mb-4">
                                <input v-model="customAmenityInput" type="text"
                                    placeholder="e.g., Rooftop Garden, EV Charging"
                                    @keyup.enter="addCustomAmenity"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                <button @click="addCustomAmenity"
                                    :disabled="!customAmenityInput.trim()"
                                    class="px-4 py-2.5 bg-amber-500 text-white font-medium rounded-xl hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                                    <PlusIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <div v-if="settingsForm.amenities.custom?.length" class="flex flex-wrap gap-2">
                                <div v-for="(item, index) in settingsForm.amenities.custom" :key="index"
                                    class="inline-flex items-center gap-2 px-3 py-2 bg-amber-50 text-amber-700 rounded-lg border border-amber-200">
                                    <StarIcon class="w-4 h-4 text-amber-500" />
                                    <span class="text-sm font-medium">{{ item }}</span>
                                    <button @click="removeCustomAmenity(index)" class="p-0.5 hover:bg-amber-100 rounded">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400 text-center py-4">No custom amenities added yet</p>
                        </div>
                    </div>
                </div>

                <!-- === TAB: LOCATION === -->
                <div v-show="activeTab === 'location'" class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                <MapPinIcon class="w-5 h-5 text-gray-400" />
                                Building Location
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Click on the map or drag the marker to set location</p>
                        </div>
                        <div class="p-6">
                            <BuildingMap
                                :coordinates="settingsForm.coordinates"
                                :address="building.property?.address"
                                :editable="true"
                                height="400px"
                                @update:coordinates="updateCoordinates"
                            />

                            <!-- Coordinate Display -->
                            <div v-if="settingsForm.coordinates?.lat" class="mt-4 flex items-center justify-between p-4 bg-gray-50 rounded-xl">
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
                                    class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-all">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Water Configuration Link (only if water billing feature is enabled) -->
                    <div v-if="canAccessWater" class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-2xl border border-blue-100 p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-500 flex items-center justify-center flex-shrink-0">
                                <Cog6ToothIcon class="w-6 h-6 text-white" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900">Water Billing Configuration</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Set up water meters, billing rates, and consumption tracking for this building.
                                </p>
                                <Link :href="route('buildings.water-settings', building.id)"
                                    class="inline-flex items-center mt-4 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                                    Configure Water Settings
                                    <ChevronDownIcon class="w-4 h-4 ml-2 -rotate-90" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- === TAB: AUTOMATION === -->
                <div v-show="activeTab === 'automation'" class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                                Invoice Automation
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Configure automatic invoice generation for this building</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Enable Automation Toggle -->
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
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
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            <!-- Generation Day -->
                            <div v-if="automationForm.auto_generate_invoices" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <CalendarDaysIcon class="w-4 h-4 inline mr-1 text-gray-400" />
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

                                <!-- Auto-Send Toggle -->
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                            <EnvelopeIcon class="w-5 h-5 text-emerald-600" />
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">Auto-Send via Email</div>
                                            <div class="text-sm text-gray-500">Automatically email invoices to tenants when generated</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="automationForm.auto_send_invoices" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- Info Box -->
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <div class="flex gap-3">
                                    <DocumentTextIcon class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
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

                            <!-- Save Button -->
                            <div class="flex justify-end pt-4 border-t border-gray-100">
                                <button @click="submitAutomation"
                                    :disabled="automationForm.processing"
                                    class="inline-flex items-center px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-md disabled:opacity-50">
                                    <CheckIcon class="w-4 h-4 mr-2" />
                                    {{ automationForm.processing ? 'Saving...' : 'Save Automation Settings' }}
                                </button>
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
                        <!-- Backdrop -->
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showActionModal = false"></div>

                        <!-- Modal -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                                <!-- Header -->
                                <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
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

                                <!-- Content -->
                                <div class="p-6">
                                    <div v-if="modalType === 'update_rent'">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Rent Amount (KES)</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <span class="text-gray-400 font-medium">KES</span>
                                            </div>
                                            <input v-model="actionForm.value" type="number"
                                                class="w-full pl-14 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-lg font-semibold"
                                                placeholder="25,000">
                                        </div>
                                    </div>

                                    <div v-if="modalType === 'update_type'">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Unit Type</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label :class="[
                                                'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                                actionForm.value === 'residential'
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            ]">
                                                <input type="radio" v-model="actionForm.value" value="residential" class="sr-only">
                                                <HomeIcon :class="[
                                                    'w-8 h-8',
                                                    actionForm.value === 'residential' ? 'text-indigo-600' : 'text-gray-400'
                                                ]" />
                                                <span :class="[
                                                    'font-medium',
                                                    actionForm.value === 'residential' ? 'text-indigo-700' : 'text-gray-700'
                                                ]">Residential</span>
                                            </label>
                                            <label :class="[
                                                'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                                actionForm.value === 'commercial'
                                                    ? 'border-purple-500 bg-purple-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            ]">
                                                <input type="radio" v-model="actionForm.value" value="commercial" class="sr-only">
                                                <BriefcaseIcon :class="[
                                                    'w-8 h-8',
                                                    actionForm.value === 'commercial' ? 'text-purple-600' : 'text-gray-400'
                                                ]" />
                                                <span :class="[
                                                    'font-medium',
                                                    actionForm.value === 'commercial' ? 'text-purple-700' : 'text-gray-700'
                                                ]">Commercial</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                                    <button @click="showActionModal = false"
                                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                                        Cancel
                                    </button>
                                    <button @click="submitAction"
                                        :disabled="!actionForm.value || actionForm.processing"
                                        :class="[
                                            'px-4 py-2.5 text-sm font-semibold text-white rounded-xl transition-all disabled:opacity-50',
                                            modalType === 'update_rent'
                                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                                : 'bg-purple-600 hover:bg-purple-700'
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
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                                    Cancel
                                </button>
                                <button @click="confirmDelete"
                                    :disabled="actionForm.processing"
                                    class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-all disabled:opacity-50">
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
                            <!-- Header -->
                            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
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

                            <!-- Form -->
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
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Rent (KES)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <span class="text-gray-400 font-medium">KES</span>
                                        </div>
                                        <input v-model="addUnitForm.target_rent" type="number"
                                            class="w-full pl-14 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="25,000">
                                    </div>
                                    <p v-if="addUnitForm.errors.target_rent" class="text-red-500 text-xs mt-1">{{ addUnitForm.errors.target_rent }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Type</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label :class="[
                                            'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all',
                                            addUnitForm.unit_type === 'residential'
                                                ? 'border-indigo-500 bg-indigo-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]">
                                            <input type="radio" v-model="addUnitForm.unit_type" value="residential" class="sr-only">
                                            <HomeIcon :class="[
                                                'w-5 h-5',
                                                addUnitForm.unit_type === 'residential' ? 'text-indigo-600' : 'text-gray-400'
                                            ]" />
                                            <span :class="addUnitForm.unit_type === 'residential' ? 'text-indigo-700' : 'text-gray-700'">Residential</span>
                                        </label>
                                        <label :class="[
                                            'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all',
                                            addUnitForm.unit_type === 'commercial'
                                                ? 'border-purple-500 bg-purple-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]">
                                            <input type="radio" v-model="addUnitForm.unit_type" value="commercial" class="sr-only">
                                            <BriefcaseIcon :class="[
                                                'w-5 h-5',
                                                addUnitForm.unit_type === 'commercial' ? 'text-purple-600' : 'text-gray-400'
                                            ]" />
                                            <span :class="addUnitForm.unit_type === 'commercial' ? 'text-purple-700' : 'text-gray-700'">Commercial</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                    <button type="button" @click="showAddUnitModal = false"
                                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        :disabled="addUnitForm.processing"
                                        class="px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all disabled:opacity-50">
                                        {{ addUnitForm.processing ? 'Creating...' : 'Create Unit' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom scrollbar for the grid */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
