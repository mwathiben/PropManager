<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import { useI18n } from '@/composables/useI18n';
import {
    UserGroupIcon,
    BuildingOfficeIcon,
    HomeModernIcon,
    UsersIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    ChevronRightIcon,
    TrophyIcon,
    UserPlusIcon,
    ExclamationCircleIcon,
    ArrowTrendingUpIcon,
    EyeIcon
} from '@heroicons/vue/24/outline';
import type { SystemHealth, AdminActionItems, LandlordSummary } from '@/types';

const props = withDefaults(defineProps<{
    systemHealth?: SystemHealth;
    actionItems?: AdminActionItems;
    landlords?: LandlordSummary[];
    topLandlords?: LandlordSummary[];
}>(), {
    systemHealth: () => ({
        active_landlords: 0,
        total_properties: 0,
        total_units: 0,
        total_tenants: 0,
        monthly_revenue: 0,
        total_revenue: 0,
    }),
    actionItems: () => ({
        inactive_landlords: 0,
        new_signups: 0,
    }),
    landlords: () => [],
    topLandlords: () => [],
});

const { t } = useI18n();
const { formatCurrency, formatDate } = useFormatters();
const { can } = useAuth();

const getOccupancyRate = (occupied, total) => {
    if (!total) return 0;
    return Math.round((occupied / total) * 100);
};

const impersonateLandlord = (landlordId) => {
    if (confirm(t('admin_dashboard.impersonate_confirm'))) {
        router.post(route('admin.impersonate', landlordId));
    }
};
</script>

<template>
    <Head :title="t('admin_dashboard.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('admin_dashboard.header_title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('admin_dashboard.header_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.settings')"
                          class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium text-sm">
                        <Cog6ToothIcon class="w-4 h-4 me-2" />
                        {{ t('admin_dashboard.settings') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 lg:p-8 space-y-6">
            <!-- === SYSTEM HEALTH METRICS === -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <MetricCard
                    :title="t('admin_dashboard.metric_active_landlords')"
                    :value="systemHealth.active_landlords"
                    format="number"
                    :subtitle="t('admin_dashboard.metric_active_landlords_subtitle')"
                    :icon="UserGroupIcon"
                    color="purple"
                    :href="route('admin.landlords')"
                />

                <MetricCard
                    :title="t('admin_dashboard.metric_properties')"
                    :value="systemHealth.total_properties"
                    format="number"
                    :subtitle="t('admin_dashboard.metric_properties_subtitle')"
                    :icon="BuildingOfficeIcon"
                    color="blue"
                />

                <MetricCard
                    :title="t('admin_dashboard.metric_units')"
                    :value="systemHealth.total_units"
                    format="number"
                    :subtitle="t('admin_dashboard.metric_units_subtitle')"
                    :icon="HomeModernIcon"
                    color="emerald"
                />

                <MetricCard
                    :title="t('admin_dashboard.metric_tenants')"
                    :value="systemHealth.total_tenants"
                    format="number"
                    :subtitle="t('admin_dashboard.metric_tenants_subtitle')"
                    :icon="UsersIcon"
                    color="yellow"
                    :href="route('admin.users')"
                />

                <MetricCard
                    :title="t('admin_dashboard.metric_this_month')"
                    :value="systemHealth.monthly_revenue"
                    format="currency"
                    :subtitle="t('admin_dashboard.metric_this_month_subtitle')"
                    :icon="ArrowTrendingUpIcon"
                    color="emerald"
                />

                <MetricCard
                    :title="t('admin_dashboard.metric_total_revenue')"
                    :value="systemHealth.total_revenue"
                    format="currency"
                    :subtitle="t('admin_dashboard.metric_total_revenue_subtitle')"
                    :icon="CurrencyDollarIcon"
                    color="indigo"
                />
            </div>

            <!-- === ACTION ITEMS === -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <ActionItemCard
                    v-if="actionItems.new_signups > 0"
                    urgency="low"
                    :icon="UserPlusIcon"
                    :count="actionItems.new_signups"
                    :title="t('admin_dashboard.action_new_signups_title')"
                    :description="t('admin_dashboard.action_new_signups_description')"
                    :actionLabel="t('admin_dashboard.action_new_signups_label')"
                    :actionHref="route('admin.landlords')"
                />

                <ActionItemCard
                    v-if="actionItems.inactive_landlords > 0"
                    urgency="medium"
                    :icon="ExclamationCircleIcon"
                    :count="actionItems.inactive_landlords"
                    :title="t('admin_dashboard.action_inactive_landlords_title')"
                    :description="t('admin_dashboard.action_inactive_landlords_description')"
                    :actionLabel="t('admin_dashboard.action_inactive_landlords_label')"
                    :actionHref="route('admin.landlords')"
                />

                <!-- Quick Actions Cards -->
                <Link :href="route('admin.landlords')"
                      class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-gray-200 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                            <UserGroupIcon class="w-5 h-5 text-purple-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ t('admin_dashboard.quick_manage_landlords_title') }}</p>
                            <p class="text-xs text-gray-500">{{ t('admin_dashboard.quick_manage_landlords_subtitle') }}</p>
                        </div>
                    </div>
                </Link>

                <Link :href="route('admin.users')"
                      class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-gray-200 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <UsersIcon class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ t('admin_dashboard.quick_all_users_title') }}</p>
                            <p class="text-xs text-gray-500">{{ t('admin_dashboard.quick_all_users_subtitle') }}</p>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- === RECENT LANDLORDS + TOP PERFORMERS === -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Landlords (2 columns) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900">{{ t('admin_dashboard.recent_landlords_title') }}</h3>
                        <Link :href="route('admin.landlords')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                            {{ t('admin_dashboard.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                        </Link>
                    </div>

                    <div v-if="landlords.length === 0" class="p-8 text-center">
                        <UserGroupIcon class="h-12 w-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500">{{ t('admin_dashboard.recent_landlords_empty') }}</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin_dashboard.table_landlord') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin_dashboard.table_portfolio') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin_dashboard.table_occupancy') }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin_dashboard.table_revenue') }}</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('admin_dashboard.table_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="landlord in landlords" :key="landlord.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold">
                                                {{ landlord.name?.charAt(0) || '?' }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ landlord.name }}</p>
                                                <p class="text-xs text-gray-500">{{ landlord.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900">{{ t('admin_dashboard.portfolio_properties', { count: landlord.properties_count }) }}</p>
                                        <p class="text-xs text-gray-500">{{ t('admin_dashboard.portfolio_units', { count: landlord.units_count }) }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                                <div class="bg-green-500 h-2 rounded-full transition-all"
                                                     :style="{ width: getOccupancyRate(landlord.occupied_units, landlord.units_count) + '%' }">
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ getOccupancyRate(landlord.occupied_units, landlord.units_count) }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(landlord.monthly_revenue) }}</p>
                                        <p class="text-xs text-gray-500">{{ t('admin_dashboard.revenue_this_month') }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-end">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="route('admin.landlords.show', landlord.id)"
                                                  class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                                <EyeIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('access-admin')" @click="impersonateLandlord(landlord.id)"
                                                    class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-white hover:bg-indigo-600 border border-gray-200 hover:border-indigo-600 rounded-lg transition">
                                                {{ t('admin_dashboard.login_as') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Performers (1 column) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <TrophyIcon class="w-5 h-5 text-amber-500" />
                        <h3 class="font-bold text-gray-900">{{ t('admin_dashboard.top_performers_title') }}</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">{{ t('admin_dashboard.top_performers_subtitle') }}</p>

                    <div v-if="topLandlords.length === 0" class="text-center py-4">
                        <p class="text-gray-500 text-sm">{{ t('admin_dashboard.top_performers_empty') }}</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="(landlord, index) in topLandlords" :key="landlord.id"
                             class="flex items-center gap-3">
                            <!-- Rank Badge -->
                            <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                                 :class="index === 0 ? 'bg-amber-100 text-amber-700' :
                                         index === 1 ? 'bg-gray-100 text-gray-600' :
                                         index === 2 ? 'bg-orange-100 text-orange-700' :
                                         'bg-gray-50 text-gray-500'">
                                {{ index + 1 }}
                            </div>

                            <!-- Landlord Info -->
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ landlord.name }}</p>
                                <p class="text-xs text-gray-500">{{ t('admin_dashboard.top_performers_properties', { count: landlord.properties_count || 0 }) }}</p>
                            </div>

                            <!-- Revenue -->
                            <div class="text-end">
                                <p class="font-semibold text-gray-900">{{ formatCurrency(landlord.monthly_revenue) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <Link :href="route('admin.landlords')"
                              class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center justify-center">
                            {{ t('admin_dashboard.view_all_landlords') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- === QUICK ACTIONS GRID === -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Link :href="route('admin.landlords')"
                      class="flex items-center gap-4 p-5 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition">
                    <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center">
                        <UserGroupIcon class="w-6 h-6 text-purple-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ t('admin_dashboard.cta_landlords_title') }}</p>
                        <p class="text-sm text-gray-500">{{ t('admin_dashboard.cta_landlords_description') }}</p>
                    </div>
                    <ChevronRightIcon class="w-5 h-5 text-gray-400 ms-auto" />
                </Link>

                <Link :href="route('admin.users')"
                      class="flex items-center gap-4 p-5 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                    <div class="h-12 w-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <UsersIcon class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ t('admin_dashboard.cta_users_title') }}</p>
                        <p class="text-sm text-gray-500">{{ t('admin_dashboard.cta_users_description') }}</p>
                    </div>
                    <ChevronRightIcon class="w-5 h-5 text-gray-400 ms-auto" />
                </Link>

                <Link :href="route('admin.settings')"
                      class="flex items-center gap-4 p-5 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition">
                    <div class="h-12 w-12 rounded-xl bg-green-100 flex items-center justify-center">
                        <Cog6ToothIcon class="w-6 h-6 text-green-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ t('admin_dashboard.cta_settings_title') }}</p>
                        <p class="text-sm text-gray-500">{{ t('admin_dashboard.cta_settings_description') }}</p>
                    </div>
                    <ChevronRightIcon class="w-5 h-5 text-gray-400 ms-auto" />
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
