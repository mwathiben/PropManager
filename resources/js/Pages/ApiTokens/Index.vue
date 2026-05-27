<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import { useFormatters } from '@/composables/useFormatters';
import { useI18n } from '@/composables/useI18n';
import { KeyIcon, TrashIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

interface ApiToken {
    id: number;
    name: string;
    scopes: string[];
    last_used_at: string | null;
    last_used_ip: string | null;
    expires_at: string | null;
    created_at: string | null;
}

const props = defineProps<{
    tokens: ApiToken[];
    allowedScopes: string[];
    plaintextToken: string | null;
}>();

const { formatDate } = useFormatters();
const { t } = useI18n();

const form = useForm<{
    name: string;
    scopes: string[];
}>({
    name: '',
    scopes: [],
});

const showPlaintext = ref(props.plaintextToken !== null);
const copied = ref(false);

const submit = () => {
    form.post(route('settings.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const revoke = (token: ApiToken) => {
    if (!confirm(t('api_tokens_index.confirm_revoke', { name: token.name }))) {
        return;
    }
    router.delete(route('settings.api-tokens.destroy', token.id), { preserveScroll: true });
};

const scopeDescriptionKey = (scope: string): string => {
    if (scope === 'landlord:manage') return 'api_tokens_index.scope_descriptions.landlord_manage';
    if (scope === 'integration:webhook') return 'api_tokens_index.scope_descriptions.integration_webhook';
    return '';
};

const copyToken = async () => {
    if (!props.plaintextToken) return;
    try {
        await navigator.clipboard.writeText(props.plaintextToken);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API unavailable — fall through to manual copy.
    }
};
</script>

<template>
    <Head :title="t('api_tokens_index.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('api_tokens_index.header') }}</h1>
        </template>

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Plaintext token banner -->
            <div
                v-if="showPlaintext && plaintextToken"
                role="alert"
                class="bg-amber-50 border border-amber-300 rounded-xl p-6"
            >
                <h2 class="text-base font-semibold text-amber-900 mb-2">{{ t('api_tokens_index.plaintext.title') }}</h2>
                <p class="text-sm text-amber-800 mb-4">
                    {{ t('api_tokens_index.plaintext.body') }}
                </p>
                <div class="flex items-center gap-2 bg-white rounded-md border border-amber-200 p-3 font-mono text-sm">
                    <span class="flex-1 break-all">{{ plaintextToken }}</span>
                    <button
                        type="button"
                        @click="copyToken"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-600 text-white text-xs font-medium rounded-md hover:bg-amber-700"
                    >
                        <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                        {{ copied ? t('api_tokens_index.plaintext.copied') : t('api_tokens_index.plaintext.copy') }}
                    </button>
                </div>
                <button
                    type="button"
                    @click="showPlaintext = false"
                    class="mt-4 text-sm text-amber-900 underline hover:text-amber-700"
                >
                    {{ t('api_tokens_index.plaintext.hide') }}
                </button>
            </div>

            <!-- Create new token -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">{{ t('api_tokens_index.create.title') }}</h2>
                <p class="text-sm text-gray-500 mb-4">
                    {{ t('api_tokens_index.create.description') }}
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel required for="token-name" :value="t('api_tokens_index.create.name_label')" />
                        <TextInput
                            id="token-name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('api_tokens_index.create.name_placeholder')"
                            required
                            maxlength="50"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel :value="t('api_tokens_index.create.scopes_label')" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="scope in allowedScopes"
                                :key="scope"
                                class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                            >
                                <input
                                    type="checkbox"
                                    :value="scope"
                                    v-model="form.scopes"
                                    class="mt-0.5"
                                />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 font-mono">{{ scope }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <template v-if="scopeDescriptionKey(scope)">
                                            {{ t(scopeDescriptionKey(scope)) }}
                                        </template>
                                    </p>
                                </div>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.scopes" />
                    </div>

                    <div class="flex justify-end">
                        <FormSubmitButton :processing="form.processing">
                            {{ t('api_tokens_index.create.submit') }}
                        </FormSubmitButton>
                    </div>
                </form>
            </div>

            <!-- Active tokens -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">{{ t('api_tokens_index.active.title') }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ t('api_tokens_index.active.description') }}
                    </p>
                </div>

                <div v-if="tokens.length === 0" class="p-12 text-center">
                    <KeyIcon class="h-12 w-12 mx-auto text-gray-300" aria-hidden="true" />
                    <p class="mt-3 text-sm text-gray-500">{{ t('api_tokens_index.active.empty') }}</p>
                </div>

                <ul v-else class="divide-y divide-gray-200">
                    <li v-for="token in tokens" :key="token.id" class="p-6 flex items-start gap-4">
                        <KeyIcon class="h-6 w-6 text-gray-400 mt-0.5 flex-shrink-0" aria-hidden="true" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ token.name }}</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <span
                                    v-for="scope in token.scopes"
                                    :key="scope"
                                    class="inline-block px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-mono rounded"
                                >
                                    {{ scope }}
                                </span>
                            </div>
                            <dl class="mt-2 text-xs text-gray-500 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                                <div>
                                    <dt class="inline">{{ t('api_tokens_index.active.created') }}</dt>
                                    <dd class="inline ms-1">{{ token.created_at ? formatDate(token.created_at) : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="inline">{{ t('api_tokens_index.active.last_used') }}</dt>
                                    <dd class="inline ms-1">
                                        {{ token.last_used_at ? formatDate(token.last_used_at) : t('api_tokens_index.active.never') }}
                                        <span v-if="token.last_used_ip" class="ms-1 font-mono">({{ token.last_used_ip }})</span>
                                    </dd>
                                </div>
                                <div v-if="token.expires_at">
                                    <dt class="inline">{{ t('api_tokens_index.active.expires') }}</dt>
                                    <dd class="inline ms-1">{{ formatDate(token.expires_at) }}</dd>
                                </div>
                            </dl>
                        </div>
                        <button
                            type="button"
                            @click="revoke(token)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-700 border border-red-200 rounded-md hover:bg-red-50"
                        >
                            <TrashIcon class="h-4 w-4" aria-hidden="true" />
                            {{ t('api_tokens_index.active.revoke') }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
