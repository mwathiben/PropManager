<script setup lang="ts">
/**
 * Phase-23 A11Y-SR-1: the visually-hidden ARIA live region pair
 * (WCAG 4.1.3). Mounted ONCE per layout. The polite region is for
 * routine status (a saved record); the assertive region carries
 * role="alert" for failures that should interrupt. Both are
 * aria-atomic so the whole message is read, not just the diff.
 *
 * Message content is owned by useAnnouncer() — call announce() from
 * anywhere; this component just renders.
 */
import { useAnnouncer } from '@/composables/useAnnouncer';

const { politeMessage, assertiveMessage } = useAnnouncer();
</script>

<template>
    <div class="sr-only">
        <div aria-live="polite" aria-atomic="true">{{ politeMessage }}</div>
        <div aria-live="assertive" aria-atomic="true" role="alert">{{ assertiveMessage }}</div>
    </div>
</template>
