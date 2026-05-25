<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import type { InvitationAcceptPageProps } from '@/types/tenants';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    BuildingOfficeIcon,
    UserIcon,
    EnvelopeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<InvitationAcceptPageProps>();

const { t } = useI18n();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
    mobile_number: ''
});

const acceptInvitation = () => {
    if (props.invitation) {
        form.post(route('invitations.accept', props.invitation.token));
    }
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('invitations.accept.head_title')" />

        <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full">
                <!-- Error State -->
                <div v-if="error" class="bg-white shadow-lg rounded-lg p-8 text-center">
                    <ExclamationTriangleIcon class="mx-auto h-16 w-16 text-red-500 mb-4" />
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ t('invitations.accept.invalid_title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ error }}</p>
                    <a
                        href="/login"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    >
                        {{ t('invitations.accept.go_to_login') }}
                    </a>
                </div>

                <!-- Valid Invitation -->
                <div v-else-if="invitation" class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="bg-indigo-600 px-6 py-8 text-center">
                        <CheckCircleIcon class="mx-auto h-16 w-16 text-white mb-4" />
                        <h1 class="text-2xl font-bold text-white mb-2">{{ t('invitations.accept.youre_invited') }}</h1>
                        <p class="text-indigo-100">{{ t('invitations.accept.join_as') }}</p>
                    </div>

                    <!-- Invitation Details -->
                    <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <UserIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('invitations.accept.invited_by') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.landlord_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('invitations.accept.property') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.property_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('invitations.accept.email') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-3">
                            <p class="text-xs text-yellow-800">
                                {{ t('invitations.accept.expires_on') }} <strong>{{ invitation.expires_at }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <form @submit.prevent="acceptInvitation" class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('invitations.accept.full_name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                :placeholder="t('invitations.accept.name_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('invitations.accept.mobile_number') }}
                            </label>
                            <input
                                v-model="form.mobile_number"
                                type="tel"
                                :placeholder="t('invitations.accept.phone_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.mobile_number" class="mt-1 text-sm text-red-600">
                                {{ form.errors.mobile_number }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('invitations.accept.password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                :placeholder="t('invitations.accept.password_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('invitations.accept.confirm_password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                :placeholder="t('invitations.accept.confirm_password_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <p class="text-xs text-blue-800">
                                {{ t('invitations.accept.terms_notice', { property: invitation.property_name }) }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? t('invitations.accept.creating') : t('invitations.accept.submit') }}
                        </button>
                    </form>

                    <div class="px-6 py-4 bg-gray-50 text-center text-sm text-gray-600">
                        {{ t('invitations.accept.already_have_account') }}
                        <a href="/login" class="text-indigo-600 hover:text-indigo-800 font-medium">
                            {{ t('invitations.accept.login_here') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
