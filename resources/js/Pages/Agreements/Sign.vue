<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    token: { type: String, required: true },
    signed: { type: Boolean, default: false },
    signerName: { type: String, default: '' },
    phoneHint: { type: String, default: '' },
    agreement: { type: Object, required: true },
    // Set by the controller ONLY when the owner is routed through Documenso's
    // embedded signing (after the OTP identity gate passes). { baseUrl, token,
    // signerName, signerEmail }. Absent on the in-house fallback path.
    embed: { type: Object, default: null },
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

// --- Documenso embedded signing ------------------------------------------------
const iframeReady = ref(false);
const finalizing = ref(false);

const embedOrigin = computed(() => {
    if (!props.embed?.baseUrl) {
        return null;
    }
    try {
        return new URL(props.embed.baseUrl).origin;
    } catch {
        return null;
    }
});

const embedUrl = computed(() => {
    if (!props.embed) {
        return '';
    }
    // Documenso reads its config from the URL hash: JSON.parse(decodeURIComponent(atob(hash))).
    const config = {
        name: props.embed.signerName || '',
        lockName: true,
        email: props.embed.signerEmail || '',
        lockEmail: true,
        darkModeDisabled: true,
    };
    const hash = btoa(encodeURIComponent(JSON.stringify(config)));
    return `${props.embed.baseUrl}/embed/sign/${props.embed.token}#${hash}`;
});

let pollTimer = null;
const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};
const startPolling = () => {
    if (pollTimer) {
        return;
    }
    // The DOCUMENT_COMPLETED webhook (the source of truth) seals + activates server-side;
    // poll the page until it reflects the signed state. postMessage is UX only.
    pollTimer = setInterval(() => {
        router.reload({
            only: ['signed'],
            onSuccess: () => {
                if (props.signed) {
                    stopPolling();
                }
            },
        });
    }, 3000);
};

const onEmbedMessage = (event) => {
    // The postMessage targetOrigin is '*' on Documenso's side, so validate the origin
    // ourselves and never act on a message from an unexpected source.
    if (!embedOrigin.value || event.origin !== embedOrigin.value) {
        return;
    }
    const action = event.data?.action;
    if (action === 'document-ready') {
        iframeReady.value = true;
    } else if (action === 'document-completed') {
        finalizing.value = true;
        startPolling();
    }
};

onMounted(() => window.addEventListener('message', onEmbedMessage));
onBeforeUnmount(() => {
    window.removeEventListener('message', onEmbedMessage);
    stopPolling();
});
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

                <div v-else-if="embed" class="space-y-5">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ agreement.title }}</h1>
                        <p class="text-base font-medium text-gray-700 mt-1">{{ t('agreements.sign.documenso.heading') }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ t('agreements.sign.documenso.intro') }}</p>
                    </div>

                    <div v-if="finalizing" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></div>
                        <p class="text-sm text-gray-600">{{ t('agreements.sign.documenso.finalizing') }}</p>
                    </div>

                    <div v-else class="relative bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div
                            v-if="!iframeReady"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-white"
                        >
                            <div class="text-center">
                                <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></div>
                                <p class="text-sm text-gray-500">{{ t('agreements.sign.documenso.loading') }}</p>
                            </div>
                        </div>
                        <iframe
                            :src="embedUrl"
                            :title="t('agreements.sign.documenso.heading')"
                            class="w-full h-[70vh] min-h-[560px]"
                        ></iframe>
                    </div>
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
                                <label for="sign-code" class="block text-sm font-medium text-gray-700">
                                    {{ t('agreements.sign.code_label') }}
                                    <input
                                        id="sign-code"
                                        v-model="signForm.code"
                                        inputmode="numeric"
                                        maxlength="6"
                                        autocomplete="one-time-code"
                                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tracking-[0.5em]"
                                    />
                                </label>
                                <p v-if="signForm.errors.code" class="mt-1 text-sm text-red-600">{{ signForm.errors.code }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ t('agreements.sign.code_hint', { phone: phoneHint }) }}</p>
                            </div>

                            <label for="sign-agree" class="flex items-start gap-2 text-sm text-gray-700">
                                <input
                                    id="sign-agree"
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
