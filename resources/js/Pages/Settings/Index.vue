<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import {
    Cog6ToothIcon,
    BuildingOffice2Icon,
    CreditCardIcon,
    BellIcon,
    PuzzlePieceIcon,
    ShieldCheckIcon,
    SwatchIcon,
    DocumentCheckIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline';
import type {
    LandlordProfile,
    PaymentConfiguration,
    PaymentMethodsLookup,
    OcrSettings,
    OcrProvidersLookup,
    BrandingSettings,
    NotificationDefaults,
    InvoiceNumberFormats,
} from '@/types';

// Tab Components
import BusinessProfileTab from './partials/BusinessProfileTab.vue';
import PaymentMethodsTab from './partials/PaymentMethodsTab.vue';
import NotificationsTab from './partials/NotificationsTab.vue';
import IntegrationsTab from './partials/IntegrationsTab.vue';
import SecurityTab from './partials/SecurityTab.vue';
import BrandingTab from './partials/BrandingTab.vue';

const props = withDefaults(defineProps<{
    activeTab?: string;
    landlordProfile?: LandlordProfile | null;
    paymentConfig?: PaymentConfiguration;
    paymentMethods?: PaymentMethodsLookup;
    ocrSettings?: OcrSettings;
    ocrProviders?: OcrProvidersLookup;
    brandingSettings?: BrandingSettings;
    notificationDefaults?: NotificationDefaults | null;
    twoFactorEnabled?: boolean;
    invoiceNumberFormats?: InvoiceNumberFormats;
}>(), {
    activeTab: 'business',
    landlordProfile: null,
    paymentConfig: () => ({} as PaymentConfiguration),
    paymentMethods: () => ({}),
    ocrSettings: () => ({} as OcrSettings),
    ocrProviders: () => ({}),
    brandingSettings: () => ({} as BrandingSettings),
    notificationDefaults: null,
    twoFactorEnabled: false,
    invoiceNumberFormats: () => ({}),
});

const { t } = useI18n();

const currentTab = ref(props.activeTab || 'business');

const tabs = computed(() => [
    { id: 'business', name: t('settings_index.tabs.business'), icon: BuildingOffice2Icon },
    { id: 'payment', name: t('settings_index.tabs.payment'), icon: CreditCardIcon },
    { id: 'notifications', name: t('settings_index.tabs.notifications'), icon: BellIcon },
    { id: 'integrations', name: t('settings_index.tabs.integrations'), icon: PuzzlePieceIcon },
    { id: 'security', name: t('settings_index.tabs.security'), icon: ShieldCheckIcon },
    { id: 'branding', name: t('settings_index.tabs.branding'), icon: SwatchIcon },
]);

const navigateToTab = (tab) => {
    currentTab.value = tab.id;
    router.get(route('settings.index', { tab: tab.id }), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="t('settings_index.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Header with Welcome Message -->
                <div class="bg-gradient-to-r from-slate-700 to-slate-900 overflow-hidden shadow-xl sm:rounded-2xl">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <Cog6ToothIcon class="w-8 h-8 text-white" />
                            </div>
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ t('settings_index.title') }}</h1>
                                <p class="mt-1 text-slate-300">
                                    {{ t('settings_index.subtitle') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5">
                    <nav class="flex gap-1 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="navigateToTab(tab)"
                            :class="['group flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-medium text-sm transition-all duration-200 whitespace-nowrap', currentTab === tab.id ? 'bg-gradient-to-r from-slate-700 to-slate-900 text-white shadow-md shrink-0' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 shrink-0']"
                        >
                            <component :is="tab.icon" class="w-5 h-5" />
                            <span class="hidden sm:inline">{{ tab.name }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 transition-all duration-300">
                    <!-- Business Profile Tab -->
                    <BusinessProfileTab
                        v-if="currentTab === 'business'"
                        :landlord-profile="landlordProfile"
                    />

                    <!-- Payment Methods Tab -->
                    <PaymentMethodsTab
                        v-if="currentTab === 'payment'"
                        :payment-config="paymentConfig"
                        :payment-methods="paymentMethods"
                    />

                    <!-- Notifications Tab -->
                    <NotificationsTab
                        v-if="currentTab === 'notifications'"
                        :notification-defaults="notificationDefaults"
                    />

                    <!-- Integrations Tab -->
                    <IntegrationsTab
                        v-if="currentTab === 'integrations'"
                        :ocr-settings="ocrSettings"
                        :ocr-providers="ocrProviders"
                    />

                    <!-- Security Tab -->
                    <SecurityTab
                        v-if="currentTab === 'security'"
                        :two-factor-enabled="twoFactorEnabled"
                    />

                    <!-- Branding Tab -->
                    <BrandingTab
                        v-if="currentTab === 'branding'"
                        :branding-settings="brandingSettings"
                        :invoice-number-formats="invoiceNumberFormats"
                    />
                </div>

                <!-- Additional Settings Links -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">{{ t('settings_index.additional.heading') }}</h3>
                    <div class="space-y-3">
                        <Link
                            :href="route('settings.kyc.index')"
                            class="flex items-center justify-between p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors group"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <DocumentCheckIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <span class="font-medium text-gray-900">{{ t('settings_index.additional.kyc_title') }}</span>
                                    <p class="text-sm text-gray-500">{{ t('settings_index.additional.kyc_description') }}</p>
                                </div>
                            </div>
                            <ArrowRightIcon class="w-5 h-5 text-gray-400 group-hover:text-gray-600 transition-colors" />
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
