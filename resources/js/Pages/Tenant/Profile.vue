<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    UserCircleIcon,
    LockClosedIcon,
    LanguageIcon,
    BellIcon,
    PhoneIcon,
} from '@heroicons/vue/24/outline';

interface TenantUser {
    id: number;
    name: string;
    email: string;
    mobile_number: string | null;
    profile_photo_url: string | null;
    email_verified_at: string | null;
    locale: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    created_at: string;
}

interface NotificationPref {
    rent_reminder_enabled: boolean;
    arrears_notice_enabled: boolean;
    invoice_enabled: boolean;
    receipt_enabled: boolean;
    lease_expiry_enabled: boolean;
    lease_renewal_enabled: boolean;
    maintenance_notice_enabled: boolean;
    general_enabled: boolean;
    email_enabled: boolean;
    sms_enabled: boolean;
    whatsapp_enabled: boolean;
    push_enabled: boolean;
    in_app_enabled: boolean;
    whatsapp_number: string | null;
}

const props = defineProps<{
    user: TenantUser;
    notificationPreference: NotificationPref | null;
    supportedLocales: string[];
    status?: string;
}>();

type TabKey = 'personal' | 'password' | 'locale' | 'notifications' | 'emergency';
const activeTab = ref<TabKey>('personal');

const tabs: { key: TabKey; label: string; icon: any }[] = [
    { key: 'personal', label: 'Personal', icon: UserCircleIcon },
    { key: 'password', label: 'Password', icon: LockClosedIcon },
    { key: 'locale', label: 'Language', icon: LanguageIcon },
    { key: 'notifications', label: 'Notifications', icon: BellIcon },
    { key: 'emergency', label: 'Emergency Contact', icon: PhoneIcon },
];

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
    mobile_number: props.user.mobile_number ?? '',
    emergency_contact_name: props.user.emergency_contact_name ?? '',
    emergency_contact_phone: props.user.emergency_contact_phone ?? '',
    profile_photo: null as File | null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const localeForm = useForm({
    locale: props.user.locale ?? usePage().props.locale ?? 'en',
});

const notifForm = useForm({
    rent_reminder_enabled: props.notificationPreference?.rent_reminder_enabled ?? true,
    arrears_notice_enabled: props.notificationPreference?.arrears_notice_enabled ?? true,
    invoice_enabled: props.notificationPreference?.invoice_enabled ?? true,
    receipt_enabled: props.notificationPreference?.receipt_enabled ?? true,
    lease_expiry_enabled: props.notificationPreference?.lease_expiry_enabled ?? true,
    lease_renewal_enabled: props.notificationPreference?.lease_renewal_enabled ?? true,
    maintenance_notice_enabled: props.notificationPreference?.maintenance_notice_enabled ?? true,
    general_enabled: props.notificationPreference?.general_enabled ?? true,
    email_enabled: props.notificationPreference?.email_enabled ?? true,
    sms_enabled: props.notificationPreference?.sms_enabled ?? false,
    whatsapp_enabled: props.notificationPreference?.whatsapp_enabled ?? false,
    push_enabled: props.notificationPreference?.push_enabled ?? true,
    in_app_enabled: props.notificationPreference?.in_app_enabled ?? true,
    whatsapp_number: props.notificationPreference?.whatsapp_number ?? '',
});

const notificationTypes: { key: keyof NotificationPref; label: string }[] = [
    { key: 'rent_reminder_enabled', label: 'Rent reminders' },
    { key: 'arrears_notice_enabled', label: 'Arrears notices' },
    { key: 'invoice_enabled', label: 'New invoices' },
    { key: 'receipt_enabled', label: 'Payment receipts' },
    { key: 'lease_expiry_enabled', label: 'Lease expiry' },
    { key: 'lease_renewal_enabled', label: 'Lease renewal' },
    { key: 'maintenance_notice_enabled', label: 'Maintenance updates' },
    { key: 'general_enabled', label: 'General announcements' },
];

const submitProfile = () => {
    profileForm.post(route('tenant.profile.update'), {
        method: 'patch',
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { profileForm.profile_photo = null; },
    });
};

const submitPassword = () => {
    passwordForm.patch(route('tenant.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const submitLocale = () => {
    localeForm.patch(route('locale.update'), { preserveScroll: true });
};

const submitNotifications = () => {
    notifForm.patch(route('tenant.profile.notification-prefs'), { preserveScroll: true });
};

const submitEmergency = () => {
    profileForm.post(route('tenant.profile.update'), {
        method: 'patch',
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="My Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold text-gray-900">My Profile</h1>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Sidebar tabs -->
                    <nav class="lg:col-span-1" aria-label="Profile sections">
                        <ul class="space-y-1">
                            <li v-for="tab in tabs" :key="tab.key">
                                <button
                                    type="button"
                                    @click="activeTab = tab.key"
                                    :class="[
                                        'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                        activeTab === tab.key
                                            ? 'bg-indigo-50 text-indigo-700'
                                            : 'text-gray-700 hover:bg-gray-50',
                                    ]"
                                    :aria-current="activeTab === tab.key ? 'page' : undefined"
                                >
                                    <component :is="tab.icon" class="w-5 h-5" />
                                    {{ tab.label }}
                                </button>
                            </li>
                        </ul>
                    </nav>

                    <div class="lg:col-span-3 space-y-6">
                        <!-- Personal tab -->
                        <form
                            v-if="activeTab === 'personal'"
                            @submit.prevent="submitProfile"
                            class="bg-white rounded-xl border border-gray-200 p-6 space-y-4"
                        >
                            <h2 class="text-lg font-medium text-gray-900">Personal information</h2>
                            <div>
                                <InputLabel for="name" value="Full name" />
                                <TextInput id="name" v-model="profileForm.name" type="text" class="mt-1 block w-full" required />
                                <InputError :message="profileForm.errors.name" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="email" value="Email address" />
                                <TextInput id="email" v-model="profileForm.email" type="email" class="mt-1 block w-full" required />
                                <InputError :message="profileForm.errors.email" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="mobile" value="Mobile number" />
                                <TextInput id="mobile" v-model="profileForm.mobile_number" type="tel" class="mt-1 block w-full" placeholder="+254 712 345 678" />
                                <InputError :message="profileForm.errors.mobile_number" class="mt-2" />
                            </div>
                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <PrimaryButton :disabled="profileForm.processing">Save changes</PrimaryButton>
                            </div>
                        </form>

                        <!-- Password tab -->
                        <form
                            v-if="activeTab === 'password'"
                            @submit.prevent="submitPassword"
                            class="bg-white rounded-xl border border-gray-200 p-6 space-y-4"
                        >
                            <h2 class="text-lg font-medium text-gray-900">Change password</h2>
                            <div>
                                <InputLabel for="current_password" value="Current password" />
                                <TextInput id="current_password" v-model="passwordForm.current_password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                                <InputError :message="passwordForm.errors.current_password" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="password" value="New password" />
                                <TextInput id="password" v-model="passwordForm.password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                                <InputError :message="passwordForm.errors.password" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="password_confirmation" value="Confirm new password" />
                                <TextInput id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            </div>
                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <PrimaryButton :disabled="passwordForm.processing">Update password</PrimaryButton>
                            </div>
                        </form>

                        <!-- Locale tab -->
                        <form
                            v-if="activeTab === 'locale'"
                            @submit.prevent="submitLocale"
                            class="bg-white rounded-xl border border-gray-200 p-6 space-y-4"
                        >
                            <h2 class="text-lg font-medium text-gray-900">Language preference</h2>
                            <p class="text-sm text-gray-500">Choose the language for the dashboard and email notifications.</p>
                            <div>
                                <InputLabel for="locale" value="Language" />
                                <select
                                    id="locale"
                                    v-model="localeForm.locale"
                                    class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="code in props.supportedLocales" :key="code" :value="code">
                                        {{ code === 'sw' ? 'Kiswahili' : code === 'en' ? 'English' : code }}
                                    </option>
                                </select>
                                <InputError :message="localeForm.errors.locale" class="mt-2" />
                            </div>
                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <PrimaryButton :disabled="localeForm.processing">Save language</PrimaryButton>
                            </div>
                        </form>

                        <!-- Notifications tab -->
                        <form
                            v-if="activeTab === 'notifications'"
                            @submit.prevent="submitNotifications"
                            class="bg-white rounded-xl border border-gray-200 p-6 space-y-6"
                        >
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Notification preferences</h2>
                                <p class="text-sm text-gray-500 mt-1">
                                    You receive a message only when both the notification type AND at least one channel below are enabled.
                                </p>
                            </div>

                            <fieldset class="space-y-3">
                                <legend class="text-sm font-medium text-gray-900">Notification types</legend>
                                <div v-for="type in notificationTypes" :key="type.key" class="flex items-center justify-between py-1">
                                    <label :for="String(type.key)" class="text-sm text-gray-700">{{ type.label }}</label>
                                    <input
                                        :id="String(type.key)"
                                        type="checkbox"
                                        v-model="notifForm[type.key]"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </div>
                            </fieldset>

                            <fieldset class="space-y-3 pt-4 border-t border-gray-200">
                                <legend class="text-sm font-medium text-gray-900">Delivery channels</legend>
                                <div class="flex items-center justify-between py-1">
                                    <label for="email_enabled" class="text-sm text-gray-700">Email</label>
                                    <input id="email_enabled" type="checkbox" v-model="notifForm.email_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <label for="sms_enabled" class="text-sm text-gray-700">SMS</label>
                                    <input id="sms_enabled" type="checkbox" v-model="notifForm.sms_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <label for="whatsapp_enabled" class="text-sm text-gray-700">WhatsApp</label>
                                    <input id="whatsapp_enabled" type="checkbox" v-model="notifForm.whatsapp_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </div>
                                <div v-if="notifForm.whatsapp_enabled" class="pl-4">
                                    <InputLabel for="whatsapp_number" value="WhatsApp number (E.164)" />
                                    <TextInput id="whatsapp_number" v-model="notifForm.whatsapp_number" type="tel" class="mt-1 block w-full" placeholder="+254712345678" />
                                    <InputError :message="notifForm.errors.whatsapp_number" class="mt-2" />
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <label for="push_enabled" class="text-sm text-gray-700">Push (browser)</label>
                                    <input id="push_enabled" type="checkbox" v-model="notifForm.push_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <label for="in_app_enabled" class="text-sm text-gray-700">In-app</label>
                                    <input id="in_app_enabled" type="checkbox" v-model="notifForm.in_app_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </div>
                            </fieldset>

                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <PrimaryButton :disabled="notifForm.processing">Save preferences</PrimaryButton>
                            </div>
                        </form>

                        <!-- Emergency contact tab -->
                        <form
                            v-if="activeTab === 'emergency'"
                            @submit.prevent="submitEmergency"
                            class="bg-white rounded-xl border border-gray-200 p-6 space-y-4"
                        >
                            <h2 class="text-lg font-medium text-gray-900">Emergency contact</h2>
                            <p class="text-sm text-gray-500">Someone we can call if we cannot reach you during an emergency at the property.</p>
                            <div>
                                <InputLabel for="emergency_name" value="Contact name" />
                                <TextInput id="emergency_name" v-model="profileForm.emergency_contact_name" type="text" class="mt-1 block w-full" />
                                <InputError :message="profileForm.errors.emergency_contact_name" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="emergency_phone" value="Contact phone" />
                                <TextInput id="emergency_phone" v-model="profileForm.emergency_contact_phone" type="tel" class="mt-1 block w-full" placeholder="+254 712 345 678" />
                                <InputError :message="profileForm.errors.emergency_contact_phone" class="mt-2" />
                            </div>
                            <div class="flex justify-end pt-4 border-t border-gray-200">
                                <PrimaryButton :disabled="profileForm.processing">Save contact</PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
