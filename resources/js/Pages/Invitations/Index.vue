<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useEcho } from '@/composables/useEcho';
import { useAuth } from '@/composables/useAuth';
import {
    EnvelopeIcon,
    PlusIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    PaperAirplaneIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';
import type { CaretakerInvitationsIndexPageProps, CaretakerInvitation } from '@/types';

const props = defineProps<CaretakerInvitationsIndexPageProps>();
const { can } = useAuth();

const page = usePage();
const { subscribePrivate, unsubscribe } = useEcho();

const showInviteModal = ref(false);
const localInvitations = ref<CaretakerInvitation[]>([...props.invitations]);
const toast = ref({ show: false, message: '', acceptedBy: '' });

watch(() => props.invitations, (newVal) => {
    localInvitations.value = [...newVal];
}, { deep: true });

const landlordId = page.props.auth?.user?.id;

onMounted(() => {
    if (landlordId) {
        subscribePrivate(`landlord.${landlordId}`, 'InvitationAccepted', handleInvitationAccepted);
    }
});

onUnmounted(() => {
    if (landlordId) {
        unsubscribe(`landlord.${landlordId}`);
    }
});

const handleInvitationAccepted = (data) => {
    const index = localInvitations.value.findIndex(inv => inv.id === data.invitation_id);
    if (index !== -1) {
        localInvitations.value[index] = {
            ...localInvitations.value[index],
            status: 'accepted',
            accepted_at: data.accepted_at
        };
    }

    toast.value = {
        show: true,
        message: `${data.accepted_by} accepted the invitation for ${data.property_name}`,
        acceptedBy: data.accepted_by
    };

    setTimeout(() => {
        toast.value.show = false;
    }, 5000);
};

const inviteForm = useForm({
    email: '',
    property_id: props.properties.length > 0 ? props.properties[0].id : null
});

const sendInvitation = () => {
    inviteForm.post(route('invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            showInviteModal.value = false;
        }
    });
};

const resendInvitation = (invitationId) => {
    if (confirm('Resend this invitation?')) {
        router.post(route('invitations.resend', invitationId), {}, {
            preserveScroll: true
        });
    }
};

const cancelInvitation = (invitationId) => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
        router.delete(route('invitations.destroy', invitationId), {
            preserveScroll: true
        });
    }
};

const statusBadge = (status) => {
    const badges = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'accepted': 'bg-green-100 text-green-800',
        'expired': 'bg-red-100 text-red-800'
    };
    return badges[status] || 'bg-gray-100 text-gray-800';
};

const statusIcon = (status) => {
    switch (status) {
        case 'accepted':
            return CheckCircleIcon;
        case 'pending':
            return ClockIcon;
        case 'expired':
            return XCircleIcon;
        default:
            return ClockIcon;
    }
};

const copyInviteLink = (token) => {
    const url = window.location.origin + '/invitations/' + token;
    navigator.clipboard.writeText(url).then(() => {
        alert('Invitation link copied to clipboard!');
    });
};
</script>

<template>
    <Head title="Caretaker Invitations" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Caretaker Invitations</h1>
                        <p class="mt-1 text-sm text-gray-500">Invite and manage caretakers for your properties</p>
                    </div>
                    <button
                        @click="showInviteModal = true"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center gap-2"
                    >
                        <PlusIcon class="w-5 h-5" />
                        Send Invitation
                    </button>
                </div>

                <!-- Invitations Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead v-once class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Caretaker Email
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Property
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sent Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="invitation in localInvitations" :key="invitation.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <EnvelopeIcon class="w-5 h-5 text-gray-400 mr-2" />
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ invitation.email }}
                                            </div>
                                            <div v-if="invitation.status === 'accepted'" class="text-xs text-gray-500">
                                                Accepted {{ invitation.accepted_at }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ invitation.property }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ invitation.sent_at }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="statusBadge(invitation.status)" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center gap-1">
                                        <component :is="statusIcon(invitation.status)" class="w-4 h-4" />
                                        {{ invitation.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <!-- Copy Link (for pending only) -->
                                    <button
                                        v-if="invitation.status === 'pending'"
                                        @click="copyInviteLink(invitation.token)"
                                        class="text-blue-600 hover:text-blue-900"
                                        title="Copy invitation link"
                                    >
                                        Copy Link
                                    </button>

                                    <!-- Resend (for pending only) -->
                                    <button
                                        v-if="invitation.status === 'pending'"
                                        @click="resendInvitation(invitation.id)"
                                        class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1"
                                        title="Resend invitation"
                                    >
                                        <PaperAirplaneIcon class="w-4 h-4" />
                                        Resend
                                    </button>

                                    <!-- Cancel (for pending only) -->
                                    <button
                                        v-if="can('team:manage') && invitation.status === 'pending'"
                                        @click="cancelInvitation(invitation.id)"
                                        class="text-red-600 hover:text-red-900 inline-flex items-center gap-1"
                                        title="Cancel invitation"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                        Cancel
                                    </button>

                                    <!-- No actions for accepted/expired -->
                                    <span v-if="invitation.status !== 'pending'" class="text-gray-400 text-xs">
                                        —
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <EmptyState
                        v-if="localInvitations.length === 0"
                        :icon="EnvelopeIcon"
                        title="No invitations sent"
                        description="Get started by sending an invitation to a caretaker."
                        action-label="Send First Invitation"
                        @action="showInviteModal = true"
                    />
                </div>
            </div>
        </div>

        <!-- Send Invitation Modal -->
        <div v-if="showInviteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showInviteModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Send Caretaker Invitation</h3>
                </div>

                <form @submit.prevent="sendInvitation">
                    <div class="px-6 py-4 space-y-4">
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address
                            </label>
                            <input
                                v-model="inviteForm.email"
                                type="email"
                                required
                                placeholder="caretaker@example.com"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">
                                {{ inviteForm.errors.email }}
                            </p>
                        </div>

                        <!-- Property -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Property
                            </label>
                            <select
                                v-model="inviteForm.property_id"
                                required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option v-for="property in properties" :key="property.id" :value="property.id">
                                    {{ property.name }}
                                </option>
                            </select>
                            <p v-if="inviteForm.errors.property_id" class="mt-1 text-sm text-red-600">
                                {{ inviteForm.errors.property_id }}
                            </p>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <p class="text-xs text-blue-800">
                                The caretaker will receive an email with a link to accept the invitation and create their account.
                                Invitations expire after 30 days.
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showInviteModal = false; inviteForm.reset()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="inviteForm.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <PaperAirplaneIcon class="w-4 h-4" />
                            {{ inviteForm.processing ? 'Sending...' : 'Send Invitation' }}
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="toast.show"
                class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50"
            >
                <CheckCircleIcon class="w-6 h-6" />
                <div>
                    <p class="font-medium">Invitation Accepted!</p>
                    <p class="text-sm text-green-100">{{ toast.message }}</p>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
