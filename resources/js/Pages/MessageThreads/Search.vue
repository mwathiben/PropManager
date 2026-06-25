<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import { ref, watch } from 'vue';

interface SearchRow {
    thread_id: number;
    title: string | null;
    status: string;
    last_message_at: string | null;
    snippet: string;
    matched_at: string | null;
}

interface Paginated {
    data: SearchRow[];
    total: number;
}

const props = defineProps<{ q: string; results: Paginated }>();
const { t } = useI18n();
const query = ref(props.q);

let debounce: ReturnType<typeof setTimeout> | null = null;
watch(query, (value) => {
    if (debounce) {
        clearTimeout(debounce);
    }
    debounce = setTimeout(() => {
        router.get(route('message-threads.search'), { q: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('inbox.search.title')" />

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900">{{ t('inbox.search.title') }}</h1>

            <input
                v-model="query"
                type="search"
                :placeholder="t('inbox.search.placeholder')"
                :aria-label="t('inbox.search.title')"
                class="w-full rounded-md border-gray-300 shadow-sm"
                data-testid="inbox-search-input"
            />

            <p v-if="q.length < 3" class="text-sm text-gray-400">{{ t('inbox.search.empty') }}</p>
            <p v-else-if="results.total === 0" class="text-sm text-gray-400">
                {{ t('inbox.search.no_results', { term: q }) }}
            </p>
            <ul v-else class="space-y-2" data-testid="inbox-search-results">
                <li
                    v-for="row in results.data"
                    :key="row.thread_id"
                    class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-100"
                >
                    <Link
                        :href="route('message-threads.show', row.thread_id)"
                        class="font-medium text-indigo-700 hover:underline"
                    >
                        {{ row.title || `Thread #${row.thread_id}` }}
                    </Link>
                    <p class="mt-1 text-sm text-gray-600">{{ row.snippet }}</p>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
