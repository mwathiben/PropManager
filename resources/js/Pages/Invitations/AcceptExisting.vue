<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { InvitationAcceptExistingPageProps } from '@/types/tenants';
import {
    CheckCircleIcon,
    UserIcon,
    BuildingOfficeIcon,
    CalendarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<InvitationAcceptExistingPageProps>();

const processing = ref(false);

const acceptInvitation = () => {
    processing.value = true;
    router.post(route('invitations.accept-authenticated', props.invitation.id), {}, {
        onFinish: () => {
            processing.value = false;
        },
    });
};

const declineInvitation = () => {
    if (!confirm('Are you sure you want to decline this invitation?')) {
        return;
    }

    processing.value = true;
    router.post(route('invitations.decline-authenticated', props.invitation.id), {}, {
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Accept Caretaker Invitation" />

        <div class="py-12">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center">
                        <CheckCircleIcon class="mx-auto h-16 w-16 text-white mb-4" />
                        <h1 class="text-2xl font-bold text-white mb-2">Caretaker Invitation</h1>
                        <p class="text-indigo-100">You've been invited to become a property caretaker</p>
                    </div>

                    <!-- Invitation Details -->
                    <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0 w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <UserIcon class="w-5 h-5 text-indigo-600" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Invited by</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ invitation.landlord_name }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <BuildingOfficeIcon class="w-5 h-5 text-green-600" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Property</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ invitation.property_name }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <CalendarIcon class="w-5 h-5 text-yellow-600" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Expires on</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ invitation.expires_at }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="px-6 py-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                By accepting this invitation, your account will be converted to a
                                <strong>caretaker</strong> role. You'll gain access to manage operations for
                                <strong>{{ invitation.property_name }}</strong>.
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="px-6 py-6 bg-gray-50 flex flex-col sm:flex-row gap-3">
                        <button
                            @click="acceptInvitation"
                            :disabled="processing"
                            class="flex-1 px-4 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ processing ? 'Processing...' : 'Accept Invitation' }}
                        </button>
                        <button
                            @click="declineInvitation"
                            :disabled="processing"
                            class="flex-1 px-4 py-3 bg-white text-red-600 font-medium rounded-lg border border-red-200 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
