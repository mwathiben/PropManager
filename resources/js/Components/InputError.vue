<script setup lang="ts">
/**
 * Phase-20 FRONT-UX-3: enhanced form-field error component.
 * Pre-Phase-20: bare <p class="text-sm text-red-600"> — color-only
 * cue + borderline WCAG-AA contrast at 14px. Post-Phase-20: icon
 * affordance for color-blind users + role="alert" for screen
 * readers + text-red-700 for AA contrast at 14px (5.0:1 vs the
 * white-ish form background; previously 4.5:1 borderline).
 *
 * Phase-23 A11Y-FORM-1: accepts an `id` so the input it belongs to
 * can point at it via aria-describedby (WCAG 1.3.1 / 3.3.1). The
 * convention is `${inputId}-error`.
 */
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline';

interface Props {
    message?: string;
    id?: string;
}

defineProps<Props>();
</script>

<template>
    <div v-show="message" :id="id" class="mt-1 flex items-start gap-1.5" role="alert">
        <ExclamationCircleIcon class="h-4 w-4 text-red-700 flex-shrink-0 mt-0.5" aria-hidden="true" />
        <p class="text-sm text-red-700">
            {{ message }}
        </p>
    </div>
</template>
