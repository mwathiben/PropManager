<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WizardProgressBar from './Components/WizardProgressBar.vue';

const props = defineProps<{
    currentStep: number;
    completedSteps?: number[];
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
                        Your landlord has invited you to manage one or more buildings. Accept by default; click decline next to a building you cannot cover.
                    </p>
                    <p class="text-sm text-gray-500">
                        Pending assignments are surfaced via the
                        <code class="text-xs">/api/v1/caretaker/assignments</code> endpoint
                        (deepened in a follow-up cycle); this wizard step flips them to accepted by default.
                    </p>
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
