<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { AdminSettingsPageProps } from '@/types';
import {
    CreditCardIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowPathIcon,
    InformationCircleIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<AdminSettingsPageProps>();

// Payment form
const paymentForm = useForm({
    paystack_public_key: '',
    paystack_secret_key: '',
});

const showPaystackPublicKey = ref(false);
const showPaystackSecretKey = ref(false);
const testingPaystack = ref(false);
const paystackTestResult = ref(null);

const savePaymentSettings = () => {
    paymentForm.post(route('admin.settings.payment'), {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.reset();
        },
    });
};

const testPaystackConnection = async () => {
    if (!paymentForm.paystack_secret_key) {
        paystackTestResult.value = { success: false, message: 'Please enter your secret key first' };
        return;
    }

    testingPaystack.value = true;
    paystackTestResult.value = null;

    try {
        const response = await fetch(route('admin.settings.payment.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                paystack_secret_key: paymentForm.paystack_secret_key,
            }),
        });
        paystackTestResult.value = await response.json();
    } catch (error) {
        paystackTestResult.value = { success: false, message: 'Connection failed: ' + error.message };
    } finally {
        testingPaystack.value = false;
    }
};
</script>

<template>
    <Head title="System Settings" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
                        <p class="text-gray-600">Configure payment gateway for subscription payments</p>
                    </div>
                    <Link :href="route('dashboard')" class="text-indigo-600 hover:text-indigo-700">
                        Back to Dashboard
                    </Link>
                </div>

                <!-- Info Banner: Email/SMS Configuration Moved -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex gap-3">
                        <InformationCircleIcon class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-800">
                            <p class="font-medium">Email and SMS Configuration</p>
                            <p class="mt-1">
                                Email and SMS provider settings are now configured in the
                                <Link :href="route('notifications.overview')" class="underline font-medium">
                                    Notification Center
                                </Link>
                                under Operations &gt; Notifications &gt; Settings.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Payment Gateway Settings -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-4">
                        <div class="h-12 w-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                            <CreditCardIcon class="h-6 w-6 text-green-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900">Payment Gateway (Paystack)</h2>
                            <p class="text-sm text-gray-500">Configure Paystack for subscription payments</p>
                        </div>
                        <div class="shrink-0">
                            <span v-if="paymentSettings.has_paystack_secret_key" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                <CheckCircleIcon class="h-4 w-4" />
                                Configured
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">
                                <ExclamationTriangleIcon class="h-4 w-4" />
                                Not Configured
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="savePaymentSettings" class="p-6 space-y-4">
                        <!-- Public Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Public Key</label>
                            <div class="relative">
                                <input
                                    :type="showPaystackPublicKey ? 'text' : 'password'"
                                    v-model="paymentForm.paystack_public_key"
                                    :placeholder="paymentSettings.has_paystack_public_key ? paymentSettings.paystack_public_key : 'pk_live_...'"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                />
                                <button
                                    type="button"
                                    @click="showPaystackPublicKey = !showPaystackPublicKey"
                                    class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                >
                                    <EyeIcon v-if="!showPaystackPublicKey" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <p v-if="paymentSettings.has_paystack_public_key" class="mt-1 text-xs text-gray-500">
                                Leave blank to keep current key
                            </p>
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                            <div class="relative">
                                <input
                                    :type="showPaystackSecretKey ? 'text' : 'password'"
                                    v-model="paymentForm.paystack_secret_key"
                                    :placeholder="paymentSettings.has_paystack_secret_key ? paymentSettings.paystack_secret_key : 'sk_live_...'"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                />
                                <button
                                    type="button"
                                    @click="showPaystackSecretKey = !showPaystackSecretKey"
                                    class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                >
                                    <EyeIcon v-if="!showPaystackSecretKey" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <!-- Test Result -->
                        <div v-if="paystackTestResult" :class="paystackTestResult.success ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'" class="p-3 rounded-lg border text-sm">
                            {{ paystackTestResult.message }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="testPaystackConnection"
                                :disabled="testingPaystack || !paymentForm.paystack_secret_key"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                            >
                                <ArrowPathIcon v-if="testingPaystack" class="h-4 w-4 animate-spin" />
                                {{ testingPaystack ? 'Testing...' : 'Test Connection' }}
                            </button>
                            <button
                                type="submit"
                                :disabled="paymentForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ paymentForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
