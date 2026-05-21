<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { DocumentTextIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline';

interface Props {
    document: {
        type: string;
        type_name: string;
        version: string;
        title: string;
        content: string;
        effective_date: string;
    };
    userConsent?: { version: string; granted_at: string } | null;
}

defineProps<Props>();
</script>

<template>
    <Head :title="document.title" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">{{ document.type_name }}</h1>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <article class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <header class="border-b border-gray-100 px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-semibold text-gray-900">{{ document.title }}</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Version {{ document.version }} · Effective {{ document.effective_date }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="userConsent"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700"
                    >
                        <CheckBadgeIcon class="w-5 h-5" />
                        You accepted version {{ userConsent.version }} on {{ userConsent.granted_at }}
                    </div>
                </header>

                <div class="prose prose-sm max-w-none px-6 py-6 text-gray-700 whitespace-pre-line">{{ document.content }}</div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
