<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useStatusColors, usePayments } from '@/composables';
import type { InvoiceStatus } from '@/types/finances';

type Size = 'sm' | 'md' | 'lg';
type BadgeColor = 'gray' | 'blue' | 'yellow' | 'green' | 'red';

interface Props {
    status: InvoiceStatus | string;
    size?: Size;
    showDot?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    showDot: false,
});

const { invoiceStatusColor } = useStatusColors();
const { getInvoiceStatusLabel } = usePayments();

const colorClasses = computed(() => invoiceStatusColor(props.status));
const label = computed(() => getInvoiceStatusLabel(props.status));

const colorForDot = computed((): BadgeColor => {
    const map: Record<string, BadgeColor> = {
        draft: 'gray',
        sent: 'blue',
        partial: 'yellow',
        paid: 'green',
        overdue: 'red',
    };
    return map[props.status] || 'gray';
});
</script>

<template>
    <Badge
        :color-classes="colorClasses"
        :color="colorForDot"
        :size="size"
        :show-dot="showDot"
        :label="label"
    />
</template>
