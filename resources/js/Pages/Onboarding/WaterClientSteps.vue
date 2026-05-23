<script setup lang="ts">
/**
 * Phase-95 WATER-CLIENT-ONBOARDING: the water-client onboarding scaffold —
 * tenant onboarding minus the lease (profile / documents / payment method).
 */
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WizardProgressBar from './Components/WizardProgressBar.vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<{ currentStep: number; completedSteps?: number[] }>();

const { t } = useI18n();

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

function submit(): void {
    form.post(route('onboarding.step.save', { step: props.currentStep }));
}
</script>

<template>
    <Head :title="t('water.client_onboarding.title')" />

    <div class="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-blue-50 px-4 py-12">
        <div class="mx-auto max-w-xl rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100">
            <WizardProgressBar :current-step="currentStep" :total-steps="3" />

            <h1 class="mb-6 text-2xl font-semibold text-gray-900">{{ t('water.client_onboarding.title') }}</h1>

            <div v-if="flashError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ flashError }}</div>

            <form class="space-y-5" @submit.prevent="submit">
                <template v-if="currentStep === 1">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_name') }}</span>
                        <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        <span v-if="form.errors.name" class="mt-1 block text-sm text-red-600">{{ form.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_mobile') }}</span>
                        <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.national_id') }}</span>
                        <input v-model="form.national_id" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                </template>

                <template v-else-if="currentStep === 2">
                    <p class="text-gray-700">{{ t('water.client_onboarding.docs_body') }}</p>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.acknowledged" type="checkbox" class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500" />
                        <span class="text-sm text-gray-700">{{ t('water.client_onboarding.docs_ack') }}</span>
                    </label>
                </template>

                <template v-else-if="currentStep === 3">
                    <p class="text-gray-700">{{ t('water.client_onboarding.pay_body') }}</p>
                    <div>
                        <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.pay_type') }}</span>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <button
                                v-for="option in [
                                    { value: '', label: t('water.client_onboarding.pay_none') },
                                    { value: 'mpesa', label: t('water.client_onboarding.pay_mpesa') },
                                    { value: 'bank', label: t('water.client_onboarding.pay_bank') },
                                ]"
                                :key="option.value"
                                type="button"
                                class="rounded-lg border px-3 py-3 text-xs font-medium transition"
                                :class="form.type === option.value ? 'border-cyan-500 bg-cyan-50 text-cyan-700 ring-2 ring-cyan-100' : 'border-gray-200 bg-white text-gray-600 hover:border-cyan-200'"
                                @click="form.type = option.value"
                            >{{ option.label }}</button>
                        </div>
                    </div>
                    <label v-if="form.type === 'mpesa'" class="block">
                        <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.pay_phone') }}</span>
                        <input v-model="(form.details as Record<string, unknown>).phone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <template v-else-if="form.type === 'bank'">
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.pay_bank_name') }}</span>
                            <input v-model="(form.details as Record<string, unknown>).bank_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </label>
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.pay_account_number') }}</span>
                            <input v-model="(form.details as Record<string, unknown>).account_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </label>
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">{{ t('water.client_onboarding.pay_account_name') }}</span>
                            <input v-model="(form.details as Record<string, unknown>).account_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </label>
                    </template>
                    <label v-if="form.type" class="inline-flex items-center gap-2">
                        <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500" />
                        <span class="text-sm text-gray-700">{{ t('water.client_onboarding.pay_default') }}</span>
                    </label>
                </template>

                <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-cyan-600 px-4 py-2 text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ t('water.client_onboarding.continue') }}
                </button>
            </form>
        </div>
    </div>
</template>
