<script setup lang="ts">
/**
 * Phase-27 BI-DELIVERY-3: scheduled-reports self-serve UI.
 *
 * Minimal: list current schedules + a small form to create new ones
 * (pick saved report, cadence, recipient from the server-emitted
 * allowlist). The recipient picker enforces Phase-13 PERSONAL-DATA-1
 * — third-party emails are not selectable client-side either.
 */
import { ref } from 'vue';
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
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
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
        </div>
    </AuthenticatedLayout>
</template>
