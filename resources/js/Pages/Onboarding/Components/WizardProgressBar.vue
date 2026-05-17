<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    currentStep: number;
    totalSteps: number;
}>(), {
    totalSteps: 3,
});

const percent = computed(() => Math.round((props.currentStep / props.totalSteps) * 100));
</script>

<template>
    <!-- Phase-48 WIZARD-PROGRESS-UX-1: shared between TenantSteps + CaretakerSteps. -->
    <div class="mb-6">
        <div class="flex justify-between text-sm mb-2">
            <span class="font-medium text-gray-700">
                {{ $t('onboarding.resume_banner.title', { current: currentStep, total: totalSteps }) }}
            </span>
            <span class="text-gray-500">{{ percent }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div
                class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all duration-500"
                :style="{ width: percent + '%' }"
            />
        </div>
    </div>
</template>
