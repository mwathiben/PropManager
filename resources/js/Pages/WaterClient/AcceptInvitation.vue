<script setup lang="ts">
/**
 * Phase-95 WATER-CLIENT-ONBOARDING: public deep-link page where an invited water
 * client creates their account, then lands in onboarding.
 */
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon } from '@heroicons/vue/24/outline';

interface InvitationInfo { email: string; token: string; landlord_name: string; identifier: string | null; expires_at: string }

const props = defineProps<{ invitation: InvitationInfo }>();

const { t } = useI18n();

const page = usePage();
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error ?? '');

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
    mobile_number: '',
});

function submit(): void {
    form.post(route('water-invite.accept', props.invitation.token));
}
</script>

<template>
    <GuestLayout>
        <Head :title="t('water.clients.accept_title')" />

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100">
                <BeakerIcon class="h-6 w-6 text-cyan-600" />
            </div>
            <h1 class="text-lg font-semibold text-gray-900">{{ t('water.clients.accept_title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('water.clients.accept_invited_by') }} {{ invitation.landlord_name }}
                <template v-if="invitation.identifier"> · {{ invitation.identifier }}</template>
            </p>
        </div>

        <div v-if="flashError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ flashError }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_name') }}</span>
                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
                <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_mobile') }}</span>
                <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_password') }}</span>
                <input v-model="form.password" type="password" required class="mt-1 w-full rounded-md border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
                <span v-if="form.errors.password" class="mt-1 block text-xs text-red-600">{{ form.errors.password }}</span>
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('water.clients.accept_password_confirm') }}</span>
                <input v-model="form.password_confirmation" type="password" required class="mt-1 w-full rounded-md border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </label>

            <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-cyan-600 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">
                {{ t('water.clients.accept_submit') }}
            </button>
        </form>
    </GuestLayout>
</template>
