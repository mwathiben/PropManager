<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import DOMPurify from 'dompurify';
import type { HelpIndexPageProps } from '@/types/help';
import {
    MagnifyingGlassIcon,
    RocketLaunchIcon,
    BookOpenIcon,
    CreditCardIcon,
    WrenchScrewdriverIcon,
    BellAlertIcon,
    ShieldCheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    EnvelopeIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<HelpIndexPageProps>();

const { logError } = useErrorHandler();
const { t } = useI18n();
const searchQuery = ref('');
const searchResults = ref({ faqs: [], articles: [] });
const isSearching = ref(false);
const expandedFaqs = ref({});
const activeCategory = ref('getting-started');

const categoryIcons = {
    'getting-started': RocketLaunchIcon,
    'features': BookOpenIcon,
    'billing': CreditCardIcon,
    'troubleshooting': WrenchScrewdriverIcon,
    'notifications': BellAlertIcon,
    'security': ShieldCheckIcon,
};

const toggleFaq = (faqId) => {
    expandedFaqs.value[faqId] = !expandedFaqs.value[faqId];
};

const searchHelp = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = { faqs: [], articles: [] };
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(route('help.search') + '?q=' + encodeURIComponent(searchQuery.value));
        searchResults.value = await response.json();
    } catch (error) {
        logError(error, { component: 'HelpIndex', action: 'search' });
    }
    isSearching.value = false;
};

const hasSearchResults = computed(() => {
    return searchResults.value.faqs?.length > 0 || searchResults.value.articles?.length > 0;
});

const currentCategoryFaqs = computed(() => {
    return props.faqs[activeCategory.value] || [];
});

const currentCategoryArticles = computed(() => {
    return props.articles[activeCategory.value] || [];
});
</script>

<template>
    <Head :title="t('help.page_title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h1 class="text-3xl font-bold text-gray-900">{{ t('help.title') }}</h1>
                    <p class="mt-2 text-gray-600">{{ t('help.subtitle') }}</p>
                </div>

                <!-- Search -->
                <div class="max-w-2xl mx-auto mb-10">
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute start-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            @input="searchHelp"
                            type="text"
                            :placeholder="t('help.search_placeholder')"
                            :aria-label="t('help.search_placeholder')"
                            class="w-full ps-12 pe-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg"
                        />
                    </div>

                    <!-- Search Results -->
                    <div v-if="searchQuery.length >= 2" class="mt-4 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div v-if="isSearching" class="p-4 text-center text-gray-500">
                            {{ t('help.searching') }}
                        </div>
                        <div v-else-if="hasSearchResults">
                            <div v-if="searchResults.faqs?.length" class="p-4 border-b border-gray-100">
                                <h4 class="text-sm font-medium text-gray-500 mb-2">{{ t('help.faqs') }}</h4>
                                <div v-for="faq in searchResults.faqs" :key="faq.id" class="py-2">
                                    <button
                                        @click="toggleFaq('search-' + faq.id)"
                                        class="w-full text-start font-medium text-gray-900 hover:text-indigo-600"
                                    >
                                        {{ faq.question }}
                                    </button>
                                    <p v-if="expandedFaqs['search-' + faq.id]" class="mt-2 text-gray-600 text-sm">
                                        {{ faq.answer }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="searchResults.articles?.length" class="p-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-2">{{ t('help.articles') }}</h4>
                                <Link
                                    v-for="article in searchResults.articles"
                                    :key="article.id"
                                    :href="route('help.show', article.slug)"
                                    class="block py-2 text-gray-900 hover:text-indigo-600"
                                >
                                    <DocumentTextIcon class="inline h-4 w-4 me-2" />
                                    {{ article.title }}
                                </Link>
                            </div>
                        </div>
                        <div v-else class="p-4 text-center text-gray-500">
                            {{ t('help.no_results', { query: searchQuery }) }}
                        </div>
                    </div>
                </div>

                <!-- Category Tabs -->
                <div class="flex flex-wrap justify-center gap-2 mb-8">
                    <button
                        v-for="(category, key) in categories"
                        :key="key"
                        @click="activeCategory = key"
                        :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors', activeCategory === key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200']"
                    >
                        <component :is="categoryIcons[key]" class="h-4 w-4" />
                        {{ category.name }}
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- FAQs Section -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ t('help.frequently_asked') }}
                                </h2>
                            </div>
                            <div class="px-6 py-4 border-b border-gray-100">
                                <p class="text-sm text-gray-600">{{ categories[activeCategory]?.description }}</p>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <div
                                    v-for="faq in currentCategoryFaqs"
                                    :key="faq.id"
                                    class="px-6 py-4"
                                >
                                    <button
                                        @click="toggleFaq(faq.id)"
                                        class="w-full flex items-center justify-between text-start"
                                    >
                                        <span class="font-medium text-gray-900">{{ faq.question }}</span>
                                        <component
                                            :is="expandedFaqs[faq.id] ? ChevronUpIcon : ChevronDownIcon"
                                            class="h-5 w-5 text-gray-400 shrink-0 ms-4"
                                        />
                                    </button>
                                    <div
                                        v-if="expandedFaqs[faq.id]"
                                        class="mt-3 text-gray-600 text-sm leading-relaxed prose prose-sm max-w-none"
                                        v-html="DOMPurify.sanitize(faq.answer)"
                                    />
                                </div>

                                <div v-if="!currentCategoryFaqs.length" class="px-6 py-8 text-center text-gray-500">
                                    {{ t('help.no_faqs') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Articles -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">{{ t('help.guides_articles') }}</h3>
                            </div>
                            <div class="divide-y divide-gray-100">
                                <Link
                                    v-for="article in currentCategoryArticles"
                                    :key="article.id"
                                    :href="route('help.show', article.slug)"
                                    class="block px-6 py-3 hover:bg-gray-50 transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <DocumentTextIcon class="h-5 w-5 text-indigo-500" />
                                        <span class="text-sm font-medium text-gray-900">{{ article.title }}</span>
                                    </div>
                                </Link>
                                <div v-if="!currentCategoryArticles.length" class="px-6 py-4 text-sm text-gray-500">
                                    {{ t('help.no_articles') }}
                                </div>
                            </div>
                        </div>

                        <!-- Contact Support -->
                        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-sm p-6 text-white">
                            <h3 class="font-semibold text-lg">{{ t('help.need_more_help') }}</h3>
                            <p class="mt-2 text-indigo-100 text-sm">
                                {{ t('help.support_blurb') }}
                            </p>
                            <a
                                :href="'mailto:' + supportEmail"
                                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-white text-indigo-600 rounded-lg font-medium text-sm hover:bg-indigo-50 transition-colors"
                            >
                                <EnvelopeIcon class="h-4 w-4" />
                                {{ t('help.contact_support') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
