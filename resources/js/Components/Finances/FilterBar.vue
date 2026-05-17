<script setup lang="ts">
import { ref, watch } from 'vue';
import { useDebouncedSearch } from '@/composables';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    XMarkIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';
import type { Building } from '@/types/finances';

interface DateRange {
    from: string | null;
    to: string | null;
}

interface FilterModelValue {
    search: string;
    status: string;
    paymentMethod: string;
    buildingId: number | string | null;
    dateRange: DateRange;
    [key: string]: unknown;
}

interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    modelValue?: FilterModelValue;
    showSearch?: boolean;
    showStatus?: boolean;
    showPaymentMethod?: boolean;
    showBuilding?: boolean;
    showDateRange?: boolean;
    statusOptions?: SelectOption[];
    paymentMethodOptions?: SelectOption[];
    buildings?: Building[] | { id: number; name: string }[];
    searchPlaceholder?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => ({
        search: '',
        status: '',
        paymentMethod: '',
        buildingId: null,
        dateRange: { from: null, to: null },
    }),
    showSearch: true,
    showStatus: true,
    showPaymentMethod: false,
    showBuilding: true,
    showDateRange: true,
    statusOptions: () => [],
    paymentMethodOptions: () => [],
    buildings: () => [],
    searchPlaceholder: 'Search...',
    loading: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: FilterModelValue];
    filter: [payload: { key: string; value: unknown }];
    clear: [];
}>();

const { searchQuery, debouncedSearch } = useDebouncedSearch({
    initialValue: props.modelValue.search,
    delay: 300,
});

const showFilters = ref(false);

watch(debouncedSearch, (value) => {
    updateFilter('search', value);
});

watch(() => props.modelValue.search, (value) => {
    if (value !== searchQuery.value) {
        searchQuery.value = value;
    }
});

const updateFilter = (key, value) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
    emit('filter', { key, value });
};

const clearFilters = () => {
    searchQuery.value = '';
    emit('update:modelValue', {
        search: '',
        status: '',
        paymentMethod: '',
        buildingId: null,
        dateRange: { from: null, to: null },
    });
    emit('clear');
};

const activeFiltersCount = () => {
    let count = 0;
    if (props.modelValue.search) count++;
    if (props.modelValue.status) count++;
    if (props.modelValue.paymentMethod) count++;
    if (props.modelValue.buildingId) count++;
    if (props.modelValue.dateRange?.from || props.modelValue.dateRange?.to) count++;
    return count;
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div v-if="showSearch" class="flex-1 min-w-50">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
            </div>

            <button
                @click="showFilters = !showFilters"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border transition-colors',
                    activeFiltersCount() > 0
                        ? 'bg-indigo-50 border-indigo-200 text-indigo-700'
                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                ]"
            >
                <FunnelIcon class="h-4 w-4" />
                Filters
                <span
                    v-if="activeFiltersCount() > 0"
                    class="inline-flex items-center justify-center h-5 w-5 text-xs font-semibold bg-indigo-600 text-white rounded-full"
                >
                    {{ activeFiltersCount() }}
                </span>
                <ChevronDownIcon :class="['h-4 w-4 transition-transform', showFilters ? 'rotate-180' : '']" />
            </button>

            <button
                v-if="activeFiltersCount() > 0"
                @click="clearFilters"
                class="inline-flex items-center gap-1 px-2 py-2 text-sm text-gray-500 hover:text-gray-700"
            >
                <XMarkIcon class="h-4 w-4" />
                Clear
            </button>

            <slot name="actions" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="showFilters" class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-if="showStatus && statusOptions.length > 0">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                        <select
                            :value="modelValue.status"
                            @change="updateFilter('status', $event.target.value)"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="showPaymentMethod && paymentMethodOptions.length > 0">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                        <select
                            :value="modelValue.paymentMethod"
                            @change="updateFilter('paymentMethod', $event.target.value)"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Methods</option>
                            <option v-for="opt in paymentMethodOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="showBuilding && buildings.length > 0">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Building</label>
                        <select
                            :value="modelValue.buildingId"
                            @change="updateFilter('buildingId', $event.target.value || null)"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Buildings</option>
                            <option v-for="building in buildings" :key="building.id" :value="building.id">
                                {{ building.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="showDateRange" class="sm:col-span-2 lg:col-span-1">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="flex gap-2">
                            <input
                                type="date"
                                :value="modelValue.dateRange?.from"
                                @change="updateFilter('dateRange', { ...modelValue.dateRange, from: $event.target.value })"
                                class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <input
                                type="date"
                                :value="modelValue.dateRange?.to"
                                @change="updateFilter('dateRange', { ...modelValue.dateRange, to: $event.target.value })"
                                class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
