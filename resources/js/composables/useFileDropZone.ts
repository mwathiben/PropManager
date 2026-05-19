/**
 * Phase-64 INBOX-POLISH-3: drag-and-drop file upload zone. Wraps a
 * target element with native DataTransfer API handlers + a reactive
 * `isDragging` flag callers use to render a drop-target overlay.
 *
 * No external library — pure HTML5 dragover/drop.
 */

import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';

interface Options {
    onDrop: (files: File[]) => void;
    accept?: string[];
}

export function useFileDropZone(
    target: Ref<HTMLElement | null>,
    options: Options,
) {
    const isDragging = ref<boolean>(false);

    function shouldAccept(file: File): boolean {
        if (!options.accept || options.accept.length === 0) {
            return true;
        }

        const mime = file.type.toLowerCase();
        return options.accept.some((pattern) => {
            const trimmed = pattern.toLowerCase().trim();
            if (trimmed.endsWith('/*')) {
                return mime.startsWith(trimmed.slice(0, -1));
            }
            return mime === trimmed;
        });
    }

    function handleDragEnter(event: DragEvent): void {
        event.preventDefault();
        isDragging.value = true;
    }

    function handleDragOver(event: DragEvent): void {
        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'copy';
        }
        isDragging.value = true;
    }

    function handleDragLeave(event: DragEvent): void {
        event.preventDefault();
        // Only flip off when leaving the target boundary, not a child.
        if (
            event.relatedTarget === null
            || (target.value !== null && !target.value.contains(event.relatedTarget as Node))
        ) {
            isDragging.value = false;
        }
    }

    function handleDrop(event: DragEvent): void {
        event.preventDefault();
        isDragging.value = false;

        const files = Array.from(event.dataTransfer?.files ?? [])
            .filter(shouldAccept);

        if (files.length > 0) {
            options.onDrop(files);
        }
    }

    function bind(el: HTMLElement): void {
        el.addEventListener('dragenter', handleDragEnter);
        el.addEventListener('dragover', handleDragOver);
        el.addEventListener('dragleave', handleDragLeave);
        el.addEventListener('drop', handleDrop);
    }

    function unbind(el: HTMLElement): void {
        el.removeEventListener('dragenter', handleDragEnter);
        el.removeEventListener('dragover', handleDragOver);
        el.removeEventListener('dragleave', handleDragLeave);
        el.removeEventListener('drop', handleDrop);
    }

    onMounted(() => {
        if (target.value !== null) {
            bind(target.value);
        }
    });

    watch(target, (newEl, oldEl) => {
        if (oldEl !== null && oldEl !== undefined) {
            unbind(oldEl);
        }
        if (newEl !== null) {
            bind(newEl);
        }
    });

    onBeforeUnmount(() => {
        if (target.value !== null) {
            unbind(target.value);
        }
    });

    return {
        isDragging,
    };
}
