<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

interface SavedReportOpt { id: number; name: string }
interface MetricOpt { slug: string; name: string; unit: string | null }
interface Card {
    type: 'saved_report' | 'metric';
    saved_report_id: number | null;
    metric_slug?: string | null;
    size: 'wide' | 'narrow';
    title?: string;
}
interface DashboardData {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    layout: Card[];
    is_default: boolean;
}

const props = defineProps<{
    dashboard: DashboardData | null;
    savedReports: SavedReportOpt[];
    metrics: MetricOpt[];
}>();

const { t } = useI18n();

const form = useForm({
    name: props.dashboard?.name ?? '',
    description: props.dashboard?.description ?? '',
    is_default: props.dashboard?.is_default ?? false,
    layout: (props.dashboard?.layout ?? []).map((c) => ({ ...c })) as Card[],
});

const preview = ref<{ cards: Array<Record<string, any>> } | null>(null);
const previewError = ref(false);
const previewing = ref(false);

const noReports = computed(() => props.savedReports.length === 0);
// Per-card validation errors (keyed layout.<i>.<field>) the form would
// otherwise swallow.
const cardErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([k]) => k.startsWith('layout.'))
        .map(([, v]) => v as string),
);

function addCard(type: 'saved_report' | 'metric'): void {
    form.layout.push({
        type,
        saved_report_id: props.savedReports[0]?.id ?? null,
        metric_slug: type === 'metric' ? (props.metrics[0]?.slug ?? null) : undefined,
        size: type === 'metric' ? 'narrow' : 'wide',
        title: '',
    });
}

function removeCard(i: number): void {
    form.layout.splice(i, 1);
}

function move(i: number, delta: number): void {
    const j = i + delta;
    if (j < 0 || j >= form.layout.length) return;
    const [card] = form.layout.splice(i, 1);
    form.layout.splice(j, 0, card);
}

async function refreshPreview(): Promise<void> {
    previewing.value = true;
    previewError.value = false;
    try {
        const res = await fetch(route('dashboards.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: form.name, layout: form.layout }),
        });
        if (!res.ok) throw new Error(`preview ${res.status}`);
        preview.value = await res.json();
    } catch {
        previewError.value = true;
        preview.value = null;
    } finally {
        previewing.value = false;
    }
}

function submit(): void {
    if (props.dashboard) {
        form.put(route('dashboards.update', props.dashboard.id), { preserveScroll: true });
    } else {
        form.post(route('dashboards.store'), { preserveScroll: true });
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('reports.dashboards.title')" />

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <Link :href="route('dashboards.index')" class="text-sm text-indigo-600 hover:underline">&larr; {{ t('reports.dashboards.back') }}</Link>

            <form class="space-y-5 rounded-lg bg-white p-5 shadow" data-testid="dashboard-editor" @submit.prevent="submit">
                <div>
                    <label for="d-name" class="block text-sm font-medium text-gray-700">{{ t('reports.dashboards.name_label') }}</label>
                    <input id="d-name" v-model="form.name" type="text" maxlength="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="d-desc" class="block text-sm font-medium text-gray-700">{{ t('reports.dashboards.description_label') }}</label>
                    <input id="d-desc" v-model="form.description" type="text" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300" />
                    {{ t('reports.dashboards.set_default') }}
                </label>

                <div class="border-t border-gray-100 pt-4">
                    <p v-if="noReports" class="mb-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800" data-testid="no-reports-hint">
                        {{ t('reports.dashboards.no_reports_hint') }}
                    </p>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50" :disabled="noReports" data-testid="card-add" @click="addCard('saved_report')">
                            + {{ t('reports.dashboards.card_report') }}
                        </button>
                        <button type="button" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50" :disabled="noReports || metrics.length === 0" @click="addCard('metric')">
                            + {{ t('reports.dashboards.card_metric') }}
                        </button>
                    </div>

                    <p v-if="form.layout.length === 0" class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-500">{{ t('reports.dashboards.no_cards') }}</p>
                    <p v-if="form.errors.layout" class="mt-1 text-xs text-rose-600">{{ form.errors.layout }}</p>
                    <ul v-if="cardErrors.length" class="mt-1 space-y-0.5">
                        <li v-for="(err, i) in cardErrors" :key="i" class="text-xs text-rose-600" data-testid="card-error">{{ err }}</li>
                    </ul>

                    <ul class="space-y-2">
                        <li v-for="(card, i) in form.layout" :key="i" class="rounded-lg border border-gray-200 p-3" data-testid="dashboard-card">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700">
                                    {{ card.type === 'metric' ? t('reports.dashboards.card_metric') : t('reports.dashboards.card_report') }}
                                </span>
                                <select v-model.number="card.saved_report_id" class="rounded-md border-gray-300 text-xs" :aria-label="t('reports.dashboards.pick_report')">
                                    <option :value="null" disabled>{{ t('reports.dashboards.pick_report') }}</option>
                                    <option v-for="r in savedReports" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <select v-if="card.type === 'metric'" v-model="card.metric_slug" class="rounded-md border-gray-300 text-xs" :aria-label="t('reports.dashboards.pick_metric')">
                                    <option :value="null" disabled>{{ t('reports.dashboards.pick_metric') }}</option>
                                    <option v-for="m in metrics" :key="m.slug" :value="m.slug">{{ m.name }}</option>
                                </select>
                                <select v-model="card.size" class="rounded-md border-gray-300 text-xs" :aria-label="t('reports.dashboards.card_size')">
                                    <option value="wide">{{ t('reports.dashboards.size_wide') }}</option>
                                    <option value="narrow">{{ t('reports.dashboards.size_narrow') }}</option>
                                </select>
                                <input v-model="card.title" type="text" maxlength="200" class="flex-1 rounded-md border-gray-300 text-xs" :placeholder="t('reports.dashboards.card_title_label')" />
                                <span class="flex gap-1">
                                    <button type="button" class="rounded px-1 text-gray-400 hover:text-gray-700" :aria-label="t('reports.dashboards.move_up')" @click="move(i, -1)">↑</button>
                                    <button type="button" class="rounded px-1 text-gray-400 hover:text-gray-700" :aria-label="t('reports.dashboards.move_down')" @click="move(i, 1)">↓</button>
                                    <button type="button" class="rounded px-1 text-rose-500 hover:text-rose-700" :aria-label="t('reports.dashboards.remove_card')" @click="removeCard(i)">✕</button>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <button type="button" class="text-sm text-gray-600 hover:underline" :disabled="previewing" @click="refreshPreview">
                        {{ t('reports.dashboards.refresh_preview') }}
                    </button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ t('reports.dashboards.save') }}
                    </button>
                </div>
            </form>

            <section v-if="previewError" class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700" data-testid="dashboard-preview-error">
                {{ t('reports.dashboards.preview_error') }}
            </section>

            <section v-else-if="preview" class="space-y-3" data-testid="dashboard-preview">
                <h2 class="text-sm font-medium text-gray-700">{{ t('reports.dashboards.preview') }}</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div v-for="(card, i) in preview.cards" :key="i" class="rounded-lg bg-white p-4 shadow" :class="card.size === 'wide' ? 'lg:col-span-2' : ''">
                        <p class="text-sm font-medium text-gray-700">{{ card.title }}</p>
                        <p v-if="card.type === 'metric'" class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ card.average ?? '—' }}<span v-if="card.unit" class="ms-1 text-sm text-gray-400">{{ card.unit }}</span>
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-400">{{ (card.rows?.length ?? 0) }} rows</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
