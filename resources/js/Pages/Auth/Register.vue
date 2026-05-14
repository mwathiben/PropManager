<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useZodForm } from '@/composables/forms/useZodForm';
import { registerSchema } from '@/composables/forms/schemas/registerSchema';

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
        <Head title="Register" />

        <!-- Phase-23 A11Y-SR-2: sr-only page heading for the document outline. -->
        <h1 class="sr-only">Register</h1>

        <form @submit.prevent="submit">
            <div>
                <InputLabel required for="name" value="Name" />
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
                <InputLabel required for="email" value="Email" />
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
                <InputLabel required for="role" value="I am a..." />
                <select
                    id="role"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    v-model="form.role"
                    aria-describedby="role-helper"
                    required
                >
                    <option value="landlord">Property Owner / Landlord</option>
                    <option value="caretaker">Property Manager / Caretaker</option>
                    <option value="tenant">Tenant</option>
                </select>

                <!-- Helper Text — Phase-23 A11Y-FORM-3: associated to the
                     select via aria-describedby so a screen-reader user
                     hears the role explanation (WCAG 1.3.1). -->
                <div id="role-helper" class="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-600 border border-gray-100">
                    <p v-if="form.role === 'landlord'">
                        <strong>Owner:</strong> You own properties and want to track rent, manage tenants, and view financial reports.
                    </p>
                    <p v-if="form.role === 'caretaker'">
                        <strong>Manager:</strong> You work for a landlord. You input meter readings and handle maintenance, but have restricted access to financial data.
                    </p>
                    <p v-if="form.role === 'tenant'">
                        <strong>Resident:</strong> You live in a unit. You want to view your rent status, pay bills, and report issues.
                    </p>
                </div>
                <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <div class="mt-4">
                <InputLabel required for="password" value="Password" />
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
                <InputLabel required for="password_confirmation" value="Confirm Password" />
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
                    Already registered?
                </Link>

                <FormSubmitButton class="ms-4" :processing="form.processing">
                    Register
                </FormSubmitButton>
            </div>
        </form>
    </GuestLayout>
</template>