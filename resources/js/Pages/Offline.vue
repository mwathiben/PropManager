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
import { useI18n } from '@/composables/useI18n';
import { WifiIcon } from '@heroicons/vue/24/outline';

const { user } = useAuth();
const { t } = useI18n();
const layout = computed(() => (user.value ? AuthenticatedLayout : 'div'));
const retrying = ref(false);

function retry() {
    retrying.value = true;
    window.location.reload();
}
</script>

<template>
    <Head :title="t('offline.page_title')" />

    <component :is="layout">
        <div class="flex min-h-[60vh] items-center justify-center px-4 py-12" data-testid="offline-page">
            <div class="max-w-md text-center">
                <WifiIcon class="mx-auto h-16 w-16 text-gray-400" aria-hidden="true" />
                <p class="mt-4 text-sm font-semibold text-indigo-600">{{ t('offline.eyebrow') }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">
                    {{ t('offline.heading') }}
                </h1>
                <p class="mt-3 text-sm text-gray-600">
                    {{ t('offline.body') }}
                </p>
                <p class="mt-2 text-xs text-gray-500">
                    {{ t('offline.sync_note') }}
                </p>
                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                    <button
                        type="button"
                        :disabled="retrying"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
                        @click="retry"
                    >
                        {{ retrying ? t('offline.retrying') : t('offline.try_again') }}
                    </button>
                    <Link
                        :href="user ? '/dashboard' : '/'"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ user ? t('offline.back_to_dashboard') : t('offline.back_to_home') }}
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>
