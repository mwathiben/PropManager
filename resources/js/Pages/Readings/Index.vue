<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useAuth, useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { ReadingsIndexPageProps } from '@/types';

const props = defineProps<ReadingsIndexPageProps>();

// Use auth composable for role-based UI
const { isLandlord, isCaretaker } = useAuth();
const { todayAsISODate } = useFormatters();
const { t } = useI18n();

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
        alert(t('readings_index.alert.invalid_image'));
        event.target.value = '';
        return;
    }

    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        alert(t('readings_index.alert.photo_too_large'));
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
        alert(t('readings_index.alert.no_readings'));
        return;
    }

    // Check for incomplete entries (reading without photo or photo without reading)
    const incompleteReadings = form.readings.filter(r => {
        const hasReading = r.current_reading !== null && r.current_reading !== '';
        const hasPhoto = r.photo !== null;
        return (hasReading && !hasPhoto) || (!hasReading && hasPhoto);
    });

    if (incompleteReadings.length > 0) {
        alert(t('readings_index.alert.incomplete'));
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

            alert(t('readings_index.alert.submitted'));
        }
    });
};

// Helper to find form entry for a unit
const getReadingEntry = (unitId) => {
    return form.readings.find(r => r.unit_id === unitId);
};
</script>

<template>
    <Head :title="t('readings_index.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
                            <div>
                                <h1 class="text-xl font-bold text-gray-800">
                                    {{ isCaretaker ? t('readings_index.header.title_caretaker') : t('readings_index.header.title_landlord') }}
                                </h1>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ isCaretaker ? t('readings_index.header.subtitle_caretaker') : t('readings_index.header.subtitle_landlord') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="date" v-model="form.reading_date" :aria-label="t('meter.replace.reading_date')" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <Link
                                    v-if="isLandlord"
                                    :href="route('readings.review')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap"
                                >
                                    {{ t('readings_index.review_pending') }}
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
                                            <div class="text-xs text-gray-500">{{ t('readings_index.previous', { value: unit.previous_reading }) }}</div>
                                            <div class="text-xs text-gray-400">{{ t('readings_index.last_reading', { value: unit.last_reading_date }) }}</div>
                                        </div>

                                        <div class="flex flex-col items-end gap-2">
                                            <input
                                                type="number"
                                                v-model="getReadingEntry(unit.id).current_reading"
                                                :placeholder="t('readings_index.new_reading_placeholder')"
                                                :aria-label="t('readings_index.new_reading_placeholder')"
                                                step="0.01"
                                                class="w-32 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-end font-mono text-lg"
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
                                                    <span class="text-sm text-gray-700">{{ t('readings_index.upload_meter_photo') }}</span>
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
                                                :alt="t('readings_index.meter_photo_alt')"
                                                class="w-full h-32 object-cover rounded-md border border-gray-300"
                                            >
                                            <button
                                                @click="removePhoto(unit.id)"
                                                type="button"
                                                class="absolute top-2 end-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <div class="mt-1 text-xs text-green-600 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                {{ t('readings_index.photo_uploaded') }}
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
                                            <strong>{{ t('readings_index.photo_required.label') }}</strong> {{ t('readings_index.photo_required.caretaker') }}
                                        </template>
                                        <template v-else>
                                            <strong>{{ t('readings_index.photo_required.label') }}</strong> {{ t('readings_index.photo_required.landlord') }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <button @click="submit" :disabled="form.processing" class="w-full py-3 bg-green-600 text-white font-bold rounded-lg shadow-md hover:bg-green-700 transition-colors text-lg disabled:opacity-50">
                                {{ form.processing ? t('readings_index.submit.processing') : (isCaretaker ? t('readings_index.submit.caretaker') : t('readings_index.submit.landlord')) }}
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>