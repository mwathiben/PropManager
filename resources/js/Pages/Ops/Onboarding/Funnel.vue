<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FunnelIcon } from '@heroicons/vue/24/outline';

interface StepRow {
    step: number;
    label: string;
    reached: number;
}
interface RoleFunnel {
    role: string;
    total: number;
    completed: number;
    abandoned: number;
    active: number;
    completion_rate: number;
    steps: StepRow[];
    drop_off_step: number | null;
    drop_off_count: number;
}
interface InviteFunnel {
    sent: number;
    viewed: number;
    accepted: number;
    pending: number;
    expired: number;
    acceptance_rate: number;
}

const props = defineProps<{
    funnels: Record<string, RoleFunnel>;
    inviteFunnel: InviteFunnel;
}>();

function pct(reached: number, total: number): number {
    return total > 0 ? Math.round((reached / total) * 100) : 0;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Onboarding funnel" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <FunnelIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Onboarding funnel</h1>
                    <p class="text-sm text-gray-500">Per-role step completion + invite conversion (platform-wide)</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-5" data-testid="onboarding-funnel">
            <section
                v-for="role in Object.keys(funnels)"
                :key="role"
                class="rounded-lg bg-white p-5 shadow"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold capitalize text-gray-900">{{ role }}</h2>
                    <div class="text-xs text-gray-500">
                        {{ funnels[role].total }} sessions ·
                        <span class="font-medium" :class="funnels[role].completion_rate < 40 ? 'text-rose-600' : 'text-emerald-700'">
                            {{ funnels[role].completion_rate }}% complete
                        </span>
                        <span v-if="funnels[role].drop_off_step"> · biggest drop at step {{ funnels[role].drop_off_step }}</span>
                    </div>
                </div>

                <ul class="space-y-1.5">
                    <li v-for="s in funnels[role].steps" :key="s.step" class="flex items-center gap-3">
                        <span class="w-40 truncate text-xs text-gray-600">{{ s.step }}. {{ s.label }}</span>
                        <div class="h-3 flex-1 overflow-hidden rounded bg-gray-100">
                            <div
                                class="h-full bg-indigo-500"
                                :style="{ width: pct(s.reached, funnels[role].total) + '%' }"
                            ></div>
                        </div>
                        <span class="w-20 text-end text-xs tabular-nums text-gray-700">{{ s.reached }} ({{ pct(s.reached, funnels[role].total) }}%)</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" data-testid="invite-funnel">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Invitation funnel</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div><p class="text-xs uppercase text-gray-400">Sent</p><p class="text-xl font-semibold text-gray-900">{{ inviteFunnel.sent }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">Viewed</p><p class="text-xl font-semibold text-gray-900">{{ inviteFunnel.viewed }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">Accepted</p><p class="text-xl font-semibold text-emerald-700">{{ inviteFunnel.accepted }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">Pending</p><p class="text-xl font-semibold text-amber-600">{{ inviteFunnel.pending }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">Expired</p><p class="text-xl font-semibold text-gray-400">{{ inviteFunnel.expired }}</p></div>
                </div>
                <p class="mt-3 text-sm text-gray-600">Acceptance rate: <span class="font-semibold text-gray-900">{{ inviteFunnel.acceptance_rate }}%</span></p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
