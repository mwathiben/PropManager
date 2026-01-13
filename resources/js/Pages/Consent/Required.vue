<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    documents: Array,
});

const acceptedConsents = ref([]);
const expandedDocs = ref([]);

const form = useForm({
    consents: [],
});

const toggleExpand = (type) => {
    const index = expandedDocs.value.indexOf(type);
    if (index === -1) {
        expandedDocs.value.push(type);
    } else {
        expandedDocs.value.splice(index, 1);
    }
};

const isExpanded = (type) => expandedDocs.value.includes(type);

const toggleAccept = (type, version) => {
    const key = `${type}:${version}`;
    const index = acceptedConsents.value.indexOf(key);
    if (index === -1) {
        acceptedConsents.value.push(key);
    } else {
        acceptedConsents.value.splice(index, 1);
    }
};

const isAccepted = (type, version) => {
    return acceptedConsents.value.includes(`${type}:${version}`);
};

const allAccepted = () => {
    return props.documents.every(doc => isAccepted(doc.type, doc.version));
};

const submit = () => {
    form.consents = acceptedConsents.value;
    form.post(route('consent.accept'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Accept Terms" />

        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Legal Agreement Required</h1>
                <p class="mt-2 text-gray-600">
                    Please review and accept our updated terms to continue using PropManager.
                </p>
            </div>

            <div class="space-y-4">
                <div
                    v-for="doc in documents"
                    :key="doc.type"
                    class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
                >
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ doc.title }}
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    Version {{ doc.version }} · Effective {{ doc.effective_date }}
                                </p>
                                <p v-if="doc.summary" class="text-sm text-gray-600 mt-2">
                                    {{ doc.summary }}
                                </p>
                            </div>
                            <button
                                @click="toggleExpand(doc.type)"
                                class="ml-4 text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                            >
                                {{ isExpanded(doc.type) ? 'Hide' : 'Read Full' }}
                            </button>
                        </div>

                        <div
                            v-if="isExpanded(doc.type)"
                            class="mt-4 p-4 bg-gray-50 rounded-lg max-h-64 overflow-y-auto"
                        >
                            <a
                                :href="route('legal.view', doc.type)"
                                target="_blank"
                                class="text-indigo-600 hover:text-indigo-800 text-sm"
                            >
                                Open in new tab →
                            </a>
                        </div>

                        <div class="mt-4 flex items-center">
                            <input
                                type="checkbox"
                                :id="`accept-${doc.type}`"
                                :checked="isAccepted(doc.type, doc.version)"
                                @change="toggleAccept(doc.type, doc.version)"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            >
                            <label :for="`accept-${doc.type}`" class="ml-2 text-sm text-gray-700">
                                I have read and agree to the {{ doc.type_name }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <button
                    @click="submit"
                    :disabled="!allAccepted() || form.processing"
                    :class="[
                        'w-full py-3 px-4 rounded-lg font-medium transition-colors',
                        allAccepted()
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    ]"
                >
                    <span v-if="form.processing">Processing...</span>
                    <span v-else>Continue to PropManager</span>
                </button>
                <p class="mt-2 text-xs text-gray-500 text-center">
                    By clicking Continue, you agree to be bound by these terms.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
