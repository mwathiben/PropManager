<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router, Link, useForm, usePage } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useEcho } from '@/composables/useEcho';
import type { OperationsTeamTabProps } from '@/types/operations';
import {
    UserGroupIcon,
    UserPlusIcon,
    EnvelopeIcon,
    TrashIcon,
    PaperAirplaneIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<OperationsTeamTabProps>();

const { formatDate } = useFormatters();
const page = usePage();
const { subscribePrivate, unsubscribe } = useEcho();

const showInviteModal = ref(false);
const localInvitations = ref([...(props.invitations?.data || [])]);
const toast = ref({ show: false, message: '', type: 'success' });

watch(() => props.invitations, (newVal) => {
    localInvitations.value = [...(newVal?.data || [])];
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

    showToast(`${data.accepted_by} accepted the invitation!`, 'success');
    router.reload({ only: ['caretakers', 'invitations'] });
};

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => {
        toast.value.show = false;
    }, 5000);
};

const inviteForm = useForm({
    email: '',
    name: '',
    building_ids: [],
});

const submitInvite = () => {
    inviteForm.post(route('invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
            showToast('Invitation sent successfully!');
        },
    });
};

const resendInvitation = (id) => {
    if (confirm('Resend this invitation?')) {
        router.post(route('invitations.resend', id), {}, {
            preserveScroll: true,
            onSuccess: () => showToast('Invitation resent!'),
        });
    }
};

const cancelInvitation = (id) => {
    if (confirm('Cancel this invitation?')) {
        router.delete(route('invitations.destroy', id), { preserveScroll: true });
    }
};

const removeCaretaker = (id) => {
    if (confirm('Remove this caretaker? They will lose access to your properties.')) {
        router.delete(route('caretakers.destroy', id), { preserveScroll: true });
    }
};

const copyInviteLink = (invitation) => {
    const url = `${window.location.origin}/invitations/${invitation.token}`;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Invitation link copied to clipboard!');
    }).catch(() => {
        showToast('Failed to copy link', 'error');
    });
};

const getStatusIcon = (status) => {
    const icons = {
        accepted: CheckCircleIcon,
        pending: ClockIcon,
        expired: XCircleIcon,
    };
    return icons[status] || ClockIcon;
};

const getStatusColor = (status) => {
    const colors = {
        accepted: 'bg-green-100 text-green-800',
        pending: 'bg-yellow-100 text-yellow-800',
        expired: 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-semibold text-gray-900">Team Members</h3>
                <p class="text-sm text-gray-500">Manage caretakers and property managers</p>
            </div>
            <button
                @click="showInviteModal = true"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium"
            >
                <UserPlusIcon class="w-5 h-5 mr-1" />
                Invite Caretaker
            </button>
        </div>

        <div class="mb-8">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Active Caretakers</h4>
            <div v-if="caretakers?.length > 0" class="space-y-3">
                <div
                    v-for="caretaker in caretakers"
                    :key="caretaker.id"
                    class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-medium">
                            {{ caretaker.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">{{ caretaker.name }}</div>
                            <div class="text-sm text-gray-500">{{ caretaker.email }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">
                            {{ caretaker.buildings_count || 0 }} buildings
                        </span>
                        <button
                            @click="removeCaretaker(caretaker.id)"
                            class="p-1 text-red-600 hover:bg-red-50 rounded"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                <UserGroupIcon class="mx-auto h-10 w-10 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500">No active caretakers</p>
            </div>
        </div>

        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-3">Pending Invitations</h4>
            <div v-if="localInvitations?.length > 0" class="space-y-3">
                <div
                    v-for="invitation in localInvitations"
                    :key="invitation.id"
                    class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600">
                            <EnvelopeIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">{{ invitation.name || invitation.email }}</div>
                            <div class="text-sm text-gray-500">{{ invitation.email }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                Expires: {{ formatDate(invitation.expires_at) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="getStatusColor(invitation.status)" class="px-2 py-1 text-xs rounded-full flex items-center gap-1">
                            <component :is="getStatusIcon(invitation.status)" class="w-3 h-3" />
                            {{ invitation.status }}
                        </span>
                        <template v-if="invitation.status === 'pending'">
                            <button
                                @click="copyInviteLink(invitation)"
                                class="p-1 text-blue-600 hover:bg-blue-50 rounded"
                                title="Copy Link"
                            >
                                <ClipboardDocumentIcon class="w-5 h-5" />
                            </button>
                            <button
                                @click="resendInvitation(invitation.id)"
                                class="p-1 text-purple-600 hover:bg-purple-50 rounded"
                                title="Resend"
                            >
                                <PaperAirplaneIcon class="w-5 h-5" />
                            </button>
                            <button
                                @click="cancelInvitation(invitation.id)"
                                class="p-1 text-red-600 hover:bg-red-50 rounded"
                                title="Cancel"
                            >
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                <ClockIcon class="mx-auto h-10 w-10 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500">No pending invitations</p>
            </div>
        </div>

        <div v-if="showInviteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showInviteModal = false"></div>

                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Invite Caretaker</h3>

                    <form @submit.prevent="submitInvite" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input
                                v-model="inviteForm.name"
                                type="text"
                                class="w-full border-gray-300 rounded-lg"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                v-model="inviteForm.email"
                                type="email"
                                class="w-full border-gray-300 rounded-lg"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assign to Buildings</label>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                <label
                                    v-for="building in buildings"
                                    :key="building.id"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="inviteForm.building_ids"
                                        :value="building.id"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-purple-600"
                                    />
                                    <span class="text-sm text-gray-700">{{ building.name }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                type="button"
                                @click="showInviteModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="inviteForm.processing"
                                class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
                            >
                                Send Invitation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                :class="[
                    'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50',
                    toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
                ]"
            >
                <CheckCircleIcon v-if="toast.type === 'success'" class="w-6 h-6" />
                <XCircleIcon v-else class="w-6 h-6" />
                <p class="font-medium">{{ toast.message }}</p>
            </div>
        </Transition>
    </div>
</template>
