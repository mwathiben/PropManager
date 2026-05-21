<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ShareIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

interface ShareRow {
    id: number;
    report_name: string | null;
    expires_at: string;
    revoked: boolean;
    active: boolean;
    view_count: number;
    url: string | null;
}

const props = defineProps<{
    shares: ShareRow[];
    savedReports: { id: number; name: string }[];
    expiryChoices: number[];
}>();

const { t } = useI18n();
const { formatDateTime } = useFormatters();

const form = useForm({
    saved_report_id: props.savedReports[0]?.id ?? null,
    expiry_days: 7,
});

const copiedId = ref<number | null>(null);

function submit(): void {
    form.post(route('reports.shares.store'), { preserveScroll: true, onSuccess: () => form.reset('saved_report_id') });
}

function revoke(id: number): void {
    router.post(route('reports.shares.revoke', id), {}, { preserveScroll: true });
}

async function copy(row: ShareRow): Promise<void> {
    if (!row.url) return;
    await navigator.clipboard.writeText(row.url);
    copiedId.value = row.id;
    window.setTimeout(() => (copiedId.value = null), 1500);
}

function statusKey(row: ShareRow): string {
    if (row.revoked) return 'status_revoked';
    return row.active ? 'status_active' : 'status_expired';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('reports.share.title')" />

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-2">
                <ShareIcon class="h-6 w-6 text-gray-500" />
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('reports.share.title') }}</h1>
            </header>

            <form class="space-y-4 rounded-lg bg-white p-5 shadow" data-testid="report-share" @submit.prevent="submit">
                <p class="text-sm text-gray-500">{{ t('reports.share.intro') }}</p>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="s-report" class="block text-sm font-medium text-gray-700">{{ t('reports.share.pick_report') }}</label>
                        <select id="s-report" v-model.number="form.saved_report_id" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="r in savedReports" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="s-expiry" class="block text-sm font-medium text-gray-700">{{ t('reports.share.expiry') }}</label>
                        <select id="s-expiry" v-model.number="form.expiry_days" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="d in expiryChoices" :key="d" :value="d">{{ t('reports.share.days', { count: d }) }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="form.processing || !form.saved_report_id" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ t('reports.share.create') }}
                    </button>
                </div>
                <p v-if="form.errors.saved_report_id" class="text-xs text-rose-600">{{ form.errors.saved_report_id }}</p>
            </form>

            <div v-if="shares.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('reports.share.empty') }}
            </div>

            <table v-else class="min-w-full overflow-hidden rounded-lg bg-white shadow">
                <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('reports.share.col_report') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('reports.share.col_expires') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('reports.share.col_views') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('reports.share.col_status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <tr v-for="s in shares" :key="s.id" data-testid="share-row">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ s.report_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ formatDateTime(s.expires_at) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ s.view_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="s.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ t(`reports.share.${statusKey(s)}`) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <span class="flex justify-end gap-3 text-xs">
                                <button v-if="s.url" type="button" class="text-indigo-600 hover:underline" @click="copy(s)">
                                    {{ copiedId === s.id ? t('reports.share.copied') : t('reports.share.copy') }}
                                </button>
                                <button v-if="s.active" type="button" class="text-rose-600 hover:underline" @click="revoke(s.id)">
                                    {{ t('reports.share.revoke') }}
                                </button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
