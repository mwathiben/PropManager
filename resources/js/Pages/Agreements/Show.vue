<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

defineProps({
    agreement: { type: Object, required: true },
});
</script>

<template>
    <Head :title="agreement.title" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto px-4 py-8">
            <Link :href="route('agreements.index')" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ t('agreements.show.back') }}</Link>

            <div class="mt-3 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">{{ agreement.title }}</h1>
                <span class="text-xs rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ t('agreements.status.' + agreement.status) }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-1">{{ t('agreements.show.owner') }}: {{ agreement.owner?.name }}</p>

            <p v-if="agreement.status === 'draft'" class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                {{ t('agreements.show.draft_note') }}
            </p>

            <div class="mt-5 bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div v-for="(clause, i) in agreement.clauses" :key="i" class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                    <h3 class="text-sm font-semibold text-gray-900">{{ clause.title }}</h3>
                    <p class="text-xs text-gray-500 mb-1">{{ clause.explanation }}</p>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ clause.body }}</p>
                </div>
            </div>

            <p v-if="agreement.content_hash" class="mt-3 text-xs text-gray-400 font-mono break-all">
                {{ t('agreements.show.hash') }}: {{ agreement.content_hash }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
