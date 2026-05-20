<script setup lang="ts">
/**
 * Phase-66 ONBOARDING-TOUR-2: the floating step card.
 *
 * @floating-ui positions it against the active [data-tour] anchor on
 * desktop; when there is no anchor (or on <sm screens) it falls back to
 * a centred card / bottom-sheet. Fully keyboard-driven: focus-trapped,
 * Esc skips, Arrow keys advance/retreat (RTL-inverted via useRtlAware),
 * and each step is announced to screen readers.
 */
import { ref, computed, watch, toRef, onMounted, onUnmounted } from 'vue';
import { useFloating, offset, flip, shift, arrow, autoUpdate } from '@floating-ui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useFocusTrap } from '@/composables/useFocusTrap';
import { useEscapeKey } from '@/composables/useEscapeKey';
import { useRtlAware } from '@/composables/useRtlAware';
import { useAnnouncer } from '@/composables/useAnnouncer';

const props = defineProps<{
    reference: HTMLElement | null;
    title: string;
    body: string;
    index: number;
    total: number;
}>();

const emit = defineEmits<{
    next: [];
    back: [];
    skip: [];
}>();

const { t } = useI18n();
const { announce } = useAnnouncer();
const { isForwardKey, isBackwardKey } = useRtlAware();

const reference = toRef(props, 'reference');
// Undefined (not null) to match the useFocusTrap container contract.
const floating = ref<HTMLElement>();
const arrowEl = ref<HTMLElement | null>(null);

const isFirst = computed(() => props.index <= 0);
const isLast = computed(() => props.index >= props.total - 1);

const isMobile = ref(false);
let motionQuery: MediaQueryList | null = null;
const onMotionQuery = (event: MediaQueryListEvent | MediaQueryList) => {
    isMobile.value = event.matches;
};

const { floatingStyles, placement, middlewareData } = useFloating(reference, floating, {
    strategy: 'fixed',
    placement: 'bottom',
    whileElementsMounted: autoUpdate,
    middleware: [offset(12), flip({ padding: 8 }), shift({ padding: 8 }), arrow({ element: arrowEl })],
});

// Centre when there is no anchor to point at, or on small screens.
const centred = computed(() => isMobile.value || props.reference === null);
const showArrow = computed(() => !centred.value);
const cardStyles = computed(() => (centred.value ? {} : floatingStyles.value));

const positionClass = computed(() => {
    if (isMobile.value) {
        return 'fixed inset-x-3 bottom-3 mx-auto max-w-md';
    }
    if (props.reference === null) {
        return 'fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-80 max-w-[calc(100vw-1.5rem)]';
    }
    return 'w-80 max-w-[calc(100vw-1.5rem)]';
});

const arrowStyles = computed(() => {
    const data = middlewareData.value.arrow;
    if (!data) {
        return {};
    }
    const side = placement.value.split('-')[0];
    const opposite: Record<string, string> = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };
    const staticSide = opposite[side] ?? 'top';
    return {
        left: data.x != null ? `${data.x}px` : '',
        top: data.y != null ? `${data.y}px` : '',
        [staticSide]: '-4px',
    };
});

const active = ref(true);
useFocusTrap(floating, active);
useEscapeKey(() => emit('skip'), active);

function onKeydown(event: KeyboardEvent): void {
    if (isForwardKey(event.key)) {
        event.preventDefault();
        emit('next');
    } else if (isBackwardKey(event.key) && !isFirst.value) {
        event.preventDefault();
        emit('back');
    }
}

watch(
    () => props.index,
    () => announce(t('onboarding.tour.step_of', { current: props.index + 1, total: props.total }), 'polite'),
    { immediate: true },
);

onMounted(() => {
    motionQuery = window.matchMedia('(max-width: 639px)');
    isMobile.value = motionQuery.matches;
    motionQuery.addEventListener('change', onMotionQuery);
    // Arrow-key navigation is bound at the document (the tour is modal +
    // focus-trapped, so it owns the keyboard) rather than via a template
    // handler on the non-interactive card.
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    motionQuery?.removeEventListener('change', onMotionQuery);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        ref="floating"
        :style="cardStyles"
        :class="['z-[60]', positionClass]"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`tour-title-${index}`"
        :aria-describedby="`tour-body-${index}`"
        data-testid="tour-tooltip"
    >
        <div class="relative rounded-2xl bg-white p-5 shadow-2xl ring-1 ring-gray-200">
            <button
                type="button"
                class="absolute end-3 top-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                :aria-label="t('onboarding.tour.nav.skip')"
                data-testid="tour-skip"
                @click="emit('skip')"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>

            <h3 :id="`tour-title-${index}`" class="pe-6 text-base font-semibold text-gray-900">
                {{ title }}
            </h3>
            <p :id="`tour-body-${index}`" class="mt-1 text-sm text-gray-600">{{ body }}</p>

            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="text-xs text-gray-400" data-testid="tour-step-of">
                    {{ t('onboarding.tour.step_of', { current: index + 1, total }) }}
                </span>
                <div class="flex items-center gap-2">
                    <button
                        v-if="!isFirst"
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-gray-900"
                        data-testid="tour-back"
                        @click="emit('back')"
                    >
                        {{ t('onboarding.tour.nav.back') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        data-testid="tour-next"
                        @click="emit('next')"
                    >
                        {{ isLast ? t('onboarding.tour.nav.done') : t('onboarding.tour.nav.next') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showArrow"
            ref="arrowEl"
            :style="arrowStyles"
            class="absolute h-2 w-2 rotate-45 bg-white ring-1 ring-gray-200"
            aria-hidden="true"
        ></div>
    </div>
</template>
