<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useZodForm } from '@/composables/forms/useZodForm';
import { registerSchema } from '@/composables/forms/schemas/registerSchema';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'landlord', // Default role
});

const { validate } = useZodForm(form, registerSchema);

const submit = () => {
    if (!validate()) {
        return;
    }
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.register.title')" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">{{ t('auth.register.title') }}</h1>

        <form @submit.prevent="submit">
            <div>
                <InputLabel required for="name" :value="t('common.name')" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    :error-message="form.errors.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError id="name-error" class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel required for="email" :value="t('common.email')" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    :error-message="form.errors.email"
                    required
                    autocomplete="username"
                />
                <InputError id="email-error" class="mt-2" :message="form.errors.email" />
            </div>

            <!-- NEW: Role Selection with Tooltips -->
            <div class="mt-4">
                <InputLabel required for="role" :value="t('auth.register.role_label')" />
                <select
                    id="role"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    v-model="form.role"
                    aria-describedby="role-helper"
                    required
                >
                    <option value="landlord">{{ t('auth.register.role_landlord') }}</option>
                    <option value="caretaker">{{ t('auth.register.role_caretaker') }}</option>
                    <option value="tenant">{{ t('auth.register.role_tenant') }}</option>
                </select>

                <!-- Helper Text — Phase-23 A11Y-FORM-3: associated to the
                     select via aria-describedby so a screen-reader user
                     hears the role explanation (WCAG 1.3.1). -->
                <div id="role-helper" class="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-600 border border-gray-100">
                    <p v-if="form.role === 'landlord'">
                        <strong>{{ t('auth.register.role_landlord_lead') }}</strong> {{ t('auth.register.role_landlord_body') }}
                    </p>
                    <p v-if="form.role === 'caretaker'">
                        <strong>{{ t('auth.register.role_caretaker_lead') }}</strong> {{ t('auth.register.role_caretaker_body') }}
                    </p>
                    <p v-if="form.role === 'tenant'">
                        <strong>{{ t('auth.register.role_tenant_lead') }}</strong> {{ t('auth.register.role_tenant_body') }}
                    </p>
                </div>
                <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <div class="mt-4">
                <InputLabel required for="password" :value="t('common.password')" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    :error-message="form.errors.password"
                    required
                    autocomplete="new-password"
                />
                <InputError id="password-error" class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel required for="password_confirmation" :value="t('common.confirm_password')" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    :error-message="form.errors.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError id="password_confirmation-error" class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link
                    :href="route('login')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    {{ t('auth.register.already_registered') }}
                </Link>

                <FormSubmitButton class="ms-4" :processing="form.processing">
                    {{ t('auth.register.submit') }}
                </FormSubmitButton>
            </div>
        </form>
    </GuestLayout>
</template>
