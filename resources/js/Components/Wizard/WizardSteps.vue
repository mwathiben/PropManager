<script setup lang="ts">
/**
 * Canonical wizard step indicator — the single source of truth for the wizard
 * archetype's progress UI (references: Onboarding WizardProgressBar +
 * Notifications SetupWizard, which both use the indigo→purple gradient bar).
 *
 * Renders the gradient progress bar + a "Step X of N" label, and — when step
 * labels are supplied — a pill rail marking done / current / upcoming steps.
 * Page wizards (Legal-Hold, Onboarding) render this above their step content.
 */
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(defineProps<{
    currentStep: number;
    totalSteps: number;
    steps?: string[];
    label?: string;
}>(), {
    steps: () => [],
    label: '',
});

const { t } = useI18n();

const percent = computed(() => Math.round((props.currentStep / props.totalSteps) * 100));
const stepLabel = computed(() => props.label || t('common.wizard.step_of', { current: props.currentStep, total: props.totalSteps }));
</script>

<template>
    <div class="space-y-2" data-testid="wizard-steps">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <ol v-if="steps.length" class="flex flex-wrap gap-2">
                <li
                    v-for="(label, i) in steps"
                    :key="i"
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="i === currentStep - 1
                        ? 'bg-indigo-600 text-white'
                        : i < currentStep - 1
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-gray-100 text-gray-500'"
                >
                    {{ label }}
                </li>
            </ol>
            <span class="text-xs text-gray-400">{{ stepLabel }}</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div
                class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all duration-500"
                :style="{ width: percent + '%' }"
            />
        </div>
    </div>
</template>
