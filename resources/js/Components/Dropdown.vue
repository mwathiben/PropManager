<script setup>
/**
 * Phase-23 A11Y-KBD-2: keyboard-operable dropdown (WCAG 2.1.1, 2.4.3).
 * Pre-Phase-23 the menu opened on click and relied on the browser's
 * default tab order — focus never moved into the menu, arrow keys did
 * nothing, and Escape closed it but stranded focus. Now:
 *   - on open, focus moves to the first menu item;
 *   - Up/Down cycle between items (wrapping), Home/End jump to ends;
 *   - Escape closes AND restores focus to the trigger.
 * Callers MUST slot a real <button> into #trigger — the wrapper is a
 * plain <div> and does not synthesise keyboard support for a
 * non-interactive trigger.
 */
import { computed, ref, watch } from 'vue';
import { useEscapeKey } from '@/composables/useEscapeKey';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-2 bg-white',
    },
    dropUp: {
        type: Boolean,
        default: false,
    },
});

const open = ref(false);
const contentRef = ref(null);
let triggerElement = null;

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

function menuItems() {
    const el = contentRef.value;
    return el ? Array.from(el.querySelectorAll(FOCUSABLE_SELECTOR)) : [];
}

function restoreFocusToTrigger() {
    if (triggerElement && typeof triggerElement.focus === 'function') {
        triggerElement.focus();
    }
    triggerElement = null;
}

function close() {
    open.value = false;
}

useEscapeKey(() => {
    if (open.value) {
        close();
        restoreFocusToTrigger();
    }
}, open);

watch(open, (isOpen) => {
    if (isOpen) {
        // Capture the element that opened the menu so Escape can
        // return focus to it.
        triggerElement = document.activeElement;
        requestAnimationFrame(() => {
            const items = menuItems();
            if (items.length > 0) {
                items[0].focus();
            }
        });
    }
});

function onMenuKeydown(event) {
    const items = menuItems();
    if (items.length === 0) {
        return;
    }
    const currentIndex = items.indexOf(document.activeElement);

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            items[(currentIndex + 1) % items.length].focus();
            break;
        case 'ArrowUp':
            event.preventDefault();
            items[(currentIndex - 1 + items.length) % items.length].focus();
            break;
        case 'Home':
            event.preventDefault();
            items[0].focus();
            break;
        case 'End':
            event.preventDefault();
            items[items.length - 1].focus();
            break;
    }
}

const widthClass = computed(() => {
    return {
        48: 'w-48',
        56: 'w-56',
        64: 'w-64',
    }[props.width.toString()] || 'w-48';
});

const alignmentClasses = computed(() => {
    const vertical = props.dropUp ? 'bottom' : 'top';
    if (props.align === 'left') {
        return `ltr:origin-${vertical}-left rtl:origin-${vertical}-right start-0`;
    } else if (props.align === 'right') {
        return `ltr:origin-${vertical}-right rtl:origin-${vertical}-left end-0`;
    } else {
        return `origin-${vertical}`;
    }
});

const positionClasses = computed(() => {
    return props.dropUp ? 'bottom-full mb-2' : 'mt-2';
});
</script>

<template>
    <div class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-30"
            @click="open = false"
        ></div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 rounded-xl shadow-lg border border-gray-200"
                :class="[widthClass, alignmentClasses, positionClasses]"
                @click="open = false"
            >
                <div
                    ref="contentRef"
                    class="rounded-xl overflow-hidden"
                    :class="contentClasses"
                    @keydown="onMenuKeydown"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
