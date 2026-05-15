<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const useRecoveryCode = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const submit = () => {
    form.post(route('two-factor.verify'), {
        preserveScroll: true,
    });
};

const toggleMode = () => {
    useRecoveryCode.value = !useRecoveryCode.value;
    form.code = '';
    form.recovery_code = '';
    form.clearErrors();
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.tfa.title')" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">{{ t('auth.tfa.title') }}</h1>

        <div class="mb-4 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center mb-4">
                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">{{ t('auth.tfa.title') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                <template v-if="!useRecoveryCode">
                    {{ t('auth.tfa.instructions_code') }}
                </template>
                <template v-else>
                    {{ t('auth.tfa.instructions_recovery') }}
                </template>
            </p>
        </div>

        <form @submit.prevent="submit">
            <!-- TOTP Code Input -->
            <div v-if="!useRecoveryCode">
                <label for="code" class="sr-only">{{ t('auth.tfa.code_label') }}</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="000000"
                    class="w-full text-center text-2xl tracking-widest font-mono border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    autofocus
                    autocomplete="one-time-code"
                >
                <p v-if="form.errors.code" class="mt-2 text-sm text-red-600 text-center">{{ form.errors.code }}</p>
            </div>

            <!-- Recovery Code Input -->
            <div v-else>
                <label for="recovery_code" class="sr-only">{{ t('auth.tfa.recovery_label') }}</label>
                <input
                    id="recovery_code"
                    v-model="form.recovery_code"
                    type="text"
                    placeholder="XXXX-XXXX-XXXX"
                    class="w-full text-center text-lg tracking-wider font-mono border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase"
                    autofocus
                >
                <p v-if="form.errors.recovery_code" class="mt-2 text-sm text-red-600 text-center">{{ form.errors.recovery_code }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing || (!useRecoveryCode && form.code.length !== 6) || (useRecoveryCode && !form.recovery_code)"
                class="w-full mt-4 px-4 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 font-medium"
            >
                {{ form.processing ? t('auth.tfa.verifying') : t('auth.tfa.verify') }}
            </button>
        </form>

        <!-- Toggle Mode -->
        <div class="mt-6 text-center">
            <button
                @click="toggleMode"
                type="button"
                class="text-sm text-indigo-600 hover:text-indigo-800"
            >
                <template v-if="!useRecoveryCode">
                    {{ t('auth.tfa.use_recovery') }}
                </template>
                <template v-else>
                    {{ t('auth.tfa.use_code') }}
                </template>
            </button>
        </div>

        <!-- Help Text -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-600 text-center">
                <template v-if="!useRecoveryCode">
                    {{ t('auth.tfa.help_code') }}
                </template>
                <template v-else>
                    {{ t('auth.tfa.help_recovery') }}
                </template>
            </p>
        </div>
    </GuestLayout>
</template>
