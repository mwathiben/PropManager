<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import WizardSteps from '@/Components/Wizard/WizardSteps.vue';

const props = withDefaults(defineProps<{
    currentStep: number;
    totalSteps: number;
}>(), {
    totalSteps: 3,
});

const { t } = useI18n();

// Phase-48 WIZARD-PROGRESS-UX-1 → standardised on the shared WizardSteps
// (Phase-74 design system). Keeps the onboarding-specific label.
const label = computed(() =>
    t('onboarding.resume_banner.title', { current: props.currentStep, total: props.totalSteps }),
);
</script>

<template>
    <div class="mb-6">
        <WizardSteps :current-step="currentStep" :total-steps="totalSteps" :label="label" />
    </div>
</template>
