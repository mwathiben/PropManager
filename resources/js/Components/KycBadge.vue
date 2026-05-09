<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useStatusColors, useFormatters } from '@/composables';

interface Props {
    completed?: boolean;
    completedAt?: string | null;
    showDate?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    completed: false,
    completedAt: null,
    showDate: false
});

const { kycStatusColor } = useStatusColors();
const { formatDate } = useFormatters();

const colorClasses = computed(() => kycStatusColor(props.completed));

const label = computed(() => {
    let text = props.completed ? 'KYC Complete' : 'KYC Incomplete';
    if (props.showDate && props.completed && props.completedAt) {
        text += ` (${formatDate(props.completedAt)})`;
    }
    return text;
});
</script>

<template>
    <Badge :color-classes="colorClasses" :label="label">
        <template #icon>
            <svg v-if="completed" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
        </template>
    </Badge>
</template>
