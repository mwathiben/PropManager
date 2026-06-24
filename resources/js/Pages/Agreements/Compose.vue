<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    owners: { type: Array, default: () => [] },
    clauses: { type: Array, default: () => [] },
});

const form = useForm({
    title: 'Management agreement',
    property_owner_id: '',
    clauses: [],
});

const sel = reactive({});
props.clauses.forEach((clause) => {
    sel[clause.id] = {
        included: clause.binding === 'neutrality',
        params: defaultParams(clause),
    };
});

function defaultParams(clause) {
    if (clause.binding === 'management_fee') {
        return { type: 'percentage', value: 8, base: 'collected', flat_cadence: 'per_period' };
    }
    const params = {};
    (clause.params_schema || []).forEach((field) => {
        params[field.name] = '';
    });
    return params;
}

function feeDescription(params) {
    if (params.type === 'flat') {
        const amount = 'KES ' + Number(params.value || 0).toLocaleString();
        return params.flat_cadence === 'per_unit'
            ? `a flat ${amount} per occupied unit`
            : `a flat ${amount} per period`;
    }
    return `${Number(params.value || 0)}% of rent ${params.base || 'collected'}`;
}

function renderClause(clause) {
    const params = { ...sel[clause.id].params };
    if (clause.binding === 'management_fee') {
        params.fee_description = feeDescription(params);
    }
    return (clause.body_template || '').replace(/\{(\w+)\}/g, (match, key) =>
        params[key] !== undefined && params[key] !== '' ? params[key] : match,
    );
}

const includedClauses = computed(() => props.clauses.filter((clause) => sel[clause.id]?.included));

function submit() {
    form
        .transform((data) => ({
            title: data.title,
            property_owner_id: data.property_owner_id,
            clauses: includedClauses.value.map((clause) => ({
                clause_id: clause.id,
                params: sel[clause.id].params,
            })),
        }))
        .post(route('agreements.store'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('agreements.compose.title')" />

    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ t('agreements.compose.title') }}</h1>
                <Link :href="route('agreements.index')" class="text-sm text-gray-500 hover:text-gray-700">{{ t('agreements.compose.cancel') }}</Link>
            </div>

            <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-5">
                <div class="lg:col-span-3 space-y-5">
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ t('agreements.compose.owner') }} *</label>
                        <select v-model="form.property_owner_id" class="w-full rounded-lg border-gray-300">
                            <option value="">{{ t('agreements.compose.owner_placeholder') }}</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.property_owner_id" />
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 class="text-base font-semibold text-gray-900">{{ t('agreements.compose.clauses') }}</h2>
                        <p class="text-sm text-gray-500 mb-3">{{ t('agreements.compose.clauses_hint') }}</p>
                        <InputError class="mb-2" :message="form.errors.clauses" />

                        <div v-for="clause in clauses" :key="clause.id" class="border border-gray-200 rounded-lg p-3 mb-2">
                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="sel[clause.id].included"
                                    :disabled="clause.binding === 'neutrality'"
                                    class="mt-1 rounded border-gray-300"
                                />
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-gray-900">
                                        {{ clause.title }}
                                        <span v-if="clause.binding === 'neutrality'" class="ml-2 text-xs text-red-600">{{ t('agreements.compose.required_clause') }}</span>
                                    </span>
                                    <span class="block text-xs text-gray-500">{{ clause.explanation }}</span>
                                </span>
                            </label>

                            <div v-if="sel[clause.id].included && clause.binding === 'management_fee'" class="mt-3 grid grid-cols-3 gap-2 pl-7">
                                <label class="text-xs text-gray-600">{{ t('agreements.compose.fee_type') }}
                                    <select v-model="sel[clause.id].params.type" class="w-full rounded border-gray-300 text-sm">
                                        <option value="percentage">percentage</option>
                                        <option value="flat">flat</option>
                                    </select>
                                </label>
                                <label v-if="sel[clause.id].params.type === 'percentage'" class="text-xs text-gray-600">{{ t('agreements.compose.fee_base') }}
                                    <select v-model="sel[clause.id].params.base" class="w-full rounded border-gray-300 text-sm">
                                        <option value="collected">collected</option>
                                        <option value="billed">billed</option>
                                        <option value="scheduled">scheduled</option>
                                    </select>
                                </label>
                                <label v-else class="text-xs text-gray-600">{{ t('agreements.compose.fee_cadence') }}
                                    <select v-model="sel[clause.id].params.flat_cadence" class="w-full rounded border-gray-300 text-sm">
                                        <option value="per_period">per_period</option>
                                        <option value="per_unit">per_unit</option>
                                    </select>
                                </label>
                                <label class="text-xs text-gray-600">{{ t('agreements.compose.fee_value') }}
                                    <input type="number" min="0" step="0.5" v-model="sel[clause.id].params.value" class="w-full rounded border-gray-300 text-sm" />
                                </label>
                            </div>

                            <div v-else-if="sel[clause.id].included && (clause.params_schema || []).length" class="mt-3 grid grid-cols-2 gap-2 pl-7">
                                <label v-for="field in clause.params_schema" :key="field.name" class="text-xs text-gray-600">{{ field.name }}
                                    <input type="text" v-model="sel[clause.id].params[field.name]" class="w-full rounded border-gray-300 text-sm" />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl border border-gray-200 p-5 sticky top-4">
                        <h2 class="text-base font-semibold text-gray-900 mb-3">{{ t('agreements.compose.preview') }}</h2>
                        <p v-if="!includedClauses.length" class="text-sm text-gray-400">{{ t('agreements.compose.preview_empty') }}</p>
                        <div v-else class="space-y-3">
                            <p v-for="clause in includedClauses" :key="clause.id" class="text-sm text-gray-700 leading-relaxed border-b border-gray-100 pb-3">
                                {{ renderClause(clause) }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.property_owner_id || !includedClauses.length"
                            class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ t('agreements.compose.submit') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
