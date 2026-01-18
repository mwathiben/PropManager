<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    reason: 'not_found' | 'revoked' | 'expired' | 'paid' | 'unavailable';
    message: string;
}>();

const reasonIcons: Record<string, string> = {
    not_found: 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    revoked: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    expired: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    paid: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    unavailable: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
};

const reasonColors: Record<string, string> = {
    not_found: 'text-gray-400',
    revoked: 'text-red-400',
    expired: 'text-amber-400',
    paid: 'text-emerald-400',
    unavailable: 'text-gray-400',
};
</script>

<template>
    <GuestLayout>
        <Head title="Payment Link" />

        <div class="max-w-md mx-auto text-center py-8">
            <div class="mb-6">
                <svg
                    class="mx-auto h-16 w-16"
                    :class="reasonColors[reason]"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="reasonIcons[reason]" />
                </svg>
            </div>

            <h1 class="text-2xl font-semibold text-gray-900 mb-4">
                {{ reason === 'paid' ? 'Invoice Already Paid' : 'Link Unavailable' }}
            </h1>

            <p class="text-gray-600 mb-8">
                {{ message }}
            </p>

            <div class="space-y-3">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center justify-center w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                >
                    Sign in to your account
                </Link>
                <p class="text-sm text-gray-500">
                    Contact your landlord if you believe this is an error.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
