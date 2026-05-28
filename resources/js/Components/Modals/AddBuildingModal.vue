<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/composables/useI18n';
import type { AddBuildingModalProps } from '@/types';
import {
    BuildingOffice2Icon,
    MapPinIcon,
    Squares2X2Icon,
    SparklesIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    CheckIcon,
    XMarkIcon,
    HomeModernIcon,
    WifiIcon,
    ShieldCheckIcon,
    TruckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<AddBuildingModalProps>();

const emit = defineEmits(['close']);

const { t } = useI18n();

// Multi-step wizard
const currentStep = ref(1);
const totalSteps = 4;

const form = useForm({
    name: '',
    building_type: 'residential_apartment',
    address: '',
    description: '',
    total_floors: 1,
    units_per_floor: 4,
    amenities: {
        selected: [],
        custom: [],
    },
    coordinates: null,
});

const stepTitles = computed(() => ({
    1: t('add_building_modal.step_basic_info'),
    2: t('add_building_modal.step_structure'),
    3: t('add_building_modal.step_amenities'),
    4: t('add_building_modal.step_review'),
}));

const stepIcons = {
    1: BuildingOffice2Icon,
    2: Squares2X2Icon,
    3: SparklesIcon,
    4: CheckIcon,
};

const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return form.name.trim() && form.building_type;
        case 2:
            return form.total_floors >= 1 && form.units_per_floor >= 1;
        case 3:
            return true; // Optional step
        case 4:
            return true;
        default:
            return false;
    }
});

const nextStep = () => {
    if (currentStep.value < totalSteps && canProceed.value) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
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

const getCategoryIcon = (category) => {
    switch(category) {
        case 'utilities': return WifiIcon;
        case 'security': return ShieldCheckIcon;
        case 'parking': return TruckIcon;
        case 'amenities': return SparklesIcon;
        case 'features': return HomeModernIcon;
        default: return CheckIcon;
    }
};

const getCategoryColor = (category, selected = false) => {
    if (!selected) return 'bg-white text-gray-600 border-gray-200 hover:border-gray-300';

    switch(category) {
        case 'utilities': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'security': return 'bg-red-50 text-red-700 border-red-200';
        case 'parking': return 'bg-yellow-50 text-yellow-700 border-yellow-200';
        case 'amenities': return 'bg-green-50 text-green-700 border-green-200';
        case 'features': return 'bg-purple-50 text-purple-700 border-purple-200';
        default: return 'bg-gray-50 text-gray-700 border-gray-200';
    }
};

const totalUnits = computed(() => form.total_floors * form.units_per_floor);

const submit = () => {
    form.post(route('buildings.storeStandalone'), {
        onSuccess: () => {
            emit('close');
        },
    });
};

const close = () => {
    form.reset();
    currentStep.value = 1;
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close" max-width="2xl">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ t('add_building_modal.title') }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ t('add_building_modal.step_progress', { current: currentStep, total: totalSteps, title: stepTitles[currentStep] }) }}</p>
                </div>
                <button @click="close" class="p-2 hover:bg-gray-100 rounded-lg transition">
                    <XMarkIcon class="w-5 h-5 text-gray-500" />
                </button>
            </div>

            <!-- Progress Steps -->
            <div class="flex items-center justify-between mb-8">
                <template v-for="step in totalSteps" :key="step">
                    <div class="flex items-center">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm transition-colors"
                            :class="step <= currentStep
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-100 text-gray-400'"
                        >
                            <component :is="stepIcons[step]" class="w-5 h-5" />
                        </div>
                    </div>
                    <div
                        v-if="step < totalSteps"
                        class="flex-1 h-1 mx-2 rounded-full transition-colors"
                        :class="step < currentStep ? 'bg-indigo-600' : 'bg-gray-200'"
                    ></div>
                </template>
            </div>

            <!-- Step Content -->
            <div class="min-h-[300px]">
                <!-- Step 1: Basic Info -->
                <div v-if="currentStep === 1" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.building_name_required') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="t('add_building_modal.building_name_placeholder')"
                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            :class="{ 'border-red-300': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.building_type_required') }}</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <button
                                v-for="(label, key) in buildingTypes"
                                :key="key"
                                type="button"
                                @click="form.building_type = key"
                                class="p-3 rounded-lg border-2 text-sm font-medium text-start transition-all"
                                :class="form.building_type === key
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-200 hover:border-gray-300 text-gray-700'"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.address_optional') }}</label>
                        <div class="relative">
                            <MapPinIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="form.address"
                                type="text"
                                :placeholder="t('add_building_modal.address_placeholder')"
                                class="w-full ps-10 border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 2: Structure -->
                <div v-if="currentStep === 2" class="space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.floors_required') }}</label>
                            <input
                                v-model.number="form.total_floors"
                                type="number"
                                min="1"
                                max="100"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-center text-lg font-semibold"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('add_building_modal.floors_hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.units_per_floor_required') }}</label>
                            <input
                                v-model.number="form.units_per_floor"
                                type="number"
                                min="1"
                                max="50"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-center text-lg font-semibold"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('add_building_modal.units_per_floor_hint') }}</p>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 rounded-xl p-6 text-center">
                        <div class="text-4xl font-bold text-indigo-600">{{ totalUnits }}</div>
                        <div class="text-sm text-gray-500 mt-1">{{ t('add_building_modal.total_units') }}</div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ t('add_building_modal.units_breakdown', { floors: form.total_floors, perFloor: form.units_per_floor, total: totalUnits }) }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('add_building_modal.description_optional') }}</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            :placeholder="t('add_building_modal.description_placeholder')"
                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <!-- Step 3: Amenities -->
                <div v-if="currentStep === 3" class="space-y-6">
                    <p class="text-sm text-gray-500">{{ t('add_building_modal.amenities_intro') }}</p>

                    <div v-for="(items, category) in amenityOptions" :key="category" class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-700 capitalize flex items-center gap-2">
                            <component :is="getCategoryIcon(category)" class="w-4 h-4" />
                            {{ category }}
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in items"
                                :key="key"
                                type="button"
                                @click="toggleAmenity(key)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all"
                                :class="getCategoryColor(category, isAmenitySelected(key))"
                            >
                                <CheckIcon v-if="isAmenitySelected(key)" class="w-4 h-4" />
                                {{ label }}
                            </button>
                        </div>
                    </div>

                    <div class="text-center text-sm text-gray-500 pt-4 border-t">
                        {{ t('add_building_modal.amenities_selected_count', { count: form.amenities.selected.length }) }}
                    </div>
                </div>

                <!-- Step 4: Review -->
                <div v-if="currentStep === 4" class="space-y-6">
                    <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">{{ t('add_building_modal.review_building_name') }}</p>
                                <p class="font-semibold text-gray-900">{{ form.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">{{ t('add_building_modal.review_type') }}</p>
                                <p class="font-semibold text-gray-900">{{ buildingTypes[form.building_type] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">{{ t('add_building_modal.review_structure') }}</p>
                                <p class="font-semibold text-gray-900">{{ t('add_building_modal.review_structure_value', { floors: form.total_floors, perFloor: form.units_per_floor }) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">{{ t('add_building_modal.total_units') }}</p>
                                <p class="font-semibold text-indigo-600">{{ t('add_building_modal.review_total_units_value', { total: totalUnits }) }}</p>
                            </div>
                        </div>

                        <div v-if="form.address">
                            <p class="text-sm text-gray-500">{{ t('add_building_modal.review_address') }}</p>
                            <p class="font-semibold text-gray-900">{{ form.address }}</p>
                        </div>

                        <div v-if="form.amenities.selected.length > 0">
                            <p class="text-sm text-gray-500 mb-2">{{ t('add_building_modal.review_amenities') }}</p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="key in form.amenities.selected"
                                    :key="key"
                                    class="px-2 py-1 bg-white rounded-full text-xs font-medium text-gray-700 border"
                                >
                                    {{ Object.values(amenityOptions).flatMap(cat => Object.entries(cat)).find(([k]) => k === key)?.[1] || key }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-800">
                            <strong>{{ t('add_building_modal.note_label') }}</strong> {{ t('add_building_modal.note_body', { total: totalUnits }) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-between mt-8 pt-6 border-t">
                <button
                    v-if="currentStep > 1"
                    @click="prevStep"
                    class="inline-flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"
                >
                    <ArrowLeftIcon class="w-4 h-4 me-2" />
                    {{ t('add_building_modal.back') }}
                </button>
                <div v-else></div>

                <div class="flex items-center gap-3">
                    <button
                        @click="close"
                        class="px-4 py-2 text-gray-600 hover:text-gray-900 transition"
                    >
                        {{ t('add_building_modal.cancel') }}
                    </button>

                    <button
                        v-if="currentStep < totalSteps"
                        @click="nextStep"
                        :disabled="!canProceed"
                        class="inline-flex items-center px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ t('add_building_modal.next') }}
                        <ArrowRightIcon class="w-4 h-4 ms-2" />
                    </button>

                    <button
                        v-else
                        @click="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition disabled:opacity-50"
                    >
                        <CheckIcon class="w-4 h-4 me-2" />
                        {{ t('add_building_modal.create_building') }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
