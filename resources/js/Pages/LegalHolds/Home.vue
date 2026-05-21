<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import LegalHoldHelpPanel from '@/Components/LegalHold/LegalHoldHelpPanel.vue';
import CenterHero from '@/Components/Center/CenterHero.vue';

interface MatterRollup {
    id: number;
    title: string;
    matter_reference: string | null;
    review_by: string | null;
    review_due: boolean;
    held_count: number;
}
interface RecentHold {
    id: number;
    subject_type: string;
    subject_id: number;
    reason: string;
    held_at: string | null;
    held_by: string | null;
}

const props = defineProps<{
    summary: { active_holds: number; active_matters: number; review_due: number; stale_holds: number };
    matters: MatterRollup[];
    recent: RecentHold[];
}>();

const { t } = useI18n();
const { formatDate, formatDateTime } = useFormatters();

const isEmpty = computed(() => props.summary.active_holds === 0 && props.summary.active_matters === 0);

const cards = computed(() => [
    { key: 'active_holds', value: props.summary.active_holds, tone: 'text-gray-900' },
    { key: 'active_matters', value: props.summary.active_matters, tone: 'text-gray-900' },
    { key: 'review_due', value: props.summary.review_due, tone: props.summary.review_due > 0 ? 'text-amber-700' : 'text-gray-900' },
    { key: 'stale_holds', value: props.summary.stale_holds, tone: props.summary.stale_holds > 0 ? 'text-rose-700' : 'text-gray-900' },
]);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('legal_holds.home.title')" />

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <CenterHero :title="t('legal_holds.home.title')" :icon="ScaleIcon">
                <template #action>
                    <Link :href="route('legal-holds.wizard')" class="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-all">
                        <PlusIcon class="w-5 h-5" /> {{ t('legal_holds.home.start_wizard') }}
                    </Link>
                </template>
            </CenterHero>

            <div class="flex flex-wrap gap-2 text-sm">
                <Link :href="route('legal-matters.index')" class="rounded-md bg-white px-3 py-2 font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ t('legal_holds.home.matters') }}
                </Link>
                <Link :href="route('legal-holds.list')" class="rounded-md bg-white px-3 py-2 font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ t('legal_holds.home.view_all_holds') }}
                </Link>
                <Link :href="route('legal-holds.settings')" class="rounded-md bg-white px-3 py-2 font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ t('legal_holds.home.settings') }}
                </Link>
            </div>

            <LegalHoldHelpPanel />

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-testid="hold-command-center">
                <div v-for="card in cards" :key="card.key" class="rounded-lg bg-white p-4 shadow">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ t(`legal_holds.home.card_${card.key}`) }}</p>
                    <p class="mt-1 text-2xl font-semibold" :class="card.tone">{{ card.value }}</p>
                </div>
            </div>

            <div v-if="isEmpty" class="rounded-lg bg-white p-8 text-center shadow" data-testid="hold-home-empty">
                <p class="text-sm text-gray-500">{{ t('legal_holds.home.empty') }}</p>
                <Link :href="route('legal-holds.wizard')" class="mt-3 inline-flex items-center gap-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
                    <PlusIcon class="h-4 w-4" /> {{ t('legal_holds.home.start_wizard') }}
                </Link>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg bg-white p-5 shadow">
                    <h2 class="mb-3 text-sm font-medium text-gray-700">{{ t('legal_holds.home.matters_rollup') }}</h2>
                    <ul v-if="matters.length" class="divide-y divide-gray-100">
                        <li v-for="m in matters" :key="m.id" class="flex items-center justify-between py-2 text-sm">
                            <Link :href="route('legal-matters.show', m.id)" class="font-medium text-indigo-600 hover:underline">
                                {{ m.title }}
                                <span v-if="m.matter_reference" class="text-xs text-gray-400">· {{ m.matter_reference }}</span>
                            </Link>
                            <span class="flex items-center gap-2">
                                <span v-if="m.review_due" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                    {{ formatDate(m.review_by, 'short') }}
                                </span>
                                <span class="text-gray-500">{{ m.held_count }}</span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">{{ t('legal_holds.matters.empty') }}</p>
                </section>

                <section class="rounded-lg bg-white p-5 shadow">
                    <h2 class="mb-3 text-sm font-medium text-gray-700">{{ t('legal_holds.home.recent_activity') }}</h2>
                    <ul v-if="recent.length" class="divide-y divide-gray-100">
                        <li v-for="h in recent" :key="h.id" class="py-2 text-sm">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ h.subject_type }}</span>
                            #{{ h.subject_id }}
                            <span class="text-gray-400">· {{ h.held_at ? formatDateTime(h.held_at) : '' }}</span>
                            <p class="truncate text-xs text-gray-500">{{ h.reason }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">{{ t('legal_holds.home.no_activity') }}</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
