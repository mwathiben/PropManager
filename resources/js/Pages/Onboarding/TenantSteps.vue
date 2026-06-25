<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import WizardProgressBar from './Components/WizardProgressBar.vue';

type KycProgress = {
    required: number;
    submitted: number;
    approved: number;
    pending: number;
    rejected: number;
    percent: number;
    remaining_labels: string[];
};

const props = defineProps<{
    currentStep: number;
    completedSteps?: number[];
    kycProgress?: KycProgress | null;
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

const { t } = useI18n();

const page = usePage();
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error ?? '');

const paymentTypeOptions = computed(() => [
    { value: '', label: t('onboarding_tenant_steps.payment_type_none') },
    { value: 'mpesa', label: t('onboarding_tenant_steps.payment_type_mpesa') },
    { value: 'bank', label: t('onboarding_tenant_steps.payment_type_bank') },
]);

// preserveState: 'errors' — remount on a successful save so the shared form
// object doesn't leak an earlier step's values into the next step; keep input
// + errors on a 422. See Onboarding/Index.vue submitStep for the full rationale.
function submit() {
    form.post(route('onboarding.step.save', { step: props.currentStep }), {
        preserveScroll: true,
        preserveState: 'errors',
    });
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
                        <label for="ts-full-name" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.full_name') }}</label>
                        <input id="ts-full-name" v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="ts-mobile-number" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.mobile_number') }}</label>
                        <input id="ts-mobile-number" v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="form.errors.mobile_number" class="mt-1 text-sm text-red-600">{{ form.errors.mobile_number }}</p>
                    </div>
                    <div>
                        <label for="ts-national-id" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.national_id') }}</label>
                        <input id="ts-national-id" v-model="form.national_id" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="form.errors.national_id" class="mt-1 text-sm text-red-600">{{ form.errors.national_id }}</p>
                    </div>
                </template>

                <template v-else-if="currentStep === 2">
                    <!-- Phase-51 TENANT-WIZARD-POLISH-3: KYC progress indicator -->
                    <div v-if="kycProgress" class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3">
                        <div class="flex items-baseline justify-between">
                            <p class="text-sm font-medium text-indigo-900">
                                {{ t('onboarding_tenant_steps.kyc_progress', { submitted: kycProgress.submitted, required: kycProgress.required }) }}
                            </p>
                            <p class="text-xs text-indigo-700">{{ kycProgress.percent }}%</p>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-white/80">
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                :style="{ width: kycProgress.percent + '%' }"
                            ></div>
                        </div>
                        <p
                            v-if="kycProgress.remaining_labels.length > 0"
                            class="mt-2 text-xs text-indigo-700"
                        >
                            {{ t('onboarding_tenant_steps.still_to_upload', { labels: kycProgress.remaining_labels.join(', ') }) }}
                        </p>
                    </div>

                    <p class="text-gray-700">
                        {{ t('onboarding_tenant_steps.kyc_intro') }}
                        <a :href="route('tenant.kyc.show')" class="text-indigo-600 underline">{{ t('onboarding_tenant_steps.kyc_link') }}</a> {{ t('onboarding_tenant_steps.kyc_intro_suffix') }}
                    </p>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="form.acknowledged" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">{{ t('onboarding_tenant_steps.kyc_acknowledged') }}</span>
                    </label>
                </template>

                <template v-else-if="currentStep === 3">
                    <p class="text-gray-700">{{ t('onboarding_tenant_steps.payment_intro') }}</p>
                    <!-- Phase-51 TENANT-WIZARD-POLISH-2: per-type SVG icon card-grid picker -->
                    <div>
                        <label for="ts-payment-type-group" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.payment_type') }}</label>
                        <div id="ts-payment-type-group" class="mt-2 grid grid-cols-3 gap-2">
                            <button
                                v-for="option in paymentTypeOptions"
                                :key="option.value"
                                type="button"
                                class="flex flex-col items-center gap-1 rounded-lg border px-3 py-3 text-xs font-medium transition"
                                :class="form.type === option.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100' : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-200'"
                                @click="form.type = option.value"
                            >
                                <svg
                                    v-if="option.value === 'mpesa'"
                                    class="h-6 w-6 text-indigo-500"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <rect x="7" y="2" width="10" height="20" rx="2" />
                                    <circle cx="12" cy="18" r="0.8" fill="currentColor" />
                                </svg>
                                <svg
                                    v-else-if="option.value === 'bank'"
                                    class="h-6 w-6 text-indigo-500"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path d="M3 10 L12 4 L21 10 V11 H3 Z" />
                                    <rect x="5" y="11" width="2" height="8" />
                                    <rect x="11" y="11" width="2" height="8" />
                                    <rect x="17" y="11" width="2" height="8" />
                                    <line x1="3" y1="20" x2="21" y2="20" />
                                </svg>
                                <svg
                                    v-else
                                    class="h-6 w-6 text-gray-300"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <line x1="6" y1="18" x2="18" y2="6" />
                                </svg>
                                <span>{{ option.label }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="form.type === 'mpesa'">
                        <label for="ts-mpesa-phone" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.mpesa_phone') }}</label>
                        <input
                            id="ts-mpesa-phone"
                            v-model="(form.details as Record<string, unknown>).phone"
                            type="text"
                            placeholder="0712345678"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                    <template v-else-if="form.type === 'bank'">
                        <div>
                            <label for="ts-bank-name" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.bank_name') }}</label>
                            <input id="ts-bank-name" v-model="(form.details as Record<string, unknown>).bank_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label for="ts-account-number" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.account_number') }}</label>
                            <input id="ts-account-number" v-model="(form.details as Record<string, unknown>).account_number" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label for="ts-account-name" class="block text-sm font-medium text-gray-700">{{ t('onboarding_tenant_steps.account_name') }}</label>
                            <input id="ts-account-name" v-model="(form.details as Record<string, unknown>).account_name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </template>
                    <label v-if="form.type" class="inline-flex items-center gap-2">
                        <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">{{ t('onboarding_tenant_steps.set_as_default') }}</span>
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
