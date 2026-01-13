<script setup>
import { Link } from '@inertiajs/vue3';
import { HomeIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    items: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <nav class="flex items-center space-x-2 text-sm">
        <Link :href="route('dashboard')" class="text-gray-400 hover:text-gray-600 transition-colors">
            <HomeIcon class="h-4 w-4" />
        </Link>
        <template v-for="(item, index) in items" :key="index">
            <ChevronRightIcon class="h-4 w-4 text-gray-300 flex-shrink-0" />
            <Link
                v-if="item.href"
                :href="item.href"
                class="text-gray-500 hover:text-gray-700 transition-colors truncate max-w-[200px]"
            >
                {{ item.label }}
            </Link>
            <span v-else class="text-gray-900 font-medium truncate max-w-[200px]">
                {{ item.label }}
            </span>
        </template>
    </nav>
</template>
