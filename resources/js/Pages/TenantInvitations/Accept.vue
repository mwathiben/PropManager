<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
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
        <Head title="Accept Lease Invitation" />

        <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-lg w-full">
                <!-- Error State -->
                <div v-if="error" class="bg-white shadow-lg rounded-lg p-8 text-center">
                    <ExclamationTriangleIcon class="mx-auto h-16 w-16 text-red-500 mb-4" />
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Invalid Invitation</h2>
                    <p class="text-gray-600 mb-6">{{ error }}</p>
                    <a
                        href="/login"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    >
                        Go to Login
                    </a>
                </div>

                <!-- Valid Invitation -->
                <div v-else-if="invitation" class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="bg-green-600 px-6 py-8 text-center">
                        <HomeIcon class="mx-auto h-16 w-16 text-white mb-4" />
                        <h1 class="text-2xl font-bold text-white mb-2">
                            {{ invitation.is_existing_user ? 'New Lease Invitation' : "You're Invited!" }}
                        </h1>
                        <p class="text-green-100">
                            {{ invitation.is_existing_user ? 'Accept your new lease' : 'Join as a Tenant' }}
                        </p>
                    </div>

                    <!-- Property & Lease Details -->
                    <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">Property Details</h3>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <UserIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">Landlord</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.landlord_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">Property</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.property_name }} - {{ invitation.building_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <HomeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">Unit</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.unit_number }} (Floor {{ invitation.floor_number }})</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-xs text-gray-500">Your Email</p>
                                    <p class="text-sm font-medium text-gray-900">{{ invitation.email }}</p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-semibold text-gray-900 mt-6 mb-4">Lease Terms</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">Monthly Rent</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.rent_amount) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">Security Deposit</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.deposit_amount) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">Service Charge</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invitation.service_charge) }}</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-xs text-gray-500">Start Date</p>
                                <p class="text-sm font-bold text-gray-900">{{ invitation.start_date }}</p>
                            </div>
                        </div>

                        <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-green-800">Total Move-in Cost</span>
                                <span class="text-xl font-bold text-green-900">{{ formatCurrency(invitation.total_move_in) }}</span>
                            </div>
                            <p class="text-xs text-green-600 mt-1">First month rent + deposit + service charge</p>
                        </div>

                        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-3">
                            <p class="text-xs text-yellow-800">
                                This invitation expires on <strong>{{ invitation.expires_at }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Form for Existing User -->
                    <form v-if="invitation.is_existing_user" @submit.prevent="acceptInvitation" class="px-6 py-6 space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p class="text-sm text-blue-800">
                                You already have an account. By clicking "Accept Lease" below, you agree to the lease terms above
                                and the lease will be activated immediately.
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
                                I have reviewed the lease terms above and agree to them.
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
                            {{ form.processing ? 'Processing...' : 'Accept Lease' }}
                        </button>
                    </form>

                    <!-- Form for New User -->
                    <form v-else @submit.prevent="acceptInvitation" class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="John Doe"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Phone Number
                            </label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="+254 712 345 678"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.phone }"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ID / Passport Number
                            </label>
                            <input
                                v-model="form.id_number"
                                type="text"
                                placeholder="National ID or Passport"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.id_number }"
                            />
                            <p v-if="form.errors.id_number" class="mt-1 text-sm text-red-600">
                                {{ form.errors.id_number }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                placeholder="Minimum 8 characters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                :class="{ 'border-red-300': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                placeholder="Re-enter password"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                            />
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <p class="text-xs text-blue-800">
                                By accepting this invitation, you'll create a tenant account, and your lease at
                                {{ invitation.unit_number }} will be activated immediately.
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full px-4 py-3 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Creating Account...' : 'Accept Invitation & Create Account' }}
                        </button>
                    </form>

                    <div class="px-6 py-4 bg-gray-50 text-center text-sm text-gray-600">
                        Already have an account?
                        <a href="/login" class="text-green-600 hover:text-green-800 font-medium">
                            Login here
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
