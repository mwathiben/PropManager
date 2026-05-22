<script setup lang="ts">
/**
 * Shared hub homepage — the landing/overview every tab-shell hub opens on (the
 * way Finances/Index opens on its Overview tab). Renders a grid of at-a-glance
 * stat cards plus quick-link cards into the hub's other tabs, so a hub no longer
 * dumps the user straight into a working first tab. Each hub feeds its own
 * `stats` (from the controller) and `links` (derived from its tab list).
 */
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';

interface StatCard {
    label: string;
    value: string | number;
    hint?: string;
    tone?: 'default' | 'emerald' | 'amber' | 'red' | 'blue' | 'indigo';
}

interface LinkCard {
    label: string;
    description?: string;
    href: string;
    icon?: Component;
    badge?: number | null;
}

withDefaults(defineProps<{
    stats?: StatCard[];
    links?: LinkCard[];
    emptyText?: string;
}>(), {
    stats: () => [],
    links: () => [],
    emptyText: '',
});

const toneClasses: Record<string, string> = {
    default: 'text-gray-900',
    emerald: 'text-emerald-600',
    amber: 'text-amber-600',
    red: 'text-red-600',
    blue: 'text-blue-600',
    indigo: 'text-indigo-600',
};
</script>

<template>
    <div class="space-y-8">
        <!-- At-a-glance stats -->
        <div v-if="stats.length" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="bg-gray-50 rounded-xl border border-gray-200 p-4"
            >
                <div class="text-sm text-gray-500">{{ stat.label }}</div>
                <div class="mt-1 text-2xl font-semibold" :class="toneClasses[stat.tone || 'default']">{{ stat.value }}</div>
                <div v-if="stat.hint" class="mt-1 text-xs text-gray-400">{{ stat.hint }}</div>
            </div>
        </div>

        <!-- Quick links into the hub's sections -->
        <div v-if="links.length">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="group flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition"
                >
                    <div v-if="link.icon" class="p-2 rounded-lg bg-gray-100 text-gray-600 group-hover:bg-gray-200">
                        <component :is="link.icon" class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900">{{ link.label }}</span>
                            <span
                                v-if="link.badge && link.badge > 0"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-900 text-white"
                            >{{ link.badge > 99 ? '99+' : link.badge }}</span>
                        </div>
                        <p v-if="link.description" class="text-sm text-gray-500 truncate">{{ link.description }}</p>
                    </div>
                    <ArrowRightIcon class="w-4 h-4 text-gray-300 group-hover:text-gray-500" />
                </Link>
            </div>
        </div>

        <p v-if="!stats.length && !links.length && emptyText" class="text-sm text-gray-500">{{ emptyText }}</p>
    </div>
</template>
