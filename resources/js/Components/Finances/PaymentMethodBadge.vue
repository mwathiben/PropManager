<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useStatusColors, usePayments } from '@/composables';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
} from '@heroicons/vue/24/outline';
import type { PaymentMethod } from '@/types/finances';

type Size = 'sm' | 'md' | 'lg';

interface Props {
    method: PaymentMethod | string;
    size?: Size;
    showIcon?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    showIcon: true,
});

const { paymentMethodColor } = useStatusColors();
const { getPaymentMethodLabel } = usePayments();

const colorClasses = computed(() => paymentMethodColor(props.method));
const label = computed(() => getPaymentMethodLabel(props.method));

const iconComponent = computed(() => {
    const icons: Record<string, typeof BanknotesIcon> = {
        cash: BanknotesIcon,
        bank_transfer: BuildingLibraryIcon,
        mobile_money: DevicePhoneMobileIcon,
        intasend_mpesa: DevicePhoneMobileIcon,
        paystack: CreditCardIcon,
    };
    return icons[props.method] || CreditCardIcon;
});

const iconSizeClasses = computed(() => {
    const sizes: Record<Size, string> = {
        sm: 'h-3 w-3',
        md: 'h-3.5 w-3.5',
        lg: 'h-4 w-4',
    };
    return sizes[props.size];
});
</script>

<template>
    <Badge :color-classes="colorClasses" :size="size" :label="label">
        <template v-if="showIcon" #icon>
            <component :is="iconComponent" :class="iconSizeClasses" />
        </template>
    </Badge>
</template>
