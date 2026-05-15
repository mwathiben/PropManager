<script setup lang="ts">
/**
 * Phase-27 BI-BUILDER-1/2/3: custom report builder page.
 *
 * UI is intentionally simple — click-to-add field/filter/group-by
 * rather than full drag-drop (which is a Phase-N polish item). The
 * contract that matters: every picker reads from the server-emitted
 * allowedFields prop, so the UI cannot synthesise an unsafe field
 * value even if a malicious actor edits the DOM. The
 * ReportBuilderService validates again on the server.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

type FieldMeta = {
    table: string;
    column: string;
    type: 'numeric' | 'date' | 'string' | 'boolean';
    label: string;
};

type Filter = { field: string; op: string; value: string };
type SortEntry = { field: string; direction: 'asc' | 'desc' };

type SavedReport = {
    id: number;
    name: string;
    description: string | null;
    config: Record<string, unknown>;
    updated_at: string;
};

const props = defineProps<{
    savedReports: SavedReport[];
    allowedTables: string[];
    allowedFields: Record<string, FieldMeta>;
    operatorMatrix: Record<'numeric' | 'date' | 'string' | 'boolean', string[]>;
}>();

const table = ref<string>(props.allowedTables[0] ?? 'payments');
const fields = ref<string[]>([]);
const filters = ref<Filter[]>([]);
const groupBy = ref<string[]>([]);
const sortBy = ref<SortEntry[]>([]);
const limit = ref(200);

const name = ref('');
const description = ref('');
const rows = ref<Array<Record<string, unknown>>>([]);
const errorMessage = ref('');
const isRunning = ref(false);

const fieldsByTable = computed(() => {
    const out: Record<string, Array<{ key: string; meta: FieldMeta }>> = {};
    for (const [key, meta] of Object.entries(props.allowedFields)) {
        out[meta.table] = out[meta.table] ?? [];
        out[meta.table].push({ key, meta });
    }
    return out;
});

const filterableFields = computed(() => Object.entries(props.allowedFields));

function addField(key: string): void {
    if (!fields.value.includes(key)) fields.value.push(key);
}

function removeField(key: string): void {
    fields.value = fields.value.filter(f => f !== key);
}

function addFilter(): void {
    filters.value.push({ field: '', op: '=', value: '' });
}

function removeFilter(i: number): void {
    filters.value.splice(i, 1);
}

function operatorsFor(fieldKey: string): string[] {
    const meta = props.allowedFields[fieldKey];
    return meta ? props.operatorMatrix[meta.type] : [];
}

function buildConfig(): Record<string, unknown> {
    return {
        table: table.value,
        fields: fields.value,
        filters: filters.value
            .filter(f => f.field !== '')
            .map(f => ({
                field: f.field,
                op: f.op,
                value: coerceValue(f),
            })),
        group_by: groupBy.value,
        sort_by: sortBy.value,
        limit: limit.value,
    };
}

function coerceValue(f: Filter): unknown {
    const meta = props.allowedFields[f.field];
    if (!meta) return f.value;
    if (meta.type === 'numeric') return Number(f.value);
    if (meta.type === 'boolean') return f.value === 'true';
    if (f.op === 'in' || f.op === 'not_in') return f.value.split(',').map(v => v.trim());
    return f.value;
}

async function preview(): Promise<void> {
    errorMessage.value = '';
    isRunning.value = true;
    try {
        const response = await axios.post(route('reports.builder.run'), { config: buildConfig() });
        rows.value = response.data.rows;
    } catch (err: any) {
        errorMessage.value = err?.response?.data?.detail
            ?? err?.response?.data?.message
            ?? 'Failed to run the report.';
    } finally {
        isRunning.value = false;
    }
}

function save(): void {
    router.post(route('reports.builder.store'), {
        name: name.value,
        description: description.value,
        config: buildConfig(),
    });
}
</script>

<template>
    <Head title="Report builder" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Report builder</h1>
        </template>

        <div class="grid grid-cols-1 gap-6 px-4 py-6 lg:grid-cols-3 lg:px-8">
            <!-- Left column: field picker + filters + actions -->
            <section class="lg:col-span-1 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Source table</label>
                    <select v-model="table" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option v-for="t in props.allowedTables" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Fields</h2>
                    <p class="mt-1 text-xs text-gray-500">Click to add. The allowlist comes from the server — fields not listed here can't be selected.</p>
                    <div class="mt-3 space-y-3">
                        <div v-for="(group, gtable) in fieldsByTable" :key="gtable">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ gtable }}</p>
                            <button v-for="entry in group" :key="entry.key" type="button"
                                class="mr-1 mt-1 rounded-full bg-gray-100 px-2 py-1 text-xs hover:bg-indigo-100"
                                @click="addField(entry.key)">
                                {{ entry.meta.label }}
                            </button>
                        </div>
                    </div>
                    <ul v-if="fields.length" class="mt-3 space-y-1">
                        <li v-for="f in fields" :key="f"
                            class="flex items-center justify-between rounded-md bg-indigo-50 px-2 py-1 text-xs text-indigo-900">
                            <span>{{ props.allowedFields[f]?.label ?? f }}</span>
                            <button type="button" class="text-indigo-600 hover:text-rose-600" @click="removeField(f)">×</button>
                        </li>
                    </ul>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">Filters</h2>
                        <button type="button" class="text-xs text-indigo-600 hover:underline" @click="addFilter">+ filter</button>
                    </div>
                    <ul class="mt-3 space-y-2">
                        <li v-for="(filter, i) in filters" :key="i" class="grid grid-cols-12 gap-1 text-xs">
                            <select v-model="filter.field" class="col-span-5 rounded-md border-gray-300 text-xs">
                                <option value="">— pick —</option>
                                <option v-for="[key, meta] in filterableFields" :key="key" :value="key">{{ meta.label }}</option>
                            </select>
                            <select v-model="filter.op" class="col-span-3 rounded-md border-gray-300 text-xs">
                                <option v-for="op in operatorsFor(filter.field)" :key="op" :value="op">{{ op }}</option>
                            </select>
                            <input v-model="filter.value" type="text" class="col-span-3 rounded-md border-gray-300 text-xs"
                                placeholder="value" />
                            <button type="button" class="col-span-1 text-rose-500 hover:text-rose-700"
                                @click="removeFilter(i)">×</button>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="isRunning || fields.length === 0" @click="preview">
                        {{ isRunning ? 'Running…' : 'Run preview' }}
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Save</h2>
                    <input v-model="name" type="text" placeholder="Name"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    <input v-model="description" type="text" placeholder="Description (optional)"
                        class="mt-2 w-full rounded-md border-gray-300 text-sm" />
                    <button type="button"
                        class="mt-2 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                        :disabled="!name || fields.length === 0" @click="save">
                        Save report
                    </button>
                </div>
            </section>

            <!-- Right column: preview + saved -->
            <section class="lg:col-span-2 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                    <p v-if="errorMessage" class="mt-1 rounded-md bg-rose-50 px-2 py-1 text-xs text-rose-900">
                        {{ errorMessage }}
                    </p>
                    <p v-if="rows.length === 0 && !errorMessage" class="mt-2 text-sm text-gray-500">
                        Run the report to see rows here.
                    </p>
                    <div v-else-if="rows.length" class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead>
                                <tr>
                                    <th v-for="key in Object.keys(rows[0])" :key="key"
                                        class="px-2 py-1 text-left font-semibold text-gray-500">{{ key }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, i) in rows" :key="i">
                                    <td v-for="value in Object.values(row)" :key="String(value)" class="px-2 py-1">
                                        {{ value }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Saved reports</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="report in props.savedReports" :key="report.id"
                            class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2">
                            <div>
                                <p class="font-medium text-gray-900">{{ report.name }}</p>
                                <p v-if="report.description" class="text-xs text-gray-500">{{ report.description }}</p>
                            </div>
                            <span class="text-xs text-gray-400">{{ report.updated_at }}</span>
                        </li>
                        <li v-if="props.savedReports.length === 0" class="text-xs text-gray-500">
                            No saved reports yet.
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
