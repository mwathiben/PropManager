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
 *
 * Phase-51 VUE-TAIL-1 REPORTS-DRILL-UI: when BuilderController::drill
 * passes a drillContext prop, the page renders a parent-report banner
 * + pre-populates the form + highlights the drill_field column. The
 * saved-reports list gains a 'Drill from this report' affordance per
 * row that has drill_field set; clicking a preview row in drill mode
 * navigates to the drill route with the row's drill-field value as
 * the segment.
 */
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import { useI18n } from '@/composables/useI18n';

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
    drill_field?: string | null;
};

type DrillContext = {
    parent_id: number;
    parent_name: string;
    drill_field: string;
    segment_value: string;
    config: Record<string, unknown>;
    rows: Array<Record<string, unknown>>;
};

const props = defineProps<{
    savedReports: SavedReport[];
    allowedTables: string[];
    allowedFields: Record<string, FieldMeta>;
    operatorMatrix: Record<'numeric' | 'date' | 'string' | 'boolean', string[]>;
    drillContext?: DrillContext | null;
}>();

const { t } = useI18n();
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
const currentReportForDrill = ref<SavedReport | null>(null);

const fieldsByTable = computed(() => {
    const out: Record<string, Array<{ key: string; meta: FieldMeta }>> = {};
    for (const [key, meta] of Object.entries(props.allowedFields)) {
        out[meta.table] = out[meta.table] ?? [];
        out[meta.table].push({ key, meta });
    }
    return out;
});

const filterableFields = computed(() => Object.entries(props.allowedFields));

const activeDrillField = computed<string | null>(() => {
    if (props.drillContext) return props.drillContext.drill_field;
    return currentReportForDrill.value?.drill_field ?? null;
});

const drillFieldColumnKey = computed<string | null>(() => {
    const f = activeDrillField.value;
    return f ? f.replace('.', '_') : null;
});

const reportsWithDrill = computed(() =>
    props.savedReports.filter((r) => !!r.drill_field),
);

function addField(key: string): void {
    if (!fields.value.includes(key)) fields.value.push(key);
}

function removeField(key: string): void {
    fields.value = fields.value.filter((f) => f !== key);
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
            .filter((f) => f.field !== '')
            .map((f) => ({
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
    if (f.op === 'in' || f.op === 'not_in') return f.value.split(',').map((v) => v.trim());
    return f.value;
}

async function preview(): Promise<void> {
    errorMessage.value = '';
    isRunning.value = true;
    try {
        const response = await axios.post(route('reports.builder.run'), { config: buildConfig() });
        rows.value = response.data.rows;
    } catch (err: any) {
        errorMessage.value =
            err?.response?.data?.detail ??
            err?.response?.data?.message ??
            'Failed to run the report.';
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

function loadConfigFromObject(cfg: Record<string, unknown>): void {
    table.value = (cfg.table as string) ?? table.value;
    fields.value = (cfg.fields as string[]) ?? [];
    filters.value = ((cfg.filters as Array<Record<string, unknown>>) ?? []).map((f) => ({
        field: (f.field as string) ?? '',
        op: (f.op as string) ?? '=',
        value: f.value !== undefined && f.value !== null ? String(f.value) : '',
    }));
    groupBy.value = (cfg.group_by as string[]) ?? [];
    sortBy.value = (cfg.sort_by as SortEntry[]) ?? [];
    limit.value = (cfg.limit as number) ?? 200;
}

function loadReportForDrill(report: SavedReport): void {
    currentReportForDrill.value = report;
    loadConfigFromObject(report.config);
    void preview();
}

function clearDrillSelection(): void {
    currentReportForDrill.value = null;
}

function backToParent(): void {
    router.visit(route('reports.builder.index'));
}

function onRowClick(row: Record<string, unknown>): void {
    const drillReport = currentReportForDrill.value;
    const colKey = drillFieldColumnKey.value;
    if (!drillReport || !colKey) return;
    const segment = row[colKey];
    if (segment === undefined || segment === null || segment === '') return;
    router.visit(
        route('reports.builder.drill', { report: drillReport.id, segment: String(segment) }),
    );
}

onMounted(() => {
    if (props.drillContext) {
        loadConfigFromObject(props.drillContext.config);
        rows.value = props.drillContext.rows;
    }
});
</script>

<template>
    <Head title="Report builder" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('reports_builder.title') }}</h1>
        </template>

        <div class="px-4 py-6 lg:px-8">
            <div
                v-if="props.drillContext"
                class="mb-4 flex items-center justify-between rounded-lg border border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium text-indigo-900">
                        Drill-down from
                        <span class="font-semibold">{{ props.drillContext.parent_name }}</span>
                    </p>
                    <p class="text-xs text-indigo-700">
                        Showing rows where
                        <code class="rounded bg-white/60 px-1 font-mono">{{
                            props.drillContext.drill_field
                        }}</code>
                        =
                        <code class="rounded bg-white/60 px-1 font-mono">{{
                            props.drillContext.segment_value
                        }}</code>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-indigo-200 bg-white px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                    @click="backToParent"
                >
                    Back to parent
                </button>
            </div>

            <div
                v-else-if="currentReportForDrill"
                class="mb-4 flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm"
            >
                <p class="text-amber-900">
                    Drill mode — click any row's
                    <code class="rounded bg-white/60 px-1 font-mono">{{
                        currentReportForDrill.drill_field
                    }}</code>
                    cell to filter down.
                </p>
                <button
                    type="button"
                    class="rounded-md border border-amber-200 bg-white px-3 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50"
                    @click="clearDrillSelection"
                >
                    Exit drill mode
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left column: field picker + filters + actions -->
                <section class="lg:col-span-1 space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <label for="source-table" class="block text-xs font-medium uppercase tracking-wide text-gray-500"
                            >Source table</label
                        >
                        <select id="source-table" v-model="table" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="tbl in props.allowedTables" :key="tbl" :value="tbl">{{ tbl }}</option>
                        </select>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 class="text-sm font-semibold text-gray-900">Fields</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Click to add. The allowlist comes from the server — fields not listed here
                            can't be selected.
                        </p>
                        <div class="mt-3 space-y-3">
                            <div v-for="(group, gtable) in fieldsByTable" :key="gtable">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    {{ gtable }}
                                </p>
                                <button
                                    v-for="entry in group"
                                    :key="entry.key"
                                    type="button"
                                    class="me-1 mt-1 rounded-full bg-gray-100 px-2 py-1 text-xs hover:bg-indigo-100"
                                    @click="addField(entry.key)"
                                >
                                    {{ entry.meta.label }}
                                </button>
                            </div>
                        </div>
                        <ul v-if="fields.length" class="mt-3 space-y-1">
                            <li
                                v-for="f in fields"
                                :key="f"
                                class="flex items-center justify-between rounded-md bg-indigo-50 px-2 py-1 text-xs text-indigo-900"
                            >
                                <span>{{ props.allowedFields[f]?.label ?? f }}</span>
                                <button
                                    type="button"
                                    class="text-indigo-600 hover:text-rose-600"
                                    @click="removeField(f)"
                                >
                                    ×
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Filters</h2>
                            <button
                                type="button"
                                class="text-xs text-indigo-600 hover:underline"
                                @click="addFilter"
                            >
                                + filter
                            </button>
                        </div>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(filter, i) in filters"
                                :key="i"
                                class="grid grid-cols-12 gap-1 text-xs"
                            >
                                <select
                                    v-model="filter.field"
                                    class="col-span-5 rounded-md border-gray-300 text-xs"
                                >
                                    <option value="">— pick —</option>
                                    <option
                                        v-for="[key, meta] in filterableFields"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ meta.label }}
                                    </option>
                                </select>
                                <select
                                    v-model="filter.op"
                                    class="col-span-3 rounded-md border-gray-300 text-xs"
                                >
                                    <option
                                        v-for="op in operatorsFor(filter.field)"
                                        :key="op"
                                        :value="op"
                                    >
                                        {{ op }}
                                    </option>
                                </select>
                                <input
                                    v-model="filter.value"
                                    type="text"
                                    class="col-span-3 rounded-md border-gray-300 text-xs"
                                    placeholder="value"
                                />
                                <button
                                    type="button"
                                    class="col-span-1 text-rose-500 hover:text-rose-700"
                                    @click="removeFilter(i)"
                                >
                                    ×
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="isRunning || fields.length === 0"
                            @click="preview"
                        >
                            {{ isRunning ? 'Running…' : 'Run preview' }}
                        </button>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 class="text-sm font-semibold text-gray-900">Save</h2>
                        <input
                            v-model="name"
                            type="text"
                            placeholder="Name"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
                        />
                        <input
                            v-model="description"
                            type="text"
                            placeholder="Description (optional)"
                            class="mt-2 w-full rounded-md border-gray-300 text-sm"
                        />
                        <button
                            type="button"
                            class="mt-2 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                            :disabled="!name || fields.length === 0"
                            @click="save"
                        >
                            Save report
                        </button>
                    </div>
                </section>

                <!-- Right column: preview + saved -->
                <section class="lg:col-span-2 space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                        <p
                            v-if="errorMessage"
                            class="mt-1 rounded-md bg-rose-50 px-2 py-1 text-xs text-rose-900"
                        >
                            {{ errorMessage }}
                        </p>
                        <p
                            v-if="rows.length === 0 && !errorMessage"
                            class="mt-2 text-sm text-gray-500"
                        >
                            Run the report to see rows here.
                        </p>
                        <div v-else-if="rows.length" class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead>
                                    <tr>
                                        <th
                                            v-for="key in Object.keys(rows[0])"
                                            :key="key"
                                            class="px-2 py-1 text-start font-semibold text-gray-500"
                                            :class="
                                                key === drillFieldColumnKey ? 'bg-indigo-50/60' : ''
                                            "
                                        >
                                            {{ key }}
                                            <span
                                                v-if="key === drillFieldColumnKey"
                                                class="ms-1 text-indigo-500"
                                                title="Click a cell to drill down"
                                                >›</span
                                            >
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(row, i) in rows"
                                        :key="i"
                                        :class="
                                            currentReportForDrill && drillFieldColumnKey
                                                ? 'cursor-pointer hover:bg-indigo-50'
                                                : ''
                                        "
                                        @click="onRowClick(row)"
                                    >
                                        <td
                                            v-for="(value, key) in row"
                                            :key="key"
                                            class="px-2 py-1"
                                            :class="
                                                key === drillFieldColumnKey
                                                    ? 'bg-indigo-50/40 font-medium text-indigo-900'
                                                    : ''
                                            "
                                        >
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
                            <li
                                v-for="report in props.savedReports"
                                :key="report.id"
                                class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">{{ report.name }}</p>
                                    <p v-if="report.description" class="text-xs text-gray-500">
                                        {{ report.description }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="report.drill_field"
                                        type="button"
                                        class="rounded-md border border-indigo-200 bg-white px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                                        @click="loadReportForDrill(report)"
                                    >
                                        Drill mode
                                    </button>
                                    <span class="text-xs text-gray-400">{{ report.updated_at }}</span>
                                </div>
                            </li>
                            <li
                                v-if="props.savedReports.length === 0"
                                class="text-xs text-gray-500"
                            >
                                No saved reports yet.
                            </li>
                        </ul>
                        <p
                            v-if="reportsWithDrill.length === 0 && props.savedReports.length > 0"
                            class="mt-2 text-xs text-gray-400"
                        >
                            None of your saved reports have a drill_field set yet.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
