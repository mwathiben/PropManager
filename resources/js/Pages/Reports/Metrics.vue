<script setup lang="ts">
/**
 * Phase-73 METRICS-DEPTH-2: author/manage custom report metrics.
 *
 * Lists the landlord's metrics + a create form whose formula is validated
 * live (debounced POST to reports.metrics.validate -> MetricFormulaService)
 * so an off-allow-list / malformed / injection expression surfaces its
 * exact message before save. The field catalogue (numeric allow-list) is
 * server-supplied; chips insert a {table.field} reference at the cursor.
 */
import { ref, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { VariableIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

interface MetricRow {
    id: number;
    slug: string;
    name: string;
    expression: string;
    unit: string | null;
}

const props = defineProps<{
    metrics: MetricRow[];
    fields: { key: string; label: string }[];
}>();

const { t } = useI18n();

const form = useForm({
    name: '',
    expression: '',
    unit: '',
});

const validation = ref<{ state: 'idle' | 'checking' | 'valid' | 'invalid'; error: string | null }>({
    state: 'idle',
    error: null,
});

let debounce: number | undefined;

function csrf(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function runValidation(expression: string): Promise<void> {
    if (expression.trim() === '') {
        validation.value = { state: 'idle', error: null };
        return;
    }
    validation.value = { state: 'checking', error: null };
    try {
        const response = await fetch(route('reports.metrics.validate'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ expression }),
        });
        const data = (await response.json()) as { valid: boolean; error?: string };
        validation.value = data.valid
            ? { state: 'valid', error: null }
            : { state: 'invalid', error: data.error ?? 'Invalid expression.' };
    } catch {
        validation.value = { state: 'idle', error: null };
    }
}

watch(
    () => form.expression,
    (expression) => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => void runValidation(expression), 400);
    },
);

function insertField(key: string): void {
    form.expression = `${form.expression}{${key}}`;
}

function submit(): void {
    form.post(route('reports.metrics.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            validation.value = { state: 'idle', error: null };
        },
    });
}

function remove(metric: MetricRow): void {
    if (!window.confirm(t('reports.metrics.delete_confirm'))) return;
    router.delete(route('reports.metrics.destroy', metric.id), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('reports.metrics.title')" />

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-2">
                <VariableIcon class="h-6 w-6 text-gray-500" />
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('reports.metrics.title') }}</h1>
            </header>

            <form class="space-y-4 rounded-lg bg-white p-5 shadow" data-testid="metrics-editor" @submit.prevent="submit">
                <p class="text-sm text-gray-500">{{ t('reports.metrics.intro') }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="m-name" class="block text-sm font-medium text-gray-700">{{ t('reports.metrics.name_label') }}</label>
                        <input id="m-name" v-model="form.name" type="text" maxlength="200" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="m-unit" class="block text-sm font-medium text-gray-700">{{ t('reports.metrics.unit_label') }}</label>
                        <input id="m-unit" v-model="form.unit" type="text" maxlength="32" :placeholder="t('reports.metrics.unit_placeholder')" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                </div>

                <div>
                    <label for="m-expression" class="block text-sm font-medium text-gray-700">{{ t('reports.metrics.expression_label') }}</label>
                    <textarea id="m-expression" v-model="form.expression" rows="2" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"></textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ t('reports.metrics.expression_hint') }}</p>

                    <p v-if="validation.state === 'checking'" class="mt-1 text-xs text-gray-400">{{ t('reports.metrics.validating') }}</p>
                    <p v-else-if="validation.state === 'valid'" class="mt-1 text-xs text-emerald-600" data-testid="metric-valid">{{ t('reports.metrics.valid') }}</p>
                    <p v-else-if="validation.state === 'invalid'" class="mt-1 text-xs text-rose-600" data-testid="metric-invalid">{{ validation.error }}</p>
                    <p v-if="form.errors.expression" class="mt-1 text-xs text-rose-600">{{ form.errors.expression }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('reports.metrics.available_fields') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="field in props.fields"
                            :key="field.key"
                            type="button"
                            class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700 hover:bg-indigo-100 hover:text-indigo-700"
                            @click="insertField(field.key)"
                        >
                            {{ field.label }}
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !form.name || validation.state === 'invalid'"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {{ t('reports.metrics.create') }}
                </button>
            </form>

            <div v-if="props.metrics.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('reports.metrics.empty') }}
            </div>

            <table v-else class="min-w-full overflow-hidden rounded-lg bg-white shadow">
                <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ t('reports.metrics.col_name') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('reports.metrics.col_expression') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('reports.metrics.col_unit') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <tr v-for="metric in props.metrics" :key="metric.id" data-testid="metric-row">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ metric.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ metric.expression }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ metric.unit ?? '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            <button type="button" class="text-xs text-rose-600 hover:underline" @click="remove(metric)">
                                {{ t('reports.metrics.delete') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
