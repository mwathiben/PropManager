<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Experiment {
    id: number;
    experiment_key: string;
    name: string;
    status: string;
    variants: Array<{ key: string; weight: number; label?: string }> | null;
    winning_variant_key: string | null;
    starts_at: string | null;
    ends_at: string | null;
    exposures_by_variant: Record<string, number>;
    exposures_total: number;
}

defineProps<{
    experiments: Experiment[];
    statuses: string[];
}>();

const statusBadgeClass = (status: string): string => {
    switch (status) {
        case 'draft':
            return 'bg-gray-100 text-gray-700';
        case 'running':
            return 'bg-green-100 text-green-800';
        case 'paused':
            return 'bg-yellow-100 text-yellow-800';
        case 'concluded':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

function flipStatus(experimentId: number, newStatus: string): void {
    router.patch(route('ops.experiments.update', experimentId), { status: newStatus });
}
</script>

<template>
    <Head title="Experiments" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Experiments</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="experiments.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center">
                    <p class="text-sm text-gray-600">No experiments yet. Create one via the API or seeder.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Key</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Exposures</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Variants</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="exp in experiments" :key="exp.id">
                            <td class="px-3 py-2 font-mono text-sm text-gray-900">{{ exp.experiment_key }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700">{{ exp.name }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(exp.status)">
                                    {{ exp.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-sm text-gray-700">{{ exp.exposures_total }}</td>
                            <td class="px-3 py-2 text-sm text-gray-600">
                                <span v-for="(count, variantKey) in exp.exposures_by_variant" :key="variantKey" class="mr-2">
                                    {{ variantKey }}: {{ count }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-sm">
                                <Link :href="route('ops.experiments.show', exp.id)" class="text-indigo-600 hover:underline">View</Link>
                                <button
                                    v-if="exp.status === 'draft' || exp.status === 'paused'"
                                    type="button"
                                    class="ml-3 text-green-700 hover:underline"
                                    @click="flipStatus(exp.id, 'running')"
                                >Run</button>
                                <button
                                    v-if="exp.status === 'running'"
                                    type="button"
                                    class="ml-3 text-yellow-700 hover:underline"
                                    @click="flipStatus(exp.id, 'paused')"
                                >Pause</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
