<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

interface Participant {
    id: number;
    name: string;
    role: string;
}

interface Thread {
    id: number;
    title: string | null;
    status: 'open' | 'archived' | 'locked';
    last_message_at: string | null;
    participants: Participant[];
}

interface Props {
    threads: {
        data: Thread[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

defineProps<Props>();
const { t } = useI18n();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('message_threads_index.head_title')" />

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('message_threads_index.heading') }}</h1>
                <Link
                    :href="route('message-threads.search')"
                    class="text-sm font-medium text-indigo-600 hover:underline"
                    data-testid="inbox-search"
                >
                    {{ t('inbox.search.title') }}
                </Link>
            </header>

            <ul class="divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
                <li v-for="thread in threads.data" :key="thread.id" class="hover:bg-gray-50">
                    <Link :href="route('message-threads.show', thread.id)" class="block px-4 py-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">
                                {{ thread.title || t('message_threads_index.thread_fallback_title', { id: thread.id }) }}
                            </p>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-emerald-100 text-emerald-800': thread.status === 'open',
                                    'bg-gray-100 text-gray-800': thread.status === 'archived',
                                    'bg-rose-100 text-rose-800': thread.status === 'locked',
                                }"
                            >
                                {{ thread.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ thread.participants.map(p => p.name).join(', ') }}
                        </p>
                    </Link>
                </li>
                <li v-if="threads.data.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                    {{ t('message_threads_index.empty') }}
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
