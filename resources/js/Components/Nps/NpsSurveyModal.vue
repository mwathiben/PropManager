<script setup lang="ts">
/**
 * Phase-66 NPS-SURVEY-3: globally-mounted NPS prompt.
 *
 * Visibility is driven by a LOCAL ref seeded once from the server's
 * auth.nps_prompt payload — not bound directly to the prop — so the
 * background impression POST (which nulls the server prop) doesn't make
 * the modal vanish mid-interaction. The server remains authoritative on
 * WHETHER to prompt; this component only renders + reports the outcome.
 */
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useEscapeKey } from '@/composables/useEscapeKey';

const { t } = useI18n();
const page = usePage();

const prompt = computed(() => (page.props as any)?.auth?.nps_prompt ?? null);

const show = ref(false);
const score = ref<number | null>(null);
const comment = ref('');
const submitting = ref(false);
const context = ref<string>('dashboard');

const scores = Array.from({ length: 11 }, (_, i) => i);

const scoreColor = (value: number): string => {
    if (value <= 6) return 'detractor';
    if (value <= 8) return 'passive';
    return 'promoter';
};

const post = (name: string, data: Record<string, unknown> = {}) =>
    router.post(route(name), data, { preserveScroll: true, preserveState: true });

onMounted(() => {
    if (prompt.value) {
        context.value = prompt.value.context ?? 'dashboard';
        show.value = true;
        post('nps.impression');
    }
});

const dismiss = () => {
    if (submitting.value) return;
    show.value = false;
    post('nps.dismiss');
};

const optOut = () => {
    if (submitting.value) return;
    show.value = false;
    post('nps.opt-out');
};

const submit = () => {
    if (score.value === null || submitting.value) return;
    submitting.value = true;

    router.post(
        route('nps.store'),
        { score: score.value, comment: comment.value.trim() || null, context: context.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { submitting.value = false; },
            onSuccess: () => { show.value = false; },
        },
    );
};

useEscapeKey(dismiss, show);
</script>

<template>
    <Teleport to="body">
        <Transition name="nps-fade">
            <div
                v-if="show"
                class="fixed bottom-4 inset-x-4 sm:bottom-6 sm:end-6 sm:inset-x-auto sm:max-w-md z-50"
                role="dialog"
                aria-modal="false"
                :aria-label="t('nps.aria_label')"
                data-testid="nps-survey-modal"
            >
                <div class="rounded-2xl bg-white shadow-2xl ring-1 ring-gray-200 p-5">
                    <button
                        type="button"
                        @click="dismiss"
                        class="absolute top-3 end-3 text-gray-400 hover:text-gray-600"
                        :aria-label="t('nps.close')"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>

                    <h3 class="text-base font-semibold text-gray-900 pe-6">
                        {{ t('nps.title') }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">{{ t('nps.subtitle') }}</p>

                    <div class="mt-4 grid grid-cols-11 gap-1" role="radiogroup" :aria-label="t('nps.aria_label')">
                        <button
                            v-for="value in scores"
                            :key="value"
                            type="button"
                            role="radio"
                            :aria-checked="score === value"
                            :aria-label="String(value)"
                            @click="score = value"
                            class="aspect-square rounded-md text-xs font-medium ring-1 transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="[
                                score === value ? 'ring-2 ring-indigo-600 scale-105' : 'ring-gray-200 hover:ring-gray-300',
                                scoreColor(value) === 'detractor' ? 'bg-rose-50 text-rose-700' : '',
                                scoreColor(value) === 'passive' ? 'bg-amber-50 text-amber-700' : '',
                                scoreColor(value) === 'promoter' ? 'bg-emerald-50 text-emerald-700' : '',
                            ]"
                            :data-testid="`nps-score-${value}`"
                        >
                            {{ value }}
                        </button>
                    </div>
                    <div class="mt-1 flex justify-between text-[10px] uppercase tracking-wide text-gray-400">
                        <span>{{ t('nps.low_label') }}</span>
                        <span>{{ t('nps.high_label') }}</span>
                    </div>

                    <div v-if="score !== null" class="mt-4">
                        <label for="nps-comment" class="block text-xs font-medium text-gray-700">
                            {{ t('nps.comment_label') }}
                        </label>
                        <textarea
                            id="nps-comment"
                            v-model="comment"
                            rows="2"
                            maxlength="1000"
                            class="mt-1 block w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :placeholder="t('nps.comment_placeholder')"
                            data-testid="nps-comment"
                        ></textarea>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            @click="optOut"
                            class="text-xs text-gray-400 hover:text-gray-600 underline"
                            data-testid="nps-opt-out"
                        >
                            {{ t('nps.opt_out') }}
                        </button>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="dismiss"
                                class="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-gray-900"
                                data-testid="nps-not-now"
                            >
                                {{ t('nps.not_now') }}
                            </button>
                            <button
                                type="button"
                                @click="submit"
                                :disabled="score === null || submitting"
                                class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50"
                                data-testid="nps-submit"
                            >
                                {{ submitting ? t('nps.submitting') : t('nps.submit') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.nps-fade-enter-active,
.nps-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.nps-fade-enter-from,
.nps-fade-leave-to {
    opacity: 0;
    transform: translateY(0.5rem);
}
@media (prefers-reduced-motion: reduce) {
    .nps-fade-enter-active,
    .nps-fade-leave-active {
        transition: none;
    }
    .nps-fade-enter-from,
    .nps-fade-leave-to {
        transform: none;
    }
}
</style>
