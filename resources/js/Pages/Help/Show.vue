<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowLeftIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps({
    article: Object,
    relatedArticles: Array,
});

const renderedContent = computed(() => {
    return marked(props.article.content || '');
});

const categoryLabels = {
    'getting-started': 'Getting Started',
    'features': 'Features & How-To',
    'billing': 'Billing & Payments',
    'troubleshooting': 'Troubleshooting',
};
</script>

<template>
    <Head :title="article.title" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Back Link -->
                <Link
                    :href="route('help.index')"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-600 mb-6"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back to Help Center
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-2 text-sm text-indigo-600 mb-3">
                                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-semibold">
                                        {{ categoryLabels[article.category] || article.category }}
                                    </span>
                                </div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ article.title }}
                                </h1>
                            </div>
                            <div
                                class="px-8 py-6 prose prose-indigo max-w-none"
                                v-html="renderedContent"
                            />
                        </article>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div v-if="relatedArticles?.length" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Related Articles</h3>
                            </div>
                            <div class="divide-y divide-gray-100">
                                <Link
                                    v-for="related in relatedArticles"
                                    :key="related.id"
                                    :href="route('help.show', related.slug)"
                                    class="block px-4 py-3 hover:bg-gray-50 transition-colors"
                                >
                                    <div class="flex items-start gap-2">
                                        <DocumentTextIcon class="h-4 w-4 text-gray-400 mt-0.5 flex-shrink-0" />
                                        <span class="text-sm text-gray-700 hover:text-indigo-600">{{ related.title }}</span>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.prose h1 {
    @apply text-2xl font-bold text-gray-900 mt-6 mb-4;
}
.prose h2 {
    @apply text-xl font-semibold text-gray-900 mt-6 mb-3;
}
.prose h3 {
    @apply text-lg font-medium text-gray-900 mt-4 mb-2;
}
.prose p {
    @apply text-gray-600 mb-4 leading-relaxed;
}
.prose ul {
    @apply list-disc list-inside mb-4 text-gray-600;
}
.prose ol {
    @apply list-decimal list-inside mb-4 text-gray-600;
}
.prose li {
    @apply mb-1;
}
.prose code {
    @apply bg-gray-100 px-1 py-0.5 rounded text-sm text-indigo-600;
}
.prose pre {
    @apply bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4;
}
.prose a {
    @apply text-indigo-600 hover:text-indigo-800 underline;
}
</style>
