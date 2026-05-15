<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.confirm.title')" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">{{ t('auth.confirm.title') }}</h1>

        <div class="mb-4 text-sm text-gray-600">
            {{ t('auth.confirm.instructions') }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel required for="password" :value="t('common.password')" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    :error-message="form.errors.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError id="password-error" class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex justify-end">
                <FormSubmitButton class="ms-4" :processing="form.processing">
                    {{ t('auth.confirm.submit') }}
                </FormSubmitButton>
            </div>
        </form>
    </GuestLayout>
</template>
