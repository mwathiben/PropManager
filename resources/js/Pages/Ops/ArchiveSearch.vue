<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

interface MonthEntry {
    month: string;
    path: string;
    size_bytes: number;
}

interface LandlordEntry {
    landlord_id: string;
    path: string;
    size_bytes: number;
}

interface EventRow {
    id: number;
    original_id: number | null;
    user_id: number | null;
    landlord_id: number | null;
    event_name: string;
    properties: Record<string, unknown> | null;
    original_created_at: string | null;
    rehydrated_at: string;
}

defineProps<{
    summary: { total_files: number; total_bytes: number; per_landlord_bytes: Record<string, number> };
    selected_landlord: string | null;
    selected_month: string | null;
    months_for_landlord: MonthEntry[];
    landlords_for_month: LandlordEntry[];
    events: EventRow[];
}>();

const filterForm = useForm({
    landlord: '',
    month: '',
});

const rehydrateForm = useForm({
    landlord: '',
    month: '',
    clear_first: false,
});

const page = usePage();

function applyFilter(): void {
    filterForm.get(route('ops.archive.show'), { preserveScroll: true });
}

function rehydrate(): void {
    rehydrateForm.post(route('ops.archive.rehydrate'), { preserveScroll: true });
}

function fmtBytes(n: number): string {
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / 1024 / 1024).toFixed(2)} MB`;
}
</script>

<template>
    <Head title="Archive search" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Archive search</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600">
                    Search cold-storage product_events. Pick a landlord + month to view rehydrated rows.
                    Use the rehydrate form below to load a missing archive into the searchable table.
                </p>

                <div v-if="(page.props.flash as any)?.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700 whitespace-pre-line">
                    {{ (page.props.flash as any).success }}
                </div>
                <div v-if="(page.props.flash as any)?.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700 whitespace-pre-line">
                    {{ (page.props.flash as any).error }}
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Total files</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ summary.total_files }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Total bytes</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ fmtBytes(summary.total_bytes) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Landlords archived</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ Object.keys(summary.per_landlord_bytes).length }}</div>
                    </div>
                </div>

                <form @submit.prevent="applyFilter" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-gray-700">Search rehydrated rows</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <input v-model="filterForm.landlord" placeholder="landlord_id (or 'unscoped')" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="filterForm.month" placeholder="YYYY-MM" class="rounded-md border-gray-300 text-sm" />
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Filter</button>
                    </div>
                </form>

                <form @submit.prevent="rehydrate" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-gray-700">Rehydrate an archive</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <input v-model="rehydrateForm.landlord" placeholder="landlord_id" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="rehydrateForm.month" placeholder="YYYY-MM" class="rounded-md border-gray-300 text-sm" />
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="rehydrateForm.clear_first" class="rounded border-gray-300" />
                            Clear previously rehydrated rows for this path
                        </label>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500" :disabled="rehydrateForm.processing">Rehydrate</button>
                    </div>
                </form>

                <div v-if="months_for_landlord.length" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-gray-700">Available months for landlord {{ selected_landlord }}</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li v-for="m in months_for_landlord" :key="m.path" class="flex justify-between">
                            <span>{{ m.month }}</span>
                            <span class="text-gray-500">{{ fmtBytes(m.size_bytes) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="landlords_for_month.length" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-gray-700">Landlords with data for {{ selected_month }}</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li v-for="l in landlords_for_month" :key="l.path" class="flex justify-between">
                            <span>{{ l.landlord_id }}</span>
                            <span class="text-gray-500">{{ fmtBytes(l.size_bytes) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">
                        Events ({{ events.length }} shown, capped at 500)
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">id</th>
                                <th class="px-3 py-2 text-start">user_id</th>
                                <th class="px-3 py-2 text-start">event_name</th>
                                <th class="px-3 py-2 text-start">created_at</th>
                                <th class="px-3 py-2 text-start">properties</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="ev in events" :key="ev.id" class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-900">{{ ev.original_id ?? ev.id }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ ev.user_id ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ ev.event_name }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ ev.original_created_at ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ JSON.stringify(ev.properties) }}</td>
                            </tr>
                            <tr v-if="!events.length">
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500">No rehydrated events match the selection. Try rehydrating a month above.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
