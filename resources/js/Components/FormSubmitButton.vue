<script setup lang="ts">
/**
 * Phase-20 FRONT-UX-2: canonical submit button for Inertia useForm()
 * flows. Auto-disables + dims while processing so double-submit is
 * impossible — critical for money-handling forms (payments, invoices,
 * credit notes, subscriptions).
 *
 * Usage:
 *   <FormSubmitButton :processing="form.processing">
 *     Record Payment
 *   </FormSubmitButton>
 *
 * For destructive actions (delete, cancel-subscription), pass
 * variant="danger" for red styling.
 */
interface Props {
    processing: boolean;
    variant?: 'primary' | 'danger' | 'secondary';
    type?: 'submit' | 'button';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    type: 'submit',
});

const variantClasses = {
    primary: 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-indigo-500',
};
</script>

<template>
    <button
        :type="type"
        :disabled="processing"
        :class="[
            'inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md',
            'focus:outline-none focus:ring-2 focus:ring-offset-2',
            'transition-opacity',
            variantClasses[variant],
            processing ? 'opacity-50 cursor-not-allowed' : 'opacity-100',
        ]"
    >
        <svg
            v-if="processing"
            class="animate-spin h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
        <slot />
    </button>
</template>
