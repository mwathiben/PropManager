<script setup>
import { watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LiveAnnouncer from '@/Components/LiveAnnouncer.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAnnouncer } from '@/composables/useAnnouncer';

// Phase-23 A11Y-SR-1: guest pages (auth flows) also need flash
// messages announced — "verification link sent", "credentials do not
// match", etc. The skip-link is intentionally NOT here: guest pages
// are short single-form layouts with no repeated nav block to bypass
// (exemption documented in the accessibility conformance statement).
const page = usePage();
const { announce } = useAnnouncer();
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            return;
        }
        if (flash.success) {
            announce(flash.success, 'polite');
        }
        if (flash.message) {
            announce(flash.message, 'polite');
        }
        if (flash.error) {
            announce(flash.error, 'assertive');
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <LiveAnnouncer />

        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
