<script setup lang="ts">
/**
 * Phase-66 ONBOARDING-TOUR-3: globally-mounted tour orchestrator.
 *
 * Reads the server-authoritative auth.onboarding_tour payload, drives
 * the step cursor, and renders a dimmed backdrop with an animated SVG
 * spotlight cut out around the active [data-tour] anchor (purely
 * visual; the backdrop blocks page interaction so the TourTooltip is
 * the only control surface). Advancing/finishing/skipping POST back to
 * the server so progress survives reloads and never re-triggers once
 * terminal. prefers-reduced-motion disables the spotlight animation.
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import TourTooltip from './TourTooltip.vue';

interface TourStep {
    key: string;
    target: string;
    route: string | null;
    title: string;
    body: string;
}

interface TourPayload {
    tour_key: string;
    active: boolean;
    current_step: number;
    steps: TourStep[];
}

const SPOTLIGHT_PADDING = 8;

const page = usePage();
const tour = computed<TourPayload | null>(
    () => ((page.props as Record<string, any>)?.auth?.onboarding_tour as TourPayload | null) ?? null,
);

const show = ref(false);
const index = ref(0);
const steps = ref<TourStep[]>([]);
const targetEl = ref<HTMLElement | null>(null);
const rect = ref<DOMRect | null>(null);
const reduceMotion = ref(false);

const currentStep = computed<TourStep | null>(() => steps.value[index.value] ?? null);

const spotlight = computed(() => {
    if (!rect.value || rect.value.width === 0) {
        return null;
    }
    return {
        x: Math.max(0, rect.value.left - SPOTLIGHT_PADDING),
        y: Math.max(0, rect.value.top - SPOTLIGHT_PADDING),
        w: rect.value.width + SPOTLIGHT_PADDING * 2,
        h: rect.value.height + SPOTLIGHT_PADDING * 2,
    };
});

function locateTarget(): void {
    const step = currentStep.value;
    if (!step) {
        targetEl.value = null;
        rect.value = null;
        return;
    }
    const el = document.querySelector<HTMLElement>(`[data-tour="${step.target}"]`);
    targetEl.value = el;
    if (el) {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reduceMotion.value ? 'auto' : 'smooth' });
        rect.value = el.getBoundingClientRect();
    } else {
        rect.value = null; // tooltip centres itself
    }
}

function recompute(): void {
    if (targetEl.value) {
        rect.value = targetEl.value.getBoundingClientRect();
    }
}

function start(): void {
    const payload = tour.value;
    if (!payload || !payload.active || payload.steps.length === 0) {
        show.value = false;
        return;
    }
    steps.value = payload.steps;
    index.value = Math.min(Math.max(0, payload.current_step), payload.steps.length - 1);
    show.value = true;
    nextTick(locateTarget);
}

function next(): void {
    if (index.value >= steps.value.length - 1) {
        complete();
        return;
    }
    index.value += 1;
    router.post(route('onboarding-tour.advance'), { step: index.value }, { preserveScroll: true, preserveState: true });
    nextTick(locateTarget);
}

function back(): void {
    if (index.value <= 0) {
        return;
    }
    index.value -= 1;
    nextTick(locateTarget);
}

function skip(): void {
    show.value = false;
    router.post(route('onboarding-tour.dismiss'), {}, { preserveScroll: true, preserveState: true });
}

function complete(): void {
    show.value = false;
    router.post(route('onboarding-tour.complete'), {}, { preserveScroll: true, preserveState: true });
}

watch(currentStep, () => nextTick(locateTarget));

// Re-sync if the server payload changes without a layout remount (e.g. a
// future migration to Inertia persistent layouts). Today the layout is a
// per-page child, so onMounted already covers navigation.
watch(tour, start);

onMounted(() => {
    reduceMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    start();
    window.addEventListener('resize', recompute, { passive: true });
    window.addEventListener('scroll', recompute, { passive: true, capture: true });
});

onUnmounted(() => {
    window.removeEventListener('resize', recompute);
    window.removeEventListener('scroll', recompute, true);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show && currentStep" class="fixed inset-0 z-[55]" data-testid="tour-overlay">
            <svg class="absolute inset-0 h-full w-full" aria-hidden="true">
                <defs>
                    <mask id="tour-spotlight-mask">
                        <rect width="100%" height="100%" fill="white" />
                        <rect
                            v-if="spotlight"
                            :x="spotlight.x"
                            :y="spotlight.y"
                            :width="spotlight.w"
                            :height="spotlight.h"
                            rx="12"
                            ry="12"
                            fill="black"
                            :class="reduceMotion ? '' : 'tour-spot'"
                        />
                    </mask>
                </defs>
                <rect width="100%" height="100%" fill="rgba(17,24,39,0.55)" mask="url(#tour-spotlight-mask)" />
            </svg>

            <TourTooltip
                :reference="targetEl"
                :title="currentStep.title"
                :body="currentStep.body"
                :index="index"
                :total="steps.length"
                @next="next"
                @back="back"
                @skip="skip"
            />
        </div>
    </Teleport>
</template>

<style scoped>
.tour-spot {
    transition:
        x 0.3s ease,
        y 0.3s ease,
        width 0.3s ease,
        height 0.3s ease;
}
</style>
