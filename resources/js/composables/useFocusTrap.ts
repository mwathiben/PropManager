import { type Ref, watch, onUnmounted } from 'vue';

/**
 * Phase-20 FRONT-UX-7: focus-trap composable for Modal.vue + future
 * dialog/drawer components. Without it, Tab navigation can escape
 * the modal to the background, which breaks keyboard-only workflows
 * + violates WCAG 2.4.3 Focus Order.
 *
 * Usage:
 *   const dialog = ref<HTMLElement>();
 *   const isOpen = computed(() => props.show);
 *   useFocusTrap(dialog, isOpen);
 *
 * Behavior:
 *   - On open: focus the first focusable element inside the container.
 *   - On Tab from the last focusable: wrap to the first.
 *   - On Shift+Tab from the first: wrap to the last.
 *   - On close: nothing (parent dialog typically handles teardown).
 */
export function useFocusTrap(containerRef: Ref<HTMLElement | undefined>, isActive: Ref<boolean>): void {
    const FOCUSABLE_SELECTOR = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    function getFocusableElements(): HTMLElement[] {
        const container = containerRef.value;
        if (!container) return [];
        return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
    }

    function handleKeydown(event: KeyboardEvent) {
        if (event.key !== 'Tab') return;

        const focusables = getFocusableElements();
        if (focusables.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const activeElement = document.activeElement as HTMLElement | null;

        if (event.shiftKey) {
            // Shift+Tab from the first focusable → wrap to last.
            if (activeElement === first || !containerRef.value?.contains(activeElement)) {
                event.preventDefault();
                last.focus();
            }
        } else {
            // Tab from the last focusable → wrap to first.
            if (activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    watch(
        isActive,
        (active) => {
            if (active) {
                document.addEventListener('keydown', handleKeydown);
                // Defer focus until the modal transition finishes.
                requestAnimationFrame(() => {
                    const focusables = getFocusableElements();
                    if (focusables.length > 0) {
                        focusables[0].focus();
                    }
                });
            } else {
                document.removeEventListener('keydown', handleKeydown);
            }
        },
        { immediate: true },
    );

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown);
    });
}
