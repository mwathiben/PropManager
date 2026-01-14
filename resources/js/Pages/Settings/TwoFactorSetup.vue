<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';

const props = defineProps({
    qrCode: String,
    secret: String,
});

const showSecret = ref(false);

const form = useForm({
    code: '',
});

const confirm = () => {
    form.post(route('two-factor.confirm'), {
        preserveScroll: true,
    });
};

const copySecret = () => {
    navigator.clipboard.writeText(props.secret);
};
</script>

<template>
    <Head title="Setup Two-Factor Authentication" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Setup Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <Link :href="route('two-factor.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                            <ArrowLeftIcon class="w-4 h-4" />
                            Back to Two-Factor Authentication
                        </Link>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Setup Two-Factor Authentication</h1>
                        <p class="text-sm text-gray-600 mb-6">
                            Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.)
                        </p>

                        <!-- QR Code -->
                        <div class="flex justify-center mb-6">
                            <div class="p-4 bg-white border-2 border-gray-200 rounded-lg" v-html="qrCode"></div>
                        </div>

                        <!-- Manual Entry Option -->
                        <div class="mb-6">
                            <button
                                @click="showSecret = !showSecret"
                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                            >
                                {{ showSecret ? 'Hide' : 'Show' }} setup key for manual entry
                            </button>

                            <div v-if="showSecret" class="mt-3 p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-600 mb-2">Enter this key manually in your authenticator app:</p>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded text-sm font-mono break-all">
                                        {{ secret }}
                                    </code>
                                    <button
                                        @click="copySecret"
                                        class="px-3 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded"
                                        title="Copy to clipboard"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Verification Form -->
                        <form @submit.prevent="confirm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Enter the 6-digit code from your authenticator app
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    placeholder="000000"
                                    class="w-full text-center text-2xl tracking-widest font-mono border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    autofocus
                                >
                                <p v-if="form.errors.code" class="mt-2 text-sm text-red-600">{{ form.errors.code }}</p>
                            </div>

                            <div class="flex gap-3">
                                <a
                                    :href="route('two-factor.index')"
                                    class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md font-medium"
                                >
                                    Cancel
                                </a>
                                <button
                                    type="submit"
                                    :disabled="form.processing || form.code.length !== 6"
                                    class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 font-medium"
                                >
                                    {{ form.processing ? 'Verifying...' : 'Verify and Enable' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm text-blue-800">
                            <strong>Important:</strong> After enabling two-factor authentication, you will receive recovery codes.
                            Store them safely - they can be used to access your account if you lose your authenticator device.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
