<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

interface SlaRow {
    id: number;
    landlord_id: number | null;
    category: string | null;
    subcategory: string | null;
    priority: string;
    response_seconds: number;
    resolution_seconds: number;
    is_active: boolean;
}

const props = defineProps<{
    overrides: SlaRow[];
    globals: SlaRow[];
    categoryOptions: string[];
    priorityOptions: string[];
}>();

const { t } = useI18n();

const modalOpen = ref(false);
const editing = ref<SlaRow | null>(null);

const form = useForm({
    category: '' as string,
    subcategory: '' as string,
    priority: 'medium' as string,
    response_seconds: 3600,
    resolution_seconds: 86400,
    is_active: true,
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.category = '';
    form.subcategory = '';
    form.priority = 'medium';
    form.response_seconds = 3600;
    form.resolution_seconds = 86400;
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(row: SlaRow): void {
    editing.value = row;
    form.category = row.category ?? '';
    form.subcategory = row.subcategory ?? '';
    form.priority = row.priority;
    form.response_seconds = row.response_seconds;
    form.resolution_seconds = row.resolution_seconds;
    form.is_active = row.is_active;
    modalOpen.value = true;
}

function submit(): void {
    const payload = {
        category: form.category || null,
        subcategory: form.subcategory || null,
        priority: form.priority,
        response_seconds: Number(form.response_seconds),
        resolution_seconds: Number(form.resolution_seconds),
        is_active: form.is_active,
    };

    if (editing.value) {
        router.patch(route('sla.update', editing.value.id), payload, {
            preserveScroll: true,
            onSuccess: () => (modalOpen.value = false),
        });
    } else {
        router.post(route('sla.store'), payload, {
            preserveScroll: true,
            onSuccess: () => (modalOpen.value = false),
        });
    }
}

function destroy(row: SlaRow): void {
    if (!window.confirm(t('common.confirm_delete'))) return;
    router.delete(route('sla.destroy', row.id), { preserveScroll: true });
}

const formatDuration = (seconds: number): string => {
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    if (seconds < 86400) return `${Math.round(seconds / 3600)}h`;
    return `${Math.round(seconds / 86400)}d`;
};

const cascadePreview = computed(() => {
    const category = form.category || 'any category';
    const subcategory = form.subcategory || 'any subcategory';
    return `${form.priority} priority • ${category} • ${subcategory}`;
});
</script>

<template>
    <Head title="SLA overrides" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ t('maintenance.sla.title') }}
            </h1>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ t('maintenance.sla.description') }}
            </p>

            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Your overrides</h2>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="openCreate"
                    >
                        New override
                    </button>
                </div>

                <table v-if="props.overrides.length" class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Category</th>
                            <th class="px-2 py-2">Subcategory</th>
                            <th class="px-2 py-2">Priority</th>
                            <th class="px-2 py-2">Response</th>
                            <th class="px-2 py-2">Resolution</th>
                            <th class="px-2 py-2">Active</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in props.overrides" :key="row.id">
                            <td class="px-2 py-2 text-gray-900">{{ row.category ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ row.subcategory ?? '—' }}</td>
                            <td class="px-2 py-2 capitalize text-gray-900">{{ row.priority }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ formatDuration(row.response_seconds) }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ formatDuration(row.resolution_seconds) }}</td>
                            <td class="px-2 py-2">
                                <span
                                    class="rounded px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ row.is_active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-2 py-2 space-x-2 text-end">
                                <button class="text-xs text-indigo-600 hover:underline" @click="openEdit(row)">Edit</button>
                                <button class="text-xs text-rose-600 hover:underline" @click="destroy(row)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-3 text-sm text-gray-500">
                    No overrides yet. Platform defaults apply for every ticket.
                </p>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-gray-900">Platform defaults (read only)</h2>
                <table v-if="props.globals.length" class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Category</th>
                            <th class="px-2 py-2">Subcategory</th>
                            <th class="px-2 py-2">Priority</th>
                            <th class="px-2 py-2">Response</th>
                            <th class="px-2 py-2">Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in props.globals" :key="row.id">
                            <td class="px-2 py-2 text-gray-900">{{ row.category ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ row.subcategory ?? '—' }}</td>
                            <td class="px-2 py-2 capitalize text-gray-900">{{ row.priority }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ formatDuration(row.response_seconds) }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ formatDuration(row.resolution_seconds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editing ? 'Edit override' : 'New override' }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">{{ cascadePreview }}</p>

                    <form class="mt-4 space-y-3" @submit.prevent="submit">
                        <div>
                            <label for="sla-category" class="block text-xs font-semibold text-gray-700">Category</label>
                            <select id="sla-category" v-model="form.category" class="mt-1 w-full rounded border-gray-300 text-sm">
                                <option value="">Any category</option>
                                <option v-for="opt in props.categoryOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="sla-subcategory" class="block text-xs font-semibold text-gray-700">Subcategory</label>
                            <input id="sla-subcategory" v-model="form.subcategory" type="text" class="mt-1 w-full rounded border-gray-300 text-sm" placeholder="Any subcategory">
                        </div>
                        <div>
                            <label for="sla-priority" class="block text-xs font-semibold text-gray-700">Priority</label>
                            <select id="sla-priority" v-model="form.priority" required class="mt-1 w-full rounded border-gray-300 text-sm">
                                <option v-for="opt in props.priorityOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="sla-response-seconds" class="block text-xs font-semibold text-gray-700">Response (seconds)</label>
                                <input id="sla-response-seconds" v-model.number="form.response_seconds" type="number" min="60" required class="mt-1 w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label for="sla-resolution-seconds" class="block text-xs font-semibold text-gray-700">Resolution (seconds)</label>
                                <input id="sla-resolution-seconds" v-model.number="form.resolution_seconds" type="number" min="60" required class="mt-1 w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_active" type="checkbox">
                            Active
                        </label>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm" @click="modalOpen = false">Cancel</button>
                            <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                {{ editing ? 'Save' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
