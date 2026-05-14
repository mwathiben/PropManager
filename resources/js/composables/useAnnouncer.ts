import { ref, type Ref } from 'vue';

/**
 * Phase-23 A11Y-SR-1: ARIA live announcer (WCAG 4.1.3 Status Messages).
 *
 * A screen reader only learns about a status change a sighted user sees
 * if it lands in an aria-live region. PropManager had none — Inertia
 * flash messages, optimistic notifications, and async refreshes were
 * silent. This composable owns two module-level message refs that
 * LiveAnnouncer.vue renders into its polite/assertive regions.
 *
 * Usage:
 *   const { announce } = useAnnouncer();
 *   announce('Invoice created', 'polite');
 *   announce('Payment failed', 'assertive');
 *
 * The brief clear-then-set (via requestAnimationFrame) guarantees a
 * repeated identical message still triggers a re-announcement — an
 * aria-live region ignores a write that does not change the text.
 */
type Politeness = 'polite' | 'assertive';

const politeMessage = ref('');
const assertiveMessage = ref('');

export function useAnnouncer(): {
    announce: (message: string, politeness?: Politeness) => void;
    politeMessage: Ref<string>;
    assertiveMessage: Ref<string>;
} {
    function announce(message: string, politeness: Politeness = 'polite'): void {
        if (!message) {
            return;
        }
        const target = politeness === 'assertive' ? assertiveMessage : politeMessage;
        target.value = '';
        requestAnimationFrame(() => {
            target.value = message;
        });
    }

    return { announce, politeMessage, assertiveMessage };
}
