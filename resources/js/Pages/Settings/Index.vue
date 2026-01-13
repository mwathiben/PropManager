<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Cog6ToothIcon,
    BuildingOffice2Icon,
    CreditCardIcon,
    BellIcon,
    PuzzlePieceIcon,
    ShieldCheckIcon,
    SwatchIcon,
} from '@heroicons/vue/24/outline';

// Tab Components
import BusinessProfileTab from './partials/BusinessProfileTab.vue';
import PaymentMethodsTab from './partials/PaymentMethodsTab.vue';
import NotificationsTab from './partials/NotificationsTab.vue';
import IntegrationsTab from './partials/IntegrationsTab.vue';
import SecurityTab from './partials/SecurityTab.vue';
import BrandingTab from './partials/BrandingTab.vue';

const props = defineProps({
    activeTab: { type: String, default: 'business' },
    landlordProfile: { type: Object, default: () => null },
    paymentConfig: { type: Object, default: () => ({}) },
    paymentMethods: { type: Object, default: () => ({}) },
    ocrSettings: { type: Object, default: () => ({}) },
    ocrProviders: { type: Object, default: () => ({}) },
    brandingSettings: { type: Object, default: () => ({}) },
    notificationDefaults: { type: Object, default: () => null },
    twoFactorEnabled: { type: Boolean, default: false },
    invoiceNumberFormats: { type: Object, default: () => ({}) },
});

const currentTab = ref(props.activeTab || 'business');

const tabs = [
    { id: 'business', name: 'Business Profile', icon: BuildingOffice2Icon },
    { id: 'payment', name: 'Payment Methods', icon: CreditCardIcon },
    { id: 'notifications', name: 'Notifications', icon: BellIcon },
    { id: 'integrations', name: 'Integrations', icon: PuzzlePieceIcon },
    { id: 'security', name: 'Security', icon: ShieldCheckIcon },
    { id: 'branding', name: 'Branding', icon: SwatchIcon },
];

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
    <Head title="Settings" />

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
                                <h1 class="text-2xl sm:text-3xl font-bold text-white">Settings</h1>
                                <p class="mt-1 text-slate-300">
                                    Manage your business profile, payment methods, and system preferences
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
                            :class="[
                                'group flex items-center justify-center gap-2 py-3 px-4 rounded-xl font-medium text-sm transition-all duration-200 whitespace-nowrap',
                                currentTab === tab.id
                                    ? 'bg-gradient-to-r from-slate-700 to-slate-900 text-white shadow-md flex-shrink-0'
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 flex-shrink-0'
                            ]"
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

            </div>
        </div>
    </AuthenticatedLayout>
</template>
