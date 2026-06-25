<script setup lang="ts">
/**
 * Phase-27 BI-DELIVERY-3 + Phase-73 SCHEDULED-DEPTH: scheduled-reports
 * self-serve UI.
 *
 * List current schedules + a form to create new ones (pick saved report,
 * cadence, recipient from the server-emitted allowlist). The recipient
 * picker enforces Phase-13 PERSONAL-DATA-1 — third-party emails are not
 * selectable client-side either. Phase-73 adds in-row edit (cadence +
 * recipient) and pause/resume without deleting.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

type Cadence = 'weekly' | 'monthly' | 'quarterly';

type Schedule = {
    id: number;
    saved_report_id: number;
    cadence: Cadence;
    recipient_email: string;
    next_due_at: string;
    last_sent_at: string | null;
    paused_at: string | null;
    saved_report: { id: number; name: string; description: string | null } | null;
};

const props = defineProps<{
    schedules: Schedule[];
    savedReports: Array<{ id: number; name: string }>;
    cadences: Cadence[];
    allowedRecipients: string[];
}>();

const { t } = useI18n();
const { formatDateTime } = useFormatters();

const form = ref({
    saved_report_id: props.savedReports[0]?.id ?? null,
    cadence: 'weekly' as Cadence,
    recipient_email: props.allowedRecipients[0] ?? '',
});

function cadenceLabel(cadence: Cadence): string {
    return t(`reports.scheduled.cadence_${cadence}`);
}

function save(): void {
    router.post(route('reports.scheduled.store'), form.value, { preserveScroll: true });
}

function remove(id: number): void {
    router.delete(route('reports.scheduled.destroy', id), { preserveScroll: true });
}

// Phase-73 SCHEDULED-DEPTH: in-row edit of cadence + recipient.
const editingId = ref<number | null>(null);
const editForm = ref<{ cadence: Cadence; recipient_email: string }>({
    cadence: 'weekly',
    recipient_email: '',
});

function startEdit(schedule: Schedule): void {
    editingId.value = schedule.id;
    editForm.value = {
        cadence: schedule.cadence,
        recipient_email: schedule.recipient_email,
    };
}

function cancelEdit(): void {
    editingId.value = null;
}

function saveEdit(id: number): void {
    router.put(route('reports.scheduled.update', id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function togglePause(id: number): void {
    router.post(route('reports.scheduled.toggle-pause', id), {}, { preserveScroll: true });
}

// Phase-50 REAL-TIME-PREVIEW-2: ad-hoc fetch of the rows the next send
// would carry, with a polling refresh option for landlords who want a
// live view while they tweak source data.
type PreviewState = {
    loading: boolean;
    error: string | null;
    rows: Array<Record<string, unknown>>;
    previewedAt: string | null;
    reportName: string | null;
};

const preview = ref<PreviewState>({
    loading: false,
    error: null,
    rows: [],
    previewedAt: null,
    reportName: null,
});

const pollInterval = ref<number | null>(null);
const pollEnabled = ref(false);
// Phase-51 SCHEDULED-PREVIEW-UX-1: pause polling when tab hidden so we
// don't waste bandwidth on a tab the user isn't looking at.
const pollPaused = ref(false);
const pollPauseCount = ref(0);
// Phase-51 SCHEDULED-PREVIEW-UX-3: click-to-sort the preview table.
const sortKey = ref<string | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

const previewColumns = computed<string[]>(() => {
    if (preview.value.rows.length === 0) return [];
    return Object.keys(preview.value.rows[0]);
});

const sortedRows = computed<Array<Record<string, unknown>>>(() => {
    const rows = preview.value.rows;
    const key = sortKey.value;
    if (!key) return rows;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const allNumeric = rows.every((r) => {
        const v = r[key];
        return v === null || v === undefined || (typeof v !== 'object' && !isNaN(Number(v)));
    });
    return [...rows].sort((a, b) => {
        const av = a[key];
        const bv = b[key];
        if (av === bv) return 0;
        if (av === null || av === undefined) return 1;
        if (bv === null || bv === undefined) return -1;
        if (allNumeric) return (Number(av) - Number(bv)) * dir;
        return String(av).localeCompare(String(bv)) * dir;
    });
});

function setSort(col: string): void {
    if (sortKey.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = col;
        sortDir.value = 'asc';
    }
}

// Phase-51 SCHEDULED-PREVIEW-UX-2: retry transient fetch errors with
// exponential backoff. 4xx responses skip the retry — validation errors
// are permanent and don't recover by retrying.
async function fetchWithBackoff(savedReportId: number): Promise<void> {
    const delays = [0, 1000, 2000, 4000];
    let lastError: string | null = null;
    for (let attempt = 0; attempt < delays.length; attempt++) {
        if (delays[attempt] > 0) {
            await new Promise((r) => setTimeout(r, delays[attempt]));
        }
        try {
            const response = await fetch(route('reports.scheduled.preview'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ saved_report_id: savedReportId }),
            });
            if (response.status >= 400 && response.status < 500) {
                preview.value.error = t('reports.scheduled.preview_rejected', { status: response.status });
                return;
            }
            if (!response.ok) {
                lastError = t('reports.scheduled.preview_failed', { status: response.status });
                continue;
            }
            const data = (await response.json()) as {
                rows: Array<Record<string, unknown>>;
                previewed_at: string;
                report_name: string;
            };
            preview.value.rows = data.rows;
            preview.value.previewedAt = data.previewed_at;
            preview.value.reportName = data.report_name;
            preview.value.error = null;
            return;
        } catch (err) {
            lastError = (err as Error).message;
        }
    }
    preview.value.error = lastError ?? t('reports.scheduled.preview_retries');
}

async function fetchPreview(savedReportId: number | null): Promise<void> {
    if (!savedReportId) return;
    preview.value.loading = true;
    preview.value.error = null;
    try {
        await fetchWithBackoff(savedReportId);
    } finally {
        preview.value.loading = false;
    }
}

function startPollInterval(): void {
    if (pollInterval.value !== null) return;
    pollInterval.value = window.setInterval(() => {
        if (!pollPaused.value) {
            void fetchPreview(form.value.saved_report_id);
        }
    }, 15000);
}

function stopPollInterval(): void {
    if (pollInterval.value !== null) {
        window.clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
}

function togglePolling(): void {
    pollEnabled.value = !pollEnabled.value;
    if (pollEnabled.value) {
        startPollInterval();
    } else {
        stopPollInterval();
    }
}

function handleVisibility(): void {
    const wasHidden = pollPaused.value;
    pollPaused.value = document.visibilityState === 'hidden';
    if (!wasHidden && pollPaused.value) {
        pollPauseCount.value += 1;
    }
}

// Phase-53 VUE-TELEMETRY-3: post the accumulated pause count to the
// server gauge on page unload via sendBeacon so the
// vue_preview_poll_pause_count time-series exists in Prometheus
// without polling. sendBeacon survives unload but does NOT send
// CSRF tokens, which is why the route is CSRF-exempt in api.php.
function reportPollPauseCount(): void {
    if (pollPauseCount.value <= 0) return;
    const body = new Blob(
        [
            JSON.stringify({
                count: pollPauseCount.value,
                route: 'reports.scheduled',
            }),
        ],
        { type: 'application/json' },
    );
    try {
        navigator.sendBeacon(
            route('telemetry.vue-preview-poll-pause'),
            body,
        );
    } catch {
        // best-effort
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('beforeunload', reportPollPauseCount);
    handleVisibility();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibility);
    window.removeEventListener('beforeunload', reportPollPauseCount);
    reportPollPauseCount();
    stopPollInterval();
});
</script>

<template>
    <Head :title="t('reports.scheduled.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('reports.scheduled.title') }}</h1>
        </template>

        <div class="grid grid-cols-1 gap-6 px-4 py-6 lg:grid-cols-3 lg:px-8">
            <section class="lg:col-span-2 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-gray-900">{{ t('reports.scheduled.active_heading') }}</h2>
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">{{ t('reports.scheduled.col_report') }}</th>
                            <th class="px-2 py-2">{{ t('reports.scheduled.col_cadence') }}</th>
                            <th class="px-2 py-2">{{ t('reports.scheduled.col_recipient') }}</th>
                            <th class="px-2 py-2">{{ t('reports.scheduled.col_next_send') }}</th>
                            <th class="px-2 py-2">{{ t('reports.scheduled.col_status') }}</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="schedule in props.schedules" :key="schedule.id" data-testid="schedule-row">
                            <td class="px-2 py-2 font-medium text-gray-900">
                                {{ schedule.saved_report?.name ?? t('reports.scheduled.deleted_report') }}
                            </td>

                            <template v-if="editingId === schedule.id">
                                <td class="px-2 py-2">
                                    <select v-model="editForm.cadence" class="w-full rounded-md border-gray-300 text-xs" data-testid="edit-cadence" :aria-label="t('reports.scheduled.col_cadence')">
                                        <option v-for="cadence in props.cadences" :key="cadence" :value="cadence">
                                            {{ cadenceLabel(cadence) }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <select v-model="editForm.recipient_email" class="w-full rounded-md border-gray-300 text-xs" data-testid="edit-recipient" :aria-label="t('reports.scheduled.col_recipient')">
                                        <option v-for="email in props.allowedRecipients" :key="email" :value="email">
                                            {{ email }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-2 text-gray-500">{{ formatDateTime(schedule.next_due_at) }}</td>
                                <td class="px-2 py-2"></td>
                                <td class="px-2 py-2">
                                    <span class="flex gap-3 text-xs">
                                        <button type="button" class="text-indigo-600 hover:underline" @click="saveEdit(schedule.id)">
                                            {{ t('reports.scheduled.save_edit') }}
                                        </button>
                                        <button type="button" class="text-gray-500 hover:underline" @click="cancelEdit">
                                            {{ t('reports.scheduled.discard_edit') }}
                                        </button>
                                    </span>
                                </td>
                            </template>

                            <template v-else>
                                <td class="px-2 py-2 text-gray-600">{{ cadenceLabel(schedule.cadence) }}</td>
                                <td class="px-2 py-2 text-gray-600">{{ schedule.recipient_email }}</td>
                                <td class="px-2 py-2 text-gray-600">{{ formatDateTime(schedule.next_due_at) }}</td>
                                <td class="px-2 py-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="schedule.paused_at
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-emerald-100 text-emerald-800'"
                                    >
                                        {{ schedule.paused_at ? t('reports.scheduled.status_paused') : t('reports.scheduled.status_active') }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <span class="flex gap-3 text-xs">
                                        <button type="button" class="text-indigo-600 hover:underline" @click="startEdit(schedule)">
                                            {{ t('reports.scheduled.edit') }}
                                        </button>
                                        <button type="button" class="text-amber-600 hover:underline" @click="togglePause(schedule.id)">
                                            {{ schedule.paused_at ? t('reports.scheduled.resume') : t('reports.scheduled.pause') }}
                                        </button>
                                        <button type="button" class="text-rose-600 hover:underline" @click="remove(schedule.id)">
                                            {{ t('reports.scheduled.cancel') }}
                                        </button>
                                    </span>
                                </td>
                            </template>
                        </tr>
                        <tr v-if="props.schedules.length === 0">
                            <td colspan="6" class="px-2 py-6 text-center text-xs text-gray-500">
                                {{ t('reports.scheduled.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-gray-900">{{ t('reports.scheduled.new_heading') }}</h2>
                <form class="mt-3 space-y-3 text-sm" @submit.prevent="save">
                    <div>
                        <label for="sched-saved-report" class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('reports.scheduled.saved_report') }}</label>
                        <select id="sched-saved-report" v-model="form.saved_report_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="report in props.savedReports" :key="report.id" :value="report.id">
                                {{ report.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="sched-cadence" class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('reports.scheduled.cadence') }}</label>
                        <select id="sched-cadence" v-model="form.cadence" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="cadence in props.cadences" :key="cadence" :value="cadence">
                                {{ cadenceLabel(cadence) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="sched-recipient" class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('reports.scheduled.recipient') }}</label>
                        <select id="sched-recipient" v-model="form.recipient_email" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="email in props.allowedRecipients" :key="email" :value="email">
                                {{ email }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('reports.scheduled.recipient_hint') }}
                        </p>
                    </div>
                    <button type="submit"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                        :disabled="!form.saved_report_id">
                        {{ t('reports.scheduled.create') }}
                    </button>
                </form>
            </section>

            <section class="lg:col-span-3 rounded-lg border border-gray-200 bg-white p-4">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ t('reports.scheduled.preview_heading') }}</h2>
                        <p class="text-xs text-gray-500">
                            {{ t('reports.scheduled.preview_intro') }}
                            <span v-if="preview.previewedAt"> {{ t('reports.scheduled.preview_refreshed', { time: preview.previewedAt }) }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                            class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            :disabled="!form.saved_report_id || preview.loading"
                            @click="fetchPreview(form.saved_report_id)">
                            {{ preview.loading ? t('reports.scheduled.loading') : t('reports.scheduled.refresh') }}
                        </button>
                        <button type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-medium"
                            :class="pollEnabled
                                ? 'bg-rose-600 text-white hover:bg-rose-500'
                                : 'bg-indigo-600 text-white hover:bg-indigo-500'"
                            :disabled="!form.saved_report_id"
                            @click="togglePolling">
                            {{ pollEnabled ? t('reports.scheduled.auto_refresh_off') : t('reports.scheduled.auto_refresh_on') }}
                        </button>
                    </div>
                </header>

                <p
                    v-if="pollEnabled && pollPaused"
                    class="mt-2 inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                    aria-live="polite"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    {{ t('reports.scheduled.paused_tab', { count: pollPauseCount }) }}
                </p>

                <p v-if="preview.error" class="mt-3 rounded bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ preview.error }}
                </p>

                <div v-if="preview.rows.length > 0" class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th
                                    v-for="col in previewColumns"
                                    :key="col"
                                    class="cursor-pointer select-none px-2 py-2 hover:text-gray-700"
                                    @click="setSort(col)"
                                >
                                    {{ col }}
                                    <span v-if="sortKey === col" class="ms-1 text-indigo-500">
                                        {{ sortDir === 'asc' ? '▲' : '▼' }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, index) in sortedRows" :key="index">
                                <td v-for="col in previewColumns" :key="col" class="px-2 py-1.5 text-gray-700">
                                    {{ row[col] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="!preview.loading && preview.previewedAt" class="mt-3 text-xs text-gray-500">
                    {{ t('reports.scheduled.preview_empty') }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
