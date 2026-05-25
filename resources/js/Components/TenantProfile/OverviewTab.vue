<script setup lang="ts">
import KycBadge from '@/Components/KycBadge.vue';
import FinancialSummaryCard from '@/Components/FinancialSummaryCard.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { TenantOverviewTabProps } from '@/types';

const props = defineProps<TenantOverviewTabProps>();
const { formatDate, formatMoney: formatCurrency } = useFormatters();
const { t } = useI18n();

const primaryContact = () => {
    return props.emergencyContacts?.find(c => c.is_primary) || props.emergencyContacts?.[0];
};

const recentActivities = () => {
    return (props.activities || []).slice(0, 5);
};

const getActivityIcon = (type) => {
    const icons = {
        'profile_updated': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'note_added': 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'emergency_contact_added': 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
        'lease_created': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    };
    return icons[type] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('tenant_profile_overview.contact_information') }}</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ t('tenant_profile_overview.email') }}</dt>
                        <dd class="text-sm text-gray-900">{{ tenant?.email || t('tenant_profile_overview.not_available') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ t('tenant_profile_overview.phone') }}</dt>
                        <dd class="text-sm text-gray-900">{{ tenant?.mobile_number || t('tenant_profile_overview.not_available') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ t('tenant_profile_overview.id_number') }}</dt>
                        <dd class="text-sm text-gray-900">{{ tenant?.national_id || t('tenant_profile_overview.not_provided') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ t('tenant_profile_overview.tenant_since') }}</dt>
                        <dd class="text-sm text-gray-900">{{ formatDate(tenant?.created_at) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('tenant_profile_overview.verification_status') }}</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">{{ t('tenant_profile_overview.kyc_status') }}</span>
                        <KycBadge
                            :completed="verificationStatus?.kyc_completed"
                            :completed-at="verificationStatus?.kyc_completed_at"
                        />
                    </div>
                    <div class="flex items-center justify-between" v-if="activeLease">
                        <span class="text-sm text-gray-500">{{ t('tenant_profile_overview.lease_verification') }}</span>
                        <span
                            :class="[verificationStatus?.lease_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800', 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium']"
                        >
                            {{ t('tenant_profile_overview.verified_count', { verified: verificationStatus?.verified_count || 0, total: verificationStatus?.verification_count || 0 }) }}
                        </span>
                    </div>
                    <div v-if="tenant?.occupation" class="flex justify-between">
                        <span class="text-sm text-gray-500">{{ t('tenant_profile_overview.occupation') }}</span>
                        <span class="text-sm text-gray-900">{{ tenant.occupation }}</span>
                    </div>
                    <div v-if="tenant?.employer" class="flex justify-between">
                        <span class="text-sm text-gray-500">{{ t('tenant_profile_overview.employer') }}</span>
                        <span class="text-sm text-gray-900">{{ tenant.employer }}</span>
                    </div>
                    <div v-if="tenant?.monthly_income" class="flex justify-between">
                        <span class="text-sm text-gray-500">{{ t('tenant_profile_overview.monthly_income') }}</span>
                        <span class="text-sm text-gray-900">{{ formatCurrency(tenant.monthly_income) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <FinancialSummaryCard :summary="financialSummary" />

        <div v-if="primaryContact()" class="bg-white border rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-3">{{ t('tenant_profile_overview.primary_emergency_contact') }}</h3>
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ primaryContact().name }}</p>
                    <p class="text-xs text-gray-500">{{ primaryContact().relationship }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ primaryContact().phone }}</p>
                </div>
            </div>
        </div>

        <div v-if="recentActivities().length" class="bg-white border rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-3">{{ t('tenant_profile_overview.recent_activity') }}</h3>
            <ul class="space-y-3">
                <li v-for="activity in recentActivities()" :key="activity.id" class="flex items-start gap-3">
                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                        <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActivityIcon(activity.type)" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-900">{{ activity.description }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ formatDate(activity.created_at) }}
                            <span v-if="activity.performer"> {{ t('tenant_profile_overview.performed_by', { name: activity.performer.name }) }}</span>
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
