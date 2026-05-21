<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

interface Hold {
    id: number;
    subject_type: string;
    subject_id: number;
    reason: string;
    is_active: boolean;
    held_at: string | null;
    held_by: string | null;
    released_at: string | null;
    released_by: string | null;
}

interface Matter {
    id: number;
    title: string;
    matter_reference: string | null;
    situation_type: string | null;
    status: 'open' | 'closed';
    review_by: string | null;
    review_due: boolean;
    description: string | null;
    closed_at: string | null;
    active_count: number;
}

const props = defineProps<{ matter: Matter; holds: Hold[] }>();

const { t } = useI18n();
const { formatDate, formatDateTime } = useFormatters();

const confirming = ref<'release' | 'close' | null>(null);
const processing = ref(false);

const shortType = (fqcn: string) => fqcn.split('\\').pop() ?? fqcn;

function run(action: 'release' | 'close' | 'reopen'): void {
    processing.value = true;
    router.post(route(`legal-matters.${action}`, props.matter.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirming.value = null;
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="matter.title" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6" data-testid="matter-show">
            <Link :href="route('legal-matters.index')" class="text-sm text-indigo-600 hover:underline">
                &larr; {{ t('legal_holds.matters.back') }}
            </Link>

            <header class="rounded-lg bg-white p-5 shadow">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ matter.title }}</h1>
                        <p v-if="matter.matter_reference" class="mt-1 text-sm text-gray-500">
                            {{ t('legal_holds.matters.col_reference') }}: {{ matter.matter_reference }}
                        </p>
                        <p v-if="matter.description" class="mt-2 max-w-prose text-sm text-gray-600">{{ matter.description }}</p>
                    </div>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                        :class="matter.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600'"
                    >
                        {{ t(`legal_holds.matters.status_${matter.status}`) }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-gray-500">{{ t('legal_holds.matters.col_held') }}</dt>
                        <dd class="font-medium text-gray-900">{{ matter.active_count }}</dd>
                    </div>
                    <div v-if="matter.review_by">
                        <dt class="text-gray-500">{{ t('legal_holds.matters.col_review') }}</dt>
                        <dd :class="matter.review_due ? 'font-medium text-amber-700' : 'text-gray-900'">
                            {{ formatDate(matter.review_by, 'short') }}
                        </dd>
                    </div>
                    <div v-if="matter.closed_at">
                        <dt class="text-gray-500">{{ t('legal_holds.matters.status_closed') }}</dt>
                        <dd class="text-gray-900">{{ formatDateTime(matter.closed_at) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="matter.active_count > 0"
                        type="button"
                        class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="processing"
                        data-testid="matter-release"
                        @click="confirming = 'release'"
                    >
                        {{ t('legal_holds.matters.release_all') }}
                    </button>
                    <button
                        v-if="matter.status === 'open' && matter.active_count === 0"
                        type="button"
                        class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="processing"
                        data-testid="matter-close"
                        @click="confirming = 'close'"
                    >
                        {{ t('legal_holds.matters.close') }}
                    </button>
                    <button
                        v-if="matter.status === 'closed'"
                        type="button"
                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="processing"
                        data-testid="matter-reopen"
                        @click="run('reopen')"
                    >
                        {{ t('legal_holds.matters.reopen') }}
                    </button>
                    <a
                        :href="route('legal-matters.audit-export', matter.id)"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        {{ t('legal_holds.matters.audit_export') }}
                    </a>
                </div>

                <div
                    v-if="confirming"
                    class="mt-3 flex items-center justify-between gap-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200"
                    data-testid="matter-confirm"
                >
                    <span>{{ t(`legal_holds.matters.${confirming}_confirm`) }}</span>
                    <span class="flex gap-2">
                        <button type="button" class="font-medium hover:underline" :disabled="processing" @click="run(confirming)">
                            {{ t('legal_holds.matters.confirm') }}
                        </button>
                        <button type="button" class="text-amber-700 hover:underline" @click="confirming = null">
                            {{ t('legal_holds.matters.cancel') }}
                        </button>
                    </span>
                </div>
            </header>

            <table class="min-w-full overflow-hidden rounded-lg bg-white shadow">
                <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_subject') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('history.reason') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('legal_holds.matters.col_status') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('history.held') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <tr v-for="h in holds" :key="h.id" data-testid="matter-hold-row">
                        <td class="px-4 py-3">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ shortType(h.subject_type) }}</span>
                            #{{ h.subject_id }}
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate text-gray-700">{{ h.reason }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                :class="h.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ h.is_active ? t('history.active') : t('history.released') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ h.held_at ? formatDateTime(h.held_at) : '—' }}<span v-if="h.held_by"> · {{ h.held_by }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
