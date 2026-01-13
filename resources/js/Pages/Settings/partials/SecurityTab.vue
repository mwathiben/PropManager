<script setup>
import { Link } from '@inertiajs/vue3';
import {
    ShieldCheckIcon,
    KeyIcon,
    FingerPrintIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    twoFactorEnabled: {
        type: Boolean,
        default: false,
    },
});

const securityLinks = [
    {
        title: 'Two-Factor Authentication',
        description: 'Add an extra layer of security to your account with 2FA',
        icon: ShieldCheckIcon,
        route: 'two-factor.index',
        color: 'indigo',
        status: props.twoFactorEnabled ? 'Enabled' : 'Disabled',
        statusColor: props.twoFactorEnabled ? 'green' : 'yellow',
    },
    {
        title: 'Password & Profile',
        description: 'Update your password and personal information',
        icon: KeyIcon,
        route: 'profile.edit',
        color: 'purple',
        status: null,
    },
    {
        title: 'Privacy & Data',
        description: 'Export or delete your personal data (GDPR compliance)',
        icon: FingerPrintIcon,
        route: 'gdpr.index',
        color: 'green',
        status: null,
    },
];
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Security & Privacy</h3>
            <p class="mt-1 text-sm text-gray-600">
                Manage your account security and data privacy settings.
            </p>
        </div>

        <!-- Security Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
                v-for="item in securityLinks"
                :key="item.route"
                :href="route(item.route)"
                class="block p-5 bg-white border border-gray-200 rounded-xl hover:border-indigo-300 hover:shadow-md transition-all group"
            >
                <div class="flex items-start gap-4">
                    <div :class="[
                        'p-3 rounded-xl transition-colors',
                        `bg-${item.color}-100 group-hover:bg-${item.color}-200`
                    ]">
                        <component
                            :is="item.icon"
                            :class="[`w-6 h-6 text-${item.color}-600`]"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-gray-900">{{ item.title }}</h4>
                            <span
                                v-if="item.status"
                                :class="[
                                    'px-2 py-0.5 text-xs font-medium rounded',
                                    item.statusColor === 'green' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                ]"
                            >
                                {{ item.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ item.description }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </Link>
        </div>

        <!-- Security Tips -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="p-2 bg-amber-100 rounded-lg h-fit">
                    <ShieldCheckIcon class="w-6 h-6 text-amber-600" />
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-amber-900">Security Recommendations</h4>
                    <ul class="mt-2 space-y-2 text-sm text-amber-800">
                        <li class="flex items-center gap-2">
                            <span :class="[
                                'w-2 h-2 rounded-full',
                                twoFactorEnabled ? 'bg-green-500' : 'bg-amber-500'
                            ]"></span>
                            <span :class="{ 'line-through opacity-60': twoFactorEnabled }">
                                Enable two-factor authentication
                            </span>
                            <span v-if="twoFactorEnabled" class="text-green-600 text-xs font-medium">Done</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Use a strong, unique password
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Review your data privacy settings regularly
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Session Information -->
        <div class="bg-gray-50 rounded-xl p-6">
            <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider mb-4">Account Security Status</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
                    <div :class="[
                        'p-2 rounded-full',
                        twoFactorEnabled ? 'bg-green-100' : 'bg-yellow-100'
                    ]">
                        <ShieldCheckIcon :class="[
                            'w-5 h-5',
                            twoFactorEnabled ? 'text-green-600' : 'text-yellow-600'
                        ]" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Two-Factor Authentication</p>
                        <p :class="[
                            'text-xs',
                            twoFactorEnabled ? 'text-green-600' : 'text-yellow-600'
                        ]">
                            {{ twoFactorEnabled ? 'Your account is protected with 2FA' : 'Not enabled - we recommend enabling 2FA' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
                    <div class="p-2 rounded-full bg-blue-100">
                        <DocumentTextIcon class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Data Privacy</p>
                        <p class="text-xs text-gray-500">GDPR compliant data handling</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
