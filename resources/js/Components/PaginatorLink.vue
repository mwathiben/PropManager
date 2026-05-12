<script setup lang="ts">
/**
 * Phase-15 FRONT-4: replaces `v-html="link.label"` across paginator
 * usages. Laravel's paginator emits labels like `&laquo; Previous` and
 * `Next &raquo;` which require HTML-entity decoding. v-html worked,
 * but the pattern desensitises maintainers to v-html safety and the
 * Laravel team has historically changed the label format. This
 * component decodes the entity and renders as text + an icon.
 */
import { computed } from 'vue';
import { ChevronDoubleLeftIcon, ChevronDoubleRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    label: string;
}>();

// Decode HTML entities using a textarea round-trip. Browser-native,
// no library, no XSS surface (we're parsing into textContent, never
// innerHTML).
function decode(raw: string): string {
    if (typeof document === 'undefined') {
        return raw;
    }
    const t = document.createElement('textarea');
    t.innerHTML = raw;
    return t.value;
}

const decoded = computed(() => decode(props.label).trim());

const variant = computed(() => {
    const d = decoded.value;
    if (d.startsWith('«') || /Previous/i.test(d)) return 'prev';
    if (d.endsWith('»') || /Next/i.test(d)) return 'next';
    return 'page';
});

const cleanText = computed(() => {
    if (variant.value === 'prev') return decoded.value.replace(/^«\s*/, '');
    if (variant.value === 'next') return decoded.value.replace(/\s*»$/, '');
    return decoded.value;
});
</script>

<template>
    <span class="inline-flex items-center gap-1" :aria-label="cleanText">
        <ChevronDoubleLeftIcon v-if="variant === 'prev'" class="h-3.5 w-3.5" aria-hidden="true" />
        {{ cleanText }}
        <ChevronDoubleRightIcon v-if="variant === 'next'" class="h-3.5 w-3.5" aria-hidden="true" />
    </span>
</template>
