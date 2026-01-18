<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowPathIcon,
    SparklesIcon,
    Cog6ToothIcon,
    MoonIcon,
    ClockIcon,
    ArrowPathRoundedSquareIcon,
    ShieldCheckIcon,
    ArchiveBoxIcon,
    SignalIcon,
    DocumentTextIcon,
    ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    globalPreferences: { type: Object, default: () => ({}) },
    setupComplete: { type: Boolean, default: false },
    whatsappTemplates: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-wizard']);

// Internal settings tabs
const settingsTab = ref('channels');

const settingsTabs = [
    { id: 'channels', name: 'Channels', icon: SignalIcon },
    { id: 'templates', name: 'Templates', icon: DocumentTextIcon },
    { id: 'delivery', name: 'Delivery & Retry', icon: ClockIcon },
    { id: 'archive', name: 'Archive', icon: ArchiveBoxIcon },
];

const showPassword = ref({});
const testingProvider = ref(null);
const testResult = ref(null);

// Provider configurations
const providers = [
    {
        id: 'email',
        name: 'Email',
        icon: EnvelopeIcon,
        description: 'Send notifications via email using SMTP or a mail service',
        color: 'indigo',
        fields: [
            { key: 'mail_mailer', label: 'Mail Driver', type: 'select', options: ['smtp', 'mailgun', 'postmark', 'ses'], default: 'smtp' },
            { key: 'mail_host', label: 'SMTP Host', type: 'text', placeholder: 'smtp.example.com' },
            { key: 'mail_port', label: 'SMTP Port', type: 'text', placeholder: '587' },
            { key: 'mail_username', label: 'Username', type: 'text' },
            { key: 'mail_password', label: 'Password', type: 'password' },
            { key: 'mail_encryption', label: 'Encryption', type: 'select', options: ['tls', 'ssl', 'none'], default: 'tls' },
            { key: 'mail_from_address', label: 'From Address', type: 'email', placeholder: 'noreply@example.com' },
            { key: 'mail_from_name', label: 'From Name', type: 'text', placeholder: 'Property Manager' },
        ]
    },
    {
        id: 'sms',
        name: 'SMS',
        icon: DevicePhoneMobileIcon,
        description: 'Send text messages via Africa\'s Talking or Twilio',
        color: 'green',
        fields: [
            { key: 'sms_provider', label: 'SMS Provider', type: 'select', options: ['africastalking', 'twilio'], default: 'africastalking' },
            { key: 'africastalking_username', label: 'AT Username', type: 'text', showWhen: 'africastalking' },
            { key: 'africastalking_api_key', label: 'AT API Key', type: 'password', showWhen: 'africastalking' },
            { key: 'africastalking_sender_id', label: 'AT Sender ID', type: 'text', showWhen: 'africastalking', placeholder: 'Optional' },
            { key: 'twilio_sid', label: 'Twilio Account SID', type: 'text', showWhen: 'twilio' },
            { key: 'twilio_token', label: 'Twilio Auth Token', type: 'password', showWhen: 'twilio' },
            { key: 'twilio_from', label: 'Twilio From Number', type: 'text', showWhen: 'twilio', placeholder: '+1234567890' },
        ]
    },
    {
        id: 'whatsapp',
        name: 'WhatsApp',
        icon: ChatBubbleLeftRightIcon,
        description: 'Send WhatsApp messages via Twilio WhatsApp API',
        color: 'emerald',
        fields: [
            { key: 'whatsapp_provider', label: 'WhatsApp Provider', type: 'select', options: ['twilio'], default: 'twilio' },
            { key: 'whatsapp_twilio_sid', label: 'Twilio Account SID', type: 'text' },
            { key: 'whatsapp_twilio_token', label: 'Twilio Auth Token', type: 'password' },
            { key: 'whatsapp_from', label: 'WhatsApp From Number', type: 'text', placeholder: '+14155238886' },
        ]
    },
    {
        id: 'push',
        name: 'Push Notifications',
        icon: BellIcon,
        description: 'Send browser push notifications using Web Push',
        color: 'purple',
        fields: [
            { key: 'vapid_public_key', label: 'VAPID Public Key', type: 'text', readonly: true },
            { key: 'vapid_private_key', label: 'VAPID Private Key', type: 'password', readonly: true },
            { key: 'vapid_subject', label: 'VAPID Subject', type: 'email', placeholder: 'mailto:admin@example.com' },
        ]
    },
];

const forms = ref({});

// Initialize forms for each provider
providers.forEach(provider => {
    const formData = {};
    provider.fields.forEach(field => {
        formData[field.key] = props.settings[field.key] || field.default || '';
    });
    formData.enabled = props.settings[`${provider.id}_enabled`] ?? true;
    forms.value[provider.id] = useForm(formData);
});

// Global preferences form (delivery, retry, rate limiting, and archive settings)
// Note: Default notification preferences for new tenants are configured in Settings > Notifications
const globalForm = useForm({
    // Quiet Hours
    quiet_hours_enabled: props.globalPreferences?.quiet_hours_enabled ?? false,
    quiet_hours_start: props.globalPreferences?.quiet_hours_start ?? '22:00',
    quiet_hours_end: props.globalPreferences?.quiet_hours_end ?? '08:00',
    quiet_hours_queue_notifications: props.globalPreferences?.quiet_hours_queue_notifications ?? true,

    // Retry Configuration
    notification_max_retries: props.globalPreferences?.notification_max_retries ?? 3,
    notification_retry_delay: props.globalPreferences?.notification_retry_delay ?? 5,

    // Rate Limiting
    notification_daily_limit_per_tenant: props.globalPreferences?.notification_daily_limit_per_tenant ?? 20,
    notification_hourly_limit_per_tenant: props.globalPreferences?.notification_hourly_limit_per_tenant ?? 5,

    // Archive Settings
    notification_archive_days: props.globalPreferences?.notification_archive_days ?? 90,
    notification_track_read_status: props.globalPreferences?.notification_track_read_status ?? true,
});

const saveGlobalPreferences = () => {
    globalForm.post(route('notifications.settings.global'), {
        preserveScroll: true,
    });
};

// WhatsApp Templates form
const templatesForm = useForm({
    templates: props.whatsappTemplates.map(t => ({
        type: t.type,
        sid: t.sid || '',
    })),
});

const saveTemplates = () => {
    templatesForm.post(route('notifications.settings.whatsapp-templates'), {
        preserveScroll: true,
    });
};

const getTemplateByType = (type) => {
    return props.whatsappTemplates.find(t => t.type === type);
};

const getProviderStatus = (providerId) => {
    const providerSettings = props.settings;
    switch (providerId) {
        case 'email':
            return providerSettings.mail_host && providerSettings.mail_from_address;
        case 'sms':
            if (providerSettings.sms_provider === 'africastalking') {
                return providerSettings.africastalking_username && providerSettings.africastalking_api_key;
            }
            return providerSettings.twilio_sid && providerSettings.twilio_token;
        case 'whatsapp':
            return providerSettings.whatsapp_twilio_sid && providerSettings.whatsapp_from;
        case 'push':
            return providerSettings.vapid_public_key && providerSettings.vapid_private_key;
        default:
            return false;
    }
};

const saveProviderSettings = (providerId) => {
    const form = forms.value[providerId];
    form.post(route('notifications.settings.provider', providerId), {
        preserveScroll: true,
    });
};

const testProvider = async (providerId) => {
    testingProvider.value = providerId;
    testResult.value = null;

    try {
        const response = await fetch(route('notifications.settings.test', providerId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        const data = await response.json();
        testResult.value = { provider: providerId, success: data.success, message: data.message };
    } catch (error) {
        testResult.value = { provider: providerId, success: false, message: 'Test failed: ' + error.message };
    } finally {
        testingProvider.value = null;
    }
};

const generateVapidKeys = async () => {
    if (!confirm('Generate new VAPID keys? Existing push subscriptions will need to re-subscribe.')) {
        return;
    }

    try {
        const response = await fetch(route('notifications.push.generate-keys'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        const data = await response.json();
        if (data.success) {
            forms.value.push.vapid_public_key = data.public_key;
            forms.value.push.vapid_private_key = data.private_key;
        }
    } catch (error) {
        alert('Failed to generate VAPID keys: ' + error.message);
    }
};

const togglePasswordVisibility = (fieldKey) => {
    showPassword.value[fieldKey] = !showPassword.value[fieldKey];
};

const shouldShowField = (field, providerId) => {
    if (!field.showWhen) return true;
    const form = forms.value[providerId];
    const providerField = `${providerId}_provider`;
    return form[providerField] === field.showWhen || props.settings[providerField] === field.showWhen;
};

const getColorClasses = (color) => {
    const colors = {
        indigo: { bg: 'bg-indigo-100', text: 'text-indigo-600', border: 'border-indigo-200', hover: 'hover:bg-indigo-50' },
        green: { bg: 'bg-green-100', text: 'text-green-600', border: 'border-green-200', hover: 'hover:bg-green-50' },
        emerald: { bg: 'bg-emerald-100', text: 'text-emerald-600', border: 'border-emerald-200', hover: 'hover:bg-emerald-50' },
        purple: { bg: 'bg-purple-100', text: 'text-purple-600', border: 'border-purple-200', hover: 'hover:bg-purple-50' },
    };
    return colors[color] || colors.indigo;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Internal Tab Navigation -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-1.5">
            <nav class="flex gap-1">
                <button
                    v-for="tab in settingsTabs"
                    :key="tab.id"
                    @click="settingsTab = tab.id"
                    :class="[
                        'flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-medium transition-all',
                        settingsTab === tab.id
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                    ]"
                >
                    <component :is="tab.icon" class="w-4 h-4" />
                    <span>{{ tab.name }}</span>
                </button>
            </nav>
        </div>

        <!-- ==================== CHANNELS TAB ==================== -->
        <div v-if="settingsTab === 'channels'" class="space-y-6">
            <!-- Setup Wizard Card -->
            <div v-if="!setupComplete" class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-2xl p-6">
            <div class="flex items-start gap-4">
                <div class="p-3 bg-indigo-100 rounded-xl">
                    <SparklesIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-indigo-900">Quick Setup Wizard</h3>
                    <p class="text-sm text-indigo-700 mt-1">
                        Not sure where to start? Run the setup wizard to configure your notification channels step by step.
                    </p>
                    <button
                        @click="$emit('open-wizard')"
                        class="mt-3 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium"
                    >
                        Run Setup Wizard
                    </button>
                </div>
            </div>
        </div>

        <!-- Provider Cards -->
        <div class="space-y-4">
            <div
                v-for="provider in providers"
                :key="provider.id"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
            >
                <!-- Provider Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div :class="['p-3 rounded-xl', getColorClasses(provider.color).bg]">
                            <component :is="provider.icon" :class="['w-6 h-6', getColorClasses(provider.color).text]" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ provider.name }}</h3>
                            <p class="text-sm text-gray-500">{{ provider.description }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            v-if="getProviderStatus(provider.id)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium bg-green-100 text-green-700 rounded-full"
                        >
                            <CheckCircleIcon class="w-4 h-4" />
                            Configured
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-700 rounded-full"
                        >
                            <ExclamationCircleIcon class="w-4 h-4" />
                            Not Configured
                        </span>
                    </div>
                </div>

                <!-- Provider Settings Form -->
                <form @submit.prevent="saveProviderSettings(provider.id)" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <template v-for="field in provider.fields" :key="field.key">
                            <div v-if="shouldShowField(field, provider.id)">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ field.label }}
                                </label>

                                <!-- Select -->
                                <select
                                    v-if="field.type === 'select'"
                                    v-model="forms[provider.id][field.key]"
                                    :disabled="field.readonly"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100"
                                >
                                    <option v-for="option in field.options" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>

                                <!-- Password with toggle -->
                                <div v-else-if="field.type === 'password'" class="relative">
                                    <input
                                        :type="showPassword[field.key] ? 'text' : 'password'"
                                        v-model="forms[provider.id][field.key]"
                                        :placeholder="field.placeholder"
                                        :readonly="field.readonly"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pr-10 disabled:bg-gray-100"
                                        :class="{ 'bg-gray-50': field.readonly }"
                                    />
                                    <button
                                        type="button"
                                        @click="togglePasswordVisibility(field.key)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <EyeSlashIcon v-if="showPassword[field.key]" class="w-5 h-5" />
                                        <EyeIcon v-else class="w-5 h-5" />
                                    </button>
                                </div>

                                <!-- Text/Email -->
                                <input
                                    v-else
                                    :type="field.type"
                                    v-model="forms[provider.id][field.key]"
                                    :placeholder="field.placeholder"
                                    :readonly="field.readonly"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    :class="{ 'bg-gray-50': field.readonly }"
                                />

                                <p v-if="forms[provider.id].errors[field.key]" class="text-sm text-red-600 mt-1">
                                    {{ forms[provider.id].errors[field.key] }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- VAPID Key Generation -->
                    <div v-if="provider.id === 'push'" class="mt-4 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="generateVapidKeys"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-purple-700 bg-purple-100 rounded-lg hover:bg-purple-200 transition-colors"
                        >
                            <Cog6ToothIcon class="w-4 h-4" />
                            Generate New VAPID Keys
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            VAPID keys are required for Web Push notifications. Generate keys if you haven't already.
                        </p>
                    </div>

                    <!-- Test Result -->
                    <div
                        v-if="testResult && testResult.provider === provider.id"
                        :class="[
                            'mt-4 p-3 rounded-lg text-sm',
                            testResult.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
                        ]"
                    >
                        {{ testResult.message }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="forms[provider.id].enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-sm text-gray-700">Enable {{ provider.name }}</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="testProvider(provider.id)"
                                :disabled="testingProvider === provider.id || !getProviderStatus(provider.id)"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <ArrowPathIcon :class="['w-4 h-4', testingProvider === provider.id ? 'animate-spin' : '']" />
                                Test Connection
                            </button>
                            <button
                                type="submit"
                                :disabled="forms[provider.id].processing"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                <CheckCircleIcon class="w-4 h-4" />
                                Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        </div>
        <!-- End Channels Tab -->

        <!-- ==================== TEMPLATES TAB ==================== -->
        <div v-if="settingsTab === 'templates'" class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-emerald-100">
                            <DocumentTextIcon class="w-6 h-6 text-emerald-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">WhatsApp Message Templates</h3>
                            <p class="text-sm text-gray-500">Configure Meta-approved template SIDs for WhatsApp Business API</p>
                        </div>
                    </div>
                    <button
                        @click="saveTemplates"
                        :disabled="templatesForm.processing"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ templatesForm.processing ? 'Saving...' : 'Save Templates' }}
                    </button>
                </div>

                <div class="p-6">
                    <!-- Info Banner -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <ChatBubbleLeftRightIcon class="w-5 h-5 text-blue-600 mt-0.5" />
                            <div>
                                <p class="text-sm text-blue-800">
                                    WhatsApp Business API requires pre-approved message templates for business-initiated conversations.
                                    Submit templates via the <a href="https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-templates" target="_blank" class="font-medium underline inline-flex items-center gap-1">Twilio Console <ArrowTopRightOnSquareIcon class="w-3 h-3" /></a>,
                                    then enter the approved Content SID below.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Template Cards -->
                    <div class="space-y-4">
                        <div
                            v-for="(template, index) in templatesForm.templates"
                            :key="template.type"
                            class="bg-gray-50 rounded-xl p-5"
                        >
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 rounded-lg">
                                        <DocumentTextIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ getTemplateByType(template.type)?.label }}</h4>
                                        <p class="text-xs text-gray-500">{{ getTemplateByType(template.type)?.name }}</p>
                                    </div>
                                </div>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                                        template.sid ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'
                                    ]"
                                >
                                    <span :class="['w-1.5 h-1.5 rounded-full', template.sid ? 'bg-green-500' : 'bg-gray-400']"></span>
                                    {{ template.sid ? 'Configured' : 'Not Configured' }}
                                </span>
                            </div>

                            <!-- Template Preview -->
                            <div class="bg-white border border-gray-200 rounded-lg p-3 mb-4">
                                <p class="text-sm text-gray-600 font-mono">{{ getTemplateByType(template.type)?.content }}</p>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span
                                        v-for="variable in getTemplateByType(template.type)?.variables"
                                        :key="variable"
                                        class="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded"
                                    >
                                        {{ variable }}
                                    </span>
                                </div>
                            </div>

                            <!-- SID Input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Content SID</label>
                                <input
                                    type="text"
                                    v-model="templatesForm.templates[index].sid"
                                    placeholder="HJXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Enter the Content SID from Twilio after Meta approval (starts with HJ)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <div v-if="templatesForm.recentlySuccessful" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon class="w-5 h-5 text-green-600" />
                            <p class="text-sm font-medium text-green-800">Templates saved successfully!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Templates Tab -->

        <!-- ==================== DELIVERY & RETRY TAB ==================== -->
        <div v-if="settingsTab === 'delivery'" class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-indigo-100">
                            <ClockIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Delivery & Retry Settings</h3>
                            <p class="text-sm text-gray-500">Control how and when notifications are sent</p>
                        </div>
                    </div>
                    <button
                        @click="saveGlobalPreferences"
                        :disabled="globalForm.processing"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ globalForm.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>

                <form @submit.prevent="saveGlobalPreferences" class="p-6 space-y-6">
                    <!-- Quiet Hours Section -->
                <div class="bg-gray-50 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <MoonIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Quiet Hours (Do Not Disturb)</h4>
                            <p class="text-sm text-gray-500">Pause notifications during specific hours</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Enable Quiet Hours</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="globalForm.quiet_hours_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div v-if="globalForm.quiet_hours_enabled" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                <input
                                    type="time"
                                    v-model="globalForm.quiet_hours_start"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                                <input
                                    type="time"
                                    v-model="globalForm.quiet_hours_end"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <div v-if="globalForm.quiet_hours_enabled" class="flex items-center justify-between">
                            <div>
                                <span class="text-sm text-gray-700">Queue notifications during quiet hours</span>
                                <p class="text-xs text-gray-500">Instead of skipping, send them when quiet hours end</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="globalForm.quiet_hours_queue_notifications" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Critical notifications bypass info -->
                        <div v-if="globalForm.quiet_hours_enabled" class="bg-blue-50 border border-blue-100 rounded-lg p-3 mt-2">
                            <p class="text-xs text-blue-700">
                                <strong>Note:</strong> Critical and urgent notifications (eviction notices, arrears alerts) will still be delivered immediately regardless of quiet hours.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Retry Configuration Section -->
                <div class="bg-gray-50 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <ArrowPathRoundedSquareIcon class="w-5 h-5 text-orange-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Retry Configuration</h4>
                            <p class="text-sm text-gray-500">Configure how failed notifications are retried</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Retries</label>
                            <input
                                type="number"
                                v-model.number="globalForm.notification_max_retries"
                                min="0"
                                max="10"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p class="text-xs text-gray-500 mt-1">0-10 attempts</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Retry Delay (minutes)</label>
                            <input
                                type="number"
                                v-model.number="globalForm.notification_retry_delay"
                                min="1"
                                max="60"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p class="text-xs text-gray-500 mt-1">1-60 minutes</p>
                        </div>
                    </div>
                </div>

                <!-- Rate Limiting Section -->
                <div class="bg-gray-50 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <ShieldCheckIcon class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Rate Limiting</h4>
                            <p class="text-sm text-gray-500">Prevent notification spam to tenants</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Daily Limit Per Tenant</label>
                            <input
                                type="number"
                                v-model.number="globalForm.notification_daily_limit_per_tenant"
                                min="1"
                                max="100"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p class="text-xs text-gray-500 mt-1">Max notifications per tenant per day</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Limit Per Tenant</label>
                            <input
                                type="number"
                                v-model.number="globalForm.notification_hourly_limit_per_tenant"
                                min="1"
                                max="20"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p class="text-xs text-gray-500 mt-1">Max notifications per tenant per hour</p>
                        </div>
                    </div>
                </div>
                </form>
            </div>
        </div>
        <!-- End Delivery & Retry Tab -->

        <!-- ==================== ARCHIVE TAB ==================== -->
        <div v-if="settingsTab === 'archive'" class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-purple-100">
                            <ArchiveBoxIcon class="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Archive Settings</h3>
                            <p class="text-sm text-gray-500">Configure notification history and tracking</p>
                        </div>
                    </div>
                    <button
                        @click="saveGlobalPreferences"
                        :disabled="globalForm.processing"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ globalForm.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>

                <form @submit.prevent="saveGlobalPreferences" class="p-6 space-y-6">
                    <!-- Info Banner -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex gap-3">
                            <BellIcon class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-800">
                                <p class="font-medium">Default notification preferences for tenants</p>
                                <p class="mt-1">
                                    Configure default notification channels and types for new tenants in
                                    <a :href="route('settings.index')" class="underline font-medium">Settings &gt; Notifications</a>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Archive Settings Section -->
                    <div class="bg-gray-50 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <ArchiveBoxIcon class="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900">History Retention</h4>
                                <p class="text-sm text-gray-500">Configure how long notification history is kept</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Keep notification history for</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        v-model.number="globalForm.notification_archive_days"
                                        min="7"
                                        max="365"
                                        class="w-20 border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <span class="text-sm text-gray-500">days</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm text-gray-700">Track read status</span>
                                    <p class="text-xs text-gray-500">Track when tenants view notifications</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="globalForm.notification_track_read_status" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Errors -->
                    <div v-if="Object.keys(globalForm.errors).length > 0" class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <p class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</p>
                        <ul class="list-disc list-inside text-sm text-red-700">
                            <li v-for="(error, key) in globalForm.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <!-- Success Message -->
                    <div v-if="globalForm.recentlySuccessful" class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon class="w-5 h-5 text-green-600" />
                            <p class="text-sm font-medium text-green-800">Settings saved successfully!</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- End Archive Tab -->

    </div>
</template>
