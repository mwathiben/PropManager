<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    currentStep: number;
    completedSteps?: number[];
}>();

const form = useForm<Record<string, unknown>>({
    name: '',
    mobile_number: '',
    national_id: '',
    acknowledged: false,
    email_enabled: true,
});

function submit() {
    form.post(route('onboarding.step.save', { step: props.currentStep }));
}
</script>

<template>
    <Head :title="$t('onboarding.wizard.resume_cta')" />

    <div class="min-h-screen bg-gray-50 py-12 px-4">
        <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-semibold text-gray-900 mb-6">
                {{ $t('onboarding.resume_banner.title', { current: currentStep, total: 3 }) }}
            </h1>

            <form @submit.prevent="submit" class="space-y-5">
                <template v-if="currentStep === 1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full name</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mobile number</label>
                        <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">National ID</label>
                        <input v-model="form.national_id" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                </template>

                <template v-else-if="currentStep === 2">
                    <p class="text-gray-700">
                        KYC documents are uploaded from the
                        <a :href="route('tenant.kyc')" class="text-indigo-600 underline">KYC verification</a> page.
                    </p>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.acknowledged" type="checkbox" class="rounded border-gray-300" />
                        <span class="text-sm text-gray-700">I understand</span>
                    </label>
                </template>

                <template v-else-if="currentStep === 3">
                    <p class="text-gray-700">Your landlord accepts the following payment channels. You can pay using cash, M-Pesa, or bank transfer.</p>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.acknowledged" type="checkbox" class="rounded border-gray-300" />
                        <span class="text-sm text-gray-700">I understand how to pay</span>
                    </label>
                </template>

                <button type="submit" :disabled="form.processing" class="w-full bg-indigo-600 text-white rounded-md py-2 px-4 hover:bg-indigo-700">
                    {{ $t('onboarding.wizard.resume_cta') }}
                </button>
            </form>
        </div>
    </div>
</template>
