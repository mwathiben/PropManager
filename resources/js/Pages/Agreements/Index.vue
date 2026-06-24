<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

defineProps({
    agreements: { type: Object, default: () => ({ data: [] }) },
});
</script>

<template>
    <Head :title="t('agreements.index.title')" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('agreements.index.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('agreements.index.subtitle') }}</p>
                </div>
                <Link :href="route('agreements.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ t('agreements.index.new') }}
                </Link>
            </div>

            <div v-if="!agreements.data.length" class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">
                {{ t('agreements.index.none') }}
            </div>

            <div v-else class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                <Link
                    v-for="agreement in agreements.data"
                    :key="agreement.id"
                    :href="route('agreements.show', agreement.id)"
                    class="flex items-center justify-between px-5 py-4 hover:bg-gray-50"
                >
                    <span>
                        <span class="block text-sm font-medium text-gray-900">{{ agreement.title }}</span>
                        <span class="block text-xs text-gray-500">{{ t('agreements.index.owner') }}: {{ agreement.owner_name }} · {{ agreement.created_at }}</span>
                    </span>
                    <span class="text-xs rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ t('agreements.status.' + agreement.status) }}</span>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
