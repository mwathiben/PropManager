<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WizardProgressBar from './Components/WizardProgressBar.vue';

type PendingAssignment = {
    id: number;
    building_id: number;
    building_name: string;
    created_at: string | null;
};

const props = defineProps<{
    currentStep: number;
    completedSteps?: number[];
    pendingAssignments?: PendingAssignment[];
}>();

const form = useForm<Record<string, unknown>>({
    name: '',
    mobile_number: '',
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

function submit() {
    form.post(route('onboarding.step.save', { step: props.currentStep }));
}
</script>

<template>
    <Head :title="$t('onboarding.wizard.resume_cta')" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 py-12 px-4">
        <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-8">
            <WizardProgressBar :current-step="currentStep" :total-steps="3" />

            <h1 class="text-2xl font-semibold text-gray-900 mb-6">
                {{ $t('onboarding.wizard.resume_cta') }}
            </h1>

            <div v-if="flashError" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <template v-if="currentStep === 1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full name</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mobile number</label>
                        <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="form.errors.mobile_number" class="mt-1 text-sm text-red-600">{{ form.errors.mobile_number }}</p>
                    </div>
                </template>

                <template v-else-if="currentStep === 2">
                    <p class="text-gray-700">
                        Your landlord has invited you to manage one or more buildings. Confirm acceptance below; expand decline to skip a building you cannot cover.
                    </p>

                    <p v-if="assignments.length === 0" class="rounded-md border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        No pending building assignments. You can advance to the next step.
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
                                    <p class="text-xs text-gray-500">Invited {{ assignment.created_at?.slice(0, 10) ?? '—' }}</p>
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
                                        <span class="text-emerald-700">Accept</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1">
                                        <input
                                            type="radio"
                                            :name="'assignment-' + assignment.id"
                                            :checked="isDeclined(assignment.id)"
                                            @change="!isDeclined(assignment.id) && toggleDecline(assignment.id)"
                                            class="text-rose-600 focus:ring-rose-500"
                                        />
                                        <span class="text-rose-700">Decline</span>
                                    </label>
                                </div>
                            </div>
                            <div v-if="isDeclined(assignment.id)" class="mt-3">
                                <label class="block text-xs font-medium text-gray-700">Reason (optional)</label>
                                <textarea
                                    v-model="(form.decline_reason as Record<number, string>)[assignment.id]"
                                    :maxlength="MAX_DECLINE_REASON_LENGTH"
                                    rows="2"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                                    placeholder="Why you cannot cover this building"
                                ></textarea>
                                <p class="mt-1 text-end text-xs text-gray-400">
                                    {{ reasonLength(assignment.id) }} / {{ MAX_DECLINE_REASON_LENGTH }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </template>

                <template v-else-if="currentStep === 3">
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700 mb-2">Channels</legend>
                        <label class="flex items-center gap-2"><input v-model="form.email_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Email</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.sms_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">SMS</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.whatsapp_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">WhatsApp</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.push_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Push</span></label>
                    </fieldset>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700 mb-2">Notification types</legend>
                        <label class="flex items-center gap-2"><input v-model="form.maintenance_notice_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Maintenance notices</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.general_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">General announcements</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.caretaker_invitation_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Caretaker invitations</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.tenant_invitation_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Tenant invitations</span></label>
                        <label class="flex items-center gap-2"><input v-model="form.lease_expiry_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> <span class="text-sm text-gray-700">Lease expiry alerts</span></label>
                    </fieldset>
                </template>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-indigo-600 text-white rounded-md py-2 px-4 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="form.processing">…</span>
                    <span v-else>{{ $t('onboarding.wizard.resume_cta') }}</span>
                </button>
            </form>
        </div>
    </div>
</template>
