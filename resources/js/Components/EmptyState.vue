<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { FolderOpenIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

type Size = 'sm' | 'md' | 'lg';

interface Props {
    icon?: Component;
    title?: string;
    description?: string | null;
    actionLabel?: string | null;
    actionHref?: string | null;
    size?: Size;
    // Phase-31 ONB-EMPTY-1: optional onboarding-checklist mode.
    showChecklist?: boolean;
    // Phase-31 ONB-EMPTY-2: optional embedded video walkthrough.
    videoUrl?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    icon: () => FolderOpenIcon,
    title: '',
    description: null,
    actionLabel: null,
    actionHref: null,
    size: 'md',
    showChecklist: false,
    videoUrl: null,
});

const resolvedTitle = computed(() => props.title || t('empty.default_title'));

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
            {{ resolvedTitle }}
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
        <MilestoneChecklist v-if="showChecklist" class="mt-6" />
        <VideoSlot v-if="videoUrl" :url="videoUrl" class="mt-6" />
        <slot />
    </div>
</template>

<script lang="ts">
import MilestoneChecklist from '@/Components/Onboarding/MilestoneChecklist.vue';
import VideoSlot from '@/Components/Onboarding/VideoSlot.vue';
</script>
