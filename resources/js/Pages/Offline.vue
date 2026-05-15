<script setup lang="ts">
/**
 * Phase-26 PWA-SHELL-2: branded offline page. Served by the service
 * worker's NavigationRoute fallback (resources/js/sw-merged.ts) when
 * a navigation request can't reach the network. Also reachable
 * directly at /offline.
 *
 * Mirrors the Errors/404 layout pattern: AuthenticatedLayout when a
 * user is in cache, plain <div> otherwise.
 */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { WifiIcon } from '@heroicons/vue/24/outline';

const { user } = useAuth();
const layout = computed(() => (user.value ? AuthenticatedLayout : 'div'));
const retrying = ref(false);

function retry() {
    retrying.value = true;
    window.location.reload();
}
</script>

<template>
    <Head title="You're offline" />

    <component :is="layout">
        <div class="flex min-h-[60vh] items-center justify-center px-4 py-12">
            <div class="max-w-md text-center">
                <WifiIcon class="mx-auto h-16 w-16 text-gray-400" aria-hidden="true" />
                <p class="mt-4 text-sm font-semibold text-indigo-600">No connection</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">
                    You're offline
                </h1>
                <p class="mt-3 text-sm text-gray-600">
                    PropManager couldn't reach the server. Recently visited pages still work
                    from cache — try the dashboard or your last lease.
                </p>
                <p class="mt-2 text-xs text-gray-500">
                    Any actions you queued (invoices, payments) will sync automatically when
                    your connection returns.
                </p>
                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                    <button
                        type="button"
                        :disabled="retrying"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
                        @click="retry"
                    >
                        {{ retrying ? 'Retrying…' : 'Try again' }}
                    </button>
                    <Link
                        :href="user ? '/dashboard' : '/'"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ user ? 'Back to dashboard' : 'Back to home' }}
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>
