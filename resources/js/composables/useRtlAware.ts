/**
 * Phase-44 A11Y-RTL-1: RTL-aware keyboard navigation + direction
 * change announcement. Arrow keys are direction-dependent semantically
 * — ArrowRight moves 'forward' in LTR, ArrowLeft 'forward' in RTL.
 * Components that respond to arrow keys (Modal, Carousel, Tabs,
 * Dropdown) must invert handlers when dir='rtl'.
 *
 * Pairs with the Phase-23 useAnnouncer composable to fulfil
 * A11Y-RTL-2: when the locale flips, an aria-live polite announcement
 * tells AT users the layout direction changed — otherwise the silent
 * re-render is jarring mid-task (WCAG 1.3.1 + 4.1.2).
 *
 * Usage:
 *   const { isRtl, forwardKey, backwardKey, onForward, onBackward } = useRtlAware();
 *   const handler = (e: KeyboardEvent) => {
 *     if (e.key === forwardKey.value) advance();
 *     if (e.key === backwardKey.value) retreat();
 *   };
 */
import { computed, watch, type ComputedRef } from 'vue';
import { useI18n } from './useI18n';
import { useAnnouncer } from './useAnnouncer';

const RTL_LOCALES = new Set(['ar', 'he', 'fa', 'ur']);

interface UseRtlAwareReturn {
    isRtl: ComputedRef<boolean>;
    dir: ComputedRef<'rtl' | 'ltr'>;
    forwardKey: ComputedRef<'ArrowRight' | 'ArrowLeft'>;
    backwardKey: ComputedRef<'ArrowLeft' | 'ArrowRight'>;
    isForwardKey: (key: string) => boolean;
    isBackwardKey: (key: string) => boolean;
}

function primarySubtag(locale: string): string {
    return locale.toLowerCase().split(/[-_]/)[0];
}

export function useRtlAware(): UseRtlAwareReturn {
    const { locale } = useI18n();
    const { announce } = useAnnouncer();

    const isRtl = computed(() => RTL_LOCALES.has(primarySubtag(locale.value)));
    const dir = computed<'rtl' | 'ltr'>(() => (isRtl.value ? 'rtl' : 'ltr'));

    const forwardKey = computed<'ArrowRight' | 'ArrowLeft'>(() =>
        isRtl.value ? 'ArrowLeft' : 'ArrowRight',
    );
    const backwardKey = computed<'ArrowLeft' | 'ArrowRight'>(() =>
        isRtl.value ? 'ArrowRight' : 'ArrowLeft',
    );

    function isForwardKey(key: string): boolean {
        return key === forwardKey.value;
    }

    function isBackwardKey(key: string): boolean {
        return key === backwardKey.value;
    }

    // A11Y-RTL-2: announce direction changes politely. Watching `dir`
    // (computed from locale) means a locale switch fires this exactly
    // once; mounting a component with stable locale does not re-announce.
    watch(dir, (next, prev) => {
        if (!prev || next === prev) return;
        announce(
            next === 'rtl'
                ? 'Page direction changed to right-to-left.'
                : 'Page direction changed to left-to-right.',
            'polite',
        );
        if (typeof document !== 'undefined') {
            document.documentElement.setAttribute('dir', next);
        }
    });

    return { isRtl, dir, forwardKey, backwardKey, isForwardKey, isBackwardKey };
}
