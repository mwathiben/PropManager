<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';

interface ReadingRow {
    id: number;
    reading_date: string;
    previous_reading: number | string;
    current_reading: number | string;
    consumption: number | string;
    cost: number | string;
    recorder?: { name?: string } | null;
    is_anomalous?: boolean;
    unit: { unit_number: string; building?: { name?: string } | null };
}

defineProps<{
    pendingReadings: {
        data: ReadingRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const { formatMoney } = useFormatters();
const { t } = useI18n();

const selected = ref<ReadingRow | null>(null);
const mode = ref<'approve' | 'reject' | null>(null);
const approveForm = useForm({ notes: '' });
const rejectForm = useForm({ reason: '' });

function open(reading: ReadingRow, action: 'approve' | 'reject'): void {
    selected.value = reading;
    mode.value = action;
    approveForm.reset();
    rejectForm.reset();
}

function close(): void {
    selected.value = null;
    mode.value = null;
}

function confirmApprove(): void {
    if (!selected.value) return;
    approveForm.post(route('readings.approve', selected.value.id), { onSuccess: close, preserveScroll: true });
}

function confirmReject(): void {
    if (!selected.value || !rejectForm.reason) return;
    rejectForm.post(route('readings.reject', selected.value.id), { onSuccess: close, preserveScroll: true });
}

function requestReread(reading: ReadingRow): void {
    router.post(route('readings.request-reread', reading.id), {}, { preserveScroll: true });
}
</script>

<template>
    <div data-testid="water-review-tab">
        <p v-if="pendingReadings.data.length === 0" class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">
            {{ t('water_review_tab.empty') }}
        </p>

        <div v-else class="space-y-3">
            <div
                v-for="reading in pendingReadings.data"
                :key="reading.id"
                class="rounded-lg border border-gray-200 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-gray-900">
                            {{ reading.unit.building?.name }} · {{ reading.unit.unit_number }}
                            <span
                                v-if="reading.is_anomalous"
                                class="ms-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                :title="t('water.review.spike_hint')"
                            >⚠ {{ t('water.review.spike') }}</span>
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ reading.reading_date }} · {{ t('water_review_tab.recorded_by', { name: reading.recorder?.name || t('water_review_tab.recorder_unknown') }) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-6 text-sm">
                        <div class="text-end">
                            <p class="text-xs text-gray-400">{{ t('water_review_tab.consumption') }}</p>
                            <p class="font-semibold text-gray-900">{{ reading.consumption }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-xs text-gray-400">{{ t('water_review_tab.charge') }}</p>
                            <p class="font-semibold text-emerald-700">{{ formatMoney(reading.cost) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700" @click="open(reading, 'approve')">{{ t('water_review_tab.actions.approve') }}</button>
                            <button class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700" @click="open(reading, 'reject')">{{ t('water_review_tab.actions.reject') }}</button>
                            <button class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="requestReread(reading)">{{ t('water.review.request_reread') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 text-sm text-gray-600">
                <span>{{ t('water_review_tab.pagination_summary', { from: pendingReadings.from ?? 0, to: pendingReadings.to ?? 0, total: pendingReadings.total }) }}</span>
                <div class="flex gap-1">
                    <a
                        v-for="link in pendingReadings.links"
                        :key="link.label"
                        :href="link.url ?? undefined"
                        class="rounded-md px-2 py-1 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        <PaginatorLink :label="link.label" />
                    </a>
                </div>
            </div>
        </div>

        <div v-if="mode" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="close"></div>
            <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-6">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">
                    {{ mode === 'approve' ? t('water_review_tab.modal.title_approve') : t('water_review_tab.modal.title_reject') }}
                </h3>
                <p class="mb-3 text-sm text-gray-600">
                    {{ selected?.unit.building?.name }} · {{ selected?.unit.unit_number }} —
                    <span class="font-semibold">{{ formatMoney(selected?.cost ?? 0) }}</span>
                </p>

                <textarea
                    v-if="mode === 'approve'"
                    id="approve-notes"
                    v-model="approveForm.notes"
                    rows="3"
                    class="mb-4 w-full rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    :placeholder="t('water_review_tab.modal.notes_placeholder')"
                    :aria-label="t('water_review_tab.modal.notes_placeholder')"
                ></textarea>
                <textarea
                    v-else
                    id="reject-reason"
                    v-model="rejectForm.reason"
                    rows="3"
                    class="mb-4 w-full rounded-md border-gray-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                    :placeholder="t('water_review_tab.modal.reason_placeholder')"
                    :aria-label="t('water_review_tab.modal.reason_placeholder')"
                ></textarea>

                <div class="flex gap-3">
                    <button
                        v-if="mode === 'approve'"
                        :disabled="approveForm.processing"
                        class="flex-1 rounded-md bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        @click="confirmApprove"
                    >{{ t('water_review_tab.actions.confirm') }}</button>
                    <button
                        v-else
                        :disabled="rejectForm.processing || !rejectForm.reason"
                        class="flex-1 rounded-md bg-rose-600 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
                        @click="confirmReject"
                    >{{ t('water_review_tab.actions.confirm') }}</button>
                    <button class="flex-1 rounded-md bg-gray-100 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="close">{{ t('water_review_tab.actions.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
