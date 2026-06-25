<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

/**
 * Phase-68 BULK-UI-1: place a legal hold on many subjects at once via the
 * Phase-65 BulkHoldService endpoint. Reused across any holdable list; the
 * caller supplies the subject type + the selected ids.
 */
interface Props {
    subjectType: string;
    subjectIds: number[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const open = ref(false);
const reason = ref('');
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

const reasonValid = computed(
    () => reason.value.trim().length >= 10 && reason.value.trim().length <= 500,
);

const openModal = () => {
    reason.value = '';
    errors.value = {};
    open.value = true;
};

const closeModal = () => {
    if (submitting.value) return;
    open.value = false;
};

const submit = () => {
    if (!reasonValid.value || submitting.value || props.subjectIds.length === 0) return;
    submitting.value = true;
    errors.value = {};

    router.post(route('legal-holds.bulk.store'), {
        subject_type: props.subjectType,
        subject_ids: props.subjectIds,
        reason: reason.value.trim(),
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs as Record<string, string>; },
        onFinish: () => { submitting.value = false; },
        onSuccess: () => { open.value = false; },
    });
};

defineExpose({ open: openModal });
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bulk-hold-title"
            data-testid="bulk-hold-modal"
        >
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" role="button" tabindex="0" @click="closeModal" @keydown.enter="closeModal" @keydown.space.prevent="closeModal"></div>

                <div class="relative bg-white rounded-2xl shadow-xl ring-1 ring-gray-100 w-full max-w-lg p-6">
                    <button
                        @click="closeModal"
                        class="absolute top-4 end-4 text-gray-400 hover:text-gray-600"
                        :aria-label="t('common.close')"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>

                    <h3 id="bulk-hold-title" class="text-lg font-semibold text-gray-900 pe-8">
                        {{ t('legal_holds.create_modal_title') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('legal_holds.bulk.selected', { count: subjectIds.length }) }}
                    </p>

                    <div class="mt-4 flex items-start gap-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 p-3">
                        <ExclamationTriangleIcon class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-amber-900">
                            {{ t('legal_holds.create_modal_warning') }}
                        </p>
                    </div>

                    <div class="mt-4">
                        <label for="bulk-hold-reason" class="block text-sm font-medium text-gray-700">
                            {{ t('legal_holds.history.reason') }}
                            <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            id="bulk-hold-reason"
                            v-model="reason"
                            rows="4"
                            maxlength="500"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            data-testid="bulk-hold-reason"
                        ></textarea>
                        <div class="mt-1 flex items-center justify-between">
                            <p v-if="errors.reason || errors.subject_ids" class="text-xs text-rose-600">
                                {{ errors.reason || errors.subject_ids }}
                            </p>
                            <p class="text-xs text-gray-400 ms-auto">{{ reason.length }} / 500</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                            :disabled="submitting"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="!reasonValid || submitting"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50"
                            data-testid="bulk-hold-submit"
                        >
                            {{ submitting ? t('legal_holds.bulk.placing') : t('legal_holds.doc.place') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
