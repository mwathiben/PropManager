<script setup lang="ts">
/**
 * Phase-21 DEFER-AUTHZ-4 (closes Phase-20 AUTHZ-FRONT-7 deferral):
 * dedicated 403 page. Reached two ways —
 *   1. server-side: bootstrap/app.php renders this for a real HTTP 403
 *      on an HTML request (e.g. /admin/* hit by a non-admin).
 *   2. client-side: the router.beforeEach guard in app.js redirects
 *      here when the shared abilities map predicts the visit will be
 *      rejected, so the user never issues the doomed request.
 * Renders inside the authenticated chrome (header + sidebar) when a
 * user is present, otherwise a standalone centred card.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { useI18n } from '@/composables/useI18n';
import { ShieldExclamationIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { user } = useAuth();
const layout = computed(() => (user.value ? AuthenticatedLayout : 'div'));
</script>

<template>
    <Head :title="t('errors_403.page_title')" />

    <component :is="layout">
        <div class="flex min-h-[60vh] items-center justify-center px-4 py-12">
            <div class="max-w-md text-center">
                <ShieldExclamationIcon class="mx-auto h-16 w-16 text-red-400" aria-hidden="true" />
                <p class="mt-4 text-sm font-semibold text-red-600">{{ t('errors_403.error_label') }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">{{ t('errors_403.heading') }}</h1>
                <p class="mt-3 text-sm text-gray-600">
                    {{ t('errors_403.body') }}
                </p>
                <div class="mt-6">
                    <Link
                        :href="user ? '/dashboard' : '/'"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ user ? t('errors_403.back_to_dashboard') : t('errors_403.back_to_home') }}
                    </Link>
                </div>
            </div>
        </div>
    </component>
</template>
