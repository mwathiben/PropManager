<script setup>
import { ref, computed, watch } from 'vue';
import { CalendarIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: {
        type: String,
        default: 'this_month',
    },
    startDate: String,
    endDate: String,
});

const emit = defineEmits(['update:modelValue', 'update:startDate', 'update:endDate', 'change']);

const isOpen = ref(false);
const localStartDate = ref(props.startDate || '');
const localEndDate = ref(props.endDate || '');

const periods = [
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_quarter', label: 'This Quarter' },
    { value: 'last_quarter', label: 'Last Quarter' },
    { value: 'this_year', label: 'This Year' },
    { value: 'custom', label: 'Custom Range' },
];

const selectedPeriod = computed(() => {
    return periods.find(p => p.value === props.modelValue) || periods[0];
});

const isCustom = computed(() => props.modelValue === 'custom');

const selectPeriod = (value) => {
    emit('update:modelValue', value);
    if (value !== 'custom') {
        isOpen.value = false;
        emit('change');
    }
};

const applyCustomRange = () => {
    emit('update:startDate', localStartDate.value);
    emit('update:endDate', localEndDate.value);
    isOpen.value = false;
    emit('change');
};

// Close dropdown when clicking outside
const closeDropdown = (e) => {
    if (!e.target.closest('.time-filter-dropdown')) {
        isOpen.value = false;
    }
};

// Watch for prop changes
watch(() => props.startDate, (val) => { localStartDate.value = val || ''; });
watch(() => props.endDate, (val) => { localEndDate.value = val || ''; });
</script>

<template>
    <div class="relative time-filter-dropdown" v-click-outside="closeDropdown">
        <!-- Trigger Button -->
        <button
            @click="isOpen = !isOpen"
            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
        >
            <CalendarIcon class="w-5 h-5 text-gray-400" />
            <span>{{ selectedPeriod.label }}</span>
            <ChevronDownIcon class="w-4 h-4 text-gray-400" />
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg border border-gray-200 z-50 overflow-hidden"
        >
            <!-- Period Options -->
            <div class="p-2">
                <button
                    v-for="period in periods"
                    :key="period.value"
                    @click="selectPeriod(period.value)"
                    class="w-full px-3 py-2 text-left text-sm rounded-lg transition"
                    :class="modelValue === period.value
                        ? 'bg-indigo-50 text-indigo-700 font-medium'
                        : 'text-gray-700 hover:bg-gray-50'"
                >
                    {{ period.label }}
                </button>
            </div>

            <!-- Custom Date Range -->
            <div v-if="isCustom" class="border-t border-gray-200 p-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                    <input
                        v-model="localStartDate"
                        type="date"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                    <input
                        v-model="localEndDate"
                        type="date"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <button
                    @click="applyCustomRange"
                    class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition"
                >
                    Apply Range
                </button>
            </div>
        </div>
    </div>
</template>
