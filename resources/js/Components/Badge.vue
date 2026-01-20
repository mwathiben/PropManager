<script setup lang="ts">
import { computed } from 'vue';

type BadgeColor = 'gray' | 'green' | 'yellow' | 'red' | 'blue' | 'purple' | 'orange' | 'indigo' | 'cyan' | 'pink';
type BadgeSize = 'sm' | 'md' | 'lg';

interface Props {
    color?: BadgeColor;
    colorClasses?: string;
    size?: BadgeSize;
    label?: string;
    showDot?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    color: 'gray',
    size: 'md',
    showDot: false,
});

const colorMap: Record<BadgeColor, string> = {
    gray: 'bg-gray-100 text-gray-800',
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    red: 'bg-red-100 text-red-800',
    blue: 'bg-blue-100 text-blue-800',
    purple: 'bg-purple-100 text-purple-800',
    orange: 'bg-orange-100 text-orange-800',
    indigo: 'bg-indigo-100 text-indigo-800',
    cyan: 'bg-cyan-100 text-cyan-800',
    pink: 'bg-pink-100 text-pink-800',
};

const dotColorMap: Record<BadgeColor, string> = {
    gray: 'bg-gray-400',
    green: 'bg-green-400',
    yellow: 'bg-yellow-400',
    red: 'bg-red-400',
    blue: 'bg-blue-400',
    purple: 'bg-purple-400',
    orange: 'bg-orange-400',
    indigo: 'bg-indigo-400',
    cyan: 'bg-cyan-400',
    pink: 'bg-pink-400',
};

const sizeMap: Record<BadgeSize, string> = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2.5 py-0.5 text-xs',
    lg: 'px-2.5 py-1 text-sm',
};

const badgeClasses = computed(() => {
    const classes = props.colorClasses || colorMap[props.color];
    return [
        'inline-flex items-center gap-1 rounded-full font-medium',
        classes,
        sizeMap[props.size],
    ];
});

const dotClasses = computed(() => ['h-1.5 w-1.5 rounded-full', dotColorMap[props.color]]);
</script>

<template>
    <span :class="badgeClasses">
        <span v-if="showDot" :class="dotClasses" />
        <slot name="icon" />
        <slot>{{ label }}</slot>
    </span>
</template>
