<script setup>
import { computed, ref } from 'vue';
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

useEscapeKey(() => { open.value = false; }, open);

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
            class="fixed inset-0 z-40"
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
                    class="rounded-xl overflow-hidden"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
