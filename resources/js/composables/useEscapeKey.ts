import { onMounted, onUnmounted, unref, type MaybeRef } from 'vue';

/**
 * Composable for handling Escape key press to close modals, dropdowns, panels, etc.
 *
 * @param callback - Function to call when Escape is pressed
 * @param enabled - Ref or getter that determines if the handler should respond
 */
export function useEscapeKey(callback: () => void, enabled: MaybeRef<boolean> = true) {
    const handleEscape = (e: KeyboardEvent) => {
        if (e.key === 'Escape' && unref(enabled)) {
            e.preventDefault();
            callback();
        }
    };

    onMounted(() => document.addEventListener('keydown', handleEscape));
    onUnmounted(() => document.removeEventListener('keydown', handleEscape));
}
