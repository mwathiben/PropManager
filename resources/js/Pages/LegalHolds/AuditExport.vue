<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

interface RecentExport {
    label: string;
    requestedAt: string;
}

const today = new Date().toISOString().slice(0, 10);
const oneMonthAgo = new Date();
oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

const from = ref<string>(oneMonthAgo.toISOString().slice(0, 10));
const to = ref<string>(today);
const submitting = ref(false);
const recents = ref<RecentExport[]>([]);

const STORAGE_KEY = 'legal_hold_audit_export_recents';

onMounted(() => {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) recents.value = JSON.parse(raw);
    } catch (e) {
        recents.value = [];
    }
});

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;

    const label = `${from.value} → ${to.value}`;
    recents.value = [{ label, requestedAt: new Date().toISOString() }, ...recents.value].slice(0, 10);
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(recents.value));
    } catch (e) {
        // session storage quota or disabled — non-fatal.
    }

    window.location.href = route('legal-holds.audit-export', { from: from.value, to: to.value });

    setTimeout(() => { submitting.value = false; }, 2000);
};
</script>

<template>
    <Head title="Legal hold audit export" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <ScaleIcon class="h-6 w-6 text-indigo-600" />
                <h2 class="text-xl font-semibold text-gray-900">Legal hold audit export</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 rounded-2xl">
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-6">
                        <p class="text-sm text-gray-600 mb-6">
                            Generates a CSV of every hold/release action over the selected window with
                            actor + lawful basis. Maximum 2-year range. Download link expires 5 minutes
                            after generation.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="from">
                                    From
                                </label>
                                <input
                                    id="from"
                                    v-model="from"
                                    type="date"
                                    class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    data-testid="audit-from"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="to">
                                    To
                                </label>
                                <input
                                    id="to"
                                    v-model="to"
                                    type="date"
                                    class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    data-testid="audit-to"
                                />
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="submit"
                            :disabled="submitting"
                            class="mt-6 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50"
                            data-testid="audit-submit"
                        >
                            <ArrowDownTrayIcon class="h-4 w-4" />
                            {{ submitting ? 'Generating…' : 'Download CSV' }}
                        </button>
                    </div>
                </div>

                <div v-if="recents.length > 0" class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-6">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Recent exports (this session)</h3>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="(r, i) in recents" :key="i" class="py-2 text-sm text-gray-600 flex justify-between">
                            <span>{{ r.label }}</span>
                            <span class="text-gray-400">{{ r.requestedAt }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
