<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types/global';

interface Props {
    links?: PaginationLink[];
    wrapperClass?: string;
    color?: 'emerald' | 'indigo';
}

const props = withDefaults(defineProps<Props>(), {
    links: () => [],
    wrapperClass: '',
    color: 'emerald',
});

const colorClasses = {
    emerald: {
        active: 'bg-emerald-600 text-white',
        inactive: 'text-gray-600 hover:bg-gray-100',
    },
    indigo: {
        active: 'bg-indigo-600 text-white',
        inactive: 'text-gray-700 hover:bg-gray-50',
    },
};
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
                            ? colorClasses[color].active
                            : colorClasses[color].inactive
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
