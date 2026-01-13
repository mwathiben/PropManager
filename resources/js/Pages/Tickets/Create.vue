<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    WrenchScrewdriverIcon,
    ChatBubbleBottomCenterTextIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    buildings: Array,
    units: Array,
    defaultBuildingId: Number,
    defaultUnitId: Number,
    subcategories: Object,
    priorities: Object
});

const form = useForm({
    building_id: props.defaultBuildingId || '',
    unit_id: props.defaultUnitId || '',
    category: 'issue',
    subcategory: '',
    title: '',
    description: '',
    location: '',
    priority: 'medium'
});

const availableUnits = ref(props.units || []);

// Watch for building changes to fetch units
watch(() => form.building_id, async (newBuildingId) => {
    if (newBuildingId && newBuildingId !== props.defaultBuildingId) {
        try {
            const response = await fetch(route('buildings.units', newBuildingId));
            availableUnits.value = await response.json();
            form.unit_id = '';
        } catch (error) {
            console.error('Failed to fetch units:', error);
        }
    }
});

const currentSubcategories = computed(() => {
    return props.subcategories[form.category] || {};
});

// Reset subcategory when category changes
watch(() => form.category, () => {
    form.subcategory = '';
});

const submit = () => {
    form.post(route('tickets.store'), {
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Report Issue" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <Link
                        :href="route('tickets.index')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <ArrowLeftIcon class="h-4 w-4 mr-1" />
                        Back to Tickets
                    </Link>
                    <h1 class="text-3xl font-bold text-gray-900">Report an Issue or Complaint</h1>
                    <p class="text-gray-600 mt-1">Let us know what needs attention</p>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-hidden border">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Category Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">What type of report is this?</label>
                            <div class="grid grid-cols-2 gap-4">
                                <button
                                    type="button"
                                    @click="form.category = 'issue'"
                                    :class="[
                                        form.category === 'issue'
                                            ? 'border-orange-500 bg-orange-50 text-orange-700'
                                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50',
                                        'relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition'
                                    ]"
                                >
                                    <WrenchScrewdriverIcon class="h-6 w-6 mr-2" />
                                    <span class="font-medium">Maintenance Issue</span>
                                </button>
                                <button
                                    type="button"
                                    @click="form.category = 'complaint'"
                                    :class="[
                                        form.category === 'complaint'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50',
                                        'relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition'
                                    ]"
                                >
                                    <ChatBubbleBottomCenterTextIcon class="h-6 w-6 mr-2" />
                                    <span class="font-medium">Complaint</span>
                                </button>
                            </div>
                        </div>

                        <!-- Building Selection -->
                        <div>
                            <label for="building_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Building <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="building_id"
                                v-model="form.building_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.building_id }"
                            >
                                <option value="">Select a building</option>
                                <option v-for="building in buildings" :key="building.id" :value="building.id">
                                    {{ building.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.building_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.building_id }}
                            </p>
                        </div>

                        <!-- Unit Selection (Optional) -->
                        <div>
                            <label for="unit_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Unit
                                <span class="text-gray-400 text-xs">(optional - leave blank for common area issues)</span>
                            </label>
                            <select
                                id="unit_id"
                                v-model="form.unit_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :disabled="!form.building_id"
                            >
                                <option value="">Common Area / Property-wide</option>
                                <option v-for="unit in availableUnits" :key="unit.id" :value="unit.id">
                                    Unit {{ unit.unit_number }}
                                </option>
                            </select>
                        </div>

                        <!-- Subcategory -->
                        <div>
                            <label for="subcategory" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ form.category === 'issue' ? 'Issue Type' : 'Complaint Type' }}
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="subcategory"
                                v-model="form.subcategory"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.subcategory }"
                            >
                                <option value="">Select a type</option>
                                <option v-for="(label, value) in currentSubcategories" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.subcategory" class="mt-1 text-sm text-red-600">
                                {{ form.errors.subcategory }}
                            </p>
                        </div>

                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                                Brief Summary <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                v-model="form.title"
                                placeholder="e.g., Leaking pipe in bathroom"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.title }"
                            />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                Detailed Description <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                placeholder="Please describe the issue in detail..."
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.description }"
                            />
                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <!-- Location -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">
                                Specific Location
                                <span class="text-gray-400 text-xs">(optional)</span>
                            </label>
                            <input
                                type="text"
                                id="location"
                                v-model="form.location"
                                placeholder="e.g., Kitchen, Bathroom, Parking area"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>

                        <!-- Priority -->
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">
                                Priority <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="priority"
                                v-model="form.priority"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option v-for="(label, value) in priorities" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                <span class="font-medium">Urgent:</span> Safety hazard, no water/electricity.
                                <span class="font-medium">High:</span> Major inconvenience.
                                <span class="font-medium">Medium:</span> Standard issues.
                                <span class="font-medium">Low:</span> Minor issues.
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <Link
                                :href="route('tickets.index')"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <span v-if="form.processing">Submitting...</span>
                                <span v-else>Submit Report</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
