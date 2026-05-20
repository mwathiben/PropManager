<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon, ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

interface HoldEntry {
    id: number;
    reason: string;
    held_at: string | null;
    held_by: string | null;
    released_at: string | null;
    released_by: string | null;
    is_active: boolean;
}

interface Subject {
    type: string;
    short_type: string;
    id: number;
}

const props = defineProps<{
    subject: Subject;
    holds: HoldEntry[];
}>();

const { t } = useI18n();
const { formatDateTime } = useFormatters();

const exportUrl = route('legal-holds.history.export', {
    subject_type: props.subject.type,
    subject_id: props.subject.id,
});
</script>

<template>
    <Head :title="t('legal_holds.history.title')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <ScaleIcon class="h-6 w-6 text-indigo-600" />
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ t('legal_holds.history.title') }}
                    <span class="text-gray-500 font-normal">— {{ subject.short_type }} #{{ subject.id }}</span>
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-4">
                <div class="flex items-center justify-between">
                    <Link
                        :href="route('legal-holds.index')"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeftIcon class="h-4 w-4" />
                        {{ t('legal_holds.history.back') }}
                    </Link>
                    <a
                        v-if="holds.length > 0"
                        :href="exportUrl"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
                        data-testid="history-export"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        {{ t('legal_holds.history.export_csv') }}
                    </a>
                </div>

                <div
                    v-if="holds.length === 0"
                    class="rounded-2xl bg-white p-12 text-center text-gray-500 ring-1 ring-gray-100"
                    data-testid="history-empty"
                >
                    {{ t('legal_holds.history.empty') }}
                </div>

                <ol v-else class="space-y-3" data-testid="history-timeline">
                    <li
                        v-for="hold in holds"
                        :key="hold.id"
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    hold.is_active ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700',
                                ]"
                            >
                                <span
                                    :class="['h-1.5 w-1.5 rounded-full', hold.is_active ? 'bg-amber-500' : 'bg-gray-400']"
                                ></span>
                                {{ hold.is_active ? t('legal_holds.history.active') : t('legal_holds.history.released') }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm text-gray-900">
                            <span class="font-medium">{{ t('legal_holds.history.reason') }}:</span>
                            {{ hold.reason }}
                        </p>

                        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-400">
                                    {{ t('legal_holds.history.held') }}
                                </dt>
                                <dd class="text-gray-700">
                                    {{ formatDateTime(hold.held_at) }}
                                    <span v-if="hold.held_by" class="text-gray-500">
                                        {{ t('legal_holds.history.by', { name: hold.held_by }) }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="hold.released_at">
                                <dt class="text-xs uppercase tracking-wide text-gray-400">
                                    {{ t('legal_holds.history.released') }}
                                </dt>
                                <dd class="text-gray-700">
                                    {{ formatDateTime(hold.released_at) }}
                                    <span v-if="hold.released_by" class="text-gray-500">
                                        {{ t('legal_holds.history.by', { name: hold.released_by }) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </li>
                </ol>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
