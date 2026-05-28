<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FunnelIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

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

const { t } = useI18n();

function pct(reached: number, total: number): number {
    return total > 0 ? Math.round((reached / total) * 100) : 0;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('ops_onboarding_funnel.page_title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <FunnelIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('ops_onboarding_funnel.header_title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('ops_onboarding_funnel.header_subtitle') }}</p>
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
                    <h2 class="text-sm font-semibold capitalize text-gray-900">{{ t(`ops_onboarding_funnel.roles.${role}`, role ?? '') }}</h2>
                    <div class="text-xs text-gray-500">
                        {{ t('ops_onboarding_funnel.sessions_count', { count: funnels[role].total }) }} ·
                        <span class="font-medium" :class="funnels[role].completion_rate < 40 ? 'text-rose-600' : 'text-emerald-700'">
                            {{ t('ops_onboarding_funnel.complete_rate', { rate: funnels[role].completion_rate }) }}
                        </span>
                        <span v-if="funnels[role].drop_off_step"> · {{ t('ops_onboarding_funnel.biggest_drop_at_step', { step: funnels[role].drop_off_step }) }}</span>
                    </div>
                </div>

                <ul class="space-y-1.5">
                    <li v-for="s in funnels[role].steps" :key="s.step" class="flex items-center gap-3">
                        <span class="w-40 truncate text-xs text-gray-600">{{ s.step }}. {{ t(`ops_onboarding_funnel.step_labels.${s.label}`, s.label ?? '') }}</span>
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
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('ops_onboarding_funnel.invitation_funnel') }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div><p class="text-xs uppercase text-gray-400">{{ t('ops_onboarding_funnel.invite.sent') }}</p><p class="text-xl font-semibold text-gray-900">{{ inviteFunnel.sent }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">{{ t('ops_onboarding_funnel.invite.viewed') }}</p><p class="text-xl font-semibold text-gray-900">{{ inviteFunnel.viewed }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">{{ t('ops_onboarding_funnel.invite.accepted') }}</p><p class="text-xl font-semibold text-emerald-700">{{ inviteFunnel.accepted }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">{{ t('ops_onboarding_funnel.invite.pending') }}</p><p class="text-xl font-semibold text-amber-600">{{ inviteFunnel.pending }}</p></div>
                    <div><p class="text-xs uppercase text-gray-400">{{ t('ops_onboarding_funnel.invite.expired') }}</p><p class="text-xl font-semibold text-gray-400">{{ inviteFunnel.expired }}</p></div>
                </div>
                <p class="mt-3 text-sm text-gray-600">{{ t('ops_onboarding_funnel.acceptance_rate_label') }} <span class="font-semibold text-gray-900">{{ inviteFunnel.acceptance_rate }}%</span></p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
