<script setup lang="ts">
/**
 * Phase-27 BI-DELIVERY-3: scheduled-reports self-serve UI.
 *
 * Minimal: list current schedules + a small form to create new ones
 * (pick saved report, cadence, recipient from the server-emitted
 * allowlist). The recipient picker enforces Phase-13 PERSONAL-DATA-1
 * — third-party emails are not selectable client-side either.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

type Schedule = {
    id: number;
    saved_report_id: number;
    cadence: 'weekly' | 'monthly' | 'quarterly';
    recipient_email: string;
    next_due_at: string;
    last_sent_at: string | null;
    saved_report: { id: number; name: string; description: string | null } | null;
};

const props = defineProps<{
    schedules: Schedule[];
    savedReports: Array<{ id: number; name: string }>;
    cadences: Array<'weekly' | 'monthly' | 'quarterly'>;
    allowedRecipients: string[];
}>();

const form = ref({
    saved_report_id: props.savedReports[0]?.id ?? null,
    cadence: 'weekly' as 'weekly' | 'monthly' | 'quarterly',
    recipient_email: props.allowedRecipients[0] ?? '',
});

function save(): void {
    router.post(route('reports.scheduled.store'), form.value);
}

function remove(id: number): void {
    router.delete(route('reports.scheduled.destroy', id));
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
                preview.value.error = `Preview rejected: ${response.status}`;
                return;
            }
            if (!response.ok) {
                lastError = `Preview failed: ${response.status}`;
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
    preview.value.error = lastError ?? 'Preview failed after 3 retries.';
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
    <Head title="Scheduled reports" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Scheduled reports</h1>
        </template>

        <div class="grid grid-cols-1 gap-6 px-4 py-6 lg:grid-cols-3 lg:px-8">
            <section class="lg:col-span-2 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-gray-900">Active schedules</h2>
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Report</th>
                            <th class="px-2 py-2">Cadence</th>
                            <th class="px-2 py-2">Recipient</th>
                            <th class="px-2 py-2">Next send</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="schedule in props.schedules" :key="schedule.id">
                            <td class="px-2 py-2 font-medium text-gray-900">
                                {{ schedule.saved_report?.name ?? '— deleted —' }}
                            </td>
                            <td class="px-2 py-2 text-gray-600 capitalize">{{ schedule.cadence }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ schedule.recipient_email }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ schedule.next_due_at }}</td>
                            <td class="px-2 py-2">
                                <button type="button"
                                    class="text-xs text-rose-600 hover:underline"
                                    @click="remove(schedule.id)">
                                    Cancel
                                </button>
                            </td>
                        </tr>
                        <tr v-if="props.schedules.length === 0">
                            <td colspan="5" class="px-2 py-6 text-center text-xs text-gray-500">
                                No scheduled reports yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-gray-900">New schedule</h2>
                <form class="mt-3 space-y-3 text-sm" @submit.prevent="save">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Saved report</label>
                        <select v-model="form.saved_report_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="report in props.savedReports" :key="report.id" :value="report.id">
                                {{ report.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Cadence</label>
                        <select v-model="form.cadence" class="mt-1 w-full rounded-md border-gray-300 text-sm capitalize">
                            <option v-for="cadence in props.cadences" :key="cadence" :value="cadence">
                                {{ cadence }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Recipient</label>
                        <select v-model="form.recipient_email" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option v-for="email in props.allowedRecipients" :key="email" :value="email">
                                {{ email }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Limited to your own email + caretakers on your account.
                        </p>
                    </div>
                    <button type="submit"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                        :disabled="!form.saved_report_id">
                        Schedule
                    </button>
                </form>
            </section>

            <section class="lg:col-span-3 rounded-lg border border-gray-200 bg-white p-4">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Preview next send</h2>
                        <p class="text-xs text-gray-500">
                            Rows the next mail would carry — read-only.
                            <span v-if="preview.previewedAt"> Refreshed {{ preview.previewedAt }}.</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                            class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            :disabled="!form.saved_report_id || preview.loading"
                            @click="fetchPreview(form.saved_report_id)">
                            {{ preview.loading ? 'Loading…' : 'Refresh preview' }}
                        </button>
                        <button type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-medium"
                            :class="pollEnabled
                                ? 'bg-rose-600 text-white hover:bg-rose-500'
                                : 'bg-indigo-600 text-white hover:bg-indigo-500'"
                            :disabled="!form.saved_report_id"
                            @click="togglePolling">
                            {{ pollEnabled ? 'Stop auto-refresh' : 'Auto-refresh (15s)' }}
                        </button>
                    </div>
                </header>

                <p
                    v-if="pollEnabled && pollPaused"
                    class="mt-2 inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                    aria-live="polite"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    Auto-refresh paused — tab hidden ({{ pollPauseCount }}× this session)
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
                    Preview returned 0 rows — the next send would carry no data.
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
