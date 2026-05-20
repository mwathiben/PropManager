<script setup lang="ts">
/**
 * Phase-66 REFERRAL-LEADERBOARD-3: landlord-facing, always-anonymised
 * referral leaderboard. Other referrers appear as "Referrer #rank";
 * only the viewer's own row carries a name.
 */
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

interface Entry {
    rank: number;
    score: number;
    attributed: number;
    rewarded: number;
    is_self: boolean;
    name: string | null;
}

interface Leaderboard {
    entries: Entry[];
    viewer: Entry | null;
    total_ranked: number;
}

const props = defineProps<{
    leaderboard: Leaderboard;
    opted_out: boolean;
}>();

const { t } = useI18n();

const medal = (rank: number): string =>
    ({ 1: '🥇', 2: '🥈', 3: '🥉' } as Record<number, string>)[rank] ?? '';

const displayName = (entry: Entry): string =>
    entry.name ?? `${t('growth.leaderboard.anonymous')} #${entry.rank}`;

const viewerInTop = computed(
    () => props.leaderboard.viewer !== null
        && props.leaderboard.entries.some((e) => e.is_self),
);

const toggleOptOut = () => {
    router.post(
        route('growth.leaderboard.opt-out'),
        { opt_out: !props.opted_out },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="t('growth.leaderboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">
                {{ t('growth.leaderboard.title') }}
            </h2>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500">{{ t('growth.leaderboard.subtitle') }}</p>

            <!-- Viewer position banner (when ranked but not in visible top-N) -->
            <div
                v-if="leaderboard.viewer && !viewerInTop"
                class="mt-4 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 p-4 text-sm text-indigo-900"
                data-testid="viewer-position"
            >
                {{ t('growth.leaderboard.your_position', { rank: leaderboard.viewer.rank }) }}
                · {{ t('growth.leaderboard.score', { score: leaderboard.viewer.score }) }}
            </div>

            <div v-if="opted_out" class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
                {{ t('growth.leaderboard.opted_out_notice') }}
            </div>

            <ul v-if="leaderboard.entries.length" class="mt-6 space-y-2">
                <li
                    v-for="entry in leaderboard.entries"
                    :key="entry.rank"
                    class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 transition"
                    :class="entry.is_self ? 'ring-indigo-400 ring-2' : 'ring-gray-100'"
                    :data-testid="entry.is_self ? 'leaderboard-self-row' : 'leaderboard-row'"
                >
                    <span class="w-10 text-center text-lg font-bold text-gray-700">
                        <span v-if="medal(entry.rank)">{{ medal(entry.rank) }}</span>
                        <span v-else>#{{ entry.rank }}</span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">
                            {{ displayName(entry) }}
                            <span v-if="entry.is_self" class="ms-1 text-xs font-normal text-indigo-600">
                                ({{ t('growth.leaderboard.you') }})
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ t('growth.leaderboard.breakdown', { attributed: entry.attributed, rewarded: entry.rewarded }) }}
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-indigo-600">
                        {{ t('growth.leaderboard.points', { score: entry.score }) }}
                    </span>
                </li>
            </ul>

            <p v-else class="mt-6 text-sm text-gray-400">{{ t('growth.leaderboard.empty') }}</p>

            <!-- DPA opt-out control -->
            <div class="mt-8 rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">
                            {{ t('growth.leaderboard.privacy_heading') }}
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('growth.leaderboard.privacy_explainer') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="toggleOptOut"
                        class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium ring-1 transition"
                        :class="opted_out
                            ? 'bg-indigo-600 text-white ring-indigo-600 hover:bg-indigo-700'
                            : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'"
                        data-testid="leaderboard-opt-out-toggle"
                    >
                        {{ opted_out ? t('growth.leaderboard.opt_in_cta') : t('growth.leaderboard.opt_out_cta') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
