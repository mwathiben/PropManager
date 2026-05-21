<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { WalletIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    mode: string;
    modes: string[];
    default: string;
}>();

const { t } = useI18n();

const form = useForm({ auto_apply_mode: props.mode });

function save(): void {
    form.put(route('wallet.settings.update'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('wallet.settings.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-100 rounded-lg">
                    <WalletIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('wallet.settings.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('wallet.settings.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-xl px-4 py-6 sm:px-6 lg:px-8" data-testid="wallet-settings">
            <form class="space-y-4 rounded-lg bg-white p-6 shadow" @submit.prevent="save">
                <label class="block text-sm font-medium text-gray-700">{{ t('wallet.settings.mode_label') }}</label>
                <div class="space-y-2">
                    <label
                        v-for="m in modes"
                        :key="m"
                        class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"
                        :class="form.auto_apply_mode === m ? 'ring-2 ring-emerald-500' : ''"
                    >
                        <input v-model="form.auto_apply_mode" type="radio" :value="m" class="text-emerald-600" />
                        <span class="text-sm text-gray-800">{{ t('wallet.settings.mode_' + m) }}</span>
                    </label>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ t('wallet.settings.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
