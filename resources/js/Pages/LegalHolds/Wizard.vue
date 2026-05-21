<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ScaleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';
import SubjectPicker from '@/Components/LegalHold/SubjectPicker.vue';
import LegalHoldHelpPanel from '@/Components/LegalHold/LegalHoldHelpPanel.vue';

interface Situation {
    key: string;
    suggested_types: string[];
    review_days: number | null;
}

const props = defineProps<{
    tenants: { id: number; name: string }[];
    situations: Situation[];
}>();

const { t } = useI18n();

const STEPS = ['step_situation', 'step_preserve', 'step_details', 'step_review'] as const;
const step = ref(0);
const currentSuggested = ref<string[]>([]);
// The reason text we last auto-filled — lets us prefill again ONLY if the user
// hasn't since edited it, so re-picking a situation never clobbers their wording.
const lastTemplate = ref('');

const form = useForm({
    situation: null as string | null,
    title: '',
    matter_reference: '',
    review_by: '' as string | null,
    reason: '',
    subjects: {} as Record<string, number[]>,
});

function pickSituation(situation: Situation | null): void {
    if (situation === null) {
        form.situation = null;
        currentSuggested.value = [];
    } else {
        form.situation = situation.key;
        currentSuggested.value = situation.suggested_types;
        const template = t(`legal_holds.wizard.situations.${situation.key}.reason`);
        // Only overwrite the reason if it's empty or still the last template we set.
        if (form.reason.trim() === '' || form.reason === lastTemplate.value) {
            form.reason = template;
            lastTemplate.value = template;
        }
        if (situation.review_days) {
            form.review_by = new Date(Date.now() + situation.review_days * 86400000).toISOString().slice(0, 10);
        }
    }
    step.value = 1;
}

const totalSubjects = computed(() =>
    Object.values(form.subjects).reduce((sum, ids) => sum + ids.length, 0),
);

const canNext = computed(() => {
    if (step.value === 1) return totalSubjects.value > 0;
    if (step.value === 2) return form.title.trim().length > 0 && form.reason.trim().length >= 10;
    return true;
});

function next(): void {
    if (step.value < STEPS.length - 1 && canNext.value) step.value += 1;
}
function back(): void {
    if (step.value > 0) step.value -= 1;
}

function submit(): void {
    form.post(route('legal-holds.wizard.store'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('legal_holds.wizard.title')" />

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-3">
                <ScaleIcon class="h-6 w-6 text-gray-500" />
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('legal_holds.wizard.title') }}</h1>
            </header>

            <div class="flex items-center justify-between text-sm">
                <ol class="flex flex-wrap gap-2" data-testid="wizard-step">
                    <li
                        v-for="(s, i) in STEPS"
                        :key="s"
                        class="rounded-full px-3 py-1 text-xs font-medium"
                        :class="i === step ? 'bg-indigo-600 text-white' : i < step ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ t(`legal_holds.wizard.${s}`) }}
                    </li>
                </ol>
                <span class="text-xs text-gray-400">{{ t('legal_holds.wizard.step_of', { current: step + 1, total: STEPS.length }) }}</span>
            </div>

            <LegalHoldHelpPanel />

            <div class="rounded-lg bg-white p-5 shadow" data-testid="hold-wizard">
                <!-- Step 1: situation -->
                <section v-if="step === 0" class="space-y-3">
                    <h2 class="text-sm font-medium text-gray-700">{{ t('legal_holds.wizard.situation_intro') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="situation in situations"
                            :key="situation.key"
                            type="button"
                            class="rounded-lg border border-gray-200 p-3 text-start hover:border-indigo-400 hover:bg-indigo-50"
                            @click="pickSituation(situation)"
                        >
                            <span class="block font-medium text-gray-900">{{ t(`legal_holds.wizard.situations.${situation.key}.label`) }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ t(`legal_holds.wizard.situations.${situation.key}.desc`) }}</span>
                        </button>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:underline" @click="pickSituation(null)">
                        {{ t('legal_holds.wizard.custom') }}
                    </button>
                </section>

                <!-- Step 2: preserve -->
                <section v-else-if="step === 1">
                    <SubjectPicker v-model="form.subjects" :tenants="tenants" :auto-select-types="currentSuggested" />
                    <p v-if="form.errors.subjects" class="mt-2 text-sm text-rose-600">{{ form.errors.subjects }}</p>
                </section>

                <!-- Step 3: details -->
                <section v-else-if="step === 2" class="space-y-4">
                    <div>
                        <label for="w-title" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.wizard.title_label') }}</label>
                        <input id="w-title" v-model="form.title" type="text" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" :placeholder="t('legal_holds.wizard.title_placeholder')" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label for="w-ref" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.wizard.reference_label') }}</label>
                        <input id="w-ref" v-model="form.matter_reference" type="text" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" :placeholder="t('legal_holds.wizard.reference_placeholder')" />
                    </div>
                    <div>
                        <label for="w-reason" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.wizard.reason_label') }}</label>
                        <textarea id="w-reason" v-model="form.reason" rows="3" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                        <p v-if="form.errors.reason" class="mt-1 text-xs text-rose-600">{{ form.errors.reason }}</p>
                    </div>
                    <div>
                        <label for="w-review" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.wizard.review_by_label') }}</label>
                        <input id="w-review" v-model="form.review_by" type="date" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                </section>

                <!-- Step 4: review -->
                <section v-else class="space-y-3 text-sm">
                    <h2 class="font-medium text-gray-700">{{ t('legal_holds.wizard.review_intro') }}</h2>
                    <dl class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500">{{ t('legal_holds.matters.col_title') }}</dt>
                        <dd class="col-span-2 text-gray-900">{{ form.title }}</dd>
                        <dt class="text-gray-500">{{ t('legal_holds.matters.col_reference') }}</dt>
                        <dd class="col-span-2 text-gray-900">{{ form.matter_reference || '—' }}</dd>
                        <dt class="text-gray-500">{{ t('legal_holds.matters.col_review') }}</dt>
                        <dd class="col-span-2 text-gray-900">{{ form.review_by || '—' }}</dd>
                    </dl>
                    <p class="rounded-md bg-indigo-50 px-3 py-2 text-indigo-900" data-testid="wizard-review-freeze">
                        {{ t('legal_holds.wizard.review_freeze', { count: totalSubjects }) }}
                    </p>
                    <p class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">{{ t('legal_holds.wizard.review_dpa') }}</p>
                    <p v-if="form.errors.subjects" class="text-sm text-rose-600">{{ form.errors.subjects }}</p>
                </section>

                <div class="mt-5 flex items-center justify-between">
                    <button v-if="step > 0" type="button" class="text-sm text-gray-600 hover:underline" @click="back">
                        {{ t('legal_holds.wizard.back') }}
                    </button>
                    <Link v-else :href="route('legal-holds.index')" class="text-sm text-gray-600 hover:underline">
                        {{ t('legal_holds.wizard.back') }}
                    </Link>

                    <button
                        v-if="step < STEPS.length - 1 && step > 0"
                        type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!canNext"
                        data-testid="wizard-next"
                        @click="next"
                    >
                        {{ t('legal_holds.wizard.next') }}
                    </button>
                    <button
                        v-else-if="step === STEPS.length - 1"
                        type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="form.processing"
                        data-testid="wizard-submit"
                        @click="submit"
                    >
                        {{ form.processing ? t('legal_holds.wizard.creating') : t('legal_holds.wizard.submit') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
