<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Phase-64 INBOX-POLISH-1: lightweight message-list virtualization.
 * Below VIRTUALIZE_THRESHOLD the component renders every message (no
 * windowing overhead). Above it, only a sliding window is mounted to
 * the DOM — bounding render time on threads with hundreds of
 * messages on low-end Kenyan mobile.
 *
 * No external library — native IntersectionObserver + index math.
 */
interface ThreadMessage {
    id: number;
    [key: string]: unknown;
}

const props = withDefaults(
    defineProps<{
        messages: ThreadMessage[];
        bufferSize?: number;
        windowSize?: number;
    }>(),
    {
        bufferSize: 20,
        windowSize: 60,
    },
);

const VIRTUALIZE_THRESHOLD = 100;

const shouldVirtualize = computed(() => props.messages.length > VIRTUALIZE_THRESHOLD);

const visibleStart = ref<number>(0);

const visibleEnd = computed<number>(() => {
    if (!shouldVirtualize.value) {
        return props.messages.length;
    }

    return Math.min(
        props.messages.length,
        visibleStart.value + props.windowSize + props.bufferSize,
    );
});

const renderStart = computed<number>(() => {
    if (!shouldVirtualize.value) {
        return 0;
    }

    return Math.max(0, visibleStart.value - props.bufferSize);
});

const visibleMessages = computed(() =>
    props.messages.slice(renderStart.value, visibleEnd.value),
);

const topSentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

function attachObserver(): void {
    if (!shouldVirtualize.value || topSentinel.value === null) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && visibleStart.value > 0) {
                    visibleStart.value = Math.max(
                        0,
                        visibleStart.value - props.windowSize,
                    );
                }
            }
        },
        { rootMargin: '200px 0px 0px 0px' },
    );

    observer.observe(topSentinel.value);
}

onMounted(() => {
    visibleStart.value = Math.max(
        0,
        props.messages.length - props.windowSize,
    );
    attachObserver();
});

watch(
    () => props.messages.length,
    () => {
        if (shouldVirtualize.value) {
            visibleStart.value = Math.max(
                0,
                props.messages.length - props.windowSize,
            );
        } else {
            visibleStart.value = 0;
        }
    },
);

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<template>
    <ol
        class="space-y-3"
        :data-virtualized="shouldVirtualize ? 'true' : 'false'"
        :data-message-count="messages.length"
        :data-rendered-count="visibleMessages.length"
    >
        <li
            v-if="shouldVirtualize && renderStart > 0"
            ref="topSentinel"
            class="h-px"
            aria-hidden="true"
            data-testid="virtual-list-top-sentinel"
        />

        <slot
            v-for="message in visibleMessages"
            :key="message.id"
            name="message"
            :message="message"
        />
    </ol>
</template>
