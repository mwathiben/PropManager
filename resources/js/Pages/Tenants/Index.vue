<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import type { TenantsIndexPageProps } from '@/types/finances';
import {
    MagnifyingGlassIcon,
    UserCircleIcon,
    PhoneIcon,
    EnvelopeIcon,
    HomeIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    XCircleIcon,
    UserPlusIcon,
    UsersIcon,
    ArchiveBoxIcon,
    EyeIcon,
    PaperAirplaneIcon,
    TrashIcon,
    PencilIcon,
    CalendarDaysIcon,
    BanknotesIcon,
    DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<TenantsIndexPageProps>();

const { formatMoney: formatCurrency, formatDate } = useFormatters();
const { can } = useAuth();

const search = ref(props.filters?.search || '');
const currentTab = ref(props.tab || 'active');
let searchTimeout = null;

// Tab definitions
const tabs = [
    { id: 'active', name: 'Active Tenants', icon: UsersIcon, countKey: 'active' },
    { id: 'pending', name: 'Pending Invitations', icon: ClockIcon, countKey: 'pending' },
    { id: 'past', name: 'Past Tenants', icon: ArchiveBoxIcon, countKey: 'past' },
];

// Watch for search changes
watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('tenants.index'), { search: value, tab: currentTab.value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

// Tab change handler
function changeTab(tabId) {
    currentTab.value = tabId;
    router.get(route('tenants.index'), { search: search.value, tab: tabId }, {
        preserveState: true,
        preserveScroll: true,
    });
}

// Helpers
const getActiveLease = (tenant) => {
    return tenant.leases?.find(l => l.is_active) || tenant.leases?.[0];
};

const getLeaseStatus = (tenant) => {
    const lease = getActiveLease(tenant);
    if (!lease) {
        return { label: 'No Lease', color: 'bg-gray-100 text-gray-800' };
    }
    if (lease.is_active) {
        return { label: 'Active', color: 'bg-green-100 text-green-800' };
    }
    return { label: 'Inactive', color: 'bg-yellow-100 text-yellow-800' };
};

const getPaymentStatus = (tenant) => {
    const lease = getActiveLease(tenant);
    if (!lease) {
        return { label: 'N/A', color: 'bg-gray-100 text-gray-800', arrears: 0 };
    }
    const arrears = lease.arrears || 0;
    if (arrears > 0) {
        return { label: 'Arrears', color: 'bg-red-100 text-red-800', arrears };
    }
    return { label: 'Up to date', color: 'bg-green-100 text-green-800', arrears: 0 };
};

const getUnitInfo = (tenant) => {
    const lease = getActiveLease(tenant);
    if (!lease?.unit) return 'No unit assigned';
    const unit = lease.unit;
    const building = unit.building;
    const property = building?.property;
    let info = `Unit ${unit.unit_number}`;
    if (building?.name) info += ` @ ${building.name}`;
    if (property?.name) info += ` (${property.name})`;
    return info;
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Invitation actions
const resendInvitation = (invitationId) => {
    if (confirm('Resend this invitation?')) {
        router.post(route('tenant-invitations.resend', invitationId), {}, { preserveScroll: true });
    }
};

const cancelInvitation = (invitationId) => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
        router.delete(route('tenant-invitations.destroy', invitationId), { preserveScroll: true });
    }
};

const copyInviteLink = (token) => {
    const url = window.location.origin + '/tenant-invite/' + token;
    navigator.clipboard.writeText(url).then(() => {
        alert('Invitation link copied!');
    });
};

// Get last lease info for past tenants
const getLastLeaseInfo = (tenant) => {
    const lease = tenant.leases?.[0];
    if (!lease) return null;
    return {
        unit: lease.unit?.unit_number,
        building: lease.unit?.building?.name,
        endDate: lease.end_date,
    };
};
</script>

<template>
    <Head title="Tenants" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Tenants</h1>
                        <p class="mt-1 text-sm text-gray-500">Manage your tenants and invitations</p>
                    </div>
                    <Link
                        :href="route('tenant-invitations.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <UserPlusIcon class="w-5 h-5" />
                        Invite Tenant
                    </Link>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <UsersIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.activeTenants }}</div>
                                <div class="text-xs text-gray-500">Active Tenants</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <ClockIcon class="w-5 h-5 text-yellow-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.pendingInvitations }}</div>
                                <div class="text-xs text-gray-500">Pending Invites</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <BanknotesIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(stats.totalMonthlyRent) }}</div>
                                <div class="text-xs text-gray-500">Monthly Rent</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.totalArrears) }}</div>
                                <div class="text-xs text-gray-500">Total Arrears</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="changeTab(tab.id)"
                                :class="[
                                    currentTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                ]"
                                class="flex-1 sm:flex-none px-4 sm:px-6 py-4 border-b-2 font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                            >
                                <component :is="tab.icon" class="w-5 h-5" />
                                <span class="hidden sm:inline">{{ tab.name }}</span>
                                <span
                                    v-if="counts[tab.countKey] > 0"
                                    :class="currentTab === tab.id ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-600'"
                                    class="ms-2 py-0.5 px-2 rounded-full text-xs font-semibold"
                                >
                                    {{ counts[tab.countKey] }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Search Bar -->
                    <div class="p-4 border-b border-gray-200">
                        <div class="relative max-w-md">
                            <MagnifyingGlassIcon class="absolute start-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="currentTab === 'pending' ? 'Search by name, email, or phone...' : 'Search tenants...'"
                                class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- TAB 1: ACTIVE TENANTS -->
                    <div v-if="currentTab === 'active'">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Contact</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Unit</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Rent</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="tenant in activeTenants?.data" :key="tenant.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-medium text-sm">
                                                {{ tenant.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div class="ms-3">
                                                <div class="text-sm font-medium text-gray-900">{{ tenant.name }}</div>
                                                <div class="text-xs text-gray-500 md:hidden">{{ tenant.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                        <div class="space-y-1">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <EnvelopeIcon class="h-4 w-4 me-1 text-gray-400" />
                                                {{ tenant.email }}
                                            </div>
                                            <div v-if="tenant.mobile_number" class="flex items-center text-sm text-gray-600">
                                                <PhoneIcon class="h-4 w-4 me-1 text-gray-400" />
                                                {{ tenant.mobile_number }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <div class="flex items-center text-sm text-gray-900">
                                            <HomeIcon class="h-4 w-4 me-1 text-gray-400" />
                                            {{ getUnitInfo(tenant) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="getPaymentStatus(tenant).color"
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center gap-1"
                                        >
                                            <ExclamationTriangleIcon v-if="getPaymentStatus(tenant).arrears > 0" class="h-3 w-3" />
                                            <CheckCircleIcon v-else class="h-3 w-3" />
                                            {{ getPaymentStatus(tenant).label }}
                                        </span>
                                        <div v-if="getPaymentStatus(tenant).arrears > 0" class="text-xs text-red-600 mt-1">
                                            {{ formatCurrency(getPaymentStatus(tenant).arrears) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 hidden md:table-cell">
                                        {{ formatCurrency(getActiveLease(tenant)?.rent_amount) }}
                                        <span class="text-gray-500">/mo</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end">
                                        <Link
                                            :href="route('tenants.show', tenant.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg transition-colors"
                                        >
                                            <EyeIcon class="w-4 h-4" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <EmptyState
                            v-if="!activeTenants?.data?.length"
                            :icon="UsersIcon"
                            title="No active tenants"
                            :description="search ? 'Try a different search term.' : 'Invite tenants to get started.'"
                            :action-label="search ? null : 'Invite Tenant'"
                            :action-href="search ? null : route('tenant-invitations.index')"
                        />

                        <!-- Pagination -->
                        <div v-if="activeTenants?.data?.length && activeTenants.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="text-sm text-gray-700">
                                Page {{ activeTenants.current_page }} of {{ activeTenants.last_page }}
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="goToPage(activeTenants.prev_page_url)"
                                    :disabled="!activeTenants.prev_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    @click="goToPage(activeTenants.next_page_url)"
                                    :disabled="!activeTenants.next_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PENDING INVITATIONS -->
                    <div v-else-if="currentTab === 'pending'">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant Info</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Unit</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Lease Terms</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="invitation in pendingInvitations?.data" :key="invitation.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600">
                                                <ClockIcon class="w-5 h-5" />
                                            </div>
                                            <div class="ms-3">
                                                <div class="text-sm font-medium text-gray-900">{{ invitation.tenant_name || 'Pending' }}</div>
                                                <div class="text-xs text-gray-500">{{ invitation.email }}</div>
                                                <div v-if="invitation.tenant_phone" class="text-xs text-gray-500">{{ invitation.tenant_phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                        <div class="text-sm text-gray-900">
                                            Unit {{ invitation.unit?.unit_number }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ invitation.unit?.building?.name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <div class="text-sm text-gray-900">{{ formatCurrency(invitation.rent_amount) }}/mo</div>
                                        <div class="text-xs text-gray-500">
                                            Deposit: {{ formatCurrency(invitation.deposit_amount) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Start: {{ formatDate(invitation.start_date) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-1">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <ClockIcon class="w-3 h-3 me-1" />
                                                Pending
                                            </span>
                                            <span v-if="invitation.viewed_at" class="text-xs text-green-600">Viewed</span>
                                            <span class="text-xs text-gray-500">
                                                Expires: {{ formatDate(invitation.expires_at) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end space-x-2">
                                        <button
                                            @click="copyInviteLink(invitation.token)"
                                            class="text-gray-600 hover:text-gray-900 p-1"
                                            title="Copy link"
                                        >
                                            <DocumentDuplicateIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            @click="resendInvitation(invitation.id)"
                                            class="text-indigo-600 hover:text-indigo-900 p-1"
                                            title="Resend"
                                        >
                                            <PaperAirplaneIcon class="w-5 h-5" />
                                        </button>
                                        <Link
                                            :href="route('tenant-invitations.index') + '?edit=' + invitation.id"
                                            class="text-gray-600 hover:text-gray-900 p-1"
                                            title="Edit"
                                        >
                                            <PencilIcon class="w-5 h-5" />
                                        </Link>
                                        <button
                                            v-if="can('tenants:manage')"
                                            @click="cancelInvitation(invitation.id)"
                                            class="text-red-600 hover:text-red-900 p-1"
                                            title="Cancel"
                                        >
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <div v-if="!pendingInvitations?.data?.length" class="text-center py-12">
                            <ClockIcon class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No pending invitations</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ search ? 'Try a different search term.' : 'All invitations have been accepted or expired.' }}
                            </p>
                            <div class="mt-6">
                                <Link
                                    :href="route('tenant-invitations.index')"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                >
                                    <UserPlusIcon class="w-5 h-5" />
                                    Invite Tenant
                                </Link>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pendingInvitations?.data?.length && pendingInvitations.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="text-sm text-gray-700">
                                Page {{ pendingInvitations.current_page }} of {{ pendingInvitations.last_page }}
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="goToPage(pendingInvitations.prev_page_url)"
                                    :disabled="!pendingInvitations.prev_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    @click="goToPage(pendingInvitations.next_page_url)"
                                    :disabled="!pendingInvitations.next_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: PAST TENANTS -->
                    <div v-else-if="currentTab === 'past'">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Contact</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Last Unit</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                    <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="tenant in pastTenants?.data" :key="tenant.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-medium text-sm">
                                                {{ tenant.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div class="ms-3">
                                                <div class="text-sm font-medium text-gray-900">{{ tenant.name }}</div>
                                                <div class="text-xs text-gray-500 md:hidden">{{ tenant.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                        <div class="space-y-1">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <EnvelopeIcon class="h-4 w-4 me-1 text-gray-400" />
                                                {{ tenant.email }}
                                            </div>
                                            <div v-if="tenant.mobile_number" class="flex items-center text-sm text-gray-600">
                                                <PhoneIcon class="h-4 w-4 me-1 text-gray-400" />
                                                {{ tenant.mobile_number }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <div v-if="getLastLeaseInfo(tenant)" class="text-sm text-gray-500">
                                            Unit {{ getLastLeaseInfo(tenant).unit }}
                                            <span v-if="getLastLeaseInfo(tenant).building">@ {{ getLastLeaseInfo(tenant).building }}</span>
                                        </div>
                                        <div v-else class="text-sm text-gray-400">—</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <CalendarDaysIcon class="w-4 h-4 me-1" />
                                            {{ formatDate(getLastLeaseInfo(tenant)?.endDate) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end">
                                        <Link
                                            :href="route('tenants.show', tenant.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors"
                                        >
                                            <EyeIcon class="w-4 h-4" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <div v-if="!pastTenants?.data?.length" class="text-center py-12">
                            <ArchiveBoxIcon class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No past tenants</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ search ? 'Try a different search term.' : 'Past tenants will appear here after their lease ends.' }}
                            </p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pastTenants?.data?.length && pastTenants.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="text-sm text-gray-700">
                                Page {{ pastTenants.current_page }} of {{ pastTenants.last_page }}
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="goToPage(pastTenants.prev_page_url)"
                                    :disabled="!pastTenants.prev_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    @click="goToPage(pastTenants.next_page_url)"
                                    :disabled="!pastTenants.next_page_url"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
