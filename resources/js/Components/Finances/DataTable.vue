<script setup lang="ts">
import { computed, type Component } from 'vue';
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/24/solid';
import EmptyState from './EmptyState.vue';
import type { ColumnDefinition, SortState } from '@/types/finances';

type SortDirection = 'asc' | 'desc';

interface RowData {
    [key: string]: unknown;
}

interface Props {
    columns: ColumnDefinition[];
    data?: RowData[];
    loading?: boolean;
    selectable?: boolean;
    selectedIds?: (string | number)[];
    sortBy?: string;
    sortDirection?: SortDirection;
    rowKey?: string;
    emptyIcon?: Component;
    emptyTitle?: string;
    emptyDescription?: string;
    stickyHeader?: boolean;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => [],
    loading: false,
    selectable: false,
    selectedIds: () => [],
    sortDirection: 'asc',
    rowKey: 'id',
    emptyTitle: 'No data found',
    stickyHeader: false,
    compact: false,
});

const emit = defineEmits<{
    sort: [payload: SortState];
    select: [ids: (string | number)[], row: RowData];
    selectAll: [ids: (string | number)[]];
    rowClick: [row: RowData];
}>();

const allSelected = computed(() => {
    if (!props.selectable || props.data.length === 0) return false;
    return props.data.every(row => props.selectedIds.includes(row[props.rowKey]));
});

const someSelected = computed(() => {
    if (!props.selectable || props.data.length === 0) return false;
    const selectedCount = props.data.filter(row => props.selectedIds.includes(row[props.rowKey])).length;
    return selectedCount > 0 && selectedCount < props.data.length;
});

const handleSort = (column) => {
    if (!column.sortable) return;
    const direction = props.sortBy === column.key && props.sortDirection === 'asc' ? 'desc' : 'asc';
    emit('sort', { key: column.key, direction });
};

const handleSelectAll = () => {
    if (allSelected.value) {
        emit('selectAll', []);
    } else {
        emit('selectAll', props.data.map(row => row[props.rowKey]));
    }
};

const handleSelect = (row) => {
    const id = row[props.rowKey];
    const newSelection = props.selectedIds.includes(id)
        ? props.selectedIds.filter(i => i !== id)
        : [...props.selectedIds, id];
    emit('select', newSelection, row);
};

const isSelected = (row) => {
    return props.selectedIds.includes(row[props.rowKey]);
};

const cellClasses = computed(() => {
    return props.compact ? 'px-3 py-2' : 'px-4 py-3';
});
</script>

<template>
    <div class="overflow-hidden border border-gray-200 rounded-xl">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead :class="['bg-gray-50', stickyHeader ? 'sticky top-0 z-10' : '']">
                    <tr>
                        <th v-if="selectable" :class="[cellClasses, 'w-10']">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                @change="handleSelectAll"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                cellClasses,
                                'text-left text-xs font-medium text-gray-500 uppercase tracking-wider',
                                column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : '',
                                column.width ? column.width : '',
                                column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : '',
                            ]"
                            @click="handleSort(column)"
                        >
                            <div class="flex items-center gap-1" :class="[column.align === 'right' ? 'justify-end' : '']">
                                <span>{{ column.label }}</span>
                                <template v-if="column.sortable">
                                    <ChevronUpIcon
                                        v-if="sortBy === column.key && sortDirection === 'asc'"
                                        class="h-3.5 w-3.5 text-indigo-600"
                                    />
                                    <ChevronDownIcon
                                        v-else-if="sortBy === column.key && sortDirection === 'desc'"
                                        class="h-3.5 w-3.5 text-indigo-600"
                                    />
                                    <span v-else class="h-3.5 w-3.5" />
                                </template>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template v-if="loading">
                        <tr v-for="i in 5" :key="i">
                            <td v-if="selectable" :class="cellClasses">
                                <div class="h-4 w-4 bg-gray-200 rounded animate-pulse" />
                            </td>
                            <td v-for="column in columns" :key="column.key" :class="cellClasses">
                                <div class="h-4 bg-gray-200 rounded animate-pulse" :class="column.width || 'w-24'" />
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="data.length === 0">
                        <tr>
                            <td :colspan="selectable ? columns.length + 1 : columns.length">
                                <EmptyState
                                    :icon="emptyIcon"
                                    :title="emptyTitle"
                                    :description="emptyDescription"
                                    size="sm"
                                />
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr
                            v-for="row in data"
                            :key="row[rowKey]"
                            :class="[
                                'transition-colors',
                                isSelected(row) ? 'bg-indigo-50' : 'hover:bg-gray-50',
                                $attrs.onRowClick ? 'cursor-pointer' : '',
                            ]"
                            @click="emit('rowClick', row)"
                        >
                            <td v-if="selectable" :class="cellClasses" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="isSelected(row)"
                                    @change="handleSelect(row)"
                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                />
                            </td>
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                :class="[
                                    cellClasses,
                                    'text-sm',
                                    column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : '',
                                ]"
                            >
                                <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                    <span class="text-gray-900">{{ row[column.key] ?? '-' }}</span>
                                </slot>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
