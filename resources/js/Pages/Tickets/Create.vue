<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useZodForm } from '@/composables/forms/useZodForm';
import { ticketSchema } from '@/composables/forms/schemas/ticketSchema';
import type { TicketCreatePageProps, Unit } from '@/types';
import {
    WrenchScrewdriverIcon,
    ChatBubbleBottomCenterTextIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<TicketCreatePageProps>();

const { logError } = useErrorHandler();
const { t } = useI18n();

const form = useForm({
    building_id: props.defaultBuildingId || '',
    unit_id: props.defaultUnitId || '',
    category: 'issue',
    subcategory: '',
    title: '',
    description: '',
    location: '',
    priority: 'medium',
    photos: [] as File[],
});

// Phase-28 TENANT-MAINT-2: cap multi-photo upload at 5 files × 5MB.
const onPhotosSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files) return;
    const accepted: File[] = [];
    for (const file of Array.from(input.files).slice(0, 5)) {
        if (file.size <= 5 * 1024 * 1024) {
            accepted.push(file);
        }
    }
    form.photos = accepted;
};

const availableUnits = ref(props.units || []);

// Watch for building changes to fetch units
watch(() => form.building_id, async (newBuildingId) => {
    if (newBuildingId && newBuildingId !== props.defaultBuildingId) {
        try {
            const response = await fetch(route('buildings.units', newBuildingId));
            availableUnits.value = await response.json();
            form.unit_id = '';
        } catch (error) {
            logError(error, { component: 'TicketsCreate', action: 'fetchUnits' });
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

const { validate } = useZodForm(form, ticketSchema);

const submit = () => {
    if (!validate()) {
        return;
    }
    form.post(route('tickets.store'), {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="t('tickets.create.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <Link
                        :href="route('tickets.index')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <ArrowLeftIcon class="h-4 w-4 me-1" />
                        {{ t('tickets.create.back_to_tickets') }}
                    </Link>
                    <h1 class="text-3xl font-bold text-gray-900">{{ t('tickets.create.heading') }}</h1>
                    <p class="text-gray-600 mt-1">{{ t('tickets.create.subheading') }}</p>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-hidden border">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Category Selection -->
                        <div>
                            <span id="category-group-label" class="block text-sm font-medium text-gray-700 mb-3">{{ t('tickets.create.report_type_label') }}</span>
                            <div class="grid grid-cols-2 gap-4" role="group" aria-labelledby="category-group-label">
                                <button
                                    type="button"
                                    @click="form.category = 'issue'"
                                    :class="[form.category === 'issue' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50', 'relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition']"
                                >
                                    <WrenchScrewdriverIcon class="h-6 w-6 me-2" />
                                    <span class="font-medium">{{ t('tickets.create.maintenance_issue') }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="form.category = 'complaint'"
                                    :class="[form.category === 'complaint' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50', 'relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition']"
                                >
                                    <ChatBubbleBottomCenterTextIcon class="h-6 w-6 me-2" />
                                    <span class="font-medium">{{ t('tickets.create.complaint') }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Building Selection -->
                        <div>
                            <label for="building_id" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tickets.create.building_label') }} <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="building_id"
                                v-model="form.building_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.building_id }"
                            >
                                <option value="">{{ t('tickets.create.select_building') }}</option>
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
                                {{ t('tickets.create.unit_label') }}
                                <span class="text-gray-400 text-xs">{{ t('tickets.create.unit_optional') }}</span>
                            </label>
                            <select
                                id="unit_id"
                                v-model="form.unit_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :disabled="!form.building_id"
                            >
                                <option value="">{{ t('tickets.create.common_area') }}</option>
                                <option v-for="unit in availableUnits" :key="unit.id" :value="unit.id">
                                    {{ t('tickets.create.unit_prefix', { number: unit.unit_number }) }}
                                </option>
                            </select>
                        </div>

                        <!-- Subcategory -->
                        <div>
                            <label for="subcategory" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ form.category === 'issue' ? t('tickets.create.issue_type') : t('tickets.create.complaint_type') }}
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="subcategory"
                                v-model="form.subcategory"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': form.errors.subcategory }"
                            >
                                <option value="">{{ t('tickets.create.select_type') }}</option>
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
                                {{ t('tickets.create.title_label') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                v-model="form.title"
                                :placeholder="t('tickets.create.title_placeholder')"
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
                                {{ t('tickets.create.description_label') }} <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                :placeholder="t('tickets.create.description_placeholder')"
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
                                {{ t('tickets.create.location_label') }}
                                <span class="text-gray-400 text-xs">{{ t('tickets.create.location_optional') }}</span>
                            </label>
                            <input
                                type="text"
                                id="location"
                                v-model="form.location"
                                :placeholder="t('tickets.create.location_placeholder')"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>

                        <!-- Priority -->
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tickets.create.priority_label') }} <span class="text-red-500">*</span>
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
                                <span class="font-medium">{{ t('tickets.create.priority_urgent') }}</span> {{ t('tickets.create.priority_urgent_desc') }}
                                <span class="font-medium">{{ t('tickets.create.priority_high') }}</span> {{ t('tickets.create.priority_high_desc') }}
                                <span class="font-medium">{{ t('tickets.create.priority_medium') }}</span> {{ t('tickets.create.priority_medium_desc') }}
                                <span class="font-medium">{{ t('tickets.create.priority_low') }}</span> {{ t('tickets.create.priority_low_desc') }}
                            </p>
                        </div>

                        <!-- Phase-28 TENANT-MAINT-2: multi-photo upload -->
                        <div>
                            <label for="photos" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tickets.create.photos_label') }}
                            </label>
                            <input
                                id="photos"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full text-sm text-gray-700 file:me-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="onPhotosSelected"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ t('tickets.create.photos_hint') }}
                            </p>
                            <p v-if="form.photos.length" class="mt-1 text-xs text-emerald-700">
                                {{ t('tickets.create.photos_attached', form.photos.length) }}
                            </p>
                            <p v-if="form.errors.photos" class="mt-1 text-sm text-red-600">{{ form.errors.photos }}</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <Link
                                :href="route('tickets.index')"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {{ t('tickets.create.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                <span v-if="form.processing">{{ t('tickets.create.submitting') }}</span>
                                <span v-else>{{ t('tickets.create.submit_report') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
