<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useStatusColors } from '@/composables';

interface Props {
    status: string;
}

const props = defineProps<Props>();

const { ticketStatusColor } = useStatusColors();

const colorClasses = computed(() => ticketStatusColor(props.status));

const labels: Record<string, string> = {
    open: 'Open',
    acknowledged: 'Acknowledged',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
    cancelled: 'Cancelled'
};

const label = computed(() => labels[props.status] || props.status);
</script>

<template>
    <Badge :color-classes="colorClasses" :label="label" />
</template>
