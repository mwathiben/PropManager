<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { VerificationsConductPageProps, LeaseVerificationItem } from '@/types/tenants';

const { formatDate } = useFormatters();
const { t } = useI18n();
import ClipboardDocumentCheckIcon from '@heroicons/vue/24/outline/ClipboardDocumentCheckIcon';
import CheckCircleIcon from '@heroicons/vue/24/outline/CheckCircleIcon';
import XCircleIcon from '@heroicons/vue/24/outline/XCircleIcon';
import ClockIcon from '@heroicons/vue/24/outline/ClockIcon';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import ExclamationCircleIcon from '@heroicons/vue/24/outline/ExclamationCircleIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import UserCircleIcon from '@heroicons/vue/24/outline/UserCircleIcon';
import HomeIcon from '@heroicons/vue/24/outline/HomeIcon';
import ArrowPathIcon from '@heroicons/vue/24/outline/ArrowPathIcon';
import CheckIcon from '@heroicons/vue/24/outline/CheckIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import MinusCircleIcon from '@heroicons/vue/24/outline/MinusCircleIcon';
import ChatBubbleLeftIcon from '@heroicons/vue/24/outline/ChatBubbleLeftIcon';
import CheckCircleSolid from '@heroicons/vue/24/solid/CheckCircleIcon';
import XCircleSolid from '@heroicons/vue/24/solid/XCircleIcon';

const props = defineProps<VerificationsConductPageProps>();

// State
const selectedTemplateId = ref(props.defaultTemplate?.id || '');
const showNoteModal = ref(false);
const currentVerification = ref(null);
const noteText = ref('');

// Computed
const tenant = computed(() => props.lease.tenant);
const unit = computed(() => props.lease.unit);
const verifications = computed(() => props.lease.verifications || []);

const verifiedCount = computed(() => verifications.value.filter(v => v.status === 'verified').length);
const waivedCount = computed(() => verifications.value.filter(v => v.status === 'waived').length);
const pendingCount = computed(() => verifications.value.filter(v => v.status === 'pending').length);
const rejectedCount = computed(() => verifications.value.filter(v => v.status === 'rejected').length);

const requiredItems = computed(() => verifications.value.filter(v => v.item?.is_required));
const requiredPending = computed(() => requiredItems.value.filter(v => v.status === 'pending').length);
const canComplete = computed(() => requiredPending.value === 0 && rejectedCount.value === 0 && props.hasVerifications);

// Helpers
const getStatusIcon = (status) => {
    switch (status) {
        case 'verified': return CheckCircleSolid;
        case 'rejected': return XCircleSolid;
        case 'waived': return MinusCircleIcon;
        default: return ClockIcon;
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'verified': return 'text-green-600';
        case 'rejected': return 'text-red-600';
        case 'waived': return 'text-yellow-600';
        default: return 'text-gray-400';
    }
};

const getStatusBg = (status) => {
    switch (status) {
        case 'verified': return 'bg-green-50 border-green-200';
        case 'rejected': return 'bg-red-50 border-red-200';
        case 'waived': return 'bg-yellow-50 border-yellow-200';
        default: return 'bg-white border-gray-200';
    }
};

// Actions
const startForm = useForm({ template_id: '' });

const startVerification = () => {
    if (!selectedTemplateId.value) {
        alert(t('verifications_conduct.alert.select_template'));
        return;
    }
    startForm.template_id = selectedTemplateId.value;
    startForm.post(route('verifications.start', props.lease.id), {
        preserveScroll: true,
    });
};

const updateVerification = (verification, status) => {
    router.put(route('verifications.update', verification.id), {
        status: status,
        notes: verification.notes,
    }, {
        preserveScroll: true,
    });
};

const openNoteModal = (verification) => {
    currentVerification.value = verification;
    noteText.value = verification.notes || '';
    showNoteModal.value = true;
};

const saveNote = () => {
    if (!currentVerification.value) return;
    router.put(route('verifications.update', currentVerification.value.id), {
        status: currentVerification.value.status,
        notes: noteText.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showNoteModal.value = false;
            currentVerification.value = null;
            noteText.value = '';
        },
    });
};

const resetVerification = () => {
    if (confirm(t('verifications_conduct.confirm.reset'))) {
        router.post(route('verifications.reset', props.lease.id), {}, {
            preserveScroll: true,
        });
    }
};

const completeVerification = () => {
    if (!canComplete.value) {
        alert(t('verifications_conduct.alert.required_first'));
        return;
    }
    if (confirm(t('verifications_conduct.confirm.complete'))) {
        router.post(route('verifications.complete', props.lease.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="t('verifications_conduct.page_title', { name: tenant?.name })" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <Link :href="route('tenants.show', tenant?.id)" class="text-gray-400 hover:text-gray-600">
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ t('verifications_conduct.heading') }}</h1>
                            <p class="text-sm text-gray-500">{{ t('verifications_conduct.verify_subtitle', { name: tenant?.name }) }}</p>
                        </div>
                    </div>
                    <Link
                        :href="route('verifications.index')"
                        class="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        {{ t('verifications_conduct.manage_templates') }}
                    </Link>
                </div>

                <!-- Tenant Info Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-start gap-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold">
                            {{ tenant?.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-semibold text-gray-900">{{ tenant?.name }}</h2>
                            <p class="text-sm text-gray-500">{{ tenant?.email }}</p>
                            <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-600">
                                <span class="flex items-center gap-1">
                                    <HomeIcon class="w-4 h-4" />
                                    {{ t('verifications_conduct.unit_line', { number: unit?.unit_number, building: unit?.building?.name }) }}
                                </span>
                                <span v-if="tenant?.mobile_number" class="flex items-center gap-1">
                                    {{ tenant.mobile_number }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Not Started State -->
                <div v-if="!hasVerifications" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <div class="text-center">
                        <ClipboardDocumentCheckIcon class="mx-auto h-16 w-16 text-gray-400" />
                        <h3 class="mt-4 text-lg font-medium text-gray-900">{{ t('verifications_conduct.start.title') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                            {{ t('verifications_conduct.start.description') }}
                        </p>

                        <div class="mt-6 max-w-md mx-auto">
                            <label for="conduct-template" class="block text-sm font-medium text-gray-700 text-start mb-2">{{ t('verifications_conduct.start.select_template') }}</label>
                            <select
                                id="conduct-template"
                                v-model="selectedTemplateId"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">{{ t('verifications_conduct.start.choose_template') }}</option>
                                <option v-for="template in templates" :key="template.id" :value="template.id">
                                    {{ t('verifications_conduct.start.option', { name: template.name, count: template.items?.length }) }}
                                    {{ template.is_default ? t('verifications_conduct.start.option_default_suffix') : '' }}
                                </option>
                            </select>

                            <button
                                @click="startVerification"
                                :disabled="!selectedTemplateId || startForm.processing"
                                class="mt-4 w-full px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <ClipboardDocumentCheckIcon class="w-5 h-5" />
                                {{ startForm.processing ? t('verifications_conduct.start.starting') : t('verifications_conduct.start.button') }}
                            </button>
                        </div>

                        <p v-if="!templates.length" class="mt-4 text-sm text-red-600">
                            {{ t('verifications_conduct.start.no_templates') }}
                            <Link :href="route('verifications.index')" class="underline">{{ t('verifications_conduct.start.create_one') }}</Link>.
                        </p>
                    </div>
                </div>

                <!-- Verification In Progress -->
                <div v-else>
                    <!-- Progress Bar -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-900">{{ t('verifications_conduct.progress.title') }}</h3>
                            <span class="text-sm text-gray-500">{{ t('verifications_conduct.progress.percent_complete', { percent: progress }) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div
                                class="h-3 rounded-full transition-all duration-500"
                                :class="progress === 100 ? 'bg-green-500' : 'bg-indigo-500'"
                                :style="{ width: `${progress}%` }"
                            ></div>
                        </div>

                        <!-- Stats -->
                        <div class="mt-4 grid grid-cols-4 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-green-600">{{ verifiedCount }}</div>
                                <div class="text-xs text-gray-500">{{ t('verifications_conduct.stats.verified') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-yellow-600">{{ waivedCount }}</div>
                                <div class="text-xs text-gray-500">{{ t('verifications_conduct.stats.waived') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-600">{{ pendingCount }}</div>
                                <div class="text-xs text-gray-500">{{ t('verifications_conduct.stats.pending') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-red-600">{{ rejectedCount }}</div>
                                <div class="text-xs text-gray-500">{{ t('verifications_conduct.stats.rejected') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Items -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">{{ t('verifications_conduct.checklist.title') }}</h3>
                            <button
                                @click="resetVerification"
                                class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
                            >
                                <ArrowPathIcon class="w-4 h-4" />
                                {{ t('verifications_conduct.checklist.reset') }}
                            </button>
                        </div>

                        <div class="divide-y divide-gray-200">
                            <div
                                v-for="verification in verifications"
                                :key="verification.id"
                                :class="getStatusBg(verification.status)"
                                class="p-4 border-s-4 transition-colors"
                            >
                                <div class="flex items-start gap-4">
                                    <!-- Status Icon -->
                                    <component
                                        :is="getStatusIcon(verification.status)"
                                        class="w-6 h-6 shrink-0"
                                        :class="getStatusColor(verification.status)"
                                    />

                                    <!-- Item Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                {{ verification.item?.name }}
                                            </h4>
                                            <span
                                                v-if="verification.item?.is_required"
                                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-red-100 text-red-700 text-xs rounded"
                                            >
                                                <ExclamationCircleIcon class="w-3 h-3" />
                                                {{ t('verifications_conduct.item.required') }}
                                            </span>
                                            <span
                                                v-if="verification.item?.document_type"
                                                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 text-gray-600 text-xs rounded"
                                            >
                                                <DocumentTextIcon class="w-3 h-3" />
                                                {{ verification.item.document_type.replace(/_/g, ' ') }}
                                            </span>
                                        </div>
                                        <p v-if="verification.item?.description" class="mt-1 text-sm text-gray-500">
                                            {{ verification.item.description }}
                                        </p>
                                        <p v-if="verification.notes" class="mt-2 text-sm text-gray-600 italic">
                                            {{ t('verifications_conduct.item.note_prefix', { note: verification.notes }) }}
                                        </p>
                                        <p v-if="verification.verifier && verification.verified_at" class="mt-1 text-xs text-gray-400">
                                            {{ t('verifications_conduct.item.audit', {
                                                action: verification.status === 'verified' ? t('verifications_conduct.action_label.verified') : verification.status === 'waived' ? t('verifications_conduct.action_label.waived') : verification.status === 'rejected' ? t('verifications_conduct.action_label.rejected') : t('verifications_conduct.action_label.updated'),
                                                name: verification.verifier.name,
                                                date: formatDate(verification.verified_at),
                                            }) }}
                                        </p>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openNoteModal(verification)"
                                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                                            :title="t('verifications_conduct.title.add_note')"
                                        >
                                            <ChatBubbleLeftIcon class="w-5 h-5" />
                                        </button>
                                        <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200 p-1">
                                            <button
                                                @click="updateVerification(verification, 'verified')"
                                                :class="verification.status === 'verified' ? 'bg-green-100 text-green-700' : 'text-gray-400 hover:text-green-600'"
                                                class="p-1.5 rounded transition-colors"
                                                :title="t('verifications_conduct.title.verify')"
                                            >
                                                <CheckIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="updateVerification(verification, 'rejected')"
                                                :class="verification.status === 'rejected' ? 'bg-red-100 text-red-700' : 'text-gray-400 hover:text-red-600'"
                                                class="p-1.5 rounded transition-colors"
                                                :title="t('verifications_conduct.title.reject')"
                                            >
                                                <XMarkIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="updateVerification(verification, 'waived')"
                                                :class="verification.status === 'waived' ? 'bg-yellow-100 text-yellow-700' : 'text-gray-400 hover:text-yellow-600'"
                                                class="p-1.5 rounded transition-colors"
                                                :title="t('verifications_conduct.title.waive')"
                                            >
                                                <MinusCircleIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                v-if="verification.status !== 'pending'"
                                                @click="updateVerification(verification, 'pending')"
                                                class="p-1.5 text-gray-400 hover:text-gray-600 rounded transition-colors"
                                                :title="t('verifications_conduct.title.reset_pending')"
                                            >
                                                <ArrowPathIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Complete Button -->
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p v-if="!canComplete" class="text-sm text-yellow-700">
                                        <ExclamationCircleIcon class="w-4 h-4 inline me-1" />
                                        {{ t('verifications_conduct.complete.pending_notice', { count: requiredPending }) }}
                                        <span v-if="rejectedCount > 0">{{ t('verifications_conduct.complete.rejected_notice', { count: rejectedCount }) }}</span>
                                    </p>
                                    <p v-else class="text-sm text-green-700">
                                        <CheckCircleIcon class="w-4 h-4 inline me-1" />
                                        {{ t('verifications_conduct.complete.ready') }}
                                    </p>
                                </div>
                                <button
                                    @click="completeVerification"
                                    :disabled="!canComplete"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    <CheckCircleIcon class="w-5 h-5" />
                                    {{ t('verifications_conduct.complete.button') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Note Modal -->
        <div v-if="showNoteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showNoteModal = false"></div>

                <div class="relative z-50 inline-block w-full max-w-md my-8 overflow-hidden text-start align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('verifications_conduct.note_modal.title') }}</h3>
                        <p class="text-sm text-gray-500">{{ currentVerification?.item?.name }}</p>
                    </div>

                    <div class="p-6">
                        <label for="conduct-note-textarea" class="sr-only">{{ t('verifications_conduct.note_modal.title') }}</label>
                        <textarea
                            id="conduct-note-textarea"
                            v-model="noteText"
                            rows="4"
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            :placeholder="t('verifications_conduct.note_modal.placeholder')"
                        ></textarea>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <button
                            @click="showNoteModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            {{ t('verifications_conduct.note_modal.cancel') }}
                        </button>
                        <button
                            @click="saveNote"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            {{ t('verifications_conduct.note_modal.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
