<script setup>
/**
 * Phase-23 A11Y-FORM-1: form error association (WCAG 1.3.1, 3.3.1,
 * 4.1.2). When `errorMessage` is set the input renders
 * aria-invalid="true" and aria-describedby pointing at the
 * InputError element — id convention `${inputId}-error`. A
 * screen-reader user tabbing onto the field now hears the error,
 * not just the label.
 */
import { computed, onMounted, ref, useAttrs } from 'vue';

const props = defineProps({
    errorMessage: {
        type: String,
        default: '',
    },
});

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);
const attrs = useAttrs();

const errorId = computed(() =>
    attrs.id ? `${attrs.id}-error` : undefined,
);
const ariaInvalid = computed(() =>
    props.errorMessage ? 'true' : undefined,
);
const ariaDescribedby = computed(() =>
    props.errorMessage ? errorId.value : undefined,
);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        v-model="model"
        ref="input"
        :aria-invalid="ariaInvalid"
        :aria-describedby="ariaDescribedby"
    />
</template>
