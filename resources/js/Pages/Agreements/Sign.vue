<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    token: { type: String, required: true },
    signed: { type: Boolean, default: false },
    signerName: { type: String, default: '' },
    phoneHint: { type: String, default: '' },
    agreement: { type: Object, required: true },
});

const codeSent = ref(false);

const otpForm = useForm({});
const requestCode = () =>
    otpForm.post(route('agreements.sign.otp', props.token), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = true;
        },
    });

const signForm = useForm({
    code: '',
    content_hash: props.agreement?.content_hash ?? '',
    agree: false,
});
const sign = () => signForm.post(route('agreements.sign', props.token), { preserveScroll: true });
</script>

<template>
    <GuestLayout>
        <Head :title="t('agreements.sign.page_title')" />

        <div class="min-h-screen bg-gray-50 py-10 px-4">
            <div class="max-w-2xl mx-auto">
                <div v-if="signed" class="bg-white rounded-xl shadow p-8 text-center">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ t('agreements.sign.signed_title') }}</h1>
                    <p class="text-gray-600">{{ t('agreements.sign.signed_body') }}</p>
                </div>

                <div v-else class="space-y-5">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ agreement.title }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ t('agreements.sign.intro') }}</p>
                    </div>

                    <div
                        class="bg-white rounded-xl border border-gray-200 p-6 whitespace-pre-line text-sm text-gray-700 leading-relaxed max-h-96 overflow-y-auto"
                    >
                        {{ agreement.rendered_body }}
                    </div>
                    <p v-if="agreement.content_hash" class="text-xs text-gray-400 font-mono break-all">
                        {{ t('agreements.sign.document_hash') }}: {{ agreement.content_hash }}
                    </p>

                    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                        <button
                            v-if="!codeSent"
                            type="button"
                            :disabled="otpForm.processing"
                            @click="requestCode"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ t('agreements.sign.request_code') }}
                        </button>

                        <form v-else @submit.prevent="sign" class="space-y-4">
                            <p class="text-sm text-green-700">{{ t('agreements.sign.otp_sent') }}</p>

                            <div>
                                <label for="sign-code" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('agreements.sign.code_label') }}
                                </label>
                                <input
                                    id="sign-code"
                                    v-model="signForm.code"
                                    inputmode="numeric"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tracking-[0.5em]"
                                />
                                <p v-if="signForm.errors.code" class="mt-1 text-sm text-red-600">{{ signForm.errors.code }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ t('agreements.sign.code_hint', { phone: phoneHint }) }}</p>
                            </div>

                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input
                                    v-model="signForm.agree"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>{{ t('agreements.sign.agree_label') }}</span>
                            </label>
                            <p v-if="signForm.errors.agree" class="text-sm text-red-600">{{ signForm.errors.agree }}</p>
                            <p v-if="signForm.errors.content_hash" class="text-sm text-red-600">{{ signForm.errors.content_hash }}</p>

                            <button
                                type="submit"
                                :disabled="signForm.processing"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('agreements.sign.sign_button') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
