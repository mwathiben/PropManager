<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { ReadingsReviewPageProps, WaterReading } from '@/types';

const props = defineProps<ReadingsReviewPageProps>();

const { formatMoney } = useFormatters();
const { t } = useI18n();

const filterForm = useForm({
    building_id: props.filters.building_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || ''
});

const applyFilters = () => {
    router.get(route('readings.review'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true
    });
};

const resetFilters = () => {
    filterForm.reset();
    router.get(route('readings.review'));
};

// Modal state
const selectedReading = ref(null);
const showApproveModal = ref(false);
const showRejectModal = ref(false);

const approveForm = useForm({
    notes: ''
});

const rejectForm = useForm({
    reason: ''
});

const openApproveModal = (reading) => {
    selectedReading.value = reading;
    showApproveModal.value = true;
    approveForm.reset();
};

const openRejectModal = (reading) => {
    selectedReading.value = reading;
    showRejectModal.value = true;
    rejectForm.reset();
};

const closeModals = () => {
    showApproveModal.value = false;
    showRejectModal.value = false;
    selectedReading.value = null;
};

const approveReading = () => {
    approveForm.post(route('readings.approve', selectedReading.value.id), {
        onSuccess: () => {
            closeModals();
        }
    });
};

const rejectReading = () => {
    if (!rejectForm.reason) {
        alert(t('readings_review.reject.reason_required'));
        return;
    }

    rejectForm.post(route('readings.reject', selectedReading.value.id), {
        onSuccess: () => {
            closeModals();
        }
    });
};

const getPhotoUrl = (reading) => {
    return route('readings.photo', reading.id);
};
</script>

<template>
    <Head :title="t('readings_review.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">

                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800">{{ t('readings_review.title') }}</h1>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ t('readings_review.pending_count', { count: pendingReadings.total }) }}
                                </p>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('readings_review.filters.building') }}</label>
                                    <select v-model="filterForm.building_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">{{ t('readings_review.filters.all_buildings') }}</option>
                                        <option v-for="building in buildings" :key="building.id" :value="building.id">
                                            {{ building.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('readings_review.filters.date_from') }}</label>
                                    <input type="date" v-model="filterForm.date_from" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('readings_review.filters.date_to') }}</label>
                                    <input type="date" v-model="filterForm.date_to" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>

                                <div class="flex items-end gap-2">
                                    <button @click="applyFilters" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                                        {{ t('readings_review.filters.apply') }}
                                    </button>
                                    <button @click="resetFilters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                                        {{ t('readings_review.filters.reset') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Readings List -->
                        <div v-if="pendingReadings.data.length === 0" class="text-center py-12 text-gray-500">
                            <p class="text-lg">{{ t('readings_review.empty.title') }}</p>
                            <p class="text-sm mt-2">{{ t('readings_review.empty.body') }}</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="reading in pendingReadings.data" :key="reading.id"
                                 class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Photo -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">{{ t('readings_review.card.meter_photo') }}</h4>
                                        <img
                                            v-if="reading.photo_path"
                                            :src="getPhotoUrl(reading)"
                                            :alt="t('readings_review.card.meter_photo_alt')"
                                            class="w-full h-48 object-cover rounded-lg border border-gray-300 cursor-pointer hover:opacity-90"
                                            @click="window.open(getPhotoUrl(reading), '_blank')"
                                        >
                                        <div v-else class="w-full h-48 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
                                            {{ t('readings_review.card.no_photo') }}
                                        </div>
                                    </div>

                                    <!-- Reading Details -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 mb-3">{{ t('readings_review.card.reading_details') }}</h4>
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.unit') }}</span>
                                                <span class="text-sm font-semibold">{{ reading.unit.unit_number }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.building') }}</span>
                                                <span class="text-sm font-semibold">{{ reading.unit.building.name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.reading_date') }}</span>
                                                <span class="text-sm font-semibold">{{ reading.reading_date }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.recorded_by') }}</span>
                                                <span class="text-sm font-semibold">{{ reading.recorder?.name || 'N/A' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Consumption & Cost -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 mb-3">{{ t('readings_review.card.consumption_cost') }}</h4>
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.previous_reading') }}</span>
                                                <span class="text-sm font-mono">{{ reading.previous_reading }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.manual_reading') }}</span>
                                                <span class="text-sm font-mono font-semibold text-indigo-600">{{ reading.current_reading }}</span>
                                            </div>

                                            <!-- OCR Reading (if available) -->
                                            <div v-if="reading.ocr_reading" class="flex justify-between bg-blue-50 -mx-2 px-2 py-1 rounded">
                                                <span class="text-sm text-gray-600 flex items-center gap-1">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ t('readings_review.card.ocr_reading') }}
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-mono font-semibold text-blue-600">{{ reading.ocr_reading }}</span>
                                                    <span v-if="reading.ocr_verified" class="px-1.5 py-0.5 text-xs bg-green-100 text-green-700 rounded font-medium">
                                                        {{ t('readings_review.card.verified') }}
                                                    </span>
                                                    <span v-else-if="Math.abs(reading.ocr_reading - reading.current_reading) > 0.5" class="px-1.5 py-0.5 text-xs bg-yellow-100 text-yellow-700 rounded font-medium">
                                                        {{ t('readings_review.card.diff', { value: Math.abs(reading.ocr_reading - reading.current_reading).toFixed(2) }) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="flex justify-between border-t pt-2">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.consumption') }}</span>
                                                <span class="text-sm font-semibold">{{ t('readings_review.card.consumption_value', { units: reading.consumption }) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-sm text-gray-600">{{ t('readings_review.card.cost') }}</span>
                                                <span class="text-sm font-bold text-green-600">{{ formatMoney(reading.cost) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-4 pt-4 border-t flex gap-3">
                                    <button
                                        @click="openApproveModal(reading)"
                                        class="flex-1 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-medium">
                                        {{ t('readings_review.actions.approve') }}
                                    </button>
                                    <button
                                        @click="openRejectModal(reading)"
                                        class="flex-1 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-medium">
                                        {{ t('readings_review.actions.reject') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pendingReadings.data.length > 0" class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                {{ t('readings_review.pagination.showing', { from: pendingReadings.from, to: pendingReadings.to, total: pendingReadings.total }) }}
                            </div>
                            <div class="flex gap-2">
                                <a
                                    v-for="link in pendingReadings.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-1 rounded-md text-sm', /* i18n-ignore */
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    ]"
                                    class="transition-colors"
                                >
                                    <PaginatorLink :label="link.label" />
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div v-if="showApproveModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="closeModals"></div>
                <div class="relative z-50 bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ t('readings_review.approve.title') }}</h3>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        {{ t('readings_review.modal.unit') }} <span class="font-semibold">{{ selectedReading?.unit.unit_number }}</span>
                    </p>
                    <p class="text-sm text-gray-600 mb-2">
                        {{ t('readings_review.modal.reading') }} <span class="font-semibold">{{ selectedReading?.current_reading }}</span>
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ t('readings_review.modal.cost') }} <span class="font-semibold text-green-600">{{ formatMoney(selectedReading?.cost) }}</span>
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('readings_review.approve.notes_label') }}
                    </label>
                    <textarea
                        v-model="approveForm.notes"
                        rows="3"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                        :placeholder="t('readings_review.approve.notes_placeholder')"
                    ></textarea>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="approveReading"
                        :disabled="approveForm.processing"
                        class="flex-1 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-medium disabled:opacity-50">
                        {{ approveForm.processing ? t('readings_review.approve.processing') : t('readings_review.approve.confirm') }}
                    </button>
                    <button
                        @click="closeModals"
                        :disabled="approveForm.processing"
                        class="flex-1 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors disabled:opacity-50">
                        {{ t('readings_review.modal.cancel') }}
                    </button>
                </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="closeModals"></div>
                <div class="relative z-50 bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ t('readings_review.reject.title') }}</h3>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        {{ t('readings_review.modal.unit') }} <span class="font-semibold">{{ selectedReading?.unit.unit_number }}</span>
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ t('readings_review.modal.reading') }} <span class="font-semibold">{{ selectedReading?.current_reading }}</span>
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ t('readings_review.reject.reason_label') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="rejectForm.reason"
                        rows="4"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                        :placeholder="t('readings_review.reject.reason_placeholder')"
                        required
                    ></textarea>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="rejectReading"
                        :disabled="rejectForm.processing"
                        class="flex-1 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-medium disabled:opacity-50">
                        {{ rejectForm.processing ? t('readings_review.reject.processing') : t('readings_review.reject.confirm') }}
                    </button>
                    <button
                        @click="closeModals"
                        :disabled="rejectForm.processing"
                        class="flex-1 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors disabled:opacity-50">
                        {{ t('readings_review.modal.cancel') }}
                    </button>
                </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
