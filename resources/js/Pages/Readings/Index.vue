<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useAuth, useFormatters } from '@/composables';
import type { ReadingsIndexPageProps } from '@/types';

const props = defineProps<ReadingsIndexPageProps>();

// Use auth composable for role-based UI
const { isLandlord, isCaretaker } = useAuth();
const { todayAsISODate } = useFormatters();

const form = useForm({
    reading_date: todayAsISODate(),
    readings: []
});

// Photo preview URLs
const photoPreview = ref({});

// Initialize form readings
props.buildings.forEach(building => {
    building.units.forEach(unit => {
        form.readings.push({
            unit_id: unit.id,
            previous_reading: unit.previous_reading,
            current_reading: null,
            photo: null
        });
    });
});

const handlePhotoUpload = (event, unitId) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file is an image
    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file');
        event.target.value = '';
        return;
    }

    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        alert('Photo size must be less than 5MB');
        event.target.value = '';
        return;
    }

    // Update form data
    const readingEntry = getReadingEntry(unitId);
    readingEntry.photo = file;

    // Create preview URL
    photoPreview.value[unitId] = URL.createObjectURL(file);
};

const removePhoto = (unitId) => {
    const readingEntry = getReadingEntry(unitId);
    readingEntry.photo = null;

    // Clean up preview URL
    if (photoPreview.value[unitId]) {
        URL.revokeObjectURL(photoPreview.value[unitId]);
        delete photoPreview.value[unitId];
    }

    // Reset file input
    const fileInput = document.getElementById(`photo-${unitId}`);
    if (fileInput) fileInput.value = '';
};

const submit = () => {
    // Filter readings: must have both reading value AND photo
    const filledReadings = form.readings.filter(r => {
        return r.current_reading !== null && r.current_reading !== '' && r.photo !== null;
    });

    if (filledReadings.length === 0) {
        alert("Please enter at least one reading with a photo.");
        return;
    }

    // Check for incomplete entries (reading without photo or photo without reading)
    const incompleteReadings = form.readings.filter(r => {
        const hasReading = r.current_reading !== null && r.current_reading !== '';
        const hasPhoto = r.photo !== null;
        return (hasReading && !hasPhoto) || (!hasReading && hasPhoto);
    });

    if (incompleteReadings.length > 0) {
        alert("Some readings are incomplete. Please provide both meter reading and photo for each entry.");
        return;
    }

    // Create FormData for file upload
    const formData = new FormData();
    formData.append('reading_date', form.reading_date);

    filledReadings.forEach((reading, index) => {
        formData.append(`readings[${index}][unit_id]`, reading.unit_id);
        formData.append(`readings[${index}][current_reading]`, reading.current_reading);
        formData.append(`readings[${index}][reading_date]`, form.reading_date);
        formData.append(`readings[${index}][photo]`, reading.photo);
    });

    form.post(route('readings.store'), {
        data: formData,
        forceFormData: true,
        onSuccess: () => {
            // Reset form
            form.reset();
            photoPreview.value = {};

            // Clear file inputs
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.value = '';
            });

            alert("Readings submitted for landlord approval!");
        }
    });
};

// Helper to find form entry for a unit
const getReadingEntry = (unitId) => {
    return form.readings.find(r => r.unit_id === unitId);
};
</script>

<template>
    <Head title="Water Readings" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">
                                    {{ isCaretaker ? 'Water Meter Input' : 'Record Water Readings' }}
                                </h2>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ isCaretaker ? 'Submit readings for landlord approval' : 'Enter meter readings for billing' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="date" v-model="form.reading_date" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <Link
                                    v-if="isLandlord"
                                    :href="route('readings.review')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap"
                                >
                                    Review Pending →
                                </Link>
                            </div>
                        </div>

                        <div v-for="building in buildings" :key="building.id" class="mb-8">
                            <h3 class="font-bold text-lg text-indigo-600 mb-3 border-b pb-2">{{ building.name }}</h3>

                            <div class="space-y-4">
                                <div v-for="unit in building.units" :key="unit.id" class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <div class="font-bold text-gray-900 text-lg">{{ unit.unit_number }}</div>
                                            <div class="text-xs text-gray-500">Previous: {{ unit.previous_reading }}</div>
                                            <div class="text-xs text-gray-400">Last reading: {{ unit.last_reading_date }}</div>
                                        </div>

                                        <div class="flex flex-col items-end gap-2">
                                            <input
                                                type="number"
                                                v-model="getReadingEntry(unit.id).current_reading"
                                                placeholder="New Reading"
                                                step="0.01"
                                                class="w-32 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-right font-mono text-lg"
                                            >
                                        </div>
                                    </div>

                                    <!-- Photo Upload Section -->
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <div v-if="!photoPreview[unit.id]" class="flex items-center gap-2">
                                            <label :for="'photo-' + unit.id" class="flex-1 cursor-pointer">
                                                <div class="flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <span class="text-sm text-gray-700">Upload Meter Photo</span>
                                                </div>
                                            </label>
                                            <input
                                                :id="'photo-' + unit.id"
                                                type="file"
                                                accept="image/*"
                                                capture="environment"
                                                class="hidden"
                                                @change="handlePhotoUpload($event, unit.id)"
                                            >
                                        </div>

                                        <!-- Photo Preview -->
                                        <div v-else class="relative">
                                            <img
                                                :src="photoPreview[unit.id]"
                                                alt="Meter photo preview"
                                                class="w-full h-32 object-cover rounded-md border border-gray-300"
                                            >
                                            <button
                                                @click="removePhoto(unit.id)"
                                                type="button"
                                                class="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <div class="mt-1 text-xs text-green-600 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Photo uploaded
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-sm text-blue-800">
                                        <template v-if="isCaretaker">
                                            <strong>Photo Required:</strong> Each reading must include a photo of the water meter for landlord verification. Readings will be submitted for approval before being added to invoices.
                                        </template>
                                        <template v-else>
                                            <strong>Photo Required:</strong> Each reading must include a photo of the water meter. Photos help verify accuracy and prevent billing disputes.
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <button @click="submit" :disabled="form.processing" class="w-full py-3 bg-green-600 text-white font-bold rounded-lg shadow-md hover:bg-green-700 transition-colors text-lg disabled:opacity-50">
                                {{ form.processing ? 'Submitting...' : (isCaretaker ? 'Submit Readings for Approval' : 'Save Readings') }}
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>