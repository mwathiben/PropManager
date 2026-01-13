<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
});
</script>

<template>
    <div class="flex justify-center gap-1">
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url || '#'"
            :class="[
                'px-3 py-1 rounded border text-sm',
                link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                !link.url ? 'opacity-50 cursor-not-allowed' : ''
            ]"
            :disabled="!link.url"
            v-html="link.label"
            preserve-scroll
        />
    </div>
</template>
