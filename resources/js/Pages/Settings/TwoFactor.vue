<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';

const props = defineProps({
    enabled: Boolean,
    required: Boolean,
    recoveryCodesCount: Number,
});

const showPasswordModal = ref(false);
const action = ref('');

const passwordForm = useForm({
    password: '',
});

const disableForm = useForm({
    password: '',
    code: '',
});

const startEnable = () => {
    action.value = 'enable';
    showPasswordModal.value = true;
};

const startViewCodes = () => {
    action.value = 'view-codes';
    showPasswordModal.value = true;
};

const confirmPassword = () => {
    if (action.value === 'enable') {
        passwordForm.post(route('two-factor.enable'), {
            preserveScroll: true,
            onSuccess: () => {
                showPasswordModal.value = false;
                passwordForm.reset();
            },
        });
    } else if (action.value === 'view-codes') {
        passwordForm.get(route('two-factor.recovery-codes'), {
            preserveScroll: true,
            onSuccess: () => {
                showPasswordModal.value = false;
                passwordForm.reset();
            },
        });
    }
};

const showDisableModal = ref(false);

const disableTwoFactor = () => {
    disableForm.post(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            showDisableModal.value = false;
            disableForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Two-Factor Authentication" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Back Link & Page Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <Link :href="route('settings.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                            <ArrowLeftIcon class="w-4 h-4" />
                            Back to Settings
                        </Link>
                        <h1 class="text-2xl font-bold text-gray-900">Two-Factor Authentication</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Add additional security to your account using two-factor authentication.
                        </p>
                    </div>
                </div>

                <!-- Status Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Enabled State -->
                        <div v-if="enabled" class="space-y-6">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Two-factor authentication is enabled</h3>
                                    <p class="text-sm text-gray-600">Your account is protected with an authenticator app.</p>
                                </div>
                            </div>

                            <!-- Recovery Codes Info -->
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="h-5 w-5 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-medium text-amber-800">Recovery Codes</h4>
                                        <p class="text-sm text-amber-700">
                                            You have <strong>{{ recoveryCodesCount }}</strong> recovery codes remaining.
                                            Store these codes safely - they can be used if you lose access to your authenticator app.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-wrap gap-3">
                                <button
                                    @click="startViewCodes"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium"
                                >
                                    View Recovery Codes
                                </button>
                                <button
                                    v-if="!required"
                                    @click="showDisableModal = true"
                                    class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 font-medium"
                                >
                                    Disable Two-Factor
                                </button>
                                <span v-else class="text-sm text-gray-500 self-center">
                                    Two-factor authentication is required for your account.
                                </span>
                            </div>
                        </div>

                        <!-- Disabled State -->
                        <div v-else class="space-y-6">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Two-factor authentication is not enabled</h3>
                                    <p class="text-sm text-gray-600">
                                        When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication.
                                    </p>
                                </div>
                            </div>

                            <!-- Required Warning -->
                            <div v-if="required" class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="h-5 w-5 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-medium text-red-800">Action Required</h4>
                                        <p class="text-sm text-red-700">
                                            Two-factor authentication is required for your account. Please enable it to continue using the application.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Enable Button -->
                            <button
                                @click="startEnable"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium"
                            >
                                Enable Two-Factor Authentication
                            </button>
                        </div>
                    </div>
                </div>

                <!-- How it Works -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">How it works</h3>
                        <div class="space-y-4">
                            <div class="flex gap-4">
                                <div class="shrink-0 h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold text-sm">1</div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Install an authenticator app</h4>
                                    <p class="text-sm text-gray-600">Download Google Authenticator, Authy, or Microsoft Authenticator on your phone.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="shrink-0 h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold text-sm">2</div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Scan the QR code</h4>
                                    <p class="text-sm text-gray-600">Use your authenticator app to scan the QR code we provide.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="shrink-0 h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold text-sm">3</div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Enter the 6-digit code</h4>
                                    <p class="text-sm text-gray-600">Enter the code from your app to verify and complete setup.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Confirmation Modal -->
        <div v-if="showPasswordModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showPasswordModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Password</h3>
                    <p class="text-sm text-gray-600 mb-4">Please confirm your password to continue.</p>

                    <form @submit.prevent="confirmPassword">
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            placeholder="Password"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mb-4"
                            autofocus
                        >
                        <p v-if="passwordForm.errors.password" class="text-sm text-red-600 mb-4">{{ passwordForm.errors.password }}</p>

                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showPasswordModal = false; passwordForm.reset();"
                                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ passwordForm.processing ? 'Confirming...' : 'Confirm' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Disable 2FA Modal -->
        <div v-if="showDisableModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showDisableModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Disable Two-Factor Authentication</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Enter your password and a code from your authenticator app to disable two-factor authentication.
                    </p>

                    <form @submit.prevent="disableTwoFactor">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input
                                    v-model="disableForm.password"
                                    type="password"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <p v-if="disableForm.errors.password" class="text-sm text-red-600 mt-1">{{ disableForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Authentication Code</label>
                                <input
                                    v-model="disableForm.code"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="Enter 6-digit code or recovery code"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <p v-if="disableForm.errors.code" class="text-sm text-red-600 mt-1">{{ disableForm.errors.code }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="showDisableModal = false; disableForm.reset();"
                                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="disableForm.processing"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                            >
                                {{ disableForm.processing ? 'Disabling...' : 'Disable' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
