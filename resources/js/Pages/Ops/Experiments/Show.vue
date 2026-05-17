<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Experiment {
    id: number;
    experiment_key: string;
    name: string;
    status: string;
    variants: Array<{ key: string; weight: number; label?: string }> | null;
    winning_variant_key: string | null;
    starts_at: string | null;
    ends_at: string | null;
}

const props = defineProps<{
    experiment: Experiment;
    exposures_by_variant: Record<string, number>;
    exposures_total: number;
    statuses: string[];
}>();

const winningKeyInput = ref<string>(props.experiment.winning_variant_key ?? '');

function conclude(): void {
    router.post(route('ops.experiments.conclude', props.experiment.id), {
        winning_variant_key: winningKeyInput.value || null,
    });
}
</script>

<template>
    <Head :title="experiment.name" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ experiment.name }}</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-gray-500">Key</dt><dd class="font-mono text-gray-900">{{ experiment.experiment_key }}</dd></div>
                        <div><dt class="text-gray-500">Status</dt><dd class="text-gray-900">{{ experiment.status }}</dd></div>
                        <div><dt class="text-gray-500">Starts at</dt><dd class="text-gray-900">{{ experiment.starts_at ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Ends at</dt><dd class="text-gray-900">{{ experiment.ends_at ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Winning variant</dt><dd class="text-gray-900">{{ experiment.winning_variant_key ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Total exposures</dt><dd class="text-gray-900">{{ exposures_total }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Exposures by variant</h2>
                    <table class="mt-3 min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-1.5 text-start text-xs font-medium uppercase tracking-wide text-gray-500">Variant</th>
                                <th class="px-3 py-1.5 text-end text-xs font-medium uppercase tracking-wide text-gray-500">Users assigned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(count, variantKey) in exposures_by_variant" :key="variantKey">
                                <td class="px-3 py-1.5 font-mono text-sm text-gray-900">{{ variantKey }}</td>
                                <td class="px-3 py-1.5 text-end text-sm text-gray-700">{{ count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section v-if="experiment.status !== 'concluded'" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Conclude</h2>
                    <div class="mt-3 flex items-center gap-3">
                        <input
                            v-model="winningKeyInput"
                            type="text"
                            placeholder="winning variant key (optional)"
                            class="block w-64 rounded-md border-gray-300 text-sm"
                        />
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500"
                            @click="conclude"
                        >Conclude experiment</button>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
