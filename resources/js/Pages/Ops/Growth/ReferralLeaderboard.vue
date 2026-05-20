<script setup lang="ts">
/**
 * Phase-66 REFERRAL-LEADERBOARD-2: super-admin leaderboard with full
 * referrer names for ops/support. Route-gated to role:super_admin.
 */
import { Head } from '@inertiajs/vue3';
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

defineProps<{
    leaderboard: {
        entries: Entry[];
        viewer: Entry | null;
        total_ranked: number;
    };
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('growth.leaderboard.ops_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">
                {{ t('growth.leaderboard.ops_title') }}
            </h2>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500">
                {{ t('growth.leaderboard.ops_subtitle', { total: leaderboard.total_ranked }) }}
            </p>

            <table class="mt-6 w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="py-2 pe-4">#</th>
                        <th class="py-2 pe-4">{{ t('growth.leaderboard.col_referrer') }}</th>
                        <th class="py-2 pe-4 text-right">{{ t('growth.leaderboard.col_attributed') }}</th>
                        <th class="py-2 pe-4 text-right">{{ t('growth.leaderboard.col_rewarded') }}</th>
                        <th class="py-2 text-right">{{ t('growth.leaderboard.col_score') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in leaderboard.entries"
                        :key="entry.rank"
                        class="border-b border-gray-100"
                        data-testid="ops-leaderboard-row"
                    >
                        <td class="py-2 pe-4 font-semibold text-gray-700">{{ entry.rank }}</td>
                        <td class="py-2 pe-4 text-gray-900">{{ entry.name }}</td>
                        <td class="py-2 pe-4 text-right text-gray-600">{{ entry.attributed }}</td>
                        <td class="py-2 pe-4 text-right text-gray-600">{{ entry.rewarded }}</td>
                        <td class="py-2 text-right font-semibold text-indigo-600">{{ entry.score }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!leaderboard.entries.length" class="mt-6 text-sm text-gray-400">
                {{ t('growth.leaderboard.empty') }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
