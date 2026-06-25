<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import type { TwoFactorRecoveryCodesPageProps } from '@/types';

const { formatDate } = useFormatters();
const { t } = useI18n();

const props = withDefaults(defineProps<TwoFactorRecoveryCodesPageProps>(), {
    recoveryCodes: () => [],
});

const showRegenerateModal = ref(false);

const passwordForm = useForm({
    password: '',
});

const regenerateCodes = () => {
    passwordForm.post(route('two-factor.recovery-codes.regenerate'), {
        preserveScroll: true,
        onSuccess: () => {
            showRegenerateModal.value = false;
            passwordForm.reset();
        },
    });
};

const copyAllCodes = () => {
    const codesText = props.recoveryCodes.join('\n');
    navigator.clipboard.writeText(codesText);
};

const downloadCodes = () => {
    const codesText = `${t('two_factor_recovery.download.file_header')}
${t('two_factor_recovery.download.generated', { date: formatDate(new Date()) })}

${t('two_factor_recovery.download.important')}

${props.recoveryCodes.join('\n')}

${t('two_factor_recovery.download.footer')}
`;

    const blob = new Blob([codesText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'propmanager-recovery-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
};

const printCodes = () => {
    window.print();
};
</script>

<template>
    <Head :title="t('two_factor_recovery.page_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Recovery Codes Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg print:shadow-none">
                    <div class="p-6">
                        <Link :href="route('two-factor.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3 print:hidden">
                            <ArrowLeftIcon class="w-4 h-4" />
                            {{ t('two_factor_recovery.back_to_2fa') }}
                        </Link>
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ t('two_factor_recovery.heading') }}</h1>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ t('two_factor_recovery.intro') }}
                                </p>
                            </div>
                            <a
                                :href="route('two-factor.index')"
                                class="text-gray-400 hover:text-gray-600 print:hidden"
                                :aria-label="t('two_factor_recovery.back_to_2fa')"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </a>
                        </div>

                        <!-- Warning -->
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 print:bg-yellow-100">
                            <div class="flex items-start gap-3">
                                <svg class="h-5 w-5 text-amber-600 mt-0.5 print:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div class="text-sm text-amber-800">
                                    <strong>{{ t('two_factor_recovery.warning.prefix') }}</strong> {{ t('two_factor_recovery.warning.used_part1') }} <strong>{{ t('two_factor_recovery.warning.once') }}</strong>{{ t('two_factor_recovery.warning.used_part2') }}
                                    {{ t('two_factor_recovery.warning.safe_place') }}
                                    {{ t('two_factor_recovery.warning.anyone') }}
                                </div>
                            </div>
                        </div>

                        <!-- Codes Grid -->
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div
                                v-for="code in recoveryCodes"
                                :key="code"
                                class="px-4 py-3 bg-gray-50 rounded-lg font-mono text-sm text-center tracking-wider"
                            >
                                {{ code }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-3 print:hidden">
                            <button
                                @click="copyAllCodes"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                {{ t('two_factor_recovery.actions.copy') }}
                            </button>
                            <button
                                @click="downloadCodes"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ t('two_factor_recovery.actions.download') }}
                            </button>
                            <button
                                @click="printCodes"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                {{ t('two_factor_recovery.actions.print') }}
                            </button>
                            <button
                                @click="showRegenerateModal = true"
                                class="px-4 py-2 bg-amber-100 text-amber-700 rounded-md hover:bg-amber-200 font-medium"
                            >
                                {{ t('two_factor_recovery.actions.regenerate') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Back Link -->
                <div class="text-center print:hidden">
                    <a
                        :href="route('two-factor.index')"
                        class="text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        {{ t('two_factor_recovery.back_to_settings') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Regenerate Modal -->
        <div v-if="showRegenerateModal" class="fixed inset-0 z-50 overflow-y-auto print:hidden">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" role="button" tabindex="0" @click="showRegenerateModal = false" @keydown.enter="showRegenerateModal = false" @keydown.space.prevent="showRegenerateModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('two_factor_recovery.regenerate.title') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('two_factor_recovery.regenerate.description') }}
                    </p>

                    <form @submit.prevent="regenerateCodes">
                        <div class="mb-4">
                            <label for="2fa-confirm-password" class="block text-sm font-medium text-gray-700 mb-1">{{ t('two_factor_recovery.regenerate.confirm_password') }}</label>
                            <input
                                id="2fa-confirm-password"
                                v-model="passwordForm.password"
                                type="password"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                autofocus
                            >
                            <p v-if="passwordForm.errors.password" class="text-sm text-red-600 mt-1">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showRegenerateModal = false; passwordForm.reset();"
                                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md"
                            >
                                {{ t('two_factor_recovery.regenerate.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:opacity-50"
                            >
                                {{ passwordForm.processing ? t('two_factor_recovery.regenerate.submitting') : t('two_factor_recovery.regenerate.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .print\:shadow-none,
    .print\:shadow-none * {
        visibility: visible;
    }
    .print\:shadow-none {
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        width: 100%;
    }
}
</style>
