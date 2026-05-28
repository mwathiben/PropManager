<script setup lang="ts">
/**
 * Phase-21 DEFER-FRONT-5 (closes Phase-20 FRONT-UX-6 deferral):
 * canonical icon-only button. Pre-Phase-21, icon-only actions
 * (TrashIcon / PencilIcon / EllipsisVerticalIcon wrapped in a bare
 * <button>) repeated manual aria-label + padding + hover/focus-ring
 * styling at every call site — easy to forget the aria-label
 * (Phase-20 FRONT-UX-5 had to audit for missing ones).
 *
 * IconButton centralises the contract:
 *   - ariaLabel is REQUIRED (a11y — icon-only buttons have no text)
 *   - size variants normalise the padding + icon box
 *   - tone variants normalise hover colour (default vs danger)
 *   - the `as` prop supports rendering as <button>, <a>, or an
 *     Inertia <Link> without duplicating the styling
 *
 * Usage:
 *   <IconButton :icon="TrashIcon" aria-label="Delete tenant"
 *     tone="danger" @click="deleteTenant(tenant.id)" />
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

interface Props {
    icon: Component;
    ariaLabel: string;
    size?: 'sm' | 'md' | 'lg';
    tone?: 'default' | 'danger' | 'primary';
    as?: 'button' | 'a' | 'link';
    type?: 'button' | 'submit';
    disabled?: boolean;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    tone: 'default',
    as: 'button',
    type: 'button',
    disabled: false,
});

const sizeClasses = {
    sm: 'p-1',
    md: 'p-1.5',
    lg: 'p-2',
};

const iconSizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6',
};

const toneClasses = {
    default: 'text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:ring-gray-400',
    danger: 'text-gray-400 hover:text-red-600 hover:bg-red-50 focus:ring-red-500',
    primary: 'text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500',
};

const elementTag = computed(() => {
    if (props.as === 'link') return Link;
    if (props.as === 'a') return 'a';
    return 'button';
});

const dynamicAttrs = computed(() => {
    if (props.as === 'button') {
        return { type: props.type, disabled: props.disabled };
    }
    if (props.as === 'link' || props.as === 'a') {
        return { href: props.href };
    }
    return {};
});
</script>

<template>
    <component
        :is="elementTag"
        v-bind="dynamicAttrs"
        :aria-label="ariaLabel"
        :title="ariaLabel"
        :class="['inline-flex items-center justify-center rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1', sizeClasses[size], toneClasses[tone], disabled ? 'opacity-50 cursor-not-allowed' : '']"
    >
        <component :is="icon" :class="iconSizeClasses[size]" aria-hidden="true" />
    </component>
</template>
