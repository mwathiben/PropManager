<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    BuildingOfficeIcon,
    UserIcon,
    EnvelopeIcon,
    HomeIcon,
    CurrencyDollarIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';
import type { TenantInvitationAcceptPageProps } from '@/types';

const props = defineProps<TenantInvitationAcceptPageProps>();

const { t } = useI18n();
const { formatMoney: formatCurrency } = useFormatters();

// Form for new user registration
const form = useForm({
    name: props.invitation?.tenant_name || '',
    password: '',
    password_confirmation: '',
    phone: '',
    id_number: '',
    confirm: false  // For existing users
});

const acceptInvitation = () => {
    if (props.invitation) {
        form.post(route('tenant-invitations.accept', props.invitation.token));
    }
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('tenant_invitations.accept.page_title')" />

        <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-lg w-full">
                <!-- Error State -->
                <div v-if="error" class="bg-white shadow-lg rounded-lg p-8 text-center">
                    <ExclamationTriangleIcon class="mx-auto h-16 w-16 text-red-500 mb-4" />
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ t('tenant_invitations.accept.invalid_title') }}</h2>
                    <p class="text-gray-600 mb-6">{{ error }}</p>
                    <a
                        href="/login"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    >
                        {{ t('tenant_invitations.accept.go_to_login') }}
                    </a>
                </div>

                <!-- Valid Invitation -->
                <div v-else-if="invitation" class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="bg-green-600 px-6 py-8 text-center">
                        <HomeIcon class="mx-auto h-16 w-16 text-white mb-4" />
                        <h1 class="text-2xl font-bold text-white mb-2">
                            {{ invitation.is_existing_user ? t('tenant_invitations.accept.header.title_existing') : t('tenant_invitations.accept.header.title_new') }}
                        </h1>
                        <p class="text-green-100">
                            {{ invitation.is_existing_user ? t('tenant_invitations.accept.header.subtitle_existing') : t('tenant_invitations.accept.header.subtitle_new') }}
                        </p>
                    </div>

                    <!-- Property & Lease Details -->
                    <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ t('tenant_invitations.accept.property_details') }}</h3>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <UserIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.landlord') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.landlord_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.property') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.property_name }} - {{ invitation.building_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <HomeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.unit') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.unit_number }} {{ t('tenant_invitations.accept.floor', { floor: invitation.floor_number }) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.your_email') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.email }}</p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-semibold text-gray-900 mt-6 mb-4">{{ t('tenant_invitations.accept.lease_terms') }}</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.monthly_rent') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.rent_amount) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.security_deposit') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.deposit_amount) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.service_charge') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.service_charge) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">{{ t('tenant_invitations.accept.start_date') }}</p>
                                <p class="text-sm font-bold text-gray-900">{{ invitation.start_date }}</p>
                            </div>
                        </div>

                        <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-green-800">{{ t('tenant_invitations.accept.total_movein') }}</span>
                                <span class="text-xl font-bold text-green-900">{{ formatCurrency(invitation.total_move_in) }}</span>
                            </div>
                            <p class="text-xs text-green-600 mt-1">{{ t('tenant_invitations.accept.movein_breakdown') }}</p>
                        </div>

                        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-3">
                            <p class="text-xs text-yellow-800">
                                {{ t('tenant_invitations.accept.expires_on') }} <strong>{{ invitation.expires_at }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Form for Existing User -->
                    <form v-if="invitation.is_existing_user" @submit.prevent="acceptInvitation" class="px-6 py-6 space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p class="text-sm text-blue-800">
                                {{ t('tenant_invitations.accept.existing_user_notice') }}
                            </p>
                        </div>

                        <div class="flex items-start gap-3">
                            <input
                                v-model="form.confirm"
                                type="checkbox"
                                id="confirm"
                                class="mt-1 h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                                required
                            />
                            <label for="confirm" class="text-sm text-gray-700">
                                {{ t('tenant_invitations.accept.agree_terms') }}
                                <span class="text-red-500">*</span>
                            </label>
                        </div>
                        <p v-if="form.errors.confirm" class="text-sm text-red-600">
                            {{ form.errors.confirm }}
                        </p>

                        <button
                            type="submit"
                            :disabled="form.processing || !form.confirm"
                            class="w-full px-4 py-3 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? t('tenant_invitations.accept.processing') : t('tenant_invitations.accept.accept_lease') }}
                        </button>
                    </form>

                    <!-- Form for New User -->
                    <form v-else @submit.prevent="acceptInvitation" class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenant_invitations.accept.full_name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                :placeholder="t('tenant_invitations.accept.name_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenant_invitations.accept.phone_number') }}
                            </label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                :placeholder="t('tenant_invitations.accept.phone_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.phone }"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenant_invitations.accept.id_number') }}
                            </label>
                            <input
                                v-model="form.id_number"
                                type="text"
                                :placeholder="t('tenant_invitations.accept.id_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.id_number }"
                            />
                            <p v-if="form.errors.id_number" class="mt-1 text-sm text-red-600">
                                {{ form.errors.id_number }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenant_invitations.accept.password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                :placeholder="t('tenant_invitations.accept.password_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenant_invitations.accept.confirm_password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                :placeholder="t('tenant_invitations.accept.confirm_password_placeholder')"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                            />
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <p class="text-xs text-blue-800">
                                {{ t('tenant_invitations.accept.new_user_notice', { unit: invitation.unit_number }) }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full px-4 py-3 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? t('tenant_invitations.accept.creating_account') : t('tenant_invitations.accept.accept_and_create') }}
                        </button>
                    </form>

                    <div class="px-6 py-4 bg-gray-50 text-center text-sm text-gray-600">
                        {{ t('tenant_invitations.accept.already_have_account') }}
                        <a href="/login" class="text-green-600 hover:text-green-800 font-medium">
                            {{ t('tenant_invitations.accept.login_here') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
