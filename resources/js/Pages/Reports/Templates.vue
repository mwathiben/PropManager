<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    templates: { type: Array, required: true },
    categories: { type: Array, required: true },
})

function templatesIn(category) {
    return props.templates.filter((t) => t.category === category)
}

function cloneTemplate(template) {
    router.post(route('reports.templates.clone', template.id))
}
</script>

<template>
    <Head title="Report templates" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Report template marketplace</h1>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 p-6">
            <header class="space-y-1">
                <p class="text-sm text-gray-600">
                    Platform-curated reports. Clone one to make a private copy you can edit and schedule.
                </p>
            </header>

            <section v-for="category in categories" :key="category" class="space-y-3">
                <h2 class="text-lg font-medium capitalize">{{ category }}</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="template in templatesIn(category)"
                        :key="template.id"
                        class="flex flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                    >
                        <div class="space-y-2">
                            <h3 class="font-medium">{{ template.name }}</h3>
                            <p class="text-sm text-gray-600">{{ template.description }}</p>
                        </div>
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                            @click="cloneTemplate(template)"
                        >
                            Clone to my reports
                        </button>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
