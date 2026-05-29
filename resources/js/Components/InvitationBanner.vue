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
import { useI18n } from '@/composables/useI18n';
import type { PendingInvitation } from '@/types';

const props = withDefaults(defineProps<{
    invitations?: PendingInvitation[];
}>(), {
    invitations: () => [],
});

const { formatMoney: formatCurrency } = useFormatters();
const { t } = useI18n();
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
    if (!confirm(t('invitation_banner.confirm_decline'))) {
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
                            {{ invitation.type === 'caretaker' ? t('invitation_banner.caretaker_heading') : t('invitation_banner.tenant_heading') }}
                        </p>
                        <p class="text-indigo-100 text-sm truncate">
                            <template v-if="invitation.type === 'caretaker'">
                                {{ t('invitation_banner.caretaker_body', { landlord: invitation.landlord_name, property: invitation.property_name }) }}
                            </template>
                            <template v-else>
                                {{ t('invitation_banner.tenant_body', { landlord: invitation.landlord_name, unit: invitation.unit_number, property: invitation.property_name }) }}
                            </template>
                        </p>
                        <div class="flex items-center gap-4 mt-1 text-indigo-200 text-xs">
                            <span v-if="invitation.type === 'tenant'">
                                {{ t('invitation_banner.rent_per_month', { amount: formatCurrency(invitation.rent_amount) }) }}
                            </span>
                            <span>{{ t('invitation_banner.expires', { date: invitation.expires_at }) }}</span>
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
                        {{ t('invitation_banner.accept') }}
                    </button>
                    <button
                        @click="declineInvitation(invitation)"
                        :disabled="processing === `${invitation.type}-${invitation.id}`"
                        class="p-2 bg-white/20 rounded-lg hover:bg-white/30 disabled:opacity-50"
                        :title="t('invitation_banner.decline_title')"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
