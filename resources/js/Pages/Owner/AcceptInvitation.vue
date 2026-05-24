<script setup lang="ts">
/**
 * Phase-102 OWNER-PORTAL: public deep-link page where an invited property owner sets
 * their password, then lands in their portal.
 */
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

interface InvitationInfo { email: string; token: string; landlord_name: string; owner_name: string | null; expires_at: string }

const props = defineProps<{ invitation: InvitationInfo }>();

const { t } = useI18n();

const page = usePage();
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error ?? '');

const form = useForm({
    name: props.invitation.owner_name ?? '',
    password: '',
    password_confirmation: '',
    mobile_number: '',
});

function submit(): void {
    form.post(route('owner-invite.accept', props.invitation.token));
}
</script>

<template>
    <GuestLayout>
        <Head :title="t('owners.accept.title')" />

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                <BuildingOffice2Icon class="h-6 w-6 text-indigo-600" />
            </div>
            <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.accept.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ t('owners.accept.invited_by') }} {{ invitation.landlord_name }}
            </p>
        </div>

        <div v-if="flashError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ flashError }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('owners.accept.name') }}</span>
                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('owners.accept.mobile') }}</span>
                <input v-model="form.mobile_number" type="text" class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('owners.accept.password') }}</span>
                <input v-model="form.password" type="password" required class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <span v-if="form.errors.password" class="mt-1 block text-xs text-red-600">{{ form.errors.password }}</span>
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">{{ t('owners.accept.password_confirm') }}</span>
                <input v-model="form.password_confirmation" type="password" required class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </label>

            <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ t('owners.accept.submit') }}
            </button>
        </form>
    </GuestLayout>
</template>
