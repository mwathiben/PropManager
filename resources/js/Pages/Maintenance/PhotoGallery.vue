<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useI18n } from '@/composables/useI18n';
import { PhotoIcon, ArrowDownTrayIcon, XMarkIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import type { PaginationLink } from '@/types/global';

interface Annotation {
    id: number;
    url: string;
}

interface Photo {
    id: number;
    url: string;
    file_name: string;
    created_at: string | null;
    ticket: {
        id: number;
        title: string;
        category: string | null;
        building: string | null;
        unit: string | null;
    } | null;
    annotations: Annotation[];
}

const props = defineProps<{
    photos: { data: Photo[]; links: PaginationLink[] };
    buildings: { id: number; name: string }[];
    categories: string[];
    filters: {
        building_id: number | null;
        unit_id: number | null;
        category: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const { t } = useI18n();

const form = reactive({
    building_id: props.filters.building_id ?? '',
    category: props.filters.category ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function apply(): void {
    router.get(route('maintenance.photos'), pruned(), { preserveScroll: true, preserveState: true });
}

function reset(): void {
    form.building_id = '';
    form.category = '';
    form.from = '';
    form.to = '';
    router.get(route('maintenance.photos'));
}

function pruned(): Record<string, string | number> {
    const out: Record<string, string | number> = {};
    if (form.building_id) out.building_id = form.building_id;
    if (form.category) out.category = form.category;
    if (form.from) out.from = form.from;
    if (form.to) out.to = form.to;
    return out;
}

function exportPdf(): void {
    const params = new URLSearchParams(pruned() as Record<string, string>).toString();
    window.location.href = route('maintenance.photos.export-pdf') + (params ? `?${params}` : '');
}

const active = ref<Photo | null>(null);
const showAnnotated = ref(false);

function open(photo: Photo): void {
    active.value = photo;
    showAnnotated.value = false;
}

function close(): void {
    active.value = null;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('maintenance.photos.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <PhotoIcon class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('maintenance.photos.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('maintenance.photos.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8 space-y-4" data-testid="photo-gallery">
            <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow">
                <label class="flex flex-col text-xs text-gray-500">
                    {{ t('maintenance.photos.filter_building') }}
                    <select v-model="form.building_id" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ t('maintenance.photos.filter_all') }}</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col text-xs text-gray-500">
                    {{ t('maintenance.photos.filter_category') }}
                    <select v-model="form.category" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ t('maintenance.photos.filter_all') }}</option>
                        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                    </select>
                </label>
                <label class="flex flex-col text-xs text-gray-500">
                    {{ t('maintenance.photos.filter_from') }}
                    <input v-model="form.from" type="date" class="mt-0.5 rounded-md border-gray-300 text-sm" />
                </label>
                <label class="flex flex-col text-xs text-gray-500">
                    {{ t('maintenance.photos.filter_to') }}
                    <input v-model="form.to" type="date" class="mt-0.5 rounded-md border-gray-300 text-sm" />
                </label>
                <button type="button" class="rounded-md bg-orange-600 px-3 py-2 text-sm font-medium text-white hover:bg-orange-700" @click="apply">
                    {{ t('maintenance.photos.apply') }}
                </button>
                <button type="button" class="rounded-md bg-white px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50" @click="reset">
                    {{ t('maintenance.photos.reset') }}
                </button>
                <button type="button" class="ms-auto inline-flex items-center gap-1 rounded-md bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50" @click="exportPdf">
                    <ArrowDownTrayIcon class="h-4 w-4" /> {{ t('maintenance.photos.export_pdf') }}
                </button>
            </div>

            <div v-if="photos.data.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('maintenance.photos.empty') }}
            </div>

            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="photo in photos.data"
                    :key="photo.id"
                    type="button"
                    class="group relative overflow-hidden rounded-lg bg-gray-100 shadow"
                    @click="open(photo)"
                >
                    <img :src="photo.url" :alt="photo.file_name" loading="lazy" class="aspect-square w-full object-cover transition group-hover:opacity-90" />
                    <span
                        v-if="photo.annotations.length"
                        class="absolute end-1 top-1 inline-flex items-center gap-0.5 rounded bg-amber-500/90 px-1 text-xs font-medium text-white"
                    >
                        <PencilSquareIcon class="h-3 w-3" /> {{ t('maintenance.photos.annotated') }}
                    </span>
                    <span class="absolute inset-x-0 bottom-0 truncate bg-black/50 px-2 py-1 text-start text-xs text-white">
                        {{ photo.ticket?.building ?? '—' }}<template v-if="photo.ticket?.unit"> / {{ photo.ticket.unit }}</template>
                    </span>
                </button>
            </div>

            <Pagination :links="photos.links" color="indigo" />
        </div>

        <div v-if="active" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="close">
            <div class="max-h-full w-full max-w-3xl overflow-auto rounded-lg bg-white">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">{{ active.ticket?.title ?? active.file_name }}</p>
                        <p class="text-xs text-gray-500">
                            <Link v-if="active.ticket" :href="route('tickets.show', active.ticket.id)" class="text-indigo-600 hover:underline">
                                {{ t('maintenance.photos.view_ticket') }}
                            </Link>
                            <span v-if="active.created_at"> · {{ active.created_at }}</span>
                        </p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" @click="close"><XMarkIcon class="h-5 w-5" /></button>
                </div>
                <div class="p-4">
                    <img
                        :src="showAnnotated && active.annotations.length ? active.annotations[0].url : active.url"
                        :alt="active.file_name"
                        class="mx-auto max-h-[70vh] w-auto"
                    />
                    <div v-if="active.annotations.length" class="mt-3 flex justify-center gap-2">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="!showAnnotated ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-600'"
                            @click="showAnnotated = false"
                        >
                            {{ active.file_name }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="showAnnotated ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-600'"
                            @click="showAnnotated = true"
                        >
                            {{ t('maintenance.photos.annotated') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
