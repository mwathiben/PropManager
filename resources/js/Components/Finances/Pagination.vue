<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    wrapperClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div v-if="links?.length > 3" :class="['flex justify-center', wrapperClass]">
        <nav class="flex items-center gap-1">
            <template v-for="link in links" :key="link.label">
                <button
                    v-if="link.url"
                    @click="router.visit(link.url)"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg transition-colors',
                        link.active
                            ? 'bg-emerald-600 text-white'
                            : 'text-gray-600 hover:bg-gray-100'
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="px-3 py-1.5 text-sm text-gray-400"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
