<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useZodForm } from '@/composables/forms/useZodForm';
import { tenantInvitationSchema } from '@/composables/forms/schemas/tenantInvitationSchema';
import type { LeasesCreatePageProps } from '@/types/finances';
import {
    EnvelopeIcon,
    CheckCircleIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon
} from '@heroicons/vue/24/outline';

const { formatMoney, todayAsISODate } = useFormatters();
const { currencySymbol } = useCurrency();
const { t } = useI18n();

const props = withDefaults(defineProps<LeasesCreatePageProps>(), {
    smsConfigured: false,
    whatsappConfigured: false,
});

// Form for sending tenant invitation
const form = useForm({
    unit_id: props.unit.id,
    email: '',
    tenant_name: '',
    tenant_phone: '',
    notification_channels: ['email'],
    rent_amount: props.unit.target_rent || 0,
    service_charge: 0,
    deposit_amount: props.unit.target_rent || 0,
    start_date: todayAsISODate(),
    end_date: ''
});

const invitationSent = ref(false);
const sentEmail = ref('');
const sentChannels = ref([]);

const { validate } = useZodForm(form, tenantInvitationSchema);

const submit = () => {
    if (!validate()) {
        return;
    }
    form.post(route('tenant-invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            invitationSent.value = true;
            sentEmail.value = form.email;
            sentChannels.value = [...form.notification_channels];
            form.reset('email', 'tenant_name', 'tenant_phone');
            form.notification_channels = ['email'];
        }
    });
};

// Check if phone is required (SMS or WhatsApp selected)
const requiresPhone = computed(() => {
    return form.notification_channels.includes('sms') ||
           form.notification_channels.includes('whatsapp');
});

// Computed Totals
const totalMoveInCost = computed(() => {
    return Number(form.rent_amount) + Number(form.service_charge) + Number(form.deposit_amount);
});

// Channel label helper
const getChannelLabel = (channel) => {
    const labels = {
        email: t('leases.create.channels.email'),
        sms: t('leases.create.channels.sms'),
        whatsapp: t('leases.create.channels.whatsapp')
    };
    return labels[channel] || channel;
};
</script>

<template>
    <Head :title="t('leases.create.title')" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

                <!-- HEADER -->
                <div class="md:flex md:items-center md:justify-between mb-8">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                            {{ t('leases.create.heading', { unit: unit.unit_number }) }}
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">{{ t('leases.create.subheading', { floor: unit.floor_number }) }}</p>
                    </div>
                </div>

                <!-- Success Message -->
                <div v-if="invitationSent" class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <CheckCircleIcon class="w-6 h-6 text-green-500 shrink-0" />
                        <div>
                            <h3 class="text-sm font-medium text-green-800">{{ t('leases.create.success.title') }}</h3>
                            <p class="mt-1 text-sm text-green-700">
                                {{ t('leases.create.success.sent_to') }} <strong>{{ sentEmail }}</strong>
                                {{ t('leases.create.success.via', { channels: sentChannels.map(c => getChannelLabel(c)).join(', ') }) }}
                                {{ t('leases.create.success.follow_up') }}
                            </p>
                            <div class="mt-3 flex gap-3">
                                <button
                                    @click="invitationSent = false"
                                    class="text-sm font-medium text-green-600 hover:text-green-800"
                                >
                                    {{ t('leases.create.success.send_another') }}
                                </button>
                                <button
                                    @click="router.visit(route('dashboard'))"
                                    class="text-sm font-medium text-green-600 hover:text-green-800"
                                >
                                    {{ t('leases.create.success.return_dashboard') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!invitationSent" class="bg-white shadow-xl rounded-lg overflow-hidden">
                    <!-- Info Banner -->
                    <div class="bg-blue-50 border-b border-blue-100 px-6 py-4">
                        <div class="flex items-start gap-3">
                            <EnvelopeIcon class="w-5 h-5 text-blue-500 mt-0.5" />
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">{{ t('leases.create.how_it_works.title') }}</p>
                                <ol class="mt-1 list-decimal list-inside space-y-1 text-blue-600">
                                    <li>{{ t('leases.create.how_it_works.step1') }}</li>
                                    <li>{{ t('leases.create.how_it_works.step2') }}</li>
                                    <li>{{ t('leases.create.how_it_works.step3') }}</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="p-8 space-y-8 divide-y divide-gray-200">

                        <!-- SECTION 1: TENANT INFO -->
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ t('leases.create.tenant_info.title') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ t('leases.create.tenant_info.subtitle') }}</p>
                            </div>
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <div class="sm:col-span-3">
                                    <label for="lease-tenant-email" class="block text-sm font-medium text-gray-700">
                                        {{ t('leases.create.fields.email') }} <span class="text-red-500">{{ t('leases.create.required') }}</span>
                                    </label>
                                    <input
                                        id="lease-tenant-email"
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 block w-full border rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        :class="form.errors.email ? 'border-red-300' : 'border-gray-300'"
                                        :placeholder="t('leases.create.fields.email_placeholder')"
                                        required
                                    >
                                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                                    <p v-else class="mt-1 text-xs text-gray-500">{{ t('leases.create.fields.email_help') }}</p>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="lease-tenant-name" class="block text-sm font-medium text-gray-700">{{ t('leases.create.fields.name') }}</label>
                                    <input
                                        id="lease-tenant-name"
                                        v-model="form.tenant_name"
                                        type="text"
                                        class="mt-1 block w-full border rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        :class="form.errors.tenant_name ? 'border-red-300' : 'border-gray-300'"
                                        :placeholder="t('leases.create.fields.name_placeholder')"
                                    >
                                    <p v-if="form.errors.tenant_name" class="mt-1 text-sm text-red-600">{{ form.errors.tenant_name }}</p>
                                    <p v-else class="mt-1 text-xs text-gray-500">{{ t('leases.create.fields.name_help') }}</p>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="lease-tenant-phone" class="block text-sm font-medium text-gray-700">
                                        {{ t('leases.create.fields.phone') }}
                                        <span v-if="requiresPhone" class="text-red-500">{{ t('leases.create.required') }}</span>
                                        <span v-else class="text-gray-400 text-xs font-normal">{{ t('leases.create.fields.phone_optional') }}</span>
                                    </label>
                                    <input
                                        id="lease-tenant-phone"
                                        v-model="form.tenant_phone"
                                        type="text"
                                        class="mt-1 block w-full border rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        :class="form.errors.tenant_phone ? 'border-red-300' : 'border-gray-300'"
                                        :placeholder="t('leases.create.fields.phone_placeholder')"
                                        :required="requiresPhone"
                                    >
                                    <p v-if="form.errors.tenant_phone" class="mt-1 text-sm text-red-600">{{ form.errors.tenant_phone }}</p>
                                    <p v-else-if="requiresPhone" class="mt-1 text-xs text-amber-600">{{ t('leases.create.fields.phone_required_help') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: FINANCIALS -->
                        <div class="pt-8 space-y-6">
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ t('leases.create.lease_terms.title') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ t('leases.create.lease_terms.subtitle') }}</p>
                            </div>
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-3">

                                <div>
                                    <label for="lease-rent-amount" class="block text-sm font-medium text-gray-700">
                                        {{ t('leases.create.fields.monthly_rent', { currency: currencySymbol }) }} <span class="text-red-500">{{ t('leases.create.required') }}</span>
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input
                                            id="lease-rent-amount"
                                            v-model="form.rent_amount"
                                            type="number"
                                            class="focus:ring-indigo-500 focus:border-indigo-500 block w-full ps-4 pe-12 sm:text-sm rounded-md py-2 border"
                                            :class="form.errors.rent_amount ? 'border-red-300' : 'border-gray-300'"
                                            :placeholder="t('leases.create.fields.amount_placeholder')"
                                            required
                                        >
                                    </div>
                                    <p v-if="form.errors.rent_amount" class="mt-1 text-sm text-red-600">{{ form.errors.rent_amount }}</p>
                                </div>

                                <div>
                                    <label for="lease-service-charge" class="block text-sm font-medium text-gray-700">{{ t('leases.create.fields.service_charge', { currency: currencySymbol }) }}</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input
                                            id="lease-service-charge"
                                            v-model="form.service_charge"
                                            type="number"
                                            class="focus:ring-indigo-500 focus:border-indigo-500 block w-full ps-4 pe-12 sm:text-sm rounded-md py-2 border"
                                            :class="form.errors.service_charge ? 'border-red-300' : 'border-gray-300'"
                                            :placeholder="t('leases.create.fields.amount_placeholder')"
                                        >
                                    </div>
                                    <p v-if="form.errors.service_charge" class="mt-1 text-sm text-red-600">{{ form.errors.service_charge }}</p>
                                    <p v-else class="text-xs text-gray-500 mt-1">{{ t('leases.create.fields.service_charge_help') }}</p>
                                </div>

                                <div>
                                    <label for="lease-deposit-amount" class="block text-sm font-medium text-gray-700">
                                        {{ t('leases.create.fields.security_deposit', { currency: currencySymbol }) }} <span class="text-red-500">{{ t('leases.create.required') }}</span>
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input
                                            id="lease-deposit-amount"
                                            v-model="form.deposit_amount"
                                            type="number"
                                            class="focus:ring-indigo-500 focus:border-indigo-500 block w-full ps-4 pe-12 sm:text-sm rounded-md py-2 border"
                                            :class="form.errors.deposit_amount ? 'border-red-300' : 'border-gray-300'"
                                            :placeholder="t('leases.create.fields.amount_placeholder')"
                                            required
                                        >
                                    </div>
                                    <p v-if="form.errors.deposit_amount" class="mt-1 text-sm text-red-600">{{ form.errors.deposit_amount }}</p>
                                </div>

                            </div>

                            <!-- TOTALS PREVIEW -->
                            <div class="bg-gray-50 rounded-lg p-4 flex justify-between items-center border border-gray-200">
                                <span class="text-sm font-medium text-gray-500">{{ t('leases.create.totals.move_in') }}</span>
                                <span class="text-xl font-bold text-gray-900">{{ formatMoney(totalMoveInCost) }}</span>
                            </div>
                        </div>

                        <!-- SECTION 3: DATES -->
                        <div class="pt-8 space-y-6">
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ t('leases.create.lease_period.title') }}</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                <div>
                                    <label for="lease-start-date" class="block text-sm font-medium text-gray-700">
                                        {{ t('leases.create.fields.start_date') }} <span class="text-red-500">{{ t('leases.create.required') }}</span>
                                    </label>
                                    <input
                                        id="lease-start-date"
                                        v-model="form.start_date"
                                        type="date"
                                        class="mt-1 block w-full border rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        :class="form.errors.start_date ? 'border-red-300' : 'border-gray-300'"
                                        required
                                    >
                                    <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
                                </div>
                                <div>
                                    <label for="lease-end-date" class="block text-sm font-medium text-gray-700">{{ t('leases.create.fields.end_date') }}</label>
                                    <input
                                        id="lease-end-date"
                                        v-model="form.end_date"
                                        type="date"
                                        class="mt-1 block w-full border rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        :class="form.errors.end_date ? 'border-red-300' : 'border-gray-300'"
                                    >
                                    <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
                                    <p v-else class="mt-1 text-xs text-gray-500">{{ t('leases.create.fields.end_date_help') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 4: NOTIFICATION CHANNELS -->
                        <div class="pt-8 space-y-6">
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ t('leases.create.channels.title') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ t('leases.create.channels.subtitle') }}</p>
                            </div>

                            <div class="space-y-3">
                                <!-- Email Channel -->
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer hover:bg-gray-50"
                                       :class="form.notification_channels.includes('email') ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200'">
                                    <input
                                        type="checkbox"
                                        value="email"
                                        v-model="form.notification_channels"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-900">{{ t('leases.create.channels.email') }}</span>
                                        <p class="text-xs text-gray-500" v-if="form.email">{{ form.email }}</p>
                                    </div>
                                </label>

                                <!-- SMS Channel -->
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer"
                                       :class="{
                                           'border-indigo-200 bg-indigo-50': form.notification_channels.includes('sms'),
                                           'border-gray-200 hover:bg-gray-50': smsConfigured && !form.notification_channels.includes('sms'),
                                           'border-gray-200 bg-gray-50 cursor-not-allowed opacity-60': !smsConfigured // i18n-ignore: tailwind classes
                                       }">
                                    <input
                                        type="checkbox"
                                        value="sms"
                                        v-model="form.notification_channels"
                                        :disabled="!smsConfigured"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                                    >
                                    <DevicePhoneMobileIcon class="w-5 h-5 text-gray-400" />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-900">{{ t('leases.create.channels.sms') }}</span>
                                        <p v-if="!smsConfigured" class="text-xs text-amber-600">{{ t('leases.create.channels.not_configured') }}</p>
                                        <p v-else-if="form.tenant_phone" class="text-xs text-gray-500">{{ form.tenant_phone }}</p>
                                        <p v-else class="text-xs text-gray-500">{{ t('leases.create.channels.enter_phone') }}</p>
                                    </div>
                                </label>

                                <!-- WhatsApp Channel -->
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer"
                                       :class="{
                                           'border-green-200 bg-green-50': form.notification_channels.includes('whatsapp'),
                                           'border-gray-200 hover:bg-gray-50': whatsappConfigured && !form.notification_channels.includes('whatsapp'),
                                           'border-gray-200 bg-gray-50 cursor-not-allowed opacity-60': !whatsappConfigured // i18n-ignore: tailwind classes
                                       }">
                                    <input
                                        type="checkbox"
                                        value="whatsapp"
                                        v-model="form.notification_channels"
                                        :disabled="!whatsappConfigured"
                                        class="rounded border-gray-300 text-green-600 focus:ring-green-500 disabled:opacity-50"
                                    >
                                    <ChatBubbleLeftRightIcon class="w-5 h-5 text-green-500" />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-900">{{ t('leases.create.channels.whatsapp') }}</span>
                                        <p v-if="!whatsappConfigured" class="text-xs text-amber-600">{{ t('leases.create.channels.not_configured') }}</p>
                                        <p v-else-if="form.tenant_phone" class="text-xs text-gray-500">{{ form.tenant_phone }}</p>
                                        <p v-else class="text-xs text-gray-500">{{ t('leases.create.channels.enter_phone') }}</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Validation error -->
                            <p v-if="form.errors.notification_channels" class="text-sm text-red-600">{{ form.errors.notification_channels }}</p>

                            <!-- Cost warning -->
                            <div v-if="form.notification_channels.includes('sms') || form.notification_channels.includes('whatsapp')"
                                 class="bg-amber-50 border border-amber-200 rounded-md p-3">
                                <p class="text-xs text-amber-800">
                                    {{ t('leases.create.channels.cost_warning') }}
                                </p>
                            </div>
                        </div>

                        <!-- Error display for unit_id -->
                        <p v-if="form.errors.unit_id" class="text-sm text-red-600">{{ form.errors.unit_id }}</p>

                        <!-- SUBMIT -->
                        <div class="pt-5">
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    @click="router.visit(route('dashboard'))"
                                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    {{ t('leases.create.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="ms-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    :disabled="form.processing"
                                >
                                    <EnvelopeIcon v-if="!form.processing" class="w-4 h-4 me-2" />
                                    {{ form.processing ? t('leases.create.sending') : t('leases.create.send') }}
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
