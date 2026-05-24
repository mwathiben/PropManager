<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import {
    XMarkIcon,
    CheckIcon,
    ArrowRightIcon,
    ArrowLeftIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    SparklesIcon,
    RocketLaunchIcon,
    EyeIcon,
    EyeSlashIcon
} from '@heroicons/vue/24/outline';
import type { ProviderSettings } from '@/types';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = withDefaults(defineProps<{
    show?: boolean;
    settings?: ProviderSettings;
}>(), {
    show: false,
    settings: () => ({} as ProviderSettings),
});

const emit = defineEmits(['close', 'complete']);

const currentStep = ref(0);
const showPassword = ref({});
const selectedChannels = ref(['email']);

const steps = computed(() => [
    { id: 'welcome', title: t('notifications_setup_wizard.steps.welcome'), icon: SparklesIcon },
    { id: 'channels', title: t('notifications_setup_wizard.steps.channels'), icon: RocketLaunchIcon },
    { id: 'email', title: t('notifications_setup_wizard.steps.email'), icon: EnvelopeIcon, channel: 'email' },
    { id: 'sms', title: t('notifications_setup_wizard.steps.sms'), icon: DevicePhoneMobileIcon, channel: 'sms' },
    { id: 'whatsapp', title: t('notifications_setup_wizard.steps.whatsapp'), icon: ChatBubbleLeftRightIcon, channel: 'whatsapp' },
    { id: 'push', title: t('notifications_setup_wizard.steps.push'), icon: BellIcon, channel: 'push' },
    { id: 'complete', title: t('notifications_setup_wizard.steps.complete'), icon: CheckIcon },
]);

const channelOptions = computed(() => [
    { id: 'email', name: t('notifications_setup_wizard.channel_options.email_name'), icon: EnvelopeIcon, desc: t('notifications_setup_wizard.channel_options.email_desc') },
    { id: 'sms', name: t('notifications_setup_wizard.channel_options.sms_name'), icon: DevicePhoneMobileIcon, desc: t('notifications_setup_wizard.channel_options.sms_desc') },
    { id: 'whatsapp', name: t('notifications_setup_wizard.channel_options.whatsapp_name'), icon: ChatBubbleLeftRightIcon, desc: t('notifications_setup_wizard.channel_options.whatsapp_desc') },
    { id: 'push', name: t('notifications_setup_wizard.channel_options.push_name'), icon: BellIcon, desc: t('notifications_setup_wizard.channel_options.push_desc') },
]);

const activeSteps = computed(() => {
    return steps.value.filter(step => {
        if (!step.channel) return true;
        return selectedChannels.value.includes(step.channel);
    });
});

const currentStepData = computed(() => activeSteps.value[currentStep.value]);
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === activeSteps.value.length - 1);
const progress = computed(() => ((currentStep.value + 1) / activeSteps.value.length) * 100);

// Forms for each channel
const emailForm = useForm({
    mail_mailer: props.settings.mail_mailer || 'smtp',
    mail_host: props.settings.mail_host || '',
    mail_port: props.settings.mail_port || '587',
    mail_username: props.settings.mail_username || '',
    mail_password: props.settings.mail_password || '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address || '',
    mail_from_name: props.settings.mail_from_name || '',
});

const smsForm = useForm({
    sms_provider: props.settings.sms_provider || 'africastalking',
    africastalking_username: props.settings.africastalking_username || '',
    africastalking_api_key: props.settings.africastalking_api_key || '',
    africastalking_sender_id: props.settings.africastalking_sender_id || '',
    twilio_sid: props.settings.twilio_sid || '',
    twilio_token: props.settings.twilio_token || '',
    twilio_from: props.settings.twilio_from || '',
});

const whatsappForm = useForm({
    whatsapp_provider: 'twilio',
    whatsapp_twilio_sid: props.settings.whatsapp_twilio_sid || '',
    whatsapp_twilio_token: props.settings.whatsapp_twilio_token || '',
    whatsapp_from: props.settings.whatsapp_from || '',
});

const pushForm = useForm({
    vapid_subject: props.settings.vapid_subject || '',
});

const toggleChannel = (channel) => {
    const index = selectedChannels.value.indexOf(channel);
    if (index > -1) {
        selectedChannels.value.splice(index, 1);
    } else {
        selectedChannels.value.push(channel);
    }
};

const nextStep = () => {
    if (!isLastStep.value) {
        // Save current step's form if applicable
        saveCurrentStep();
        currentStep.value++;
    }
};

const prevStep = () => {
    if (!isFirstStep.value) {
        currentStep.value--;
    }
};

const saveCurrentStep = () => {
    const stepId = currentStepData.value?.id;

    switch (stepId) {
        case 'email':
            emailForm.post(route('notifications.settings.provider', 'email'), {
                preserveScroll: true,
                onError: () => {},
            });
            break;
        case 'sms':
            smsForm.post(route('notifications.settings.provider', 'sms'), {
                preserveScroll: true,
                onError: () => {},
            });
            break;
        case 'whatsapp':
            whatsappForm.post(route('notifications.settings.provider', 'whatsapp'), {
                preserveScroll: true,
                onError: () => {},
            });
            break;
        case 'push':
            pushForm.post(route('notifications.settings.provider', 'push'), {
                preserveScroll: true,
                onError: () => {},
            });
            break;
    }
};

const skipStep = () => {
    if (!isLastStep.value) {
        currentStep.value++;
    }
};

const completeWizard = () => {
    // Mark setup as complete
    router.post(route('notifications.settings.complete-setup'), {}, {
        onSuccess: () => {
            emit('complete');
            emit('close');
        },
    });
};

const generateVapidKeys = async () => {
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
            alert(t('notifications_setup_wizard.alert.vapid_generated'));
        }
    } catch (error) {
        alert(t('notifications_setup_wizard.alert.vapid_failed', { error: error.message }));
    }
};

const togglePasswordVisibility = (field) => {
    showPassword.value[field] = !showPassword.value[field];
};

// Reset wizard when opened
watch(() => props.show, (newVal) => {
    if (newVal) {
        currentStep.value = 0;
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="$emit('close')"></div>

                <div class="relative z-50 bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden">
                    <!-- Progress Bar -->
                    <div class="h-1 bg-gray-100">
                        <div
                            class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500"
                            :style="{ width: `${progress}%` }"
                        ></div>
                    </div>

                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl text-white">
                                    <component :is="currentStepData?.icon || SparklesIcon" class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">{{ t('notifications_setup_wizard.header.step_progress', { current: currentStep + 1, total: activeSteps.length }) }}</p>
                                    <h2 class="text-xl font-bold text-gray-900">{{ currentStepData?.title }}</h2>
                                </div>
                            </div>
                            <button
                                @click="$emit('close')"
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
                            >
                                <XMarkIcon class="w-6 h-6" />
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-8 py-6 min-h-[400px]">
                        <!-- Welcome Step -->
                        <div v-if="currentStepData?.id === 'welcome'" class="text-center py-8">
                            <div class="p-6 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-3xl w-24 h-24 mx-auto mb-6 flex items-center justify-center">
                                <SparklesIcon class="w-12 h-12 text-indigo-600" />
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ t('notifications_setup_wizard.welcome.heading') }}</h3>
                            <p class="text-gray-600 max-w-md mx-auto mb-6">
                                {{ t('notifications_setup_wizard.welcome.intro') }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ t('notifications_setup_wizard.welcome.guide') }}
                            </p>
                        </div>

                        <!-- Channel Selection Step -->
                        <div v-else-if="currentStepData?.id === 'channels'" class="space-y-4">
                            <p class="text-gray-600 mb-6">
                                {{ t('notifications_setup_wizard.channels.intro') }}
                            </p>

                            <div class="grid grid-cols-2 gap-4">
                                <button
                                    v-for="channel in channelOptions"
                                    :key="channel.id"
                                    @click="toggleChannel(channel.id)"
                                    :class="[ /* i18n-ignore */
                                        'p-4 rounded-2xl border-2 text-start transition-all',
                                        selectedChannels.includes(channel.id)
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <div class="flex items-start gap-3">
                                        <div :class="[
                                            'p-2 rounded-xl',
                                            selectedChannels.includes(channel.id) ? 'bg-indigo-100' : 'bg-gray-100'
                                        ]">
                                            <component
                                                :is="channel.icon"
                                                :class="[
                                                    'w-5 h-5',
                                                    selectedChannels.includes(channel.id) ? 'text-indigo-600' : 'text-gray-500'
                                                ]"
                                            />
                                        </div>
                                        <div class="flex-1">
                                            <h4 :class="[
                                                'font-semibold',
                                                selectedChannels.includes(channel.id) ? 'text-indigo-900' : 'text-gray-900'
                                            ]">
                                                {{ channel.name }}
                                            </h4>
                                            <p class="text-sm text-gray-500">{{ channel.desc }}</p>
                                        </div>
                                        <div v-if="selectedChannels.includes(channel.id)" class="p-1 bg-indigo-500 rounded-full">
                                            <CheckIcon class="w-4 h-4 text-white" />
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Email Setup Step -->
                        <div v-else-if="currentStepData?.id === 'email'" class="space-y-4">
                            <p class="text-gray-600 mb-4">{{ t('notifications_setup_wizard.email.intro') }}</p>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.mail_driver') }}</label>
                                    <select
                                        v-model="emailForm.mail_mailer"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="smtp">{{ t('notifications_setup_wizard.email.driver_smtp') }}</option>
                                        <option value="mailgun">{{ t('notifications_setup_wizard.email.driver_mailgun') }}</option>
                                        <option value="postmark">{{ t('notifications_setup_wizard.email.driver_postmark') }}</option>
                                        <option value="ses">{{ t('notifications_setup_wizard.email.driver_ses') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.encryption') }}</label>
                                    <select
                                        v-model="emailForm.mail_encryption"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="tls">{{ t('notifications_setup_wizard.email.encryption_tls') }}</option>
                                        <option value="ssl">{{ t('notifications_setup_wizard.email.encryption_ssl') }}</option>
                                        <option value="none">{{ t('notifications_setup_wizard.email.encryption_none') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.smtp_host') }}</label>
                                    <input
                                        v-model="emailForm.mail_host"
                                        type="text"
                                        placeholder="smtp.example.com"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.smtp_port') }}</label>
                                    <input
                                        v-model="emailForm.mail_port"
                                        type="text"
                                        placeholder="587"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.username') }}</label>
                                    <input
                                        v-model="emailForm.mail_username"
                                        type="text"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.password') }}</label>
                                    <div class="relative">
                                        <input
                                            v-model="emailForm.mail_password"
                                            :type="showPassword.mail_password ? 'text' : 'password'"
                                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                        />
                                        <button
                                            type="button"
                                            @click="togglePasswordVisibility('mail_password')"
                                            class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400"
                                        >
                                            <EyeSlashIcon v-if="showPassword.mail_password" class="w-5 h-5" />
                                            <EyeIcon v-else class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.from_address') }}</label>
                                    <input
                                        v-model="emailForm.mail_from_address"
                                        type="email"
                                        placeholder="noreply@example.com"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.email.from_name') }}</label>
                                    <input
                                        v-model="emailForm.mail_from_name"
                                        type="text"
                                        :placeholder="t('notifications_setup_wizard.email.from_name_placeholder')"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- SMS Setup Step -->
                        <div v-else-if="currentStepData?.id === 'sms'" class="space-y-4">
                            <p class="text-gray-600 mb-4">{{ t('notifications_setup_wizard.sms.intro') }}</p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.provider') }}</label>
                                <select
                                    v-model="smsForm.sms_provider"
                                    class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="africastalking">{{ t('notifications_setup_wizard.sms.provider_africastalking') }}</option>
                                    <option value="twilio">{{ t('notifications_setup_wizard.sms.provider_twilio') }}</option>
                                </select>
                            </div>

                            <!-- Africa's Talking Fields -->
                            <template v-if="smsForm.sms_provider === 'africastalking'">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.username') }}</label>
                                    <input
                                        v-model="smsForm.africastalking_username"
                                        type="text"
                                        :placeholder="t('notifications_setup_wizard.sms.username_placeholder')"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.api_key') }}</label>
                                    <div class="relative">
                                        <input
                                            v-model="smsForm.africastalking_api_key"
                                            :type="showPassword.at_api_key ? 'text' : 'password'"
                                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                        />
                                        <button
                                            type="button"
                                            @click="togglePasswordVisibility('at_api_key')"
                                            class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400"
                                        >
                                            <EyeSlashIcon v-if="showPassword.at_api_key" class="w-5 h-5" />
                                            <EyeIcon v-else class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.sender_id') }}</label>
                                    <input
                                        v-model="smsForm.africastalking_sender_id"
                                        type="text"
                                        :placeholder="t('notifications_setup_wizard.sms.sender_id_placeholder')"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </template>

                            <!-- Twilio Fields -->
                            <template v-else>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.account_sid') }}</label>
                                    <input
                                        v-model="smsForm.twilio_sid"
                                        type="text"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.auth_token') }}</label>
                                    <div class="relative">
                                        <input
                                            v-model="smsForm.twilio_token"
                                            :type="showPassword.twilio_token ? 'text' : 'password'"
                                            class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                        />
                                        <button
                                            type="button"
                                            @click="togglePasswordVisibility('twilio_token')"
                                            class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400"
                                        >
                                            <EyeSlashIcon v-if="showPassword.twilio_token" class="w-5 h-5" />
                                            <EyeIcon v-else class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.sms.from_number') }}</label>
                                    <input
                                        v-model="smsForm.twilio_from"
                                        type="text"
                                        placeholder="+1234567890"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </template>
                        </div>

                        <!-- WhatsApp Setup Step -->
                        <div v-else-if="currentStepData?.id === 'whatsapp'" class="space-y-4">
                            <p class="text-gray-600 mb-4">{{ t('notifications_setup_wizard.whatsapp.intro') }}</p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.whatsapp.account_sid') }}</label>
                                <input
                                    v-model="whatsappForm.whatsapp_twilio_sid"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.whatsapp.auth_token') }}</label>
                                <div class="relative">
                                    <input
                                        v-model="whatsappForm.whatsapp_twilio_token"
                                        :type="showPassword.wa_token ? 'text' : 'password'"
                                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pe-10"
                                    />
                                    <button
                                        type="button"
                                        @click="togglePasswordVisibility('wa_token')"
                                        class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400"
                                    >
                                        <EyeSlashIcon v-if="showPassword.wa_token" class="w-5 h-5" />
                                        <EyeIcon v-else class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.whatsapp.from_number') }}</label>
                                <input
                                    v-model="whatsappForm.whatsapp_from"
                                    type="text"
                                    placeholder="+14155238886"
                                    class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="text-xs text-gray-500 mt-1">{{ t('notifications_setup_wizard.whatsapp.sandbox_hint') }}</p>
                            </div>
                        </div>

                        <!-- Push Setup Step -->
                        <div v-else-if="currentStepData?.id === 'push'" class="space-y-4">
                            <p class="text-gray-600 mb-4">{{ t('notifications_setup_wizard.push.intro') }}</p>

                            <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-4">
                                <h4 class="font-medium text-purple-900 mb-2">{{ t('notifications_setup_wizard.push.vapid_required') }}</h4>
                                <p class="text-sm text-purple-700 mb-3">
                                    {{ t('notifications_setup_wizard.push.vapid_explainer') }}
                                </p>
                                <button
                                    @click="generateVapidKeys"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium"
                                >
                                    {{ t('notifications_setup_wizard.push.generate_keys') }}
                                </button>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_setup_wizard.push.vapid_subject') }}</label>
                                <input
                                    v-model="pushForm.vapid_subject"
                                    type="email"
                                    placeholder="mailto:admin@example.com"
                                    class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="text-xs text-gray-500 mt-1">{{ t('notifications_setup_wizard.push.vapid_subject_hint') }}</p>
                            </div>
                        </div>

                        <!-- Complete Step -->
                        <div v-else-if="currentStepData?.id === 'complete'" class="text-center py-8">
                            <div class="p-6 bg-gradient-to-br from-green-100 to-emerald-100 rounded-3xl w-24 h-24 mx-auto mb-6 flex items-center justify-center">
                                <CheckIcon class="w-12 h-12 text-green-600" />
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ t('notifications_setup_wizard.complete.heading') }}</h3>
                            <p class="text-gray-600 max-w-md mx-auto mb-6">
                                {{ t('notifications_setup_wizard.complete.body') }}
                            </p>
                            <div class="flex flex-wrap justify-center gap-2 mb-6">
                                <span
                                    v-for="channel in selectedChannels"
                                    :key="channel"
                                    class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium capitalize"
                                >
                                    {{ channel }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500">
                                {{ t('notifications_setup_wizard.complete.footer') }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50 rounded-b-3xl">
                        <button
                            v-if="!isFirstStep"
                            @click="prevStep"
                            class="inline-flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            <ArrowLeftIcon class="w-4 h-4" />
                            {{ t('notifications_setup_wizard.footer.back') }}
                        </button>
                        <div v-else></div>

                        <div class="flex items-center gap-3">
                            <button
                                v-if="!isFirstStep && !isLastStep && currentStepData?.channel"
                                @click="skipStep"
                                class="px-4 py-2 text-gray-500 hover:text-gray-700 transition-colors"
                            >
                                {{ t('notifications_setup_wizard.footer.skip') }}
                            </button>

                            <button
                                v-if="!isLastStep"
                                @click="nextStep"
                                class="inline-flex items-center gap-2 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
                            >
                                {{ currentStepData?.id === 'welcome' ? t('notifications_setup_wizard.footer.get_started') : t('notifications_setup_wizard.footer.continue') }}
                                <ArrowRightIcon class="w-4 h-4" />
                            </button>

                            <button
                                v-else
                                @click="completeWizard"
                                class="inline-flex items-center gap-2 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium"
                            >
                                <CheckIcon class="w-4 h-4" />
                                {{ t('notifications_setup_wizard.footer.complete_setup') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
