<script setup lang="ts">
import { computed } from 'vue';
import type { PaymentMethodOption } from '@/types/finances';
import {
    BanknotesIcon,
    BuildingLibraryIcon,
    DevicePhoneMobileIcon,
    CreditCardIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

interface Props {
    modelValue: string | null;
    methods: PaymentMethodOption[];
    mode?: 'dropdown' | 'card';
    error?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    mode: 'dropdown',
    error: '',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const iconMap: Record<string, typeof BanknotesIcon> = {
    cash: BanknotesIcon,
    bank_transfer: BuildingLibraryIcon,
    mobile_money: DevicePhoneMobileIcon,
    intasend_mpesa: DevicePhoneMobileIcon,
    paystack: CreditCardIcon,
};

const getIcon = (methodId: string) => iconMap[methodId] || CreditCardIcon;

const selectClasses = computed(() => [
    'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
    props.error
        ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
        : 'border-gray-300 focus:ring-emerald-500 focus:border-emerald-500',
]);

const onDropdownChange = (event: Event) => {
    emit('update:modelValue', (event.target as HTMLSelectElement).value);
};
</script>

<template>
    <div>
        <template v-if="mode === 'dropdown'">
            <select
                id="payment-method-select"
                :value="modelValue"
                @change="onDropdownChange"
                :disabled="disabled"
                :class="selectClasses"
                aria-label="Payment method"
            >
                <option v-for="method in methods" :key="method.id" :value="method.id">
                    {{ method.label }}
                </option>
            </select>
        </template>

        <template v-else>
            <div class="space-y-3">
                <button
                    v-for="method in methods"
                    :key="method.id"
                    type="button"
                    :disabled="disabled"
                    @click="emit('update:modelValue', method.id)"
                    :class="['w-full flex items-start gap-4 p-4 rounded-xl border-2 transition-all text-start', modelValue === method.id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300']"
                >
                    <div :class="[
                        'p-2.5 rounded-lg',
                        modelValue === method.id ? 'bg-emerald-100' : 'bg-gray-100'
                    ]">
                        <component
                            :is="getIcon(method.id)"
                            :class="[
                                'h-5 w-5',
                                modelValue === method.id ? 'text-emerald-600' : 'text-gray-500'
                            ]"
                        />
                    </div>
                    <div class="flex-1">
                        <p :class="[
                            'font-medium',
                            modelValue === method.id ? 'text-emerald-900' : 'text-gray-900'
                        ]">
                            {{ method.label }}
                        </p>
                        <p v-if="method.description" class="text-sm text-gray-500 mt-0.5">
                            {{ method.description }}
                        </p>
                    </div>
                    <div v-if="modelValue === method.id" class="shrink-0">
                        <CheckCircleIcon class="h-6 w-6 text-emerald-500" />
                    </div>
                </button>
            </div>
        </template>

        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
