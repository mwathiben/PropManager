<script setup lang="ts">
/**
 * Phase-21 DEFER-AUTHZ-4 (closes Phase-20 AUTHZ-FRONT-7 deferral):
 * dedicated 404 page rendered by bootstrap/app.php for a real HTTP 404
 * on an HTML request. Renders inside the authenticated chrome (header +
 * sidebar) when a user is present, otherwise a standalone centred card.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const { user } = useAuth();
const layout = computed(() => (user.value ? AuthenticatedLayout : 'div'));
</script>

<template>
    <Head title="Page not found" />

    <component :is="layout">
        <div class="flex min-h-[60vh] items-center justify-center px-4 py-12">
            <div class="max-w-md text-center">
                <MagnifyingGlassIcon class="mx-auto h-16 w-16 text-gray-400" aria-hidden="true" />
                <p class="mt-4 text-sm font-semibold text-indigo-600">Error 404</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">Page not found</h1>
                <p class="mt-3 text-sm text-gray-600">
                    The page you're looking for doesn't exist or may have been moved.
                </p>
                <div class="mt-6">
                    <Link
                        :href="user ? '/dashboard' : '/'"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ user ? 'Back to dashboard' : 'Back to home' }}
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>
