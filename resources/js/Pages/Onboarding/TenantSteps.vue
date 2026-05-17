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
    national_id: '',
    acknowledged: false,
    type: '',
    details: {} as Record<string, unknown>,
    is_default: false,
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

            <!-- Phase-48 WIZARD-PROGRESS-UX-2: surface flash + form errors. -->
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">National ID</label>
                        <input v-model="form.national_id" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="form.errors.national_id" class="mt-1 text-sm text-red-600">{{ form.errors.national_id }}</p>
                    </div>
                </template>

                <template v-else-if="currentStep === 2">
                    <p class="text-gray-700">
                        KYC documents are uploaded from the
                        <a :href="route('tenant.kyc.show')" class="text-indigo-600 underline">KYC verification</a> page.
                        Submit every required document before continuing — review by the landlord can happen after.
                    </p>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.acknowledged" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">I have submitted my required documents</span>
                    </label>
                </template>

                <template v-else-if="currentStep === 3">
                    <p class="text-gray-700">Optionally save a payment method so your landlord can auto-debit rent.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment type</label>
                        <select v-model="form.type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">No saved method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank account</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'mpesa'">
                        <label class="block text-sm font-medium text-gray-700">M-Pesa phone</label>
                        <input
                            v-model="(form.details as Record<string, unknown>).phone"
                            type="text"
                            placeholder="0712345678"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                    <template v-else-if="form.type === 'bank'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Bank name</label>
                            <input v-model="(form.details as Record<string, unknown>).bank_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account number</label>
                            <input v-model="(form.details as Record<string, unknown>).account_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account name</label>
                            <input v-model="(form.details as Record<string, unknown>).account_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </template>
                    <label v-if="form.type" class="inline-flex items-center gap-2">
                        <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Set as default</span>
                    </label>
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
