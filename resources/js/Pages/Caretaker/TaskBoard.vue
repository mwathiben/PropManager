<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon, ClipboardDocumentListIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

interface Task {
    id: number;
    title: string;
    priority: string;
    status: string;
    building: string | null;
    unit: string | null;
    reporter: string | null;
    created_at: string | null;
    is_overdue: boolean;
    is_escalated: boolean;
}

const props = defineProps<{
    tasks: Task[];
    waterEnabled: boolean;
    escalationReasons: Record<string, string>;
}>();

const { t } = useI18n();

// Phase-80 TASK-BOARD-1: group by urgency — overdue first, then urgent, then the rest.
const groups = computed(() => ({
    overdue: props.tasks.filter((task) => task.is_overdue),
    urgent: props.tasks.filter((task) => !task.is_overdue && task.priority === 'urgent'),
    today: props.tasks.filter((task) => !task.is_overdue && task.priority !== 'urgent'),
}));

function nextStatus(status: string): { target: string; label: string } | null {
    if (status === 'open') return { target: 'acknowledged', label: t('maintenance.task_board.acknowledge') };
    if (status === 'acknowledged') return { target: 'in_progress', label: t('maintenance.task_board.start') };
    if (status === 'in_progress') return { target: 'resolved', label: t('maintenance.task_board.resolve') };
    return null;
}

const busy = ref<number | null>(null);

function transition(task: Task): void {
    const next = nextStatus(task.status);
    if (!next) return;
    busy.value = task.id;
    router.post(route('tasks.transition', task.id), { status: next.target }, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}

// Escalation modal.
const escalating = ref<Task | null>(null);
const escalateForm = useForm({ preset: '', reason: '' });

function openEscalate(task: Task): void {
    escalating.value = task;
    escalateForm.reset();
}

function submitEscalate(): void {
    if (!escalating.value) return;
    escalateForm.post(route('tasks.escalate', escalating.value.id), {
        preserveScroll: true,
        onSuccess: () => (escalating.value = null),
    });
}

const priorityClass: Record<string, string> = {
    urgent: 'bg-rose-100 text-rose-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-amber-100 text-amber-700',
    normal: 'bg-sky-100 text-sky-700',
    low: 'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('maintenance.task_board.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <ClipboardDocumentListIcon class="w-6 h-6 text-yellow-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('maintenance.task_board.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('maintenance.task_board.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-2xl px-3 py-4 space-y-5" data-testid="caretaker-task-board">
            <!-- Quick actions -->
            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="waterEnabled"
                    :href="route('water.hub')"
                    class="inline-flex items-center gap-1 rounded-lg bg-cyan-600 px-3 py-2 text-sm font-medium text-white"
                >
                    <BeakerIcon class="h-5 w-5" /> {{ t('maintenance.task_board.record_water') }}
                </Link>
                <Link :href="route('tickets.create')" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                    {{ t('maintenance.task_board.report_issue') }}
                </Link>
                <Link :href="route('tickets.index')" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                    {{ t('maintenance.task_board.view_all') }}
                </Link>
            </div>

            <p v-if="tasks.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('maintenance.task_board.empty') }}
            </p>

            <template v-for="(label, key) in { overdue: t('maintenance.task_board.group_overdue'), urgent: t('maintenance.task_board.group_urgent'), today: t('maintenance.task_board.group_today') }" :key="key">
                <section v-if="groups[key].length > 0" class="space-y-2">
                    <h2 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ label }} ({{ groups[key].length }})</h2>
                    <div
                        v-for="task in groups[key]"
                        :key="task.id"
                        class="rounded-xl bg-white p-4 shadow-sm"
                        :class="task.is_overdue ? 'ring-1 ring-rose-200' : ''"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link :href="route('tickets.show', task.id)" class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-gray-900">{{ task.title }}</p>
                                <p class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ task.building }}<span v-if="task.unit"> · {{ task.unit }}</span>
                                </p>
                            </Link>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityClass[task.priority] ?? priorityClass.low">{{ task.priority }}</span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span v-if="task.is_overdue" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">{{ t('maintenance.task_board.overdue') }}</span>
                            <span v-if="task.is_escalated" class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                                <ExclamationTriangleIcon class="h-3.5 w-3.5" /> {{ t('maintenance.task_board.escalated') }}
                            </span>
                        </div>

                        <div class="mt-3 flex gap-2">
                            <button
                                v-if="nextStatus(task.status)"
                                :disabled="busy === task.id"
                                class="flex-1 rounded-lg bg-indigo-600 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                                @click="transition(task)"
                            >{{ nextStatus(task.status)?.label }}</button>
                            <button
                                v-if="!task.is_escalated"
                                class="rounded-lg bg-purple-50 px-4 py-2.5 text-sm font-medium text-purple-700"
                                @click="openEscalate(task)"
                            >{{ t('maintenance.task_board.escalate') }}</button>
                        </div>
                    </div>
                </section>
            </template>
        </div>

        <!-- Escalate modal -->
        <div v-if="escalating" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div class="fixed inset-0 bg-gray-900/50" @click="escalating = null"></div>
            <div class="relative z-10 w-full max-w-md rounded-t-2xl sm:rounded-2xl bg-white p-5">
                <h3 class="mb-3 text-lg font-semibold text-gray-900">{{ t('maintenance.task_board.escalate_title') }}</h3>
                <select v-model="escalateForm.preset" class="mb-3 w-full rounded-lg border-gray-300 text-sm" :aria-label="t('maintenance.task_board.escalate_reason')">
                    <option value="">{{ t('maintenance.task_board.escalate_reason') }}</option>
                    <option v-for="(label, key) in escalationReasons" :key="key" :value="key">{{ label }}</option>
                </select>
                <textarea
                    v-model="escalateForm.reason"
                    rows="3"
                    class="mb-1 w-full rounded-lg border-gray-300 text-sm"
                    :placeholder="t('maintenance.task_board.escalate_reason')"
                    :aria-label="t('maintenance.task_board.escalate_reason')"
                ></textarea>
                <p v-if="escalateForm.errors.reason" class="mb-2 text-xs text-rose-600">{{ escalateForm.errors.reason }}</p>
                <div class="mt-2 flex gap-2">
                    <button
                        :disabled="escalateForm.processing"
                        class="flex-1 rounded-lg bg-purple-600 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                        @click="submitEscalate"
                    >{{ t('maintenance.task_board.submit') }}</button>
                    <button class="flex-1 rounded-lg bg-gray-100 py-2.5 text-sm text-gray-700" @click="escalating = null">{{ t('maintenance.task_board.cancel') }}</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
