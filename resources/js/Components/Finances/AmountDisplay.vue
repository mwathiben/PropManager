<script setup>
import { computed } from 'vue';
import { useFormatters } from '@/composables';

const props = defineProps({
    amount: {
        type: [Number, String],
        required: true,
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
    showSign: {
        type: Boolean,
        default: false,
    },
    colorize: {
        type: Boolean,
        default: false,
    },
    currency: {
        type: String,
        default: 'KES',
    },
});

const { formatMoney } = useFormatters();

const numericAmount = computed(() => {
    const val = parseFloat(props.amount);
    return isNaN(val) ? 0 : val;
});

const formattedAmount = computed(() => {
    const formatted = formatMoney(Math.abs(numericAmount.value), { currency: props.currency });
    if (props.showSign && numericAmount.value !== 0) {
        return numericAmount.value > 0 ? `+${formatted}` : `-${formatted}`;
    }
    return numericAmount.value < 0 ? `-${formatted}` : formatted;
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'text-sm',
        md: 'text-base',
        lg: 'text-lg font-medium',
        xl: 'text-2xl font-semibold',
    };
    return sizes[props.size];
});

const colorClasses = computed(() => {
    if (!props.colorize) return 'text-gray-900';
    if (numericAmount.value > 0) return 'text-emerald-600';
    if (numericAmount.value < 0) return 'text-red-600';
    return 'text-gray-900';
});
</script>

<template>
    <span :class="[sizeClasses, colorClasses]">
        {{ formattedAmount }}
    </span>
</template>
