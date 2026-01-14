<script setup lang="ts">
import { computed } from 'vue';
import { useStatusColors, usePayments } from '@/composables';
import type { InvoiceStatus } from '@/types/finances';

type Size = 'sm' | 'md' | 'lg';

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

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'px-1.5 py-0.5 text-xs',
        md: 'px-2 py-1 text-xs',
        lg: 'px-2.5 py-1 text-sm',
    };
    return sizes[props.size];
});

const dotColorClasses = computed(() => {
    const colors = {
        draft: 'bg-gray-400',
        sent: 'bg-blue-400',
        partial: 'bg-yellow-400',
        paid: 'bg-green-400',
        overdue: 'bg-red-400',
    };
    return colors[props.status] || 'bg-gray-400';
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full font-medium',
            colorClasses,
            sizeClasses,
        ]"
    >
        <span v-if="showDot" :class="['h-1.5 w-1.5 rounded-full', dotColorClasses]" />
        {{ label }}
    </span>
</template>
