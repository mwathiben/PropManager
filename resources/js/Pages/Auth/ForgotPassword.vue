<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.forgot.title')" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">{{ t('auth.forgot.title') }}</h1>

        <div class="mb-4 text-sm text-gray-600">
            {{ t('auth.forgot.instructions') }}
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel required for="email" :value="t('common.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    :error-message="form.errors.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError id="email-error" class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <FormSubmitButton :processing="form.processing">
                    {{ t('auth.forgot.submit') }}
                </FormSubmitButton>
            </div>
        </form>
    </GuestLayout>
</template>
