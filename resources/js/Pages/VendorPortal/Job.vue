<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import VendorPortalLayout from '@/Layouts/VendorPortalLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

interface TimeLog { id: number; minutes: number; note: string | null; logged_at: string | null }
interface Props {
    vendor: { id: number; name: string };
    ticket: {
        id: number; title: string; description: string | null; status: string;
        priority: string; location: string | null; vendor_status: string | null;
        resolution_due_at: string | null; is_open: boolean;
    };
    time_logs: TimeLog[];
    total_minutes: number;
}

const props = defineProps<Props>();
const { t } = useI18n();
const { formatDateTime } = useFormatters();

const canAct = props.ticket.vendor_status === 'accepted' && props.ticket.is_open;

const timeForm = useForm({ minutes: null as number | null, note: '' });
const resolveForm = useForm({ notes: '' });

const submitTime = () => timeForm.post(`/v/portal/tickets/${props.ticket.id}/time`, {
    preserveScroll: true,
    onSuccess: () => timeForm.reset(),
});
const submitResolve = () => resolveForm.post(`/v/portal/tickets/${props.ticket.id}/resolve`, {
    preserveScroll: true,
});
</script>

<template>
    <Head :title="ticket.title" />
    <VendorPortalLayout :vendor-name="vendor.name">
        <Link href="/v/portal/jobs" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
            <ArrowLeftIcon class="h-4 w-4" /> {{ t('vendor_portal.nav.inbox') }}
        </Link>

        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <h1 class="text-lg font-semibold text-gray-900">{{ ticket.title }}</h1>
            <p class="text-xs text-gray-500">{{ ticket.location }} · {{ ticket.priority }} · {{ ticket.status }}</p>
            <p v-if="ticket.description" class="mt-3 text-sm text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>
        </div>

        <div v-if="canAct" class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <form @submit.prevent="submitTime" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100" data-testid="vendor-time-form">
                <h2 class="font-medium text-gray-900">{{ t('vendor_portal.job.log_time') }}</h2>
                <label class="mt-3 block text-sm text-gray-600">{{ t('vendor_portal.job.minutes') }}</label>
                <input v-model.number="timeForm.minutes" type="number" min="1" max="1440" class="mt-1 w-full rounded-lg border-gray-200 text-sm" />
                <label class="mt-3 block text-sm text-gray-600">{{ t('vendor_portal.job.note') }}</label>
                <textarea v-model="timeForm.note" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-gray-200 text-sm"></textarea>
                <button type="submit" :disabled="timeForm.processing || !timeForm.minutes" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ t('vendor_portal.job.add_time') }}
                </button>
            </form>

            <form @submit.prevent="submitResolve" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100" data-testid="vendor-resolve-form">
                <h2 class="font-medium text-gray-900">{{ t('vendor_portal.job.resolve') }}</h2>
                <label class="mt-3 block text-sm text-gray-600">{{ t('vendor_portal.job.resolve_notes') }}</label>
                <textarea v-model="resolveForm.notes" rows="4" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-200 text-sm"></textarea>
                <button type="submit" :disabled="resolveForm.processing" class="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ t('vendor_portal.job.mark_resolved') }}
                </button>
            </form>
        </div>

        <div class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center justify-between">
                <h2 class="font-medium text-gray-900">{{ t('vendor_portal.job.prior_logs') }}</h2>
                <span class="text-sm text-gray-500">
                    {{ t('vendor_portal.job.total_time') }}: {{ total_minutes }} {{ t('vendor_portal.job.minutes_unit') }}
                </span>
            </div>
            <ul class="mt-3 divide-y divide-gray-100">
                <li v-for="log in time_logs" :key="log.id" class="flex items-center justify-between py-2 text-sm">
                    <span class="text-gray-700">{{ log.minutes }} {{ t('vendor_portal.job.minutes_unit') }}<span v-if="log.note" class="text-gray-500"> · {{ log.note }}</span></span>
                    <time class="text-xs text-gray-400">{{ formatDateTime(log.logged_at) }}</time>
                </li>
            </ul>
        </div>
    </VendorPortalLayout>
</template>
