<script setup lang="ts">
/**
 * Phase-27 BI-DELIVERY-3: scheduled-reports self-serve UI.
 *
 * Minimal: list current schedules + a small form to create new ones
 * (pick saved report, cadence, recipient from the server-emitted
 * allowlist). The recipient picker enforces Phase-13 PERSONAL-DATA-1
 * — third-party emails are not selectable client-side either.
 */
import { computed, onUnmounted, ref } from 'vue';
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

const previewColumns = computed<string[]>(() => {
    if (preview.value.rows.length === 0) return [];
    return Object.keys(preview.value.rows[0]);
});

async function fetchPreview(savedReportId: number | null): Promise<void> {
    if (!savedReportId) return;
    preview.value.loading = true;
    preview.value.error = null;
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
        if (!response.ok) {
            preview.value.error = `Preview failed: ${response.status}`;
            return;
        }
        const data = (await response.json()) as {
            rows: Array<Record<string, unknown>>;
            previewed_at: string;
            report_name: string;
        };
        preview.value.rows = data.rows;
        preview.value.previewedAt = data.previewed_at;
        preview.value.reportName = data.report_name;
    } catch (err) {
        preview.value.error = (err as Error).message;
    } finally {
        preview.value.loading = false;
    }
}

function togglePolling(): void {
    pollEnabled.value = !pollEnabled.value;
    if (pollEnabled.value) {
        pollInterval.value = window.setInterval(
            () => fetchPreview(form.value.saved_report_id),
            15000,
        );
    } else if (pollInterval.value !== null) {
        window.clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
}

onUnmounted(() => {
    if (pollInterval.value !== null) {
        window.clearInterval(pollInterval.value);
    }
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

                <p v-if="preview.error" class="mt-3 rounded bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ preview.error }}
                </p>

                <div v-if="preview.rows.length > 0" class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th v-for="col in previewColumns" :key="col" class="px-2 py-2">{{ col }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, index) in preview.rows" :key="index">
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
