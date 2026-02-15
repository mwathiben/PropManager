<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters, useCurrency } from '@/composables';
import type { TenantShowPageProps } from '@/types/finances';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import UserCircleIcon from '@heroicons/vue/24/outline/UserCircleIcon';
import PhoneIcon from '@heroicons/vue/24/outline/PhoneIcon';
import EnvelopeIcon from '@heroicons/vue/24/outline/EnvelopeIcon';
import IdentificationIcon from '@heroicons/vue/24/outline/IdentificationIcon';
import HomeIcon from '@heroicons/vue/24/outline/HomeIcon';
import CalendarIcon from '@heroicons/vue/24/outline/CalendarIcon';
import BanknotesIcon from '@heroicons/vue/24/outline/BanknotesIcon';
import ExclamationTriangleIcon from '@heroicons/vue/24/outline/ExclamationTriangleIcon';
import CheckCircleIcon from '@heroicons/vue/24/outline/CheckCircleIcon';
import PencilIcon from '@heroicons/vue/24/outline/PencilIcon';
import TrashIcon from '@heroicons/vue/24/outline/TrashIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import ChatBubbleLeftIcon from '@heroicons/vue/24/outline/ChatBubbleLeftIcon';
import ClockIcon from '@heroicons/vue/24/outline/ClockIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import StarIcon from '@heroicons/vue/24/outline/StarIcon';
import UserGroupIcon from '@heroicons/vue/24/outline/UserGroupIcon';
import CurrencyDollarIcon from '@heroicons/vue/24/outline/CurrencyDollarIcon';
import ArrowTrendingUpIcon from '@heroicons/vue/24/outline/ArrowTrendingUpIcon';
import DocumentIcon from '@heroicons/vue/24/outline/DocumentIcon';
import EyeIcon from '@heroicons/vue/24/outline/EyeIcon';
import StarIconSolid from '@heroicons/vue/24/solid/StarIcon';

const props = defineProps<TenantShowPageProps>();

const { formatMoney: formatCurrency, formatDate, formatDateTime } = useFormatters();
const { currencyCode } = useCurrency();

// UI State
const activeSection = ref('overview');
const showEditModal = ref(false);
const showNoteModal = ref(false);
const showContactModal = ref(false);
const showWalletModal = ref(false);
const editingNote = ref(null);
const editingContact = ref(null);

// Forms
const editForm = useForm({
    name: props.tenant.name,
    email: props.tenant.email,
    phone: props.tenant.mobile_number,
    id_number: props.tenant.national_id,
});

const noteForm = useForm({
    content: '',
    is_pinned: false,
});

const contactForm = useForm({
    name: '',
    relationship: '',
    phone: '',
    email: '',
    is_primary: false,
});

const walletForm = useForm({
    type: 'credit',
    amount: '',
    reason: '',
});

// Sections
const sections = [
    { id: 'overview', name: 'Overview', icon: UserCircleIcon },
    { id: 'lease', name: 'Lease Details', icon: DocumentTextIcon },
    { id: 'payments', name: 'Payments', icon: BanknotesIcon },
    { id: 'documents', name: 'Documents', icon: DocumentIcon },
    { id: 'notes', name: 'Notes', icon: ChatBubbleLeftIcon },
    { id: 'contacts', name: 'Emergency Contacts', icon: UserGroupIcon },
    { id: 'activity', name: 'Activity', icon: ClockIcon },
];

// Computed
const pastLeases = computed(() => props.tenant.leases?.filter(l => !l.is_active) ?? []);

// Helpers
const getInitials = (name) => {
    return name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';
};

const getPaymentStatus = computed(() => {
    if (!props.activeLease) return { label: 'No Active Lease', color: 'bg-gray-100 text-gray-800' };
    const arrears = props.activeLease.arrears || 0;
    if (arrears > 0) return { label: 'In Arrears', color: 'bg-red-100 text-red-800', amount: arrears };
    return { label: 'Up to Date', color: 'bg-green-100 text-green-800', amount: 0 };
});

const getLeaseStatus = computed(() => {
    if (!props.activeLease) return { label: 'No Active Lease', color: 'bg-gray-100 text-gray-800' };
    if (props.activeLease.is_active) return { label: 'Active', color: 'bg-green-100 text-green-800' };
    return { label: 'Inactive', color: 'bg-yellow-100 text-yellow-800' };
});

// Submission handlers
const submitEdit = () => {
    editForm.put(route('tenants.update', props.tenant.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const submitNote = () => {
    if (editingNote.value) {
        noteForm.put(route('tenants.notes.update', editingNote.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showNoteModal.value = false;
                editingNote.value = null;
                noteForm.reset();
            },
        });
    } else {
        noteForm.post(route('tenants.notes.store', props.tenant.id), {
            preserveScroll: true,
            onSuccess: () => {
                showNoteModal.value = false;
                noteForm.reset();
            },
        });
    }
};

const editNote = (note) => {
    editingNote.value = note;
    noteForm.content = note.content;
    noteForm.is_pinned = note.is_pinned;
    showNoteModal.value = true;
};

const deleteNote = (noteId) => {
    if (confirm('Delete this note?')) {
        router.delete(route('tenants.notes.destroy', noteId), { preserveScroll: true });
    }
};

const submitContact = () => {
    if (editingContact.value) {
        contactForm.put(route('tenants.emergency-contacts.update', editingContact.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                editingContact.value = null;
                contactForm.reset();
            },
        });
    } else {
        contactForm.post(route('tenants.emergency-contacts.store', props.tenant.id), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                contactForm.reset();
            },
        });
    }
};

const editContact = (contact) => {
    editingContact.value = contact;
    contactForm.name = contact.name;
    contactForm.relationship = contact.relationship;
    contactForm.phone = contact.phone;
    contactForm.email = contact.email;
    contactForm.is_primary = contact.is_primary;
    showContactModal.value = true;
};

const deleteContact = (contactId) => {
    if (confirm('Delete this emergency contact?')) {
        router.delete(route('tenants.emergency-contacts.destroy', contactId), { preserveScroll: true });
    }
};

const openNewNoteModal = () => {
    editingNote.value = null;
    noteForm.reset();
    showNoteModal.value = true;
};

const openNewContactModal = () => {
    editingContact.value = null;
    contactForm.reset();
    showContactModal.value = true;
};

const openWalletModal = () => {
    walletForm.reset();
    walletForm.type = 'credit';
    showWalletModal.value = true;
};

const submitWalletAdjustment = () => {
    if (!props.activeLease) return;
    walletForm.post(route('leases.wallet-adjustment', props.activeLease.id), {
        preserveScroll: true,
        onSuccess: () => {
            showWalletModal.value = false;
            walletForm.reset();
        },
    });
};

// Activity icon mapping
const activityIcons = {
    profile_updated: PencilIcon,
    note_added: ChatBubbleLeftIcon,
    emergency_contact_added: UserGroupIcon,
    payment_received: BanknotesIcon,
    lease_created: DocumentTextIcon,
    lease_terminated: XMarkIcon,
    rent_adjusted: ArrowTrendingUpIcon,
    default: ClockIcon,
};

const getActivityIcon = (action) => {
    return activityIcons[action] || activityIcons.default;
};
</script>

<template>
    <Head :title="`Tenant: ${tenant.name}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Back Button & Header -->
                <div class="mb-6">
                    <Link
                        :href="route('tenants.index')"
                        class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 mb-4"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        Back to Tenants
                    </Link>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="h-16 w-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl">
                                {{ getInitials(tenant.name) }}
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ tenant.name }}</h1>
                                <div class="flex items-center gap-3 mt-1">
                                    <span :class="getLeaseStatus.color" class="px-2 py-0.5 text-xs font-semibold rounded-full">
                                        {{ getLeaseStatus.label }}
                                    </span>
                                    <span :class="getPaymentStatus.color" class="px-2 py-0.5 text-xs font-semibold rounded-full">
                                        {{ getPaymentStatus.label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="showEditModal = true"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            <PencilIcon class="w-4 h-4" />
                            Edit Profile
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Sidebar Navigation -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                            <nav class="p-2">
                                <button
                                    v-for="section in sections"
                                    :key="section.id"
                                    @click="activeSection = section.id"
                                    :class="[
                                        activeSection === section.id
                                            ? 'bg-indigo-50 text-indigo-700'
                                            : 'text-gray-600 hover:bg-gray-50',
                                    ]"
                                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                                >
                                    <component :is="section.icon" class="w-5 h-5" />
                                    {{ section.name }}
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="lg:col-span-3 space-y-6">
                        <!-- OVERVIEW SECTION -->
                        <div v-show="activeSection === 'overview'" class="space-y-6">
                            <!-- Contact Info Card -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <EnvelopeIcon class="w-5 h-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Email</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.email }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <PhoneIcon class="w-5 h-5 text-green-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Phone</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.mobile_number || '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <IdentificationIcon class="w-5 h-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">ID Number</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.national_id || '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                            <CalendarIcon class="w-5 h-5 text-orange-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Tenant Since</div>
                                            <div class="text-sm font-medium text-gray-900">{{ formatDate(tenant.created_at) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                            <HomeIcon class="w-5 h-5 text-indigo-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Unit</div>
                                            <div class="text-sm font-bold text-gray-900">
                                                {{ activeLease?.unit?.unit_number || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <BanknotesIcon class="w-5 h-5 text-green-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Monthly Rent</div>
                                            <div class="text-sm font-bold text-gray-900">
                                                {{ formatCurrency(activeLease?.rent_amount) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                            <CurrencyDollarIcon class="w-5 h-5 text-yellow-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Deposit</div>
                                            <div class="text-sm font-bold text-gray-900">
                                                {{ formatCurrency(activeLease?.deposit_amount) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                    <div class="flex items-center gap-3">
                                        <div :class="getPaymentStatus.amount > 0 ? 'bg-red-100' : 'bg-green-100'" class="w-10 h-10 rounded-lg flex items-center justify-center">
                                            <ExclamationTriangleIcon v-if="getPaymentStatus.amount > 0" class="w-5 h-5 text-red-600" />
                                            <CheckCircleIcon v-else class="w-5 h-5 text-green-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Arrears</div>
                                            <div :class="getPaymentStatus.amount > 0 ? 'text-red-600' : 'text-green-600'" class="text-sm font-bold">
                                                {{ formatCurrency(activeLease?.arrears || 0) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div :class="(activeLease?.wallet_balance || 0) > 0 ? 'bg-emerald-100' : 'bg-gray-100'" class="w-10 h-10 rounded-lg flex items-center justify-center">
                                                <CurrencyDollarIcon :class="(activeLease?.wallet_balance || 0) > 0 ? 'text-emerald-600' : 'text-gray-400'" class="w-5 h-5" />
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Credit Balance</div>
                                                <div :class="(activeLease?.wallet_balance || 0) > 0 ? 'text-emerald-600' : 'text-gray-500'" class="text-sm font-bold">
                                                    {{ formatCurrency(activeLease?.wallet_balance || 0) }}
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            v-if="activeLease"
                                            @click="openWalletModal"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                        >
                                            Adjust
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Primary Emergency Contact -->
                            <div v-if="tenant.emergency_contacts?.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Primary Emergency Contact</h3>
                                <div v-if="tenant.emergency_contacts.find(c => c.is_primary)" class="flex items-start gap-4">
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                        <UserGroupIcon class="w-6 h-6 text-red-600" />
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ tenant.emergency_contacts.find(c => c.is_primary).name }}</div>
                                        <div class="text-sm text-gray-500">{{ tenant.emergency_contacts.find(c => c.is_primary).relationship }}</div>
                                        <div class="text-sm text-gray-600 mt-1">{{ tenant.emergency_contacts.find(c => c.is_primary).phone }}</div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500">No primary contact set</div>
                            </div>
                        </div>

                        <!-- LEASE DETAILS SECTION -->
                        <div v-show="activeSection === 'lease'" class="space-y-6">
                            <div v-if="activeLease" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Lease</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="text-sm text-gray-500">Property / Building / Unit</div>
                                        <div class="text-lg font-medium text-gray-900">
                                            {{ activeLease.unit?.building?.property?.name || 'Property' }} /
                                            {{ activeLease.unit?.building?.name || 'Building' }} /
                                            Unit {{ activeLease.unit?.unit_number }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Lease Period</div>
                                        <div class="text-lg font-medium text-gray-900">
                                            {{ formatDate(activeLease.start_date) }} -
                                            {{ activeLease.end_date ? formatDate(activeLease.end_date) : 'Ongoing' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Monthly Rent</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.rent_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Deposit Paid</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.deposit_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Service Charge</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.service_charge) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Status</div>
                                        <span :class="activeLease.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 text-sm font-medium rounded-full">
                                            {{ activeLease.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Rent History -->
                                <div v-if="activeLease.rent_history?.length" class="mt-6 pt-6 border-t border-gray-200">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Rent History</h4>
                                    <div class="space-y-2">
                                        <div v-for="history in activeLease.rent_history" :key="history.id" class="flex justify-between items-center py-2 border-b border-gray-100">
                                            <div class="text-sm text-gray-600">{{ formatDate(history.effective_date) }}</div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm text-gray-500 line-through">{{ formatCurrency(history.previous_amount) }}</span>
                                                <ArrowTrendingUpIcon class="w-4 h-4 text-gray-400" />
                                                <span class="text-sm font-medium text-gray-900">{{ formatCurrency(history.new_amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                                <DocumentTextIcon class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Lease</h3>
                                <p class="mt-1 text-sm text-gray-500">This tenant does not have an active lease.</p>
                            </div>

                            <!-- Past Leases -->
                            <div v-if="pastLeases.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Past Leases</h3>
                                <div class="space-y-4">
                                    <div v-for="lease in pastLeases" :key="lease.id" class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-medium text-gray-900">Unit {{ lease.unit?.unit_number }}</div>
                                                <div class="text-sm text-gray-500">{{ lease.unit?.building?.name }}</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-gray-500">{{ formatDate(lease.start_date) }} - {{ formatDate(lease.end_date) }}</div>
                                                <div class="text-sm font-medium text-gray-900">{{ formatCurrency(lease.rent_amount) }}/mo</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENTS SECTION -->
                        <div v-show="activeSection === 'payments'" class="space-y-6">
                            <!-- Recent Invoices -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Invoices</h3>
                                <div v-if="invoices?.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="invoice in invoices" :key="invoice.id">
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ invoice.invoice_number }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(invoice.created_at) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ formatCurrency(invoice.total_amount) }}</td>
                                                <td class="px-4 py-3">
                                                    <span :class="{
                                                        'bg-green-100 text-green-800': invoice.status === 'paid',
                                                        'bg-yellow-100 text-yellow-800': invoice.status === 'partial',
                                                        'bg-red-100 text-red-800': invoice.status === 'overdue',
                                                        'bg-gray-100 text-gray-800': ['draft', 'sent'].includes(invoice.status),
                                                    }" class="px-2 py-0.5 text-xs font-medium rounded-full capitalize">
                                                        {{ invoice.status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    No invoices found
                                </div>
                            </div>

                            <!-- Recent Payments -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Payments</h3>
                                <div v-if="payments?.length" class="space-y-3">
                                    <div v-for="payment in payments" :key="payment.id" class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</div>
                                            <div class="text-xs text-gray-500">{{ payment.payment_method }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-gray-500">{{ formatDate(payment.created_at) }}</div>
                                            <div class="text-xs text-gray-400">{{ payment.reference }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    No payments recorded
                                </div>
                            </div>
                        </div>

                        <!-- DOCUMENTS SECTION -->
                        <div v-show="activeSection === 'documents'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Documents</h3>
                                    <span class="text-sm text-gray-500">{{ documents?.length || 0 }} files</span>
                                </div>

                                <div v-if="documents?.length" class="space-y-3">
                                    <div v-for="doc in documents" :key="doc.id" class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                <DocumentIcon class="w-5 h-5 text-indigo-600" />
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ doc.original_filename }}</div>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <span class="px-2 py-0.5 bg-gray-200 rounded capitalize">{{ doc.document_type?.replace('_', ' ') || 'Other' }}</span>
                                                    <span>{{ formatDate(doc.created_at) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a
                                                :href="route('documents.view', doc.id)"
                                                target="_blank"
                                                class="p-2 text-gray-400 hover:text-indigo-600"
                                                title="View"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </a>
                                            <a
                                                :href="route('documents.download', doc.id)"
                                                class="p-2 text-gray-400 hover:text-green-600"
                                                title="Download"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <DocumentIcon class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                    No documents uploaded
                                </div>
                            </div>
                        </div>

                        <!-- NOTES SECTION -->
                        <div v-show="activeSection === 'notes'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Private Notes</h3>
                                    <button
                                        @click="openNewNoteModal"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        Add Note
                                    </button>
                                </div>

                                <div v-if="tenant.tenant_notes?.length" class="space-y-4">
                                    <!-- Pinned notes first -->
                                    <div v-for="note in tenant.tenant_notes.sort((a, b) => b.is_pinned - a.is_pinned)" :key="note.id" :class="note.is_pinned ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200'" class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-2">
                                                <StarIconSolid v-if="note.is_pinned" class="w-4 h-4 text-yellow-500" />
                                                <span class="text-xs text-gray-500">
                                                    {{ note.author?.name || 'Unknown' }} - {{ formatDateTime(note.created_at) }}
                                                </span>
                                            </div>
                                            <div class="flex gap-1">
                                                <button @click="editNote(note)" class="p-1 text-gray-400 hover:text-gray-600">
                                                    <PencilIcon class="w-4 h-4" />
                                                </button>
                                                <button @click="deleteNote(note.id)" class="p-1 text-gray-400 hover:text-red-600">
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ note.content }}</p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <ChatBubbleLeftIcon class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                    No notes yet. Add your first note about this tenant.
                                </div>
                            </div>
                        </div>

                        <!-- EMERGENCY CONTACTS SECTION -->
                        <div v-show="activeSection === 'contacts'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Emergency Contacts</h3>
                                    <button
                                        @click="openNewContactModal"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        Add Contact
                                    </button>
                                </div>

                                <div v-if="tenant.emergency_contacts?.length" class="space-y-4">
                                    <div v-for="contact in tenant.emergency_contacts" :key="contact.id" :class="contact.is_primary ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200'" class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <UserGroupIcon class="w-5 h-5 text-gray-500" />
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-900">{{ contact.name }}</span>
                                                        <span v-if="contact.is_primary" class="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-800 rounded-full">Primary</span>
                                                    </div>
                                                    <div class="text-sm text-gray-500">{{ contact.relationship }}</div>
                                                </div>
                                            </div>
                                            <div class="flex gap-1">
                                                <button @click="editContact(contact)" class="p-1 text-gray-400 hover:text-gray-600">
                                                    <PencilIcon class="w-4 h-4" />
                                                </button>
                                                <button @click="deleteContact(contact.id)" class="p-1 text-gray-400 hover:text-red-600">
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <PhoneIcon class="w-4 h-4 text-gray-400" />
                                                <span>{{ contact.phone }}</span>
                                            </div>
                                            <div v-if="contact.email" class="flex items-center gap-2">
                                                <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                                                <span>{{ contact.email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <UserGroupIcon class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                    No emergency contacts. Add one for this tenant.
                                </div>
                            </div>
                        </div>

                        <!-- ACTIVITY SECTION -->
                        <div v-show="activeSection === 'activity'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity Timeline</h3>

                                <div v-if="tenant.activities?.length" class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        <li v-for="(activity, idx) in tenant.activities" :key="activity.id">
                                            <div class="relative pb-8">
                                                <span v-if="idx !== tenant.activities.length - 1" class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center ring-8 ring-white">
                                                            <component :is="getActivityIcon(activity.action)" class="h-4 w-4 text-gray-500" />
                                                        </span>
                                                    </div>
                                                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                        <div>
                                                            <p class="text-sm text-gray-700">{{ activity.description }}</p>
                                                            <p class="text-xs text-gray-400">by {{ activity.performer?.name || 'System' }}</p>
                                                        </div>
                                                        <div class="whitespace-nowrap text-right text-xs text-gray-500">
                                                            {{ formatDateTime(activity.created_at) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <ClockIcon class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                    No activity recorded yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDIT TENANT MODAL -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showEditModal = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Edit Tenant Profile</h3>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="editForm.name" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="editForm.email" type="email" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input v-model="editForm.phone" type="tel" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                            <input v-model="editForm.id_number" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- NOTE MODAL -->
        <div v-if="showNoteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showNoteModal = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingNote ? 'Edit Note' : 'Add Note' }}</h3>
                        <button @click="showNoteModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitNote" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                            <textarea v-model="noteForm.content" rows="4" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Write your note here..."></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="noteForm.is_pinned" type="checkbox" id="is_pinned" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="is_pinned" class="text-sm text-gray-700">Pin this note</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showNoteModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="noteForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingNote ? 'Save' : 'Add Note' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EMERGENCY CONTACT MODAL -->
        <div v-if="showContactModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showContactModal = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingContact ? 'Edit Contact' : 'Add Emergency Contact' }}</h3>
                        <button @click="showContactModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitContact" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="contactForm.name" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="John Doe" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                            <input v-model="contactForm.relationship" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Spouse, Parent, Sibling, etc." />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input v-model="contactForm.phone" type="tel" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="+254 712 345 678" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                            <input v-model="contactForm.email" type="email" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="contact@example.com" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="contactForm.is_primary" type="checkbox" id="is_primary" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="is_primary" class="text-sm text-gray-700">Set as primary contact</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showContactModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="contactForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingContact ? 'Save' : 'Add Contact' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- WALLET ADJUSTMENT MODAL -->
        <div v-if="showWalletModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showWalletModal = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Adjust Wallet Balance</h3>
                        <button @click="showWalletModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1">Current Balance</div>
                        <div :class="(activeLease?.wallet_balance || 0) > 0 ? 'text-emerald-600' : 'text-gray-500'" class="text-lg font-bold">
                            {{ formatCurrency(activeLease?.wallet_balance || 0) }}
                        </div>
                    </div>

                    <form @submit.prevent="submitWalletAdjustment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type</label>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="walletForm.type = 'credit'"
                                    :class="walletForm.type === 'credit' ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                    class="flex-1 px-4 py-2 text-sm font-medium border rounded-lg transition-colors"
                                >
                                    + Credit (Add)
                                </button>
                                <button
                                    type="button"
                                    @click="walletForm.type = 'debit'"
                                    :class="walletForm.type === 'debit' ? 'bg-red-100 text-red-700 border-red-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                    class="flex-1 px-4 py-2 text-sm font-medium border rounded-lg transition-colors"
                                >
                                    − Debit (Remove)
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount ({{ currencyCode }})</label>
                            <input
                                v-model="walletForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Enter amount"
                            />
                            <p v-if="walletForm.errors.amount" class="mt-1 text-sm text-red-600">{{ walletForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                            <input
                                v-model="walletForm.reason"
                                type="text"
                                required
                                maxlength="255"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g., Refund for overcharge, Goodwill credit"
                            />
                            <p v-if="walletForm.errors.reason" class="mt-1 text-sm text-red-600">{{ walletForm.errors.reason }}</p>
                        </div>

                        <div v-if="walletForm.type === 'debit' && parseFloat(walletForm.amount) > (activeLease?.wallet_balance || 0)" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-700">
                                <strong>Warning:</strong> Debit amount exceeds current balance. This will result in a negative balance.
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showWalletModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                            <button
                                type="submit"
                                :disabled="walletForm.processing"
                                :class="walletForm.type === 'credit' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                                class="px-4 py-2 text-white rounded-lg disabled:opacity-50"
                            >
                                {{ walletForm.type === 'credit' ? 'Add Credit' : 'Remove Credit' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
