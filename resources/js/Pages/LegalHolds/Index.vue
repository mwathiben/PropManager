<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

interface Hold {
    id: number;
    holdable_type: string;
    holdable_id: number;
    reason: string;
    held_at: string;
    released_at: string | null;
    held_by: { id: number; name: string } | null;
    released_by: { id: number; name: string } | null;
}

const props = defineProps<{
    holds: { data: Hold[]; links: any[]; meta?: any };
    filters: { status: string; subject_type: string };
    subject_types: string[];
}>();

const { t } = useI18n();
const releasing = ref<number | null>(null);

const subjectTypeLabel = (fqcn: string): string => {
    const parts = fqcn.split('\\');
    return parts[parts.length - 1].replace(/([A-Z])/g, ' $1').trim();
};

const pillClass = (fqcn: string): string => {
    const last = fqcn.split('\\').pop() ?? '';
    if (last === 'MessageThread') return 'bg-indigo-100 text-indigo-800';
    if (last === 'Document') return 'bg-blue-100 text-blue-800';
    if (last === 'Invoice') return 'bg-amber-100 text-amber-800';
    if (last === 'Ticket') return 'bg-rose-100 text-rose-800';
    return 'bg-gray-100 text-gray-800';
};

const switchTab = (status: string) => {
    router.get(route('legal-holds.list'), { status }, { preserveScroll: true, preserveState: true });
};

const filterSubject = (subjectType: string) => {
    router.get(route('legal-holds.list'), {
        status: props.filters.status,
        subject_type: subjectType,
    }, { preserveScroll: true, preserveState: true });
};

const releaseHold = (hold: Hold) => {
    if (!window.confirm(t('legal_holds.release_confirm'))) return;
    releasing.value = hold.id;
    router.delete(route('legal-holds.destroy', hold.id), {
        preserveScroll: true,
        onFinish: () => { releasing.value = null; },
    });
};

const historyUrl = (hold: Hold): string =>
    route('legal-holds.history', { subject_type: hold.holdable_type, subject_id: hold.holdable_id });

const isActiveTab = computed(() => props.filters.status !== 'released');
</script>

<template>
    <Head :title="t('legal_holds.page_title')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <ScaleIcon class="h-6 w-6 text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ t('legal_holds.page_title') }}
                </h1>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 rounded-2xl">
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div class="flex gap-2">
                                <button
                                    @click="switchTab('active')"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm font-medium transition',
                                        isActiveTab
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                    ]"
                                    data-testid="tab-active"
                                >
                                    {{ t('legal_holds.tab_active') }}
                                </button>
                                <button
                                    @click="switchTab('released')"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm font-medium transition',
                                        !isActiveTab
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                    ]"
                                    data-testid="tab-released"
                                >
                                    {{ t('legal_holds.tab_released') }}
                                </button>
                            </div>
                            <select
                                :value="filters.subject_type"
                                :aria-label="t('legal_holds.filter_subject_type')"
                                @change="(e) => filterSubject((e.target as HTMLSelectElement).value)"
                                class="rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                data-testid="filter-subject-type"
                            >
                                <option value="">All subject types</option>
                                <option v-for="st in subject_types" :key="st" :value="st">
                                    {{ subjectTypeLabel(st) }}
                                </option>
                            </select>
                        </div>

                        <div v-if="holds.data.length === 0" class="text-center py-12 text-gray-500">
                            {{ t('legal_holds.empty_state') }}
                        </div>

                        <div v-else class="overflow-hidden ring-1 ring-gray-200 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Subject</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Reason</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Held by</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">When</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="hold in holds.data" :key="hold.id">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium', pillClass(hold.holdable_type)]">
                                                {{ subjectTypeLabel(hold.holdable_type) }}
                                            </span>
                                            <span class="ms-2 text-sm text-gray-600">#{{ hold.holdable_id }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 max-w-md">
                                            <span :title="hold.reason">
                                                {{ hold.reason.length > 60 ? hold.reason.slice(0, 60) + '…' : hold.reason }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {{ hold.held_by?.name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {{ isActiveTab ? hold.held_at : hold.released_at }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-3">
                                                <Link
                                                    :href="historyUrl(hold)"
                                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    data-testid="hold-history-link"
                                                >
                                                    {{ t('legal_holds.history.view') }}
                                                </Link>
                                                <button
                                                    v-if="isActiveTab"
                                                    @click="releaseHold(hold)"
                                                    :disabled="releasing === hold.id"
                                                    class="text-sm font-medium text-rose-600 hover:text-rose-800 disabled:opacity-50"
                                                    :data-testid="`release-${hold.id}`"
                                                >
                                                    Release
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
