<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { FolderOpenIcon } from '@heroicons/vue/24/outline';

type Size = 'sm' | 'md' | 'lg';

interface Props {
    icon?: Component;
    title?: string;
    description?: string | null;
    actionLabel?: string | null;
    actionHref?: string | null;
    size?: Size;
}

const props = withDefaults(defineProps<Props>(), {
    icon: () => FolderOpenIcon,
    title: 'No data found',
    description: null,
    actionLabel: null,
    actionHref: null,
    size: 'md',
});

const emit = defineEmits<{
    action: [];
}>();

const sizeClasses = computed(() => {
    const sizes = {
        sm: {
            wrapper: 'py-6',
            icon: 'h-8 w-8',
            title: 'text-sm',
            description: 'text-xs',
        },
        md: {
            wrapper: 'py-12',
            icon: 'h-12 w-12',
            title: 'text-base',
            description: 'text-sm',
        },
        lg: {
            wrapper: 'py-16',
            icon: 'h-16 w-16',
            title: 'text-lg',
            description: 'text-base',
        },
    };
    return sizes[props.size];
});
</script>

<template>
    <div :class="['text-center', sizeClasses.wrapper]">
        <component
            :is="icon"
            :class="['mx-auto text-gray-400', sizeClasses.icon]"
        />
        <h3 :class="['mt-3 font-medium text-gray-900', sizeClasses.title]">
            {{ title }}
        </h3>
        <p v-if="description" :class="['mt-1 text-gray-500', sizeClasses.description]">
            {{ description }}
        </p>
        <div v-if="actionLabel" class="mt-4">
            <Link
                v-if="actionHref"
                :href="actionHref"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
            >
                {{ actionLabel }}
            </Link>
            <button
                v-else
                @click="emit('action')"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
            >
                {{ actionLabel }}
            </button>
        </div>
        <slot />
    </div>
</template>
