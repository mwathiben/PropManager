<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    danger: {
        type: Boolean,
        default: false,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        :class="[
            'flex items-center gap-3 w-full mx-2 px-3 py-2.5 text-sm rounded-lg transition-colors',
            danger
                ? 'text-red-600 hover:bg-red-50'
                : 'text-gray-700 hover:bg-gray-50'
        ]"
        style="width: calc(100% - 1rem);"
    >
        <slot />
    </Link>
</template>
