<script setup lang="ts">
import { computed, type Component } from 'vue';
import ChevronUpIcon from '@heroicons/vue/24/solid/ChevronUpIcon';
import ChevronDownIcon from '@heroicons/vue/24/solid/ChevronDownIcon';
import EmptyState from '@/Components/EmptyState.vue';
import { useI18n } from '@/composables/useI18n';
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
    // Phase-23 A11Y-SR-3: a screen reader needs the table named.
    // Rendered as a visually-hidden <caption>.
    caption?: string;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => [],
    loading: false,
    selectable: false,
    selectedIds: () => [],
    sortDirection: 'asc',
    rowKey: 'id',
    stickyHeader: false,
    compact: false,
});

const emit = defineEmits<{
    sort: [payload: SortState];
    select: [ids: (string | number)[], row: RowData];
    selectAll: [ids: (string | number)[]];
    rowClick: [row: RowData];
}>();

const { t } = useI18n();

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

// Phase-23 A11Y-SR-3: reflect the current sort state on the <th> so a
// screen reader can announce "sorted ascending" etc. (WCAG 1.3.1).
const ariaSortFor = (column): 'ascending' | 'descending' | 'none' | undefined => {
    if (!column.sortable) return undefined;
    if (props.sortBy !== column.key) return 'none';
    return props.sortDirection === 'asc' ? 'ascending' : 'descending';
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
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead :class="['bg-gray-50', stickyHeader ? 'sticky top-0 z-10' : '']">
                    <tr>
                        <th v-if="selectable" scope="col" :class="[cellClasses, 'w-10']">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                @change="handleSelectAll"
                                :aria-label="t('finances_data_table.select_all_rows')"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :aria-sort="ariaSortFor(column)"
                            :class="[cellClasses, 'text-start text-xs font-medium text-gray-500 uppercase tracking-wider', column.align === 'right' ? 'text-end' : column.align === 'center' ? 'text-center' : '', column.width ? column.width : '']"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                @click="handleSort(column)"
                                class="flex items-center gap-1 w-full uppercase tracking-wider cursor-pointer select-none hover:text-gray-700"
                                :class="[column.align === 'right' ? 'justify-end' : '']"
                            >
                                <span>{{ column.label }}</span>
                                <ChevronUpIcon
                                    v-if="sortBy === column.key && sortDirection === 'asc'"
                                    class="h-3.5 w-3.5 text-indigo-600"
                                    aria-hidden="true"
                                />
                                <ChevronDownIcon
                                    v-else-if="sortBy === column.key && sortDirection === 'desc'"
                                    class="h-3.5 w-3.5 text-indigo-600"
                                    aria-hidden="true"
                                />
                                <span v-else class="h-3.5 w-3.5" aria-hidden="true" />
                            </button>
                            <div v-else class="flex items-center gap-1" :class="[column.align === 'right' ? 'justify-end' : '']">
                                <span>{{ column.label }}</span>
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
                                    :title="emptyTitle ?? t('finances_data_table.no_data_found')"
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
                                    :aria-label="t('finances_data_table.select_row')"
                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                />
                            </td>
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                :class="[
                                    cellClasses,
                                    'text-sm',
                                    column.align === 'right' ? 'text-end' : column.align === 'center' ? 'text-center' : '',
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

<style scoped>
/* Virtual scrolling optimization: skip rendering off-screen rows */
:deep(tbody tr) {
    content-visibility: auto;
    contain-intrinsic-size: 0 52px;
}
</style>
