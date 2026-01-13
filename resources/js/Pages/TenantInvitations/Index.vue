<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import {
    UserPlusIcon,
    PaperAirplaneIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    TrashIcon,
    PencilIcon,
    DocumentDuplicateIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    XMarkIcon,
    ArrowLeftIcon,
    HomeIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    invitations: Array,
    vacantUnits: Array,
    editInvitation: Object,
    smsConfigured: Boolean,
    whatsappConfigured: Boolean,
});

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(!!props.editInvitation);
const editingInvitation = ref(props.editInvitation || null);

// Create form
const createForm = useForm({
    unit_id: '',
    email: '',
    tenant_name: '',
    tenant_phone: '',
    rent_amount: '',
    service_charge: 0,
    deposit_amount: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    notification_channels: ['email'],
});

// Edit form
const editForm = useForm({
    email: '',
    tenant_name: '',
    tenant_phone: '',
    rent_amount: '',
    service_charge: 0,
    deposit_amount: '',
    start_date: '',
    end_date: '',
    notification_channels: ['email'],
});

// Watch for edit invitation prop changes
watch(() => props.editInvitation, (newVal) => {
    if (newVal) {
        editingInvitation.value = newVal;
        populateEditForm(newVal);
        showEditModal.value = true;
    }
}, { immediate: true });

// Populate edit form with invitation data
const populateEditForm = (invitation) => {
    editForm.email = invitation.email || '';
    editForm.tenant_name = invitation.tenant_name || '';
    editForm.tenant_phone = invitation.tenant_phone || '';
    editForm.rent_amount = invitation.rent_amount || '';
    editForm.service_charge = invitation.service_charge || 0;
    editForm.deposit_amount = invitation.deposit_amount || '';
    editForm.start_date = invitation.start_date || '';
    editForm.end_date = invitation.end_date || '';
    editForm.notification_channels = invitation.notification_channels || ['email'];
};

// Auto-fill rent when unit is selected
watch(() => createForm.unit_id, (unitId) => {
    if (unitId) {
        const unit = props.vacantUnits.find(u => u.id === parseInt(unitId));
        if (unit && unit.target_rent) {
            createForm.rent_amount = unit.target_rent;
            // Default deposit to 1 month rent
            createForm.deposit_amount = unit.target_rent;
        }
    }
});

// Computed for filtering invitations
const statusFilter = ref('all');

const filteredInvitations = computed(() => {
    if (statusFilter.value === 'all') return props.invitations;
    return props.invitations.filter(inv => inv.status === statusFilter.value);
});

const pendingCount = computed(() => props.invitations.filter(i => i.status === 'pending').length);
const acceptedCount = computed(() => props.invitations.filter(i => i.status === 'accepted').length);
const expiredCount = computed(() => props.invitations.filter(i => i.status === 'expired').length);

// Helpers
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
    }).format(amount || 0);
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'pending':
            return { color: 'bg-yellow-100 text-yellow-800', icon: ClockIcon, label: 'Pending' };
        case 'accepted':
            return { color: 'bg-green-100 text-green-800', icon: CheckCircleIcon, label: 'Accepted' };
        case 'expired':
            return { color: 'bg-red-100 text-red-800', icon: XCircleIcon, label: 'Expired' };
        default:
            return { color: 'bg-gray-100 text-gray-800', icon: ClockIcon, label: status };
    }
};

const selectedUnit = computed(() => {
    if (!createForm.unit_id) return null;
    return props.vacantUnits.find(u => u.id === parseInt(createForm.unit_id));
});

const totalMoveIn = computed(() => {
    const rent = parseFloat(createForm.rent_amount) || 0;
    const service = parseFloat(createForm.service_charge) || 0;
    const deposit = parseFloat(createForm.deposit_amount) || 0;
    return rent + service + deposit;
});

const editTotalMoveIn = computed(() => {
    const rent = parseFloat(editForm.rent_amount) || 0;
    const service = parseFloat(editForm.service_charge) || 0;
    const deposit = parseFloat(editForm.deposit_amount) || 0;
    return rent + service + deposit;
});

// Toggle notification channel
const toggleChannel = (form, channel) => {
    const idx = form.notification_channels.indexOf(channel);
    if (idx > -1) {
        if (form.notification_channels.length > 1) {
            form.notification_channels.splice(idx, 1);
        }
    } else {
        form.notification_channels.push(channel);
    }
};

// Actions
const createInvitation = () => {
    createForm.post(route('tenant-invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const updateInvitation = () => {
    if (!editingInvitation.value) return;
    editForm.put(route('tenant-invitations.update', editingInvitation.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
};

const resendInvitation = (invitationId) => {
    if (confirm('Resend this invitation?')) {
        router.post(route('tenant-invitations.resend', invitationId), {}, { preserveScroll: true });
    }
};

const cancelInvitation = (invitationId) => {
    if (confirm('Are you sure you want to cancel this invitation? This cannot be undone.')) {
        router.delete(route('tenant-invitations.destroy', invitationId), { preserveScroll: true });
    }
};

const copyInviteLink = (token) => {
    const url = window.location.origin + '/tenant-invite/' + token;
    navigator.clipboard.writeText(url).then(() => {
        alert('Invitation link copied to clipboard!');
    });
};

const openEditModal = (invitation) => {
    editingInvitation.value = invitation;
    populateEditForm(invitation);
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingInvitation.value = null;
    editForm.reset();
    // Remove edit query param from URL
    if (window.location.search.includes('edit=')) {
        router.get(route('tenant-invitations.index'), {}, { preserveState: true, replace: true });
    }
};
</script>

<template>
    <Head title="Tenant Invitations" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <Link :href="route('tenants.index')" class="text-gray-400 hover:text-gray-600">
                            <ArrowLeftIcon class="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Tenant Invitations</h1>
                            <p class="text-sm text-gray-500">Invite new tenants to your properties</p>
                        </div>
                    </div>
                    <button
                        @click="showCreateModal = true"
                        :disabled="!vacantUnits.length"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <UserPlusIcon class="w-5 h-5" />
                        Send Invitation
                    </button>
                </div>

                <!-- No Vacant Units Warning -->
                <div v-if="!vacantUnits.length" class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <HomeIcon class="w-5 h-5 text-yellow-600 mt-0.5" />
                        <div>
                            <h3 class="text-sm font-medium text-yellow-800">No Vacant Units</h3>
                            <p class="mt-1 text-sm text-yellow-700">
                                All your units are occupied. Free up a unit or add new units to send tenant invitations.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <button
                        @click="statusFilter = 'all'"
                        :class="statusFilter === 'all' ? 'ring-2 ring-indigo-500' : ''"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 text-left hover:shadow-md transition-shadow"
                    >
                        <div class="text-2xl font-bold text-gray-900">{{ invitations.length }}</div>
                        <div class="text-sm text-gray-500">Total Invitations</div>
                    </button>
                    <button
                        @click="statusFilter = 'pending'"
                        :class="statusFilter === 'pending' ? 'ring-2 ring-yellow-500' : ''"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 text-left hover:shadow-md transition-shadow"
                    >
                        <div class="text-2xl font-bold text-yellow-600">{{ pendingCount }}</div>
                        <div class="text-sm text-gray-500">Pending</div>
                    </button>
                    <button
                        @click="statusFilter = 'accepted'"
                        :class="statusFilter === 'accepted' ? 'ring-2 ring-green-500' : ''"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 text-left hover:shadow-md transition-shadow"
                    >
                        <div class="text-2xl font-bold text-green-600">{{ acceptedCount }}</div>
                        <div class="text-sm text-gray-500">Accepted</div>
                    </button>
                </div>

                <!-- Invitations List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lease Terms</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="invitation in filteredInvitations" :key="invitation.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full flex items-center justify-center"
                                                :class="invitation.status === 'accepted' ? 'bg-green-100' : invitation.status === 'pending' ? 'bg-yellow-100' : 'bg-gray-100'">
                                                <component
                                                    :is="getStatusBadge(invitation.status).icon"
                                                    class="w-5 h-5"
                                                    :class="invitation.status === 'accepted' ? 'text-green-600' : invitation.status === 'pending' ? 'text-yellow-600' : 'text-gray-500'"
                                                />
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ invitation.tenant_name || 'Pending Registration' }}</div>
                                                <div class="text-xs text-gray-500">{{ invitation.email }}</div>
                                                <div v-if="invitation.tenant_phone" class="text-xs text-gray-500">{{ invitation.tenant_phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">Unit {{ invitation.unit }}</div>
                                        <div class="text-xs text-gray-500">{{ invitation.building }}</div>
                                        <div class="text-xs text-gray-400">{{ invitation.property }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ formatCurrency(invitation.rent_amount) }}/mo</div>
                                        <div class="text-xs text-gray-500">Deposit: {{ formatCurrency(invitation.deposit_amount) }}</div>
                                        <div class="text-xs text-gray-500">Start: {{ invitation.start_date_display }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="getStatusBadge(invitation.status).color"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                                        >
                                            <component :is="getStatusBadge(invitation.status).icon" class="w-3.5 h-3.5" />
                                            {{ getStatusBadge(invitation.status).label }}
                                        </span>
                                        <div v-if="invitation.status === 'pending'" class="text-xs text-gray-500 mt-1">
                                            Expires: {{ invitation.expires_at }}
                                        </div>
                                        <div v-if="invitation.viewed_at" class="text-xs text-green-600 mt-1">
                                            <EyeIcon class="w-3 h-3 inline" /> Viewed
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <!-- Copy Link -->
                                            <button
                                                v-if="invitation.status === 'pending'"
                                                @click="copyInviteLink(invitation.token)"
                                                class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                                                title="Copy invitation link"
                                            >
                                                <DocumentDuplicateIcon class="w-5 h-5" />
                                            </button>
                                            <!-- Resend -->
                                            <button
                                                v-if="invitation.status === 'pending'"
                                                @click="resendInvitation(invitation.id)"
                                                class="p-2 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg"
                                                title="Resend invitation"
                                            >
                                                <PaperAirplaneIcon class="w-5 h-5" />
                                            </button>
                                            <!-- Edit -->
                                            <button
                                                v-if="invitation.status === 'pending'"
                                                @click="openEditModal(invitation)"
                                                class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg"
                                                title="Edit invitation"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <!-- Cancel -->
                                            <button
                                                v-if="invitation.status === 'pending'"
                                                @click="cancelInvitation(invitation.id)"
                                                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg"
                                                title="Cancel invitation"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!filteredInvitations.length" class="text-center py-12">
                        <UserPlusIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No invitations</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ statusFilter !== 'all' ? 'No invitations match this filter.' : 'Get started by sending a tenant invitation.' }}
                        </p>
                        <div v-if="vacantUnits.length && statusFilter === 'all'" class="mt-6">
                            <button
                                @click="showCreateModal = true"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                            >
                                <UserPlusIcon class="w-5 h-5" />
                                Send Invitation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>

                <div class="relative inline-block w-full max-w-2xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Send Tenant Invitation</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="createInvitation" class="p-6 space-y-6">
                        <!-- Unit Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Unit *</label>
                            <select
                                v-model="createForm.unit_id"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            >
                                <option value="">Choose a vacant unit...</option>
                                <option v-for="unit in vacantUnits" :key="unit.id" :value="unit.id">
                                    Unit {{ unit.unit_number }} - {{ unit.building_name }} ({{ unit.property_name }}) - {{ formatCurrency(unit.target_rent) }}/mo
                                </option>
                            </select>
                            <p v-if="createForm.errors.unit_id" class="mt-1 text-sm text-red-600">{{ createForm.errors.unit_id }}</p>
                        </div>

                        <!-- Tenant Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input
                                    v-model="createForm.email"
                                    type="email"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="tenant@example.com"
                                />
                                <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Name</label>
                                <input
                                    v-model="createForm.tenant_name"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="John Doe"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input
                                    v-model="createForm.tenant_phone"
                                    type="tel"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="+254 712 345 678"
                                />
                                <p v-if="createForm.errors.tenant_phone" class="mt-1 text-sm text-red-600">{{ createForm.errors.tenant_phone }}</p>
                            </div>
                        </div>

                        <!-- Lease Terms -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Lease Terms</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent (KES) *</label>
                                    <input
                                        v-model="createForm.rent_amount"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Charge</label>
                                    <input
                                        v-model="createForm.service_charge"
                                        type="number"
                                        min="0"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit (KES) *</label>
                                    <input
                                        v-model="createForm.deposit_amount"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                                    <input
                                        v-model="createForm.start_date"
                                        type="date"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                                    <input
                                        v-model="createForm.end_date"
                                        type="date"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <!-- Total Move-in Cost -->
                            <div class="mt-4 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-indigo-800">Total Move-in Cost</span>
                                    <span class="text-xl font-bold text-indigo-900">{{ formatCurrency(totalMoveIn) }}</span>
                                </div>
                                <p class="text-xs text-indigo-600 mt-1">First month rent + service charge + deposit</p>
                            </div>
                        </div>

                        <!-- Notification Channels -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Send Invitation Via *</h4>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    @click="toggleChannel(createForm, 'email')"
                                    :class="createForm.notification_channels.includes('email') ? 'bg-indigo-100 border-indigo-500 text-indigo-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors"
                                >
                                    <EnvelopeIcon class="w-5 h-5" />
                                    Email
                                </button>
                                <button
                                    type="button"
                                    @click="toggleChannel(createForm, 'sms')"
                                    :disabled="!smsConfigured"
                                    :class="createForm.notification_channels.includes('sms') ? 'bg-indigo-100 border-indigo-500 text-indigo-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <DevicePhoneMobileIcon class="w-5 h-5" />
                                    SMS
                                    <span v-if="!smsConfigured" class="text-xs text-gray-500">(Not configured)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="toggleChannel(createForm, 'whatsapp')"
                                    :disabled="!whatsappConfigured"
                                    :class="createForm.notification_channels.includes('whatsapp') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    WhatsApp
                                    <span v-if="!whatsappConfigured" class="text-xs text-gray-500">(Not configured)</span>
                                </button>
                            </div>
                            <p v-if="createForm.errors.notification_channels" class="mt-1 text-sm text-red-600">{{ createForm.errors.notification_channels }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <PaperAirplaneIcon class="w-5 h-5" />
                                {{ createForm.processing ? 'Sending...' : 'Send Invitation' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal && editingInvitation" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEditModal"></div>

                <div class="relative inline-block w-full max-w-2xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Edit Invitation</h3>
                            <p class="text-sm text-gray-500">Unit {{ editingInvitation.unit }} - {{ editingInvitation.building }}</p>
                        </div>
                        <button @click="closeEditModal" class="text-gray-400 hover:text-gray-500">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="updateInvitation" class="p-6 space-y-6">
                        <!-- Tenant Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input
                                    v-model="editForm.email"
                                    type="email"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p v-if="editForm.errors.email" class="mt-1 text-sm text-red-600">{{ editForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Name</label>
                                <input
                                    v-model="editForm.tenant_name"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input
                                    v-model="editForm.tenant_phone"
                                    type="tel"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p v-if="editForm.errors.tenant_phone" class="mt-1 text-sm text-red-600">{{ editForm.errors.tenant_phone }}</p>
                            </div>
                        </div>

                        <!-- Lease Terms -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Lease Terms</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent (KES) *</label>
                                    <input
                                        v-model="editForm.rent_amount"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Charge</label>
                                    <input
                                        v-model="editForm.service_charge"
                                        type="number"
                                        min="0"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit (KES) *</label>
                                    <input
                                        v-model="editForm.deposit_amount"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                                    <input
                                        v-model="editForm.start_date"
                                        type="date"
                                        required
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                                    <input
                                        v-model="editForm.end_date"
                                        type="date"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <!-- Total Move-in Cost -->
                            <div class="mt-4 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-indigo-800">Total Move-in Cost</span>
                                    <span class="text-xl font-bold text-indigo-900">{{ formatCurrency(editTotalMoveIn) }}</span>
                                </div>
                                <p class="text-xs text-indigo-600 mt-1">First month rent + service charge + deposit</p>
                            </div>
                        </div>

                        <!-- Notification Channels -->
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Notification Channels</h4>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    @click="toggleChannel(editForm, 'email')"
                                    :class="editForm.notification_channels.includes('email') ? 'bg-indigo-100 border-indigo-500 text-indigo-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors"
                                >
                                    <EnvelopeIcon class="w-5 h-5" />
                                    Email
                                </button>
                                <button
                                    type="button"
                                    @click="toggleChannel(editForm, 'sms')"
                                    :disabled="!smsConfigured"
                                    :class="editForm.notification_channels.includes('sms') ? 'bg-indigo-100 border-indigo-500 text-indigo-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <DevicePhoneMobileIcon class="w-5 h-5" />
                                    SMS
                                </button>
                                <button
                                    type="button"
                                    @click="toggleChannel(editForm, 'whatsapp')"
                                    :disabled="!whatsappConfigured"
                                    :class="editForm.notification_channels.includes('whatsapp') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-gray-50 border-gray-300 text-gray-700'"
                                    class="flex items-center gap-2 px-4 py-2 border rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    WhatsApp
                                </button>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <CheckCircleIcon class="w-5 h-5" />
                                {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
