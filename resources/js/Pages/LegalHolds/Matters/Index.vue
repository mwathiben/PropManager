<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

interface Matter {
    id: number;
    title: string;
    matter_reference: string | null;
    situation_type: string | null;
    status: 'open' | 'closed';
    review_by: string | null;
    review_due: boolean;
    held_count: number;
    created_at: string | null;
}

const props = defineProps<{
    matters: { data: Matter[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { status: string };
}>();

const { t } = useI18n();
const { formatDate } = useFormatters();

function setStatus(status: string): void {
    router.get(route('legal-matters.index'), { status }, { preserveState: true, replace: true });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('legal_holds.matters.title')" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-3">
                <ScaleIcon class="h-6 w-6 text-gray-500" />
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('legal_holds.matters.title') }}</h1>
            </header>

            <div class="flex gap-2" data-testid="matters-index">
                <button
                    v-for="tab in ['open', 'closed']"
                    :key="tab"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="filters.status === tab ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                    @click="setStatus(tab)"
                >
                    {{ t(`legal_holds.matters.tab_${tab}`) }}
                </button>
            </div>

            <div v-if="matters.data.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow" data-testid="matters-empty">
                {{ t('legal_holds.matters.empty') }}
            </div>

            <table v-else class="min-w-full overflow-hidden rounded-lg bg-white shadow">
                <thead class="bg-gray-50 text-start text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_title') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_reference') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_held') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_review') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <tr v-for="m in matters.data" :key="m.id">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ m.title }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ m.matter_reference || '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ m.held_count }}</td>
                        <td class="px-4 py-3">
                            <span v-if="!m.review_by" class="text-gray-400">—</span>
                            <span
                                v-else
                                class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                :class="m.review_due ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ formatDate(m.review_by, 'short') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <Link
                                :href="route('legal-matters.show', m.id)"
                                class="text-sm font-medium text-indigo-600 hover:underline"
                            >
                                {{ t('legal_holds.matters.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <nav v-if="matters.links.length > 3" class="flex flex-wrap gap-1">
                <Link
                    v-for="link in matters.links"
                    :key="link.label"
                    :href="link.url || ''"
                    class="rounded px-3 py-1 text-sm"
                    :class="[link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600', !link.url && 'pointer-events-none opacity-50']"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
