<script setup>
import { computed } from 'vue';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import { useEscapeKey } from '@/composables/useEscapeKey';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    width: {
        type: String,
        default: 'md', // sm, md, lg, xl
        validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value)
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close']);

const widthClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl'
};

const close = () => {
    emit('close');
};

const showRef = computed(() => props.show);
useEscapeKey(close, showRef);
useBodyScrollLock(showRef);
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 bg-gray-900/50 z-40"
                @click="close"
            />
        </Transition>

        <!-- Panel -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="show"
                :class="[widthClasses[width], 'fixed inset-y-0 right-0 w-full bg-white shadow-2xl z-50 flex flex-col border-l border-gray-200']"
            >
                <!-- Header -->
                <div class="shrink-0 px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">
                                {{ title }}
                            </h2>
                            <p v-if="subtitle" class="mt-1 text-sm text-gray-500">
                                {{ subtitle }}
                            </p>
                        </div>
                        <button
                            @click="close"
                            class="p-2 -mr-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <slot />
                </div>

                <!-- Footer (optional) -->
                <div v-if="$slots.footer" class="shrink-0 px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <slot name="footer" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
