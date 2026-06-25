<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useAuth } from '@/composables/useAuth';
import { useI18n } from '@/composables/useI18n';
import type { OcrSettings, OcrProvidersLookup } from '@/types';

const props = withDefaults(defineProps<{
    ocrSettings?: OcrSettings;
    ocrProviders?: OcrProvidersLookup;
}>(), {
    ocrSettings: () => ({} as OcrSettings),
    ocrProviders: () => ({} as OcrProvidersLookup),
});

const { can } = useAuth();
const { t } = useI18n();

const showApiKeyInput = ref(false);
const selectedProvider = ref(props.ocrSettings.provider || 'none');

const ocrForm = useForm({
    provider: props.ocrSettings.provider || 'none',
    enabled: props.ocrSettings.enabled || false,
    auto_verify: props.ocrSettings.auto_verify || false,
    api_key: '',
    azure_endpoint: ''
});

const updateOcrSettings = () => {
    ocrForm.post(route('settings.ocr.update'), {
        onSuccess: () => {
            ocrForm.reset('api_key', 'azure_endpoint');
            showApiKeyInput.value = false;
        },
        preserveScroll: true,
    });
};

const testOcr = () => {
    ocrForm.post(route('settings.ocr.test'), {
        preserveState: true,
        preserveScroll: true
    });
};

const deleteApiKey = (provider) => {
    if (confirm(t('integrations.ocr.delete_confirm'))) {
        useForm({ provider }).post(route('settings.apiKey.delete'), {
            preserveScroll: true,
        });
    }
};

const providerChanged = (provider) => {
    selectedProvider.value = provider;
    ocrForm.provider = provider;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ t('integrations.title') }}</h3>
            <p class="mt-1 text-sm text-gray-600">
                {{ t('integrations.subtitle') }}
            </p>
        </div>

        <!-- OCR Configuration -->
        <div class="bg-gray-50 rounded-xl p-6 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('integrations.ocr.heading') }}</h4>
                    <p class="mt-1 text-sm text-gray-500">{{ t('integrations.ocr.description') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">{{ t('integrations.ocr.enable') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="ocrForm.enabled"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>

            <!-- Provider Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">{{ t('integrations.ocr.select_provider') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(provider, key) in ocrProviders"
                        :key="key"
                        @click="providerChanged(key)"
                        :class="['relative border-2 rounded-xl p-4 cursor-pointer transition-all', ocrForm.provider === key ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-indigo-300']"
                    >
                        <div class="flex items-start">
                            <input
                                type="radio"
                                :value="key"
                                v-model="ocrForm.provider"
                                :aria-label="provider.name"
                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                            >
                            <div class="ms-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <h5 class="text-sm font-semibold text-gray-900">{{ provider.name }}</h5>
                                    <span v-if="provider.recommended" class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded">
                                        {{ t('integrations.ocr.recommended') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-600">{{ provider.description }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs">
                                    <span class="text-green-600 font-medium">{{ provider.free_tier }}</span>
                                    <span class="text-gray-400">|</span>
                                    <a :href="provider.setup_url" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                        {{ t('integrations.ocr.setup_guide') }}
                                    </a>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ t('integrations.ocr.requires', { requirements: provider.requires.join(', ') }) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No OCR Option -->
                    <div
                        @click="providerChanged('none')"
                        :class="['relative border-2 rounded-xl p-4 cursor-pointer transition-all', ocrForm.provider === 'none' ? 'border-gray-400 bg-gray-100 ring-1 ring-gray-400' : 'border-gray-200 hover:border-gray-300']"
                    >
                        <div class="flex items-start">
                            <input
                                type="radio"
                                value="none"
                                v-model="ocrForm.provider"
                                :aria-label="t('integrations.ocr.none.name')"
                                class="mt-1 text-gray-600 focus:ring-gray-500"
                            >
                            <div class="ms-3">
                                <h5 class="text-sm font-semibold text-gray-900">{{ t('integrations.ocr.none.name') }}</h5>
                                <p class="mt-1 text-xs text-gray-600">{{ t('integrations.ocr.none.description') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Key Input -->
            <div v-if="ocrForm.provider !== 'none' && ocrForm.provider !== 'tesseract'">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm text-blue-800">
                            <strong>{{ t('integrations.ocr.api_key_required_label') }}</strong> {{ t('integrations.ocr.api_key_required_body', { provider: ocrProviders[ocrForm.provider]?.name }) }}
                            <a :href="ocrProviders[ocrForm.provider]?.setup_url" target="_blank" class="underline font-medium ms-1">
                                {{ t('integrations.ocr.get_api_key') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div v-if="ocrSettings.has_api_key && !showApiKeyInput" class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-green-800">{{ t('integrations.ocr.api_key_configured') }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="showApiKeyInput = true"
                            type="button"
                            class="px-3 py-1 text-sm bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            {{ t('integrations.ocr.update_key') }}
                        </button>
                        <button
                            v-if="can('settings:manage')"
                            @click="deleteApiKey(ocrForm.provider)"
                            type="button"
                            class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                        >
                            {{ t('integrations.ocr.delete') }}
                        </button>
                    </div>
                </div>

                <div v-if="!ocrSettings.has_api_key || showApiKeyInput" class="space-y-4">
                    <div>
                        <label for="ocr-api-key" class="block text-sm font-medium text-gray-700 mb-2">{{ t('integrations.ocr.api_key_label') }}</label>
                        <input
                            id="ocr-api-key"
                            v-model="ocrForm.api_key"
                            type="password"
                            :placeholder="t('integrations.ocr.api_key_placeholder')"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                        <p class="mt-1 text-xs text-gray-500">{{ t('integrations.ocr.api_key_hint') }}</p>
                    </div>

                    <!-- Azure Endpoint (if Azure selected) -->
                    <div v-if="ocrForm.provider === 'azure_vision'">
                        <label for="ocr-azure-endpoint" class="block text-sm font-medium text-gray-700 mb-2">{{ t('integrations.ocr.endpoint_label') }}</label>
                        <input
                            id="ocr-azure-endpoint"
                            v-model="ocrForm.azure_endpoint"
                            type="url"
                            placeholder="https://your-resource.cognitiveservices.azure.com/"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                    </div>
                </div>
            </div>

            <!-- Auto-Verify Option -->
            <div v-if="ocrForm.provider !== 'none'" class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="ocrForm.auto_verify"
                        class="sr-only peer"
                    >
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
                <div>
                    <span class="text-sm font-medium text-gray-700">{{ t('integrations.ocr.auto_verify') }}</span>
                    <p class="text-xs text-gray-500">{{ t('integrations.ocr.auto_verify_hint') }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <PrimaryButton
                    @click="updateOcrSettings"
                    :disabled="ocrForm.processing"
                    :class="{ 'opacity-50': ocrForm.processing }"
                >
                    {{ ocrForm.processing ? t('integrations.ocr.saving') : t('integrations.ocr.save') }}
                </PrimaryButton>
                <button
                    v-if="ocrForm.provider !== 'none' && ocrSettings.has_api_key"
                    @click="testOcr"
                    type="button"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium"
                >
                    {{ t('integrations.ocr.test_connection') }}
                </button>
            </div>
        </div>

        <!-- Future Integrations Placeholder -->
        <div class="bg-gray-50 rounded-xl p-6 border-2 border-dashed border-gray-300">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <h4 class="mt-2 text-sm font-medium text-gray-900">{{ t('integrations.coming_soon.title') }}</h4>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('integrations.coming_soon.body') }}
                </p>
            </div>
        </div>
    </div>
</template>
