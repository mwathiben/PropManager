<script setup>
/**
 * Phase-23 A11Y-FORM-2: a `required` prop renders a visible asterisk
 * marker PLUS sr-only " (required)" text (WCAG 1.3.1, 3.3.2). The
 * asterisk is aria-hidden so the sr-only text is the single spoken
 * source — colour/symbol is never the sole cue. Pass :required in
 * sync with the input's own native `required` attribute.
 */
const props = defineProps({
    value: {
        type: String,
    },
    // A11Y-FORM: associate this label with its control (satisfies label-has-for).
    for: {
        type: String,
        default: undefined,
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <label :for="props.for" class="block text-sm font-medium text-gray-700">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
        <span v-if="required">
            <span class="text-red-600" aria-hidden="true">*</span>
            <span class="sr-only"> (required)</span>
        </span>
    </label>
</template>
