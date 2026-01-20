<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useStatusColors } from '@/composables';

interface Props {
    priority: string;
}

const props = defineProps<Props>();

const { ticketPriorityColor } = useStatusColors();

const colorClasses = computed(() => ticketPriorityColor(props.priority));

const labels: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent'
};

const label = computed(() => labels[props.priority] || props.priority);
</script>

<template>
    <Badge :color-classes="colorClasses" :label="label" />
</template>
