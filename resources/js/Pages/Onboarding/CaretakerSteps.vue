<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WizardProgressBar from './Components/WizardProgressBar.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

type PendingAssignment = {
    id: number;
    building_id: number;
    building_name: string;
    unit_count: number;
    occupied_count: number;
    open_ticket_count: number;
    created_at: string | null;
};

type BuildingSummary = {
    building_id: number;
    name: string;
    unit_count: number;
    occupied_count: number;
    open_ticket_count: number;
};

const props = defineProps<{
    currentStep: number;
    completedSteps?: number[];
    totalSteps?: number;
    pendingAssignments?: PendingAssignment[];
    buildingSummary?: BuildingSummary[];
    firstTaskUrl?: string;
    profile?: { name: string | null; mobile_number: string | null };
}>();

const form = useForm<Record<string, unknown>>({
    name: props.profile?.name ?? '',
    mobile_number: props.profile?.mobile_number ?? '',
    acknowledged: false,
    decline: [] as number[],
    decline_reason: {} as Record<number, string>,
    email_enabled: true,
    sms_enabled: false,
    whatsapp_enabled: false,
    push_enabled: false,
    maintenance_notice_enabled: true,
    general_enabled: true,
    caretaker_invitation_enabled: true,
    tenant_invitation_enabled: false,
    lease_expiry_enabled: false,
});

const MAX_DECLINE_REASON_LENGTH = 200;

const assignments = computed<PendingAssignment[]>(() => props.pendingAssignments ?? []);
const summary = computed<BuildingSummary[]>(() => props.buildingSummary ?? []);

function isDeclined(assignmentId: number): boolean {
    return (form.decline as number[]).includes(assignmentId);
}

function toggleDecline(assignmentId: number): void {
    const list = form.decline as number[];
    if (list.includes(assignmentId)) {
        form.decline = list.filter((id) => id !== assignmentId);
        const reasons = { ...(form.decline_reason as Record<number, string>) };
        delete reasons[assignmentId];
        form.decline_reason = reasons;
    } else {
        form.decline = [...list, assignmentId];
    }
}

function reasonLength(assignmentId: number): number {
    return ((form.decline_reason as Record<number, string>)[assignmentId] ?? '').length;
}

const page = usePage();
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error ?? '');

const ctaLabel = computed(() => {
    if (props.currentStep === 1) return t('onboarding.caretaker.welcome_cta');
    if (props.currentStep === (props.totalSteps ?? 5)) return t('onboarding.caretaker.orientation_cta');
    return t('onboarding.wizard.resume_cta');
});

// preserveState: 'errors' — remount + re-hydrate from the new step's props on a
// successful save (this wizard's useForm defaults are prop-derived); keep input
// + errors on a 422. See Onboarding/Index.vue submitStep for the full rationale.
function submit() {
    form.post(route('onboarding.step.save', { step: props.currentStep }), {
        preserveScroll: true,
        preserveState: 'errors',
    });
}
</script>

<template>
    <Head :title="$t('onboarding.caretaker.title')" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 py-12 px-4">
        <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-8">
            <WizardProgressBar :current-step="currentStep" :total-steps="totalSteps ?? 5" />

            <h1 class="text-2xl font-semibold text-gray-900 mb-6">
                {{ $t('onboarding.caretaker.title') }}
            </h1>

            <div v-if="flashError" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <template v-if="currentStep === 1">
                    <div class="rounded-lg bg-indigo-50 px-4 py-5 text-gray-700" data-testid="caretaker-welcome">
                        <h2 class="text-lg font-semibold text-indigo-900">{{ $t('onboarding.caretaker.welcome_title') }}</h2>
                        <p class="mt-2 text-sm">{{ $t('onboarding.caretaker.welcome_body') }}</p>
                    </div>
                </template>

                <template v-else-if="currentStep === 2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('onboarding_caretaker_steps.full_name') }}</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ t('onboarding_caretaker_steps.mobile_number') }}</label>
                        <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="form.errors.mobile_number" class="mt-1 text-sm text-red-600">{{ form.errors.mobile_number }}</p>
                    </div>
                </template>

                <template v-else-if="currentStep === 3">
                    <p class="text-gray-700">
                        {{ t('onboarding_caretaker_steps.assignments_intro') }}
                    </p>

                    <p v-if="assignments.length === 0" class="rounded-md border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        {{ t('onboarding_caretaker_steps.no_assignments') }}
                    </p>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="assignment in assignments"
                            :key="assignment.id"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-3"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ assignment.building_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ t('onboarding_caretaker_steps.building_stats', { units: assignment.unit_count, occupied: assignment.occupied_count, tickets: assignment.open_ticket_count }) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <label class="inline-flex items-center gap-1">
                                        <input
                                            type="radio"
                                            :name="'assignment-' + assignment.id"
                                            :checked="!isDeclined(assignment.id)"
                                            @change="isDeclined(assignment.id) && toggleDecline(assignment.id)"
                                            class="text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span class="text-emerald-700">{{ t('onboarding_caretaker_steps.accept') }}</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1">
                                        <input
                                            type="radio"
                                            :name="'assignment-' + assignment.id"
                                            :checked="isDeclined(assignment.id)"
                                            @change="!isDeclined(assignment.id) && toggleDecline(assignment.id)"
                                            class="text-rose-600 focus:ring-rose-500"
                                        />
                                        <span class="text-rose-700">{{ t('onboarding_caretaker_steps.decline') }}</span>
                                    </label>
                                </div>
                            </div>
                            <div v-if="isDeclined(assignment.id)" class="mt-3">
                                <label class="block text-xs font-medium text-gray-700">{{ t('onboarding_caretaker_steps.reason_label') }}</label>
                                <textarea
                                    v-model="(form.decline_reason as Record<number, string>)[assignment.id]"
                                    :maxlength="MAX_DECLINE_REASON_LENGTH"
                                    rows="2"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                                    :placeholder="t('onboarding_caretaker_steps.reason_placeholder')"
                                ></textarea>
                                <p class="mt-1 text-end text-xs text-gray-400">
                                    {{ reasonLength(assignment.id) }} / {{ MAX_DECLINE_REASON_LENGTH }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </template>

                <template v-else-if="currentStep === 4">
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700 mb-2">{{ t('onboarding_caretaker_steps.channels') }}</legend>
                        <label class="flex items-center gap-2"><input v-model="form.email_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.channel_email') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.sms_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.channel_sms') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.whatsapp_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.channel_whatsapp') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.push_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.channel_push') }}</span></label>
                    </fieldset>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700 mb-2">{{ t('onboarding_caretaker_steps.notification_types') }}</legend>
                        <label class="flex items-center gap-2"><input v-model="form.maintenance_notice_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.maintenance_notices') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.general_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.general_announcements') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.caretaker_invitation_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.caretaker_invitations') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.tenant_invitation_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.tenant_invitations') }}</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.lease_expiry_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">{{ t('onboarding_caretaker_steps.lease_expiry_alerts') }}</span></label>
                    </fieldset>
                </template>

                <template v-else-if="currentStep === 5">
                    <div data-testid="caretaker-orientation">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $t('onboarding.caretaker.orientation_title') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $t('onboarding.caretaker.orientation_body') }}</p>

                        <p v-if="summary.length === 0" class="mt-3 rounded-md border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                            {{ $t('onboarding.caretaker.orientation_empty') }}
                        </p>
                        <ul v-else class="mt-3 space-y-2">
                            <li v-for="b in summary" :key="b.building_id" class="rounded-lg border border-gray-200 px-4 py-3">
                                <p class="font-medium text-gray-900">{{ b.name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ t('onboarding_caretaker_steps.building_stats', { units: b.unit_count, occupied: b.occupied_count, tickets: b.open_ticket_count }) }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </template>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-indigo-600 text-white rounded-md py-2 px-4 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="form.processing">…</span>
                    <span v-else>{{ ctaLabel }}</span>
                </button>
            </form>
        </div>
    </div>
</template>
