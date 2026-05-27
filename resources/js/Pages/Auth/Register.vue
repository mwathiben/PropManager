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
        <Head :title="t('auth_register.title')" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">{{ t('auth_register.title') }}</h1>

        <form @submit.prevent="submit">
            <div>
                <InputLabel required for="name" :value="t('auth_register.name')" />
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
                <InputLabel required for="email" :value="t('auth_register.email')" />
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

            <!-- Phase-51 PHASE-46-WIZARD-STYLE-1: role card-grid picker
                 with indigo/purple gradient — visually consistent with
                 the rest of the wizard. Native select retained as
                 aria-fallback for screen readers. -->
            <div class="mt-4">
                <InputLabel required for="role" :value="t('auth_register.role_label')" />
                <div
                    role="radiogroup"
                    aria-describedby="role-helper"
                    class="mt-2 grid grid-cols-3 gap-2"
                >
                    <button
                        v-for="role in ['landlord', 'caretaker', 'tenant']"
                        :key="role"
                        type="button"
                        role="radio"
                        :aria-checked="form.role === role"
                        :class="[
                            'flex flex-col items-center gap-1 rounded-lg px-3 py-3 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-400', /* i18n-ignore: tailwind classes */
                            form.role === role
                                ? 'bg-gradient-to-br from-indigo-100 via-white to-purple-100 ring-2 ring-indigo-500 text-indigo-900' /* i18n-ignore: tailwind classes */
                                : 'bg-white border border-gray-200 text-gray-600 hover:border-indigo-300', /* i18n-ignore: tailwind classes */
                        ]"
                        @click="form.role = role"
                    >
                        <svg
                            class="h-6 w-6 text-indigo-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <template v-if="role === 'landlord'">
                                <path d="M3 10 L12 4 L21 10 V20 H3 Z" />
                                <rect x="10" y="14" width="4" height="6" />
                            </template>
                            <template v-else-if="role === 'caretaker'">
                                <circle cx="12" cy="8" r="3" />
                                <path d="M6 20 V18 a6 6 0 0 1 12 0 V20" />
                            </template>
                            <template v-else>
                                <rect x="5" y="9" width="14" height="11" rx="1" />
                                <path d="M9 9 V6 a3 3 0 0 1 6 0 V9" />
                            </template>
                        </svg>
                        <span>{{ t(`auth_register.role_${role}`, role ?? '') }}</span>
                    </button>
                </div>

                <!-- Phase-51 PHASE-46-WIZARD-STYLE-2: branded gradient
                     for the role-help card so it visually matches the
                     picker above. -->
                <div
                    id="role-helper"
                    class="mt-2 rounded-lg bg-gradient-to-br from-indigo-50 via-white to-purple-50 ring-1 ring-indigo-100/50 p-3 text-xs text-gray-700"
                >
                    <p v-if="form.role === 'landlord'">
                        <strong>{{ t('auth_register.role_landlord_lead') }}</strong> {{ t('auth_register.role_landlord_body') }}
                    </p>
                    <p v-if="form.role === 'caretaker'">
                        <strong>{{ t('auth_register.role_caretaker_lead') }}</strong> {{ t('auth_register.role_caretaker_body') }}
                    </p>
                    <p v-if="form.role === 'tenant'">
                        <strong>{{ t('auth_register.role_tenant_lead') }}</strong> {{ t('auth_register.role_tenant_body') }}
                    </p>
                </div>
                <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <div class="mt-4">
                <InputLabel required for="password" :value="t('auth_register.password')" />
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
                <InputLabel required for="password_confirmation" :value="t('auth_register.confirm_password')" />
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
                    {{ t('auth_register.already_registered') }}
                </Link>

                <FormSubmitButton class="ms-4" :processing="form.processing">
                    {{ t('auth_register.submit') }}
                </FormSubmitButton>
            </div>
        </form>
    </GuestLayout>
</template>
