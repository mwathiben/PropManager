<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon, PlusIcon } from '@heroicons/vue/24/outline';

interface Meter {
    id: number;
    serial_number: string | null;
    status: string;
    utility_type: string;
    meter_type: string | null;
    initial_reading: string | number;
    current_value: number;
    unit: string | null;
    building: string | null;
    installed_at: string | null;
    decommissioned_at: string | null;
    replaced_by_meter_id: number | null;
    readings_count: number;
}

interface BuildingOption {
    id: number;
    name: string;
    units: Array<{ id: number; unit_number: string }>;
}

const props = defineProps<{ meters: Meter[]; buildings: BuildingOption[] }>();
const { t } = useI18n();

const showCreate = ref(false);
const replacingId = ref<number | null>(null);

const createForm = useForm({
    building_id: null as number | null,
    unit_id: null as number | null,
    serial_number: '',
    meter_type: '',
    initial_reading: 0,
    installed_at: '',
    notes: '',
});

const replaceForm = useForm({
    old_final_reading: 0,
    new_serial: '',
    new_initial_reading: 0,
    reading_date: '',
});

const decommissionForm = useForm({});

const submitCreate = () => {
    createForm.post(route('meters.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};

const submitReplace = (meterId: number) => {
    replaceForm.post(route('meters.replace', meterId), {
        preserveScroll: true,
        onSuccess: () => {
            replaceForm.reset();
            replacingId.value = null;
        },
    });
};

const decommission = (meterId: number) => {
    if (! window.confirm(t('meter.decommission.confirm'))) {
        return;
    }
    decommissionForm.post(route('meters.decommission', meterId), { preserveScroll: true });
};

const statusTone = (status: string) => ({
    active: 'bg-emerald-100 text-emerald-800',
    replaced: 'bg-gray-100 text-gray-700',
    decommissioned: 'bg-gray-100 text-gray-500',
    faulty: 'bg-amber-100 text-amber-800',
    inactive: 'bg-gray-100 text-gray-600',
}[status] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head :title="t('meter.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-cyan-100">
                            <BeakerIcon class="w-6 h-6 text-cyan-600" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ t('meter.title') }}</h1>
                            <p class="text-gray-600">{{ t('meter.subtitle') }}</p>
                        </div>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-700" @click="showCreate = ! showCreate">
                        <PlusIcon class="w-4 h-4" /> {{ t('meter.add') }}
                    </button>
                </div>

                <!-- Create form -->
                <form v-if="showCreate" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" @submit.prevent="submitCreate">
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.building') }}</span>
                        <select v-model="createForm.building_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.unit') }}</span>
                        <select v-model="createForm.unit_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <template v-for="b in buildings" :key="b.id">
                                <option v-for="u in b.units" :key="u.id" :value="u.id">{{ b.name }} · {{ u.unit_number }}</option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.serial') }}</span>
                        <input v-model="createForm.serial_number" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.meter_type') }}</span>
                        <input v-model="createForm.meter_type" type="text" class="mt-1 w-full rounded-md border-gray-300" />
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.initial_reading') }}</span>
                        <input v-model="createForm.initial_reading" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300" />
                        <span class="text-xs text-gray-400">{{ t('meter.form.initial_hint') }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700">{{ t('meter.form.installed_at') }}</span>
                        <input v-model="createForm.installed_at" type="date" class="mt-1 w-full rounded-md border-gray-300" />
                    </label>
                    <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2">
                        <button type="button" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="showCreate = false">{{ t('meter.form.cancel') }}</button>
                        <button type="submit" :disabled="createForm.processing" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{{ t('meter.form.save') }}</button>
                    </div>
                </form>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table v-if="meters.length" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.serial') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.building') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.unit') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.baseline') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.current') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.status') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase">{{ t('meter.columns.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <template v-for="m in meters" :key="m.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ m.serial_number || '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ m.building || '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ m.unit || '—' }}</td>
                                    <td class="px-4 py-3 text-end text-gray-600">{{ m.initial_reading }}</td>
                                    <td class="px-4 py-3 text-end text-gray-600">{{ m.current_value }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusTone(m.status)]">{{ t(`meter.status.${m.status}`) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div v-if="m.status === 'active'" class="flex justify-end gap-2">
                                            <button type="button" class="text-cyan-700 hover:text-cyan-900 text-xs" @click="replacingId = replacingId === m.id ? null : m.id">{{ t('meter.replace.title') }}</button>
                                            <button type="button" class="text-red-600 hover:text-red-800 text-xs" @click="decommission(m.id)">{{ t('meter.status.decommissioned') }}</button>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                </tr>
                                <tr v-if="replacingId === m.id" class="bg-cyan-50/40">
                                    <td colspan="7" class="px-4 py-3">
                                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitReplace(m.id)">
                                            <label class="block text-xs">
                                                <span class="text-gray-600">{{ t('meter.replace.old_final_reading') }}</span>
                                                <input v-model="replaceForm.old_final_reading" type="number" step="0.01" min="0" class="mt-1 w-40 rounded-md border-gray-300 text-sm" />
                                            </label>
                                            <label class="block text-xs">
                                                <span class="text-gray-600">{{ t('meter.replace.new_serial') }}</span>
                                                <input v-model="replaceForm.new_serial" type="text" class="mt-1 w-40 rounded-md border-gray-300 text-sm" />
                                            </label>
                                            <label class="block text-xs">
                                                <span class="text-gray-600">{{ t('meter.replace.new_initial_reading') }}</span>
                                                <input v-model="replaceForm.new_initial_reading" type="number" step="0.01" min="0" class="mt-1 w-40 rounded-md border-gray-300 text-sm" />
                                            </label>
                                            <button type="submit" :disabled="replaceForm.processing" class="rounded-lg bg-cyan-600 px-3 py-2 text-xs font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{{ t('meter.replace.submit') }}</button>
                                            <p class="basis-full text-xs text-gray-400">{{ t('meter.replace.hint') }}</p>
                                        </form>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-else class="p-8 text-center text-gray-500">{{ t('meter.empty') }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
