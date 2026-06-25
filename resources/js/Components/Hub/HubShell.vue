<script setup lang="ts">
/**
 * Canonical hub scaffold — the single source of truth for the "hub" archetype
 * (reference: Finances/Index.vue). Every tab-shell hub (Operations, Maintenance,
 * Water, Archive, Tenants) renders through this so the chrome — icon header,
 * breadcrumb, white card, tab bar, hover-prefetch, loading state — is identical.
 *
 * The hub passes its tab list + accent + the active tab's component (in the
 * default slot). Navigation + prefetch are handled here via ?tab=.
 *
 * Tailwind purges dynamic class strings, so accents resolve through a static map.
 */
import { computed, type Component } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface HubTab {
    id: string;
    name: string;
    icon: Component;
    badge?: number | null;
}

interface BreadcrumbItem {
    label: string;
    href?: string;
}

const props = withDefaults(defineProps<{
    title: string;
    subtitle?: string;
    icon: Component;
    routeName: string;
    tabs: HubTab[];
    currentTab: string;
    accent?: string;
    breadcrumb?: BreadcrumbItem[] | null;
}>(), {
    subtitle: '',
    accent: 'emerald',
    breadcrumb: null,
});

const ACCENTS: Record<string, { iconBox: string; iconText: string; activeBorder: string; activeText: string; activeIcon: string; badge: string }> = {
    emerald: { iconBox: 'bg-emerald-100', iconText: 'text-emerald-600', activeBorder: 'border-emerald-500', activeText: 'text-emerald-600', activeIcon: 'text-emerald-500', badge: 'bg-emerald-100 text-emerald-800' },
    orange: { iconBox: 'bg-orange-100', iconText: 'text-orange-600', activeBorder: 'border-orange-500', activeText: 'text-orange-600', activeIcon: 'text-orange-500', badge: 'bg-orange-100 text-orange-800' },
    blue: { iconBox: 'bg-blue-100', iconText: 'text-blue-600', activeBorder: 'border-blue-500', activeText: 'text-blue-600', activeIcon: 'text-blue-500', badge: 'bg-blue-100 text-blue-800' },
    cyan: { iconBox: 'bg-cyan-100', iconText: 'text-cyan-600', activeBorder: 'border-cyan-500', activeText: 'text-cyan-600', activeIcon: 'text-cyan-500', badge: 'bg-cyan-100 text-cyan-800' },
    gray: { iconBox: 'bg-gray-100', iconText: 'text-gray-600', activeBorder: 'border-gray-800', activeText: 'text-gray-900', activeIcon: 'text-gray-700', badge: 'bg-gray-100 text-gray-700' },
    purple: { iconBox: 'bg-purple-100', iconText: 'text-purple-600', activeBorder: 'border-purple-500', activeText: 'text-purple-600', activeIcon: 'text-purple-500', badge: 'bg-purple-100 text-purple-800' },
    indigo: { iconBox: 'bg-indigo-100', iconText: 'text-indigo-600', activeBorder: 'border-indigo-500', activeText: 'text-indigo-600', activeIcon: 'text-indigo-500', badge: 'bg-indigo-100 text-indigo-800' },
};

const accent = computed(() => ACCENTS[props.accent] ?? ACCENTS.emerald);

const currentTabName = computed(() => props.tabs.find((t) => t.id === props.currentTab)?.name ?? props.title);
const pageTitle = computed(() => `${props.title} - ${currentTabName.value}`);

const breadcrumbItems = computed<BreadcrumbItem[]>(() =>
    props.breadcrumb ?? [
        { label: props.title, href: route(props.routeName) },
        { label: currentTabName.value },
    ],
);

const navigateToTab = (tab: HubTab): void => {
    router.get(route(props.routeName, { tab: tab.id }), {}, { preserveState: true, preserveScroll: true });
};

const prefetchTab = (tab: HubTab): void => {
    if (tab.id === props.currentTab) return;
    router.prefetch(route(props.routeName, { tab: tab.id }), { method: 'get' }, { cacheFor: '1m' });
};
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg" :class="accent.iconBox">
                        <component :is="icon" class="w-6 h-6" :class="accent.iconText" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ title }}</h1>
                        <p v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</p>
                    </div>
                </div>
                <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                @click="navigateToTab(tab)"
                                @mouseenter="prefetchTab(tab)"
                                @focus="prefetchTab(tab)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    currentTab === tab.id
                                        ? `${accent.activeBorder} ${accent.activeText}`
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                ]"
                                :aria-current="currentTab === tab.id ? 'page' : undefined"
                            >
                                <component :is="tab.icon" class="w-5 h-5" :class="currentTab === tab.id ? accent.activeIcon : 'text-gray-400'" />
                                {{ tab.name }}
                                <span
                                    v-if="tab.badge && tab.badge > 0"
                                    class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="accent.badge"
                                >
                                    {{ tab.badge > 99 ? '99+' : tab.badge }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
