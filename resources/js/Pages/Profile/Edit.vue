<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PersonalInfoTab from './Partials/PersonalInfoTab.vue';
import SecurityTab from './Partials/SecurityTab.vue';
import BusinessProfileTab from './Partials/BusinessProfileTab.vue';
import VerificationTab from './Partials/VerificationTab.vue';
import DangerZoneTab from './Partials/DangerZoneTab.vue';
import NotificationsTab from './Partials/NotificationsTab.vue';
import type { ProfileEditPageProps } from '@/types';
import { useI18n } from '@/composables/useI18n';
import {
    UserCircleIcon,
    ShieldCheckIcon,
    BuildingOfficeIcon,
    IdentificationIcon,
    ExclamationTriangleIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ProfileEditPageProps>();

const { t } = useI18n();

const activeTab = ref('personal');

const tabs = computed(() => {
    const baseTabs = [
        { id: 'personal', name: t('profile_edit.tabs.personal'), icon: UserCircleIcon },
        { id: 'security', name: t('profile_edit.tabs.security'), icon: ShieldCheckIcon },
        { id: 'notifications', name: t('profile_edit.tabs.notifications'), icon: BellIcon },
    ];

    // Add role-specific tabs
    if (props.user.role === 'landlord') {
        baseTabs.push({ id: 'business', name: t('profile_edit.tabs.business'), icon: BuildingOfficeIcon });
    }

    if (props.user.role === 'tenant') {
        baseTabs.push({ id: 'verification', name: t('profile_edit.tabs.verification'), icon: IdentificationIcon });
    }

    // Always add danger zone last
    baseTabs.push({ id: 'danger', name: t('profile_edit.tabs.danger_zone'), icon: ExclamationTriangleIcon });

    return baseTabs;
});

const roleLabel = computed(() => {
    const labels: Record<string, string> = {
        landlord: t('profile_personal_info.roles.landlord'),
        caretaker: t('profile_personal_info.roles.caretaker'),
        tenant: t('profile_personal_info.roles.tenant'),
        super_admin: t('profile_personal_info.roles.super_admin'),
    };
    return labels[props.user.role] || props.user.role;
});

const roleBadgeClass = computed(() => {
    const classes = {
        landlord: 'bg-indigo-100 text-indigo-800',
        caretaker: 'bg-emerald-100 text-emerald-800',
        tenant: 'bg-blue-100 text-blue-800',
        super_admin: 'bg-purple-100 text-purple-800',
    };
    return classes[props.user.role] || 'bg-gray-100 text-gray-800';
});
</script>

<template>
    <Head :title="t('profile_edit.page_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <UserCircleIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('profile_edit.page_title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('profile_edit.page_subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Profile Header Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center gap-4">
                        <!-- Profile Photo -->
                        <div class="relative">
                            <div
                                v-if="user.profile_photo_url"
                                class="w-20 h-20 rounded-full bg-cover bg-center border-4 border-white shadow-lg"
                                :style="'background-image: url(' + user.profile_photo_url + ')'"
                            ></div>
                            <div
                                v-else
                                class="w-20 h-20 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 border-4 border-white shadow-lg flex items-center justify-center"
                            >
                                <span class="text-2xl font-semibold text-white">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h2 class="text-xl font-semibold text-gray-900">{{ user.name }}</h2>
                                <span :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    roleBadgeClass
                                ]">
                                    {{ roleLabel }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">{{ user.email }}</p>
                            <p v-if="user.mobile_number" class="text-sm text-gray-500">{{ user.mobile_number }}</p>
                        </div>

                        <!-- Quick Stats for Landlords -->
                        <div v-if="user.role === 'landlord' && landlordProfile?.company_name" class="hidden sm:block text-end">
                            <p class="text-sm font-medium text-gray-900">{{ landlordProfile.company_name }}</p>
                            <p v-if="landlordProfile.city" class="text-xs text-gray-500">{{ landlordProfile.city }}, {{ landlordProfile.country }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                    activeTab === tab.id
                                        ? tab.id === 'danger'
                                            ? 'border-red-500 text-red-600'
                                            : 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                <component
                                    :is="tab.icon"
                                    :class="[
                                        'w-5 h-5',
                                        activeTab === tab.id
                                            ? tab.id === 'danger' ? 'text-red-500' : 'text-indigo-500'
                                            : 'text-gray-400'
                                    ]"
                                />
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="transition-all duration-200">
                    <!-- Personal Info Tab -->
                    <PersonalInfoTab
                        v-if="activeTab === 'personal'"
                        :user="user"
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />

                    <!-- Security Tab -->
                    <SecurityTab
                        v-if="activeTab === 'security'"
                    />

                    <!-- Notifications Tab -->
                    <NotificationsTab
                        v-if="activeTab === 'notifications'"
                        :user="user"
                    />

                    <!-- Business Profile Tab (Landlords Only) -->
                    <BusinessProfileTab
                        v-if="activeTab === 'business' && user.role === 'landlord'"
                        :user="user"
                        :landlord-profile="landlordProfile"
                    />

                    <!-- Verification Tab (Tenants Only) -->
                    <VerificationTab
                        v-if="activeTab === 'verification' && user.role === 'tenant'"
                        :user="user"
                    />

                    <!-- Danger Zone Tab -->
                    <DangerZoneTab
                        v-if="activeTab === 'danger'"
                        :user="user"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
