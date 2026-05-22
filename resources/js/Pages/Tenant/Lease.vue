<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import type { TenantLeasePageProps } from '@/types';
import {
    HomeIcon,
    DocumentTextIcon,
    CalendarIcon,
    CurrencyDollarIcon,
    ArrowTrendingUpIcon,
    ArrowPathIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantLeasePageProps>();

// Use composables
const { formatCurrency, formatDate } = useFormatters();
</script>

<template>
    <Head title="My Lease" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">My Lease Details</h1>
                    <Link :href="route('dashboard')" class="text-indigo-600 hover:text-indigo-700">
                        Back to Dashboard
                    </Link>
                </div>

                <div v-if="!hasLease" class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <p class="text-gray-600">No active lease found.</p>
                </div>

                <template v-else>
                    <!-- Phase-84 RENEWAL-RESPONSE: renewal-pending banner -->
                    <div v-if="activeRenewal" class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <ArrowPathIcon class="h-5 w-5 text-indigo-600" />
                            <p class="text-sm text-indigo-800">{{ $t('tenant_renewal.pending_banner') }}</p>
                        </div>
                        <Link :href="route('tenant.renewals.index')" class="text-sm font-medium text-indigo-700 hover:text-indigo-900 whitespace-nowrap">
                            {{ $t('tenant_renewal.review') }}
                        </Link>
                    </div>

                    <!-- Phase-84 LEASE-VISIBILITY: download generated lease agreement -->
                    <div v-if="leaseAgreementId" class="mb-6">
                        <a :href="route('tenant.documents.download', leaseAgreementId)" class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800">
                            <DocumentArrowDownIcon class="h-4 w-4" /> {{ $t('tenant_renewal.download_agreement') }}
                        </a>
                    </div>

                    <!-- Unit Information -->
                    <div class="bg-white rounded-lg shadow-sm border mb-6">
                        <div class="px-4 py-3 border-b bg-gray-50">
                            <div class="flex items-center">
                                <HomeIcon class="h-5 w-5 text-gray-500 me-2" />
                                <h3 class="font-semibold text-gray-900">Property Information</h3>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Building</p>
                                    <p class="font-medium text-gray-900">{{ building.name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Unit Number</p>
                                    <p class="font-medium text-gray-900">{{ unit.unit_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Floor</p>
                                    <p class="font-medium text-gray-900">{{ unit.floor_number }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lease Terms -->
                    <div class="bg-white rounded-lg shadow-sm border mb-6">
                        <div class="px-4 py-3 border-b bg-gray-50">
                            <div class="flex items-center">
                                <DocumentTextIcon class="h-5 w-5 text-gray-500 me-2" />
                                <h3 class="font-semibold text-gray-900">Lease Terms</h3>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Start Date</p>
                                    <p class="font-medium text-gray-900">{{ formatDate(lease.start_date) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">End Date</p>
                                    <p class="font-medium text-gray-900">{{ lease.end_date ? formatDate(lease.end_date) : 'Open-ended' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Monthly Rent</p>
                                    <p class="font-medium text-gray-900">{{ formatCurrency(lease.rent_amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Security Deposit</p>
                                    <p class="font-medium text-gray-900">{{ formatCurrency(lease.deposit_amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Phase-84 LEASE-VISIBILITY: co-tenants + guarantors (read-only) -->
                    <div v-if="(coTenants && coTenants.length) || (guarantors && guarantors.length)" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div v-if="coTenants && coTenants.length" class="bg-white rounded-lg shadow-sm border">
                            <div class="px-4 py-3 border-b bg-gray-50 flex items-center">
                                <UserGroupIcon class="h-5 w-5 text-gray-500 me-2" />
                                <h3 class="font-semibold text-gray-900">{{ $t('tenant_renewal.co_tenants') }}</h3>
                            </div>
                            <ul class="p-4 divide-y divide-gray-100 text-sm">
                                <li v-for="c in coTenants" :key="c.id" class="py-2 flex justify-between">
                                    <span class="text-gray-900">{{ c.name }}</span>
                                    <span class="text-gray-500">{{ c.relationship || '—' }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="guarantors && guarantors.length" class="bg-white rounded-lg shadow-sm border">
                            <div class="px-4 py-3 border-b bg-gray-50 flex items-center">
                                <ShieldCheckIcon class="h-5 w-5 text-gray-500 me-2" />
                                <h3 class="font-semibold text-gray-900">{{ $t('tenant_renewal.guarantors') }}</h3>
                            </div>
                            <ul class="p-4 divide-y divide-gray-100 text-sm">
                                <li v-for="g in guarantors" :key="g.id" class="py-2 flex justify-between">
                                    <span class="text-gray-900">{{ g.name }}</span>
                                    <span class="text-gray-500">{{ g.relationship || '—' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Rent History -->
                    <div v-if="rentHistory && rentHistory.length > 0" class="bg-white rounded-lg shadow-sm border">
                        <div class="px-4 py-3 border-b bg-gray-50">
                            <div class="flex items-center">
                                <ArrowTrendingUpIcon class="h-5 w-5 text-gray-500 me-2" />
                                <h3 class="font-semibold text-gray-900">Rent History</h3>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Effective Date</th>
                                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Previous Rent</th>
                                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">New Rent</th>
                                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Change</th>
                                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="history in rentHistory" :key="history.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ formatDate(history.effective_date) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ formatCurrency(history.previous_rent) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ formatCurrency(history.new_rent) }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span v-if="history.new_rent > history.previous_rent" class="text-red-600">
                                                +{{ formatCurrency(history.new_rent - history.previous_rent) }}
                                            </span>
                                            <span v-else-if="history.new_rent < history.previous_rent" class="text-green-600">
                                                {{ formatCurrency(history.new_rent - history.previous_rent) }}
                                            </span>
                                            <span v-else class="text-gray-500">No change</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ history.reason || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- No Rent History -->
                    <div v-else class="bg-white rounded-lg shadow-sm border p-4 text-center text-gray-500">
                        <p>No rent adjustments have been made since your lease started.</p>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
