<script setup lang="ts">
/**
 * Canonical "center" hero header — the single source of truth for the center
 * archetype's masthead (reference: Notifications/Index.vue "Notification Center").
 * A center is a top-level command surface: a gradient hero (icon + title +
 * subtitle + a primary action) above its content. Shared so every center
 * (Notification Center, Legal-Hold Command Center) reads identically.
 *
 * The #action slot holds the right-aligned primary action(s).
 */
import type { Component } from 'vue';

withDefaults(defineProps<{
    title: string;
    subtitle?: string;
    icon: Component;
}>(), {
    subtitle: '',
});
</script>

<template>
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 overflow-hidden shadow-xl sm:rounded-2xl">
        <div class="p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-xl">
                        <component :is="icon" class="w-8 h-8 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-1 text-indigo-100">{{ subtitle }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <slot name="action" />
                </div>
            </div>
        </div>
    </div>
</template>
