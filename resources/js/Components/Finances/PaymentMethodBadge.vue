<script setup>
import { computed } from 'vue';
import { useStatusColors, usePayments } from '@/composables';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    method: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    showIcon: {
        type: Boolean,
        default: true,
    },
});

const { paymentMethodColor } = useStatusColors();
const { getPaymentMethodLabel } = usePayments();

const colorClasses = computed(() => paymentMethodColor(props.method));
const label = computed(() => getPaymentMethodLabel(props.method));

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'px-1.5 py-0.5 text-xs',
        md: 'px-2 py-1 text-xs',
        lg: 'px-2.5 py-1 text-sm',
    };
    return sizes[props.size];
});

const iconComponent = computed(() => {
    const icons = {
        cash: BanknotesIcon,
        bank_transfer: BuildingLibraryIcon,
        mobile_money: DevicePhoneMobileIcon,
        mpesa: DevicePhoneMobileIcon,
        paystack: CreditCardIcon,
        stripe: CreditCardIcon,
    };
    return icons[props.method] || CreditCardIcon;
});

const iconSizeClasses = computed(() => {
    const sizes = {
        sm: 'h-3 w-3',
        md: 'h-3.5 w-3.5',
        lg: 'h-4 w-4',
    };
    return sizes[props.size];
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full font-medium',
            colorClasses,
            sizeClasses,
        ]"
    >
        <component v-if="showIcon" :is="iconComponent" :class="iconSizeClasses" />
        {{ label }}
    </span>
</template>
