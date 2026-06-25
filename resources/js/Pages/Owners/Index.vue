<script setup lang="ts">
/**
 * Phase-101 OWNER-FOUNDATION: the landlord/PM manages the owners they look after,
 * assigns properties to them, and emails/downloads each owner's statement.
 */
import { ref } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { UsersIcon, DocumentArrowDownIcon, EnvelopeIcon, PencilSquareIcon, TrashIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

interface Owner {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    id_number: string | null;
    notes: string | null;
    is_active: boolean;
    properties_count: number;
    has_login: boolean;
}
interface PropertyRow {
    id: number;
    name: string;
    owner_id: number | null;
    owner_name: string | null;
}

const props = withDefaults(defineProps<{ owners?: Owner[]; properties?: PropertyRow[] }>(), {
    owners: () => [],
    properties: () => [],
});

const { t } = useI18n();
const page = usePage();

const showForm = ref(false);
const editing = ref<Owner | null>(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    id_number: '',
    notes: '',
    is_active: true,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (owner: Owner) => {
    editing.value = owner;
    form.name = owner.name;
    form.email = owner.email ?? '';
    form.phone = owner.phone ?? '';
    form.id_number = owner.id_number ?? '';
    form.notes = owner.notes ?? '';
    form.is_active = owner.is_active;
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('finances.owners.update', editing.value.id), { onSuccess: () => (showForm.value = false) });
    } else {
        form.post(route('finances.owners.store'), { onSuccess: () => (showForm.value = false) });
    }
};

const destroy = (owner: Owner) => {
    if (confirm(t('owners.delete_confirm'))) {
        router.delete(route('finances.owners.destroy', owner.id), { preserveScroll: true });
    }
};

const assignOwner = (property: PropertyRow, ownerId: string) => {
    if (ownerId) {
        router.put(route('properties.owner.assign', { property: property.id, owner: ownerId }), {}, { preserveScroll: true });
    } else {
        router.delete(route('properties.owner.unassign', property.id), { preserveScroll: true });
    }
};

const downloadStatement = (owner: Owner) => {
    window.location.href = route('finances.owners.statement', { owner: owner.id, period: '12' });
};

const emailStatement = (owner: Owner) => {
    router.post(route('finances.owners.statement.email', owner.id), { period: '12' }, { preserveScroll: true });
};

const invite = (owner: Owner) => {
    router.post(route('finances.owners.invite', owner.id), {}, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('owners.title')" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-100 p-2"><UsersIcon class="h-6 w-6 text-indigo-600" /></div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">{{ t('owners.title') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('owners.subtitle') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    data-testid="owner-add"
                    @click="openCreate"
                >
                    {{ t('owners.add') }}
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-testid="owners-index">
            <div v-if="(page.props.flash as any)?.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700" data-testid="flash-success">
                {{ (page.props.flash as any).success }}
            </div>
            <div v-if="(page.props.flash as any)?.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700" data-testid="flash-error">
                {{ (page.props.flash as any).error }}
            </div>

            <p v-if="!owners.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('owners.none') }}
            </p>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('owners.fields.name') }}</th>
                            <th class="px-4 py-3">{{ t('owners.fields.email') }}</th>
                            <th class="px-4 py-3 text-center">{{ t('owners.fields.properties') }}</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="owner in owners" :key="owner.id" :data-testid="`owner-row-${owner.id}`">
                            <td class="px-4 py-3">
                                <Link :href="route('finances.owners.show', owner.id)" class="font-medium text-indigo-600 hover:text-indigo-800" :data-testid="`owner-link-${owner.id}`">{{ owner.name }}</Link>
                                <span v-if="!owner.is_active" class="ms-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ t('owners.fields.active') }}: —</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ owner.email || '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-700">{{ owner.properties_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        v-if="!owner.has_login"
                                        type="button"
                                        class="rounded p-1.5 text-indigo-600 hover:bg-indigo-50 disabled:opacity-40"
                                        :title="t('owners.actions.invite')"
                                        :disabled="!owner.email"
                                        data-testid="owner-invite"
                                        @click="invite(owner)"
                                    >
                                        <UserPlusIcon class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100" :title="t('owners.actions.download_statement')" @click="downloadStatement(owner)">
                                        <DocumentArrowDownIcon class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100" :title="t('owners.actions.email_statement')" :disabled="!owner.email" @click="emailStatement(owner)">
                                        <EnvelopeIcon class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100" :title="t('owners.edit')" @click="openEdit(owner)">
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1.5 text-rose-500 hover:bg-rose-50" :title="t('owners.actions.delete')" @click="destroy(owner)">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Property assignment -->
            <div v-if="properties.length" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ t('owners.assign.title') }}</h2>
                <div class="space-y-2">
                    <div v-for="property in properties" :key="property.id" class="flex items-center justify-between gap-3">
                        <span class="text-sm text-gray-800">{{ property.name }}</span>
                        <label :for="`assign-owner-${property.id}`" class="sr-only">{{ t('owners.assign.owner') }}</label>
                        <select
                            :id="`assign-owner-${property.id}`"
                            class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
                            :value="property.owner_id ?? ''"
                            :data-testid="`assign-${property.id}`"
                            @change="assignOwner(property, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">{{ t('owners.assign.unassigned') }}</option>
                            <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}{{ o.is_active ? '' : ' (—)' }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="button" tabindex="0" @click.self="showForm = false" @keydown.enter="showForm = false" @keydown.space.prevent="showForm = false">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ editing ? t('owners.edit') : t('owners.add') }}</h3>
                <form class="space-y-3" @submit.prevent="submit">
                    <div>
                        <label for="owner-name" class="block text-xs font-medium text-gray-600">{{ t('owners.fields.name') }}</label>
                        <input id="owner-name" v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="owner-email" class="block text-xs font-medium text-gray-600">{{ t('owners.fields.email') }}</label>
                        <input id="owner-email" v-model="form.email" type="email" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="owner-phone" class="block text-xs font-medium text-gray-600">{{ t('owners.fields.phone') }}</label>
                            <input id="owner-phone" v-model="form.phone" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label for="owner-id-number" class="block text-xs font-medium text-gray-600">{{ t('owners.fields.id_number') }}</label>
                            <input id="owner-id-number" v-model="form.id_number" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label for="owner-notes" class="block text-xs font-medium text-gray-600">{{ t('owners.fields.notes') }}</label>
                        <textarea id="owner-notes" v-model="form.notes" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <label v-if="editing" class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                        {{ t('owners.fields.active') }}
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" @click="showForm = false">{{ t('owners.actions.cancel') }}</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" :disabled="form.processing">{{ t('owners.actions.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
