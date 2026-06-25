<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { ExclamationTriangleIcon, PlusIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

interface Incident {
    id: number;
    title: string;
    severity: string;
    status: string;
    summary?: string | null;
    opened_at?: string | null;
    resolved_at?: string | null;
    post_mortem_url?: string | null;
}

defineProps<{ incidents?: Incident[] }>();

const { formatDateTime } = useFormatters();

const SEVERITIES = ['sev1', 'sev2', 'sev3', 'sev4'];
const STATUSES = ['open', 'investigating', 'mitigated', 'resolved'];

const showForm = ref(false);
const form = useForm({ title: '', severity: 'sev3', summary: '' });

const submit = () => {
    form.post(route('ops.incidents.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
};

const setStatus = (incident: Incident, status: string) => {
    if (status === incident.status) return;
    router.post(route('ops.incidents.set-status', incident.id), { status }, { preserveScroll: true });
};

const sevColor = (s: string): string => ({
    sev1: 'bg-red-100 text-red-800',
    sev2: 'bg-orange-100 text-orange-800',
    sev3: 'bg-yellow-100 text-yellow-800',
    sev4: 'bg-gray-100 text-gray-700',
}[s] || 'bg-gray-100 text-gray-700');

const statusColor = (s: string): string => ({
    open: 'bg-red-100 text-red-800',
    investigating: 'bg-amber-100 text-amber-800',
    mitigated: 'bg-blue-100 text-blue-800',
    resolved: 'bg-green-100 text-green-800',
}[s] || 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head title="Operational Incidents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <ExclamationTriangleIcon class="w-6 h-6 text-rose-500" />
                <h1 class="text-lg font-semibold text-gray-900">Operational Incidents</h1>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
            <div class="flex justify-end">
                <button @click="showForm = !showForm" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                    <PlusIcon class="w-5 h-5" />
                    Open incident
                </button>
            </div>

            <form v-if="showForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="inc-title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input id="inc-title" v-model="form.title" type="text" maxlength="255" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                </div>
                <div class="flex gap-4">
                    <div>
                        <label for="inc-severity" class="block text-sm font-medium text-gray-700">Severity</label>
                        <select id="inc-severity" v-model="form.severity" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm uppercase">
                            <option v-for="s in SEVERITIES" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="inc-summary" class="block text-sm font-medium text-gray-700">Summary (optional)</label>
                    <textarea id="inc-summary" v-model="form.summary" rows="2" maxlength="5000" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                </div>
                <button type="submit" :disabled="form.processing || !form.title" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Open</button>
            </form>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table v-if="incidents?.length" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Incident</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Opened</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="incident in incidents" :key="incident.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                    {{ incident.title }}
                                    <a v-if="incident.post_mortem_url" :href="incident.post_mortem_url" target="_blank" rel="noopener" class="text-indigo-500 hover:text-indigo-700">
                                        <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                                    </a>
                                </div>
                                <div v-if="incident.summary" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ incident.summary }}</div>
                            </td>
                            <td class="px-6 py-4 text-center"><span :class="[sevColor(incident.severity), 'px-2 py-1 text-xs font-medium rounded-full uppercase']">{{ incident.severity }}</span></td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDateTime(incident.opened_at) }}</td>
                            <td class="px-6 py-4 text-center">
                                <label :for="`status-${incident.id}`" class="sr-only">Status for {{ incident.title }}</label>
                                <select
                                    :id="`status-${incident.id}`"
                                    :value="incident.status"
                                    @change="setStatus(incident, ($event.target as HTMLSelectElement).value)"
                                    :class="[statusColor(incident.status), 'rounded-full border-0 px-2 py-1 text-xs font-medium capitalize focus:ring-2 focus:ring-rose-400']"
                                >
                                    <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="px-6 py-12 text-center">
                    <ExclamationTriangleIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-gray-500">No incidents</p>
                    <p class="text-xs text-gray-400 mt-1">Open one when an operational issue occurs.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
