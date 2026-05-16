<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    engagement_history: Array<{ day: string; score: number; components: Record<string, number> | null }>;
    referrals: Array<{ id: number; referred_user_id: number; status: string; attributed_at: string | null; created_at: string }>;
    summary: {
        engagement_score: number;
        engagement_score_delta_7d: number;
        referral_count_30d: number;
        usage_ratios: Array<{ feature: string; usage: number; limit: number; ratio: number }>;
    };
}>();
</script>

<template>
    <Head title="Growth dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Growth dashboard</h1>
        </template>

        <div class="py-6 space-y-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current engagement score</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.engagement_score }} / 100</p>
                        <p class="mt-1 text-sm" :class="summary.engagement_score_delta_7d >= 0 ? 'text-green-700' : 'text-red-700'">
                            {{ summary.engagement_score_delta_7d >= 0 ? '+' : '' }}{{ summary.engagement_score_delta_7d }} vs 7d ago
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Referrals (30d)</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.referral_count_30d }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active features</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.usage_ratios.length }}</p>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-base font-semibold text-gray-900">Engagement history (90 days)</h2>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Day</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Score</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Components</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in engagement_history" :key="row.day">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ row.day }}</td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-900">{{ row.score }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    <span v-for="(value, key) in row.components || {}" :key="key" class="mr-3 inline-block">
                                        {{ key }}: <span class="font-medium">{{ value }}</span>
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!engagement_history.length">
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No engagement history yet — score is computed nightly.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-base font-semibold text-gray-900">Recent referrals</h2>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">ID</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Created</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Attributed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="ref in referrals" :key="ref.id">
                                <td class="px-4 py-2 text-sm text-gray-700">#{{ ref.id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 capitalize">{{ ref.status }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ ref.created_at }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ ref.attributed_at ?? '—' }}</td>
                            </tr>
                            <tr v-if="!referrals.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No referrals yet. Share your code from the dashboard.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
