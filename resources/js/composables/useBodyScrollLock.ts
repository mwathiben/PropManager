import { watch, onUnmounted, type Ref } from 'vue';

/**
 * Composable for locking body scroll when modals or panels are open.
 *
 * @param isLocked - Ref that controls whether body scroll should be locked
 */
export function useBodyScrollLock(isLocked: Ref<boolean>) {
    watch(isLocked, (locked) => {
        document.body.style.overflow = locked ? 'hidden' : '';
    }, { immediate: true });

    onUnmounted(() => {
        document.body.style.overflow = '';
    });
}
