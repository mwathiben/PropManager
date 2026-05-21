<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{ image: { url: string; title: string } | null }>();
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const closeButton = ref<HTMLButtonElement | null>(null);

// Esc dismisses; Tab is trapped on the close button (the only focusable
// control), so focus can never escape the modal. No-op while closed.
function onKeydown(event: KeyboardEvent): void {
    if (!props.image) return;
    if (event.key === 'Escape') {
        emit('close');
    } else if (event.key === 'Tab') {
        event.preventDefault();
        closeButton.value?.focus();
    }
}

watch(
    () => props.image,
    (image) => {
        if (image) nextTick(() => closeButton.value?.focus());
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="image"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            role="dialog"
            aria-modal="true"
            data-testid="attachment-lightbox"
        >
            <!-- Backdrop is a real button so click-to-close is keyboard-operable. -->
            <button
                type="button"
                class="absolute inset-0 h-full w-full cursor-default"
                :aria-label="t('inbox.chat.attachment.close')"
                @click="$emit('close')"
            ></button>

            <button
                ref="closeButton"
                type="button"
                class="absolute end-4 top-4 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                :aria-label="t('inbox.chat.attachment.close')"
                @click="$emit('close')"
            >
                <XMarkIcon class="h-6 w-6" />
            </button>
            <img
                :src="image.url"
                :alt="image.title"
                referrerpolicy="no-referrer"
                class="relative z-10 max-h-[90vh] max-w-[90vw] rounded-lg object-contain"
            />
        </div>
    </Teleport>
</template>
