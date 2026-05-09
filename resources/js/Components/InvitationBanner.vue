<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    BuildingOffice2Icon,
    HomeModernIcon,
    XMarkIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables';
import type { PendingInvitation } from '@/types';

const props = withDefaults(defineProps<{
    invitations?: PendingInvitation[];
}>(), {
    invitations: () => [],
});

const { formatMoney: formatCurrency } = useFormatters();
const processing = ref(null);

const acceptInvitation = (invitation) => {
    processing.value = `${invitation.type}-${invitation.id}`;
    const routeName = invitation.type === 'caretaker'
        ? 'invitations.accept-authenticated'
        : 'tenant-invitations.accept-authenticated';

    router.post(route(routeName, invitation.id), {}, {
        preserveScroll: true,
        onFinish: () => processing.value = null,
    });
};

const declineInvitation = (invitation) => {
    if (!confirm('Are you sure you want to decline this invitation?')) {
        return;
    }

    processing.value = `${invitation.type}-${invitation.id}`;
    const routeName = invitation.type === 'caretaker'
        ? 'invitations.decline-authenticated'
        : 'tenant-invitations.decline-authenticated';

    router.post(route(routeName, invitation.id), {}, {
        preserveScroll: true,
        onFinish: () => processing.value = null,
    });
};
</script>

<template>
    <div v-if="invitations.length > 0" class="space-y-4">
        <div
            v-for="invitation in invitations"
            :key="`${invitation.type}-${invitation.id}`"
            class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-4 text-white shadow-lg"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <div class="h-12 w-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <BuildingOffice2Icon v-if="invitation.type === 'caretaker'" class="h-6 w-6" />
                        <HomeModernIcon v-else class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-lg truncate">
                            {{ invitation.type === 'caretaker' ? 'Caretaker Invitation' : 'Lease Invitation' }}
                        </p>
                        <p class="text-indigo-100 text-sm truncate">
                            <template v-if="invitation.type === 'caretaker'">
                                {{ invitation.landlord_name }} invited you to manage {{ invitation.property_name }}
                            </template>
                            <template v-else>
                                {{ invitation.landlord_name }} invited you to Unit {{ invitation.unit_number }} at {{ invitation.property_name }}
                            </template>
                        </p>
                        <div class="flex items-center gap-4 mt-1 text-indigo-200 text-xs">
                            <span v-if="invitation.type === 'tenant'">
                                Rent: {{ formatCurrency(invitation.rent_amount) }}/month
                            </span>
                            <span>Expires {{ invitation.expires_at }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button
                        @click="acceptInvitation(invitation)"
                        :disabled="processing === `${invitation.type}-${invitation.id}`"
                        class="px-4 py-2 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 font-medium text-sm disabled:opacity-50 flex items-center gap-1"
                    >
                        <CheckIcon class="w-4 h-4" />
                        Accept
                    </button>
                    <button
                        @click="declineInvitation(invitation)"
                        :disabled="processing === `${invitation.type}-${invitation.id}`"
                        class="p-2 bg-white/20 rounded-lg hover:bg-white/30 disabled:opacity-50"
                        title="Decline invitation"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
