<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    CreditCardIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    paymentSettings: Object,
    emailSettings: Object,
    smsSettings: Object,
});

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

// Email form
const emailForm = useForm({
    smtp_host: props.emailSettings?.smtp_host || '',
    smtp_port: props.emailSettings?.smtp_port || '587',
    smtp_username: props.emailSettings?.smtp_username || '',
    smtp_password: '',
    smtp_encryption: props.emailSettings?.smtp_encryption || 'tls',
    mail_from_address: props.emailSettings?.mail_from_address || '',
    mail_from_name: props.emailSettings?.mail_from_name || 'PropManager',
});

const showSmtpPassword = ref(false);
const testingEmail = ref(false);
const emailTestResult = ref(null);
const testEmailAddress = ref('');

const saveEmailSettings = () => {
    emailForm.post(route('admin.settings.email'), {
        preserveScroll: true,
        onSuccess: () => {
            emailForm.smtp_password = '';
        },
    });
};

const testEmailConnection = async () => {
    if (!testEmailAddress.value) {
        emailTestResult.value = { success: false, message: 'Please enter a test email address' };
        return;
    }

    testingEmail.value = true;
    emailTestResult.value = null;

    try {
        const response = await fetch(route('admin.settings.email.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                ...emailForm.data(),
                test_email: testEmailAddress.value,
            }),
        });
        emailTestResult.value = await response.json();
    } catch (error) {
        emailTestResult.value = { success: false, message: 'Test failed: ' + error.message };
    } finally {
        testingEmail.value = false;
    }
};

// SMS form
const smsForm = useForm({
    africastalking_username: props.smsSettings?.africastalking_username || '',
    africastalking_api_key: '',
    africastalking_sender_id: props.smsSettings?.africastalking_sender_id || '',
    africastalking_environment: props.smsSettings?.africastalking_environment || 'sandbox',
});

const showSmsApiKey = ref(false);
const testingSms = ref(false);
const smsTestResult = ref(null);
const testPhoneNumber = ref('');

const saveSmsSettings = () => {
    smsForm.post(route('admin.settings.sms'), {
        preserveScroll: true,
        onSuccess: () => {
            smsForm.africastalking_api_key = '';
        },
    });
};

const testSmsConnection = async () => {
    if (!testPhoneNumber.value) {
        smsTestResult.value = { success: false, message: 'Please enter a test phone number' };
        return;
    }

    if (!smsForm.africastalking_api_key) {
        smsTestResult.value = { success: false, message: 'Please enter your API key first' };
        return;
    }

    testingSms.value = true;
    smsTestResult.value = null;

    try {
        const response = await fetch(route('admin.settings.sms.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                ...smsForm.data(),
                test_phone: testPhoneNumber.value,
            }),
        });
        smsTestResult.value = await response.json();
    } catch (error) {
        smsTestResult.value = { success: false, message: 'Test failed: ' + error.message };
    } finally {
        testingSms.value = false;
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
                        <p class="text-gray-600">Configure payment gateways, email, and SMS services</p>
                    </div>
                    <Link :href="route('dashboard')" class="text-indigo-600 hover:text-indigo-700">
                        Back to Dashboard
                    </Link>
                </div>

                <!-- Payment Gateway Settings -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-4">
                        <div class="h-12 w-12 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
                            <CreditCardIcon class="h-6 w-6 text-green-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900">Payment Gateway (Paystack)</h2>
                            <p class="text-sm text-gray-500">Configure Paystack for subscription payments</p>
                        </div>
                        <div class="flex-shrink-0">
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
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showPaystackPublicKey = !showPaystackPublicKey"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
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
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showPaystackSecretKey = !showPaystackSecretKey"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
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

                <!-- Email Settings -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-4">
                        <div class="h-12 w-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <EnvelopeIcon class="h-6 w-6 text-blue-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900">Email Configuration (SMTP)</h2>
                            <p class="text-sm text-gray-500">Configure SMTP for sending emails</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span v-if="emailSettings.smtp_host" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                <CheckCircleIcon class="h-4 w-4" />
                                Configured
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">
                                <ExclamationTriangleIcon class="h-4 w-4" />
                                Not Configured
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="saveEmailSettings" class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- SMTP Host -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                                <input
                                    type="text"
                                    v-model="emailForm.smtp_host"
                                    placeholder="smtp.example.com"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <!-- SMTP Port -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                                <input
                                    type="number"
                                    v-model="emailForm.smtp_port"
                                    placeholder="587"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <!-- SMTP Username -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input
                                    type="text"
                                    v-model="emailForm.smtp_username"
                                    placeholder="user@example.com"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <!-- SMTP Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <div class="relative">
                                    <input
                                        :type="showSmtpPassword ? 'text' : 'password'"
                                        v-model="emailForm.smtp_password"
                                        :placeholder="emailSettings.has_smtp_password ? '••••••••••••' : 'Enter password'"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10"
                                    />
                                    <button
                                        type="button"
                                        @click="showSmtpPassword = !showSmtpPassword"
                                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        <EyeIcon v-if="!showSmtpPassword" class="h-5 w-5" />
                                        <EyeSlashIcon v-else class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Encryption -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                                <select
                                    v-model="emailForm.smtp_encryption"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                            </div>

                            <!-- From Address -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">From Address</label>
                                <input
                                    type="email"
                                    v-model="emailForm.mail_from_address"
                                    placeholder="noreply@propmanager.co.ke"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <!-- From Name -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                                <input
                                    type="text"
                                    v-model="emailForm.mail_from_name"
                                    placeholder="PropManager"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Test Email -->
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Send Test Email To</label>
                            <div class="flex gap-2">
                                <input
                                    type="email"
                                    v-model="testEmailAddress"
                                    placeholder="test@example.com"
                                    class="flex-1 border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <button
                                    type="button"
                                    @click="testEmailConnection"
                                    :disabled="testingEmail"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 inline-flex items-center gap-2"
                                >
                                    <ArrowPathIcon v-if="testingEmail" class="h-4 w-4 animate-spin" />
                                    {{ testingEmail ? 'Sending...' : 'Send Test' }}
                                </button>
                            </div>
                        </div>

                        <!-- Test Result -->
                        <div v-if="emailTestResult" :class="emailTestResult.success ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'" class="p-3 rounded-lg border text-sm">
                            {{ emailTestResult.message }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4">
                            <button
                                type="submit"
                                :disabled="emailForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ emailForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SMS Settings -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-4">
                        <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
                            <DevicePhoneMobileIcon class="h-6 w-6 text-purple-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900">SMS Gateway (Africa's Talking)</h2>
                            <p class="text-sm text-gray-500">Configure SMS notifications</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span v-if="smsSettings.has_africastalking_api_key" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                <CheckCircleIcon class="h-4 w-4" />
                                Configured
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">
                                <ExclamationTriangleIcon class="h-4 w-4" />
                                Not Configured
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="saveSmsSettings" class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Username -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input
                                    type="text"
                                    v-model="smsForm.africastalking_username"
                                    placeholder="sandbox"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>

                            <!-- API Key -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                <div class="relative">
                                    <input
                                        :type="showSmsApiKey ? 'text' : 'password'"
                                        v-model="smsForm.africastalking_api_key"
                                        :placeholder="smsSettings.has_africastalking_api_key ? '••••••••••••' : 'Enter API key'"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10"
                                    />
                                    <button
                                        type="button"
                                        @click="showSmsApiKey = !showSmsApiKey"
                                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        <EyeIcon v-if="!showSmsApiKey" class="h-5 w-5" />
                                        <EyeSlashIcon v-else class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Sender ID -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sender ID</label>
                                <input
                                    type="text"
                                    v-model="smsForm.africastalking_sender_id"
                                    placeholder="PROPMANAGER"
                                    maxlength="11"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Max 11 characters. Leave empty to use default.</p>
                            </div>

                            <!-- Environment -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                                <div class="flex gap-4 mt-2">
                                    <label class="inline-flex items-center">
                                        <input
                                            type="radio"
                                            v-model="smsForm.africastalking_environment"
                                            value="sandbox"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">Sandbox</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input
                                            type="radio"
                                            v-model="smsForm.africastalking_environment"
                                            value="production"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">Production</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Test SMS -->
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Send Test SMS To</label>
                            <div class="flex gap-2">
                                <input
                                    type="tel"
                                    v-model="testPhoneNumber"
                                    placeholder="+254712345678"
                                    class="flex-1 border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <button
                                    type="button"
                                    @click="testSmsConnection"
                                    :disabled="testingSms"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 inline-flex items-center gap-2"
                                >
                                    <ArrowPathIcon v-if="testingSms" class="h-4 w-4 animate-spin" />
                                    {{ testingSms ? 'Sending...' : 'Send Test' }}
                                </button>
                            </div>
                        </div>

                        <!-- Test Result -->
                        <div v-if="smsTestResult" :class="smsTestResult.success ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'" class="p-3 rounded-lg border text-sm">
                            {{ smsTestResult.message }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4">
                            <button
                                type="submit"
                                :disabled="smsForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ smsForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
