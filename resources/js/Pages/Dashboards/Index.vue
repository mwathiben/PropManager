<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Squares2X2Icon, PlusIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

interface DashboardRow {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    is_default: boolean;
    card_count: number;
}

defineProps<{ dashboards: DashboardRow[] }>();

const { t } = useI18n();

function setDefault(id: number): void {
    router.post(route('dashboards.set-default', id), {}, { preserveScroll: true });
}

function destroy(id: number): void {
    if (confirm(t('reports.dashboards.delete_confirm'))) {
        router.delete(route('dashboards.destroy', id), { preserveScroll: true });
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('reports.dashboards.title')" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="flex items-center gap-2 text-2xl font-semibold text-gray-900">
                    <Squares2X2Icon class="h-6 w-6 text-gray-500" />
                    {{ t('reports.dashboards.title') }}
                </h1>
                <Link :href="route('dashboards.create')" class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white">
                    <PlusIcon class="h-4 w-4" /> {{ t('reports.dashboards.new') }}
                </Link>
            </header>

            <div v-if="dashboards.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow" data-testid="dashboards-empty">
                {{ t('reports.dashboards.empty') }}
            </div>

            <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="d in dashboards" :key="d.id" class="rounded-lg bg-white p-4 shadow" data-testid="dashboard-row">
                    <div class="flex items-start justify-between gap-2">
                        <Link :href="route('dashboards.show', d.slug)" class="font-medium text-indigo-600 hover:underline">{{ d.name }}</Link>
                        <span v-if="d.is_default" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">{{ t('reports.dashboards.default') }}</span>
                    </div>
                    <p v-if="d.description" class="mt-1 text-xs text-gray-500">{{ d.description }}</p>
                    <p class="mt-2 text-xs text-gray-400">{{ d.card_count }} {{ t('reports.dashboards.cards') }}</p>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs">
                        <Link :href="route('dashboards.edit', d.id)" class="text-indigo-600 hover:underline">{{ t('reports.dashboards.edit') }}</Link>
                        <button v-if="!d.is_default" type="button" class="text-gray-600 hover:underline" @click="setDefault(d.id)">
                            {{ t('reports.dashboards.set_default') }}
                        </button>
                        <button type="button" class="text-rose-600 hover:underline" @click="destroy(d.id)">
                            {{ t('reports.dashboards.delete') }}
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
