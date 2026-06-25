<script setup lang="ts">
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    BellIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';
import type { NotificationDefaults } from '@/types';

const { t } = useI18n();

const props = withDefaults(defineProps<{
    notificationDefaults?: NotificationDefaults | null;
}>(), {
    notificationDefaults: null,
});

const form = useForm({
    rent_reminder_enabled: props.notificationDefaults?.rent_reminder_enabled ?? true,
    arrears_notice_enabled: props.notificationDefaults?.arrears_notice_enabled ?? true,
    invoice_enabled: props.notificationDefaults?.invoice_enabled ?? true,
    receipt_enabled: props.notificationDefaults?.receipt_enabled ?? true,
    rent_hike_enabled: props.notificationDefaults?.rent_hike_enabled ?? true,
    lease_expiry_enabled: props.notificationDefaults?.lease_expiry_enabled ?? true,
    maintenance_notice_enabled: props.notificationDefaults?.maintenance_notice_enabled ?? true,
    general_enabled: props.notificationDefaults?.general_enabled ?? true,
    email_enabled: props.notificationDefaults?.email_enabled ?? true,
    sms_enabled: props.notificationDefaults?.sms_enabled ?? false,
    whatsapp_enabled: props.notificationDefaults?.whatsapp_enabled ?? false,
    rent_reminder_days_before: props.notificationDefaults?.rent_reminder_days_before ?? 3,
});

const notificationTypes = computed(() => [
    { key: 'rent_reminder_enabled', label: t('settings_notifications.types.rent_reminder.label'), description: t('settings_notifications.types.rent_reminder.description') },
    { key: 'arrears_notice_enabled', label: t('settings_notifications.types.arrears_notice.label'), description: t('settings_notifications.types.arrears_notice.description') },
    { key: 'invoice_enabled', label: t('settings_notifications.types.invoice.label'), description: t('settings_notifications.types.invoice.description') },
    { key: 'receipt_enabled', label: t('settings_notifications.types.receipt.label'), description: t('settings_notifications.types.receipt.description') },
    { key: 'rent_hike_enabled', label: t('settings_notifications.types.rent_hike.label'), description: t('settings_notifications.types.rent_hike.description') },
    { key: 'lease_expiry_enabled', label: t('settings_notifications.types.lease_expiry.label'), description: t('settings_notifications.types.lease_expiry.description') },
    { key: 'maintenance_notice_enabled', label: t('settings_notifications.types.maintenance_notice.label'), description: t('settings_notifications.types.maintenance_notice.description') },
    { key: 'general_enabled', label: t('settings_notifications.types.general.label'), description: t('settings_notifications.types.general.description') },
]);

const channels = computed(() => [
    { key: 'whatsapp_enabled', label: t('settings_notifications.channels.whatsapp'), icon: ChatBubbleLeftRightIcon, color: 'text-emerald-600', bg: 'bg-emerald-100', isPrimary: true },
    { key: 'sms_enabled', label: t('settings_notifications.channels.sms'), icon: DevicePhoneMobileIcon, color: 'text-green-600', bg: 'bg-green-100' },
    { key: 'email_enabled', label: t('settings_notifications.channels.email'), icon: EnvelopeIcon, color: 'text-blue-600', bg: 'bg-blue-100' },
]);

const submit = () => {
    form.post(route('settings.notifications.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('settings_notifications.header.title') }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ t('settings_notifications.header.subtitle') }}
                </p>
            </div>
            <Link
                :href="route('notifications.overview')"
                class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
            >
                {{ t('settings_notifications.header.notification_center') }}
                <ArrowTopRightOnSquareIcon class="w-4 h-4" />
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Communication Channels -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('settings_notifications.channels.heading') }}</h4>
                <p class="text-sm text-gray-500">{{ t('settings_notifications.channels.prompt') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div
                        v-for="channel in channels"
                        :key="channel.key"
                        role="button"
                        tabindex="0"
                        @click="form[channel.key] = !form[channel.key]"
                        @keydown.enter="form[channel.key] = !form[channel.key]"
                        @keydown.space.prevent="form[channel.key] = !form[channel.key]"
                        :class="['relative border-2 rounded-xl p-4 cursor-pointer transition-all', form[channel.key] ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="['p-2 rounded-lg', channel.bg]">
                                <component :is="channel.icon" :class="['w-5 h-5', channel.color]" />
                            </div>
                            <span :class="['text-sm font-medium', form[channel.key] ? 'text-indigo-900' : 'text-gray-700']">
                                {{ channel.label }}
                            </span>
                            <span
                                v-if="channel.isPrimary"
                                class="ms-1.5 px-1.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded"
                            >
                                {{ t('settings_notifications.channels.primary') }}
                            </span>
                            <div class="ms-auto">
                                <div :class="['w-5 h-5 rounded-full border-2 flex items-center justify-center', form[channel.key] ? 'border-indigo-600 bg-indigo-600' : 'border-gray-300']">
                                    <svg v-if="form[channel.key]" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Types -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('settings_notifications.types.heading') }}</h4>
                <p class="text-sm text-gray-500">{{ t('settings_notifications.types.prompt') }}</p>

                <div class="space-y-3">
                    <div
                        v-for="type in notificationTypes"
                        :key="type.key"
                        class="flex items-center justify-between py-3 border-b border-gray-200 last:border-0"
                    >
                        <div class="flex items-center gap-3">
                            <BellIcon class="w-5 h-5 text-gray-400" />
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ type.label }}</p>
                                <p class="text-xs text-gray-500">{{ type.description }}</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form[type.key]"
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Timing Settings -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('settings_notifications.timing.heading') }}</h4>

                <div class="max-w-xs">
                    <InputLabel for="rent_reminder_days" :value="t('settings_notifications.timing.days_before_label')" />
                    <div class="mt-1 flex items-center gap-2">
                        <TextInput
                            id="rent_reminder_days"
                            v-model="form.rent_reminder_days_before"
                            type="number"
                            min="1"
                            max="30"
                            class="block w-24"
                        />
                        <span class="text-sm text-gray-500">{{ t('settings_notifications.timing.days_before_suffix') }}</span>
                    </div>
                    <InputError :message="form.errors.rent_reminder_days_before" class="mt-2" />
                </div>
            </div>

            <!-- Info Banner -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex gap-3">
                    <BellIcon class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-800">
                        <p class="font-medium">{{ t('settings_notifications.info.title') }}</p>
                        <p class="mt-1">
                            {{ t('settings_notifications.info.body_lead') }}
                            <Link :href="route('notifications.overview')" class="underline font-medium">{{ t('settings_notifications.info.notification_center') }}</Link>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                >
                    {{ form.processing ? t('settings_notifications.saving') : t('settings_notifications.save') }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
