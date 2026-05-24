<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
import InitiateThreadDialog from '@/Components/Inbox/InitiateThreadDialog.vue';
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
import IconButton from '@/Components/IconButton.vue';

const props = defineProps<TenantShowPageProps>();

const { t } = useI18n();
const { formatMoney: formatCurrency, formatDate, formatDateTime } = useFormatters();
const { currencyCode } = useCurrency();

// UI State
const activeSection = ref('overview');
const showEditModal = ref(false);
const showNoteModal = ref(false);
const showContactModal = ref(false);
const showWalletModal = ref(false);
const showNoticeModal = ref(false);
const editingNote = ref(null);
const editingContact = ref(null);

// Phase-21 DEFER-AUTHZ-3: server-resolved per-record gates. Each computed
// mirrors a TenantPolicy method outcome from props.tenant.abilities so the
// UI never advertises an action the policy will deny.
const canViewTenant = computed(() => props.tenant?.abilities?.view ?? false);
const canEditTenant = computed(() => props.tenant?.abilities?.update ?? false);

// Phase-64 INBOX-MOUNT-1: ref to the InitiateThreadDialog slide-over.
const messageDialog = ref<InstanceType<typeof InitiateThreadDialog> | null>(null);
const canViewLedger = computed(() => props.tenant?.abilities?.viewLedger ?? false);
const canRestoreTenant = computed(() => props.tenant?.abilities?.restore ?? false);

// Forms
const editForm = useForm({
    name: props.tenant.name,
    email: props.tenant.email,
    phone: props.tenant.mobile_number,
    id_number: props.tenant.national_id,
    // Phase-21 DEFER-DPA-1: Kenya DPA Article 8 / Section 33 — minor data.
    dob: props.tenant.dob || '',
    parental_consent_artefact_url: props.tenant.parental_consent_artefact_url || '',
    parental_consent_provided_at: props.tenant.parental_consent_provided_at || '',
});

// Phase-21 DEFER-DPA-1: reactive minor detection. When the operator
// enters a dob that resolves to under-18, the parental consent fields
// become visually prominent + the artefact URL becomes required. Mirrors
// KenyaDpaService::isMinor — UX preview of the server-side validation.
const isMinorTenant = computed(() => {
    if (!editForm.dob) {
        return false;
    }
    const dob = new Date(editForm.dob);
    if (isNaN(dob.getTime())) {
        return false;
    }
    const today = new Date();
    const age = today.getFullYear() - dob.getFullYear()
        - (today < new Date(today.getFullYear(), dob.getMonth(), dob.getDate()) ? 1 : 0);
    return age < 18;
});

const hasParentalConsent = computed(() =>
    Boolean(editForm.parental_consent_artefact_url && editForm.parental_consent_provided_at),
);

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

// Phase-82 NOTICE-GEN-2: generate a notice PDF stored as a Document on the lease.
const noticeForm = useForm({
    notice_type: 'rent_increase',
    reason: '',
    effective_date: '',
});

// Sections
const sections = computed(() => [
    { id: 'overview', name: t('tenants.show.sections.overview'), icon: UserCircleIcon },
    { id: 'lease', name: t('tenants.show.sections.lease'), icon: DocumentTextIcon },
    { id: 'payments', name: t('tenants.show.sections.payments'), icon: BanknotesIcon },
    { id: 'documents', name: t('tenants.show.sections.documents'), icon: DocumentIcon },
    { id: 'notes', name: t('tenants.show.sections.notes'), icon: ChatBubbleLeftIcon },
    { id: 'contacts', name: t('tenants.show.sections.contacts'), icon: UserGroupIcon },
    { id: 'activity', name: t('tenants.show.sections.activity'), icon: ClockIcon },
]);

// Computed
const pastLeases = computed(() => props.tenant.leases?.filter(l => !l.is_active) ?? []);

// Helpers
const getInitials = (name) => {
    return name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';
};

const getPaymentStatus = computed(() => {
    if (!props.activeLease) return { label: t('tenants.show.status.no_active_lease'), color: 'bg-gray-100 text-gray-800' };
    const arrears = props.activeLease.arrears || 0;
    if (arrears > 0) return { label: t('tenants.show.status.in_arrears'), color: 'bg-red-100 text-red-800', amount: arrears };
    return { label: t('tenants.show.status.up_to_date'), color: 'bg-green-100 text-green-800', amount: 0 };
});

const getLeaseStatus = computed(() => {
    if (!props.activeLease) return { label: t('tenants.show.status.no_active_lease'), color: 'bg-gray-100 text-gray-800' };
    if (props.activeLease.is_active) return { label: t('tenants.show.status.active'), color: 'bg-green-100 text-green-800' };
    return { label: t('tenants.show.status.inactive'), color: 'bg-yellow-100 text-yellow-800' };
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
    if (confirm(t('tenants.show.confirm.delete_note'))) {
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
    if (confirm(t('tenants.show.confirm.delete_contact'))) {
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

const openNoticeModal = () => {
    noticeForm.reset();
    showNoticeModal.value = true;
};

const submitNotice = () => {
    if (!props.activeLease) return;
    noticeForm.post(route('documents.generate-notice', props.activeLease.id), {
        preserveScroll: true,
        onSuccess: () => {
            showNoticeModal.value = false;
            noticeForm.reset();
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
    <Head :title="t('tenants.show.head_title', { name: tenant.name })" />

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
                        {{ t('tenants.show.back_to_tenants') }}
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
                        <div class="flex items-center gap-2">
                            <button
                                v-if="canEditTenant"
                                @click="messageDialog?.open()"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                data-testid="tenant-message-cta"
                            >
                                <ChatBubbleLeftIcon class="w-4 h-4" />
                                {{ t('tenants.show.message') }}
                            </button>
                            <Link
                                v-if="activeLease"
                                :href="route('leases.show', activeLease.id)"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                data-testid="view-lease-cta"
                            >
                                <DocumentTextIcon class="w-4 h-4" />
                                {{ $t('lease.lifecycle.view') }}
                            </Link>
                            <button
                                v-if="canEditTenant && activeLease"
                                @click="openNoticeModal"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                data-testid="generate-notice-cta"
                            >
                                <DocumentTextIcon class="w-4 h-4" />
                                {{ $t('document.notice.generate') }}
                            </button>
                            <button
                                v-if="canEditTenant"
                                @click="showEditModal = true"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                <PencilIcon class="w-4 h-4" />
                                {{ t('tenants.show.edit_profile') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Phase-64 INBOX-MOUNT-1: thread initiation modal -->
                <InitiateThreadDialog
                    ref="messageDialog"
                    :tenant-id="tenant.id"
                    :tenant-name="tenant.name"
                />

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
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.contact_info.title') }}</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <EnvelopeIcon class="w-5 h-5 text-blue-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.contact_info.email') }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.email }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <PhoneIcon class="w-5 h-5 text-green-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.contact_info.phone') }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.mobile_number || '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <IdentificationIcon class="w-5 h-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.contact_info.id_number') }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ tenant.national_id || '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                            <CalendarIcon class="w-5 h-5 text-orange-600" />
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.contact_info.tenant_since') }}</div>
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
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.stats.unit') }}</div>
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
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.stats.monthly_rent') }}</div>
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
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.stats.deposit') }}</div>
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
                                            <div class="text-xs text-gray-500">{{ t('tenants.show.stats.arrears') }}</div>
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
                                                <div class="text-xs text-gray-500">{{ t('tenants.show.stats.credit_balance') }}</div>
                                                <div :class="(activeLease?.wallet_balance || 0) > 0 ? 'text-emerald-600' : 'text-gray-500'" class="text-sm font-bold">
                                                    {{ formatCurrency(activeLease?.wallet_balance || 0) }}
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            v-if="activeLease && canEditTenant"
                                            @click="openWalletModal"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                        >
                                            {{ t('tenants.show.stats.adjust') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Primary Emergency Contact -->
                            <div v-if="tenant.emergency_contacts?.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.primary_contact.title') }}</h3>
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
                                <div v-else class="text-sm text-gray-500">{{ t('tenants.show.primary_contact.none') }}</div>
                            </div>
                        </div>

                        <!-- LEASE DETAILS SECTION -->
                        <div v-show="activeSection === 'lease'" class="space-y-6">
                            <div v-if="activeLease" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.lease.current_title') }}</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.property_building_unit') }}</div>
                                        <div class="text-lg font-medium text-gray-900">
                                            {{ activeLease.unit?.building?.property?.name || t('tenants.show.lease.property_fallback') }} /
                                            {{ activeLease.unit?.building?.name || t('tenants.show.lease.building_fallback') }} /
                                            {{ t('tenants.show.lease.unit_prefix') }} {{ activeLease.unit?.unit_number }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.lease_period') }}</div>
                                        <div class="text-lg font-medium text-gray-900">
                                            {{ formatDate(activeLease.start_date) }} -
                                            {{ activeLease.end_date ? formatDate(activeLease.end_date) : t('tenants.show.lease.ongoing') }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.monthly_rent') }}</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.rent_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.deposit_paid') }}</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.deposit_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.service_charge') }}</div>
                                        <div class="text-lg font-medium text-gray-900">{{ formatCurrency(activeLease.service_charge) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">{{ t('tenants.show.lease.status_label') }}</div>
                                        <span :class="activeLease.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 text-sm font-medium rounded-full">
                                            {{ activeLease.is_active ? t('tenants.show.status.active') : t('tenants.show.status.inactive') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Rent History -->
                                <div v-if="activeLease.rent_history?.length" class="mt-6 pt-6 border-t border-gray-200">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">{{ t('tenants.show.lease.rent_history') }}</h4>
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
                                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('tenants.show.lease.no_active_title') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ t('tenants.show.lease.no_active_body') }}</p>
                            </div>

                            <!-- Past Leases -->
                            <div v-if="pastLeases.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.lease.past_leases') }}</h3>
                                <div class="space-y-4">
                                    <div v-for="lease in pastLeases" :key="lease.id" class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ t('tenants.show.lease.unit_prefix') }} {{ lease.unit?.unit_number }}</div>
                                                <div class="text-sm text-gray-500">{{ lease.unit?.building?.name }}</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-sm text-gray-500">{{ formatDate(lease.start_date) }} - {{ formatDate(lease.end_date) }}</div>
                                                <div class="text-sm font-medium text-gray-900">{{ formatCurrency(lease.rent_amount) }}{{ t('tenants.show.lease.per_month_suffix') }}</div>
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
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.payments.recent_invoices') }}</h3>
                                <div v-if="invoices?.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ t('tenants.show.payments.invoice_number') }}</th>
                                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ t('tenants.show.payments.date') }}</th>
                                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ t('tenants.show.payments.amount') }}</th>
                                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ t('tenants.show.payments.status') }}</th>
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
                                    {{ t('tenants.show.payments.no_invoices') }}
                                </div>
                            </div>

                            <!-- Recent Payments -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.payments.recent_payments') }}</h3>
                                <div v-if="payments?.length" class="space-y-3">
                                    <div v-for="payment in payments" :key="payment.id" class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</div>
                                            <div class="text-xs text-gray-500">{{ payment.payment_method }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-sm text-gray-500">{{ formatDate(payment.created_at) }}</div>
                                            <div class="text-xs text-gray-400">{{ payment.reference }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    {{ t('tenants.show.payments.no_payments') }}
                                </div>
                            </div>
                        </div>

                        <!-- DOCUMENTS SECTION -->
                        <div v-show="activeSection === 'documents'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ t('tenants.show.documents.title') }}</h3>
                                    <span class="text-sm text-gray-500">{{ t('tenants.show.documents.files_count', { count: documents?.length || 0 }) }}</span>
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
                                                    <span class="px-2 py-0.5 bg-gray-200 rounded capitalize">{{ doc.document_type?.replace('_', ' ') || t('tenants.show.documents.type_fallback') }}</span>
                                                    <span>{{ formatDate(doc.created_at) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a
                                                :href="route('documents.view', doc.id)"
                                                target="_blank"
                                                class="p-2 text-gray-400 hover:text-indigo-600"
                                                :title="t('tenants.show.documents.view')"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </a>
                                            <a
                                                :href="route('documents.download', doc.id)"
                                                class="p-2 text-gray-400 hover:text-green-600"
                                                :title="t('tenants.show.documents.download')"
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
                                    {{ t('tenants.show.documents.none') }}
                                </div>
                            </div>
                        </div>

                        <!-- NOTES SECTION -->
                        <div v-show="activeSection === 'notes'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ t('tenants.show.notes.title') }}</h3>
                                    <button
                                        v-if="canEditTenant"
                                        @click="openNewNoteModal"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        {{ t('tenants.show.notes.add') }}
                                    </button>
                                </div>

                                <div v-if="tenant.tenant_notes?.length" class="space-y-4">
                                    <!-- Pinned notes first -->
                                    <div v-for="note in tenant.tenant_notes.sort((a, b) => b.is_pinned - a.is_pinned)" :key="note.id" :class="note.is_pinned ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200'" class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-2">
                                                <StarIconSolid v-if="note.is_pinned" class="w-4 h-4 text-yellow-500" />
                                                <span class="text-xs text-gray-500">
                                                    {{ note.author?.name || t('tenants.show.notes.author_unknown') }} - {{ formatDateTime(note.created_at) }}
                                                </span>
                                            </div>
                                            <div class="flex gap-1">
                                                <IconButton v-if="canEditTenant" :icon="PencilIcon" size="sm" :aria-label="t('tenants.show.notes.edit_aria')" @click="editNote(note)" />
                                                <IconButton v-if="canEditTenant" :icon="TrashIcon" size="sm" tone="danger" :aria-label="t('tenants.show.notes.delete_aria')" @click="deleteNote(note.id)" />
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ note.content }}</p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <ChatBubbleLeftIcon class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                    {{ t('tenants.show.notes.none') }}
                                </div>
                            </div>
                        </div>

                        <!-- EMERGENCY CONTACTS SECTION -->
                        <div v-show="activeSection === 'contacts'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ t('tenants.show.contacts.title') }}</h3>
                                    <button
                                        v-if="canEditTenant"
                                        @click="openNewContactModal"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        {{ t('tenants.show.contacts.add') }}
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
                                                        <span v-if="contact.is_primary" class="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-800 rounded-full">{{ t('tenants.show.contacts.primary_badge') }}</span>
                                                    </div>
                                                    <div class="text-sm text-gray-500">{{ contact.relationship }}</div>
                                                </div>
                                            </div>
                                            <div class="flex gap-1">
                                                <IconButton v-if="canEditTenant" :icon="PencilIcon" size="sm" :aria-label="t('tenants.show.contacts.edit_aria')" @click="editContact(contact)" />
                                                <IconButton v-if="canEditTenant" :icon="TrashIcon" size="sm" tone="danger" :aria-label="t('tenants.show.contacts.delete_aria')" @click="deleteContact(contact.id)" />
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
                                    {{ t('tenants.show.contacts.none') }}
                                </div>
                            </div>
                        </div>

                        <!-- ACTIVITY SECTION -->
                        <div v-show="activeSection === 'activity'" class="space-y-6">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('tenants.show.activity.title') }}</h3>

                                <div v-if="tenant.activities?.length" class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        <li v-for="(activity, idx) in tenant.activities" :key="activity.id">
                                            <div class="relative pb-8">
                                                <span v-if="idx !== tenant.activities.length - 1" class="absolute start-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center ring-8 ring-white">
                                                            <component :is="getActivityIcon(activity.action)" class="h-4 w-4 text-gray-500" />
                                                        </span>
                                                    </div>
                                                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                        <div>
                                                            <p class="text-sm text-gray-700">{{ activity.description }}</p>
                                                            <p class="text-xs text-gray-400">{{ t('tenants.show.activity.by', { name: activity.performer?.name || t('tenants.show.activity.system') }) }}</p>
                                                        </div>
                                                        <div class="whitespace-nowrap text-end text-xs text-gray-500">
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
                                    {{ t('tenants.show.activity.none') }}
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
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('tenants.show.edit_modal.title') }}</h3>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.edit_modal.name') }}</label>
                            <input v-model="editForm.name" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.edit_modal.email') }}</label>
                            <input v-model="editForm.email" type="email" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.edit_modal.phone') }}</label>
                            <input v-model="editForm.phone" type="tel" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.edit_modal.id_number') }}</label>
                            <input v-model="editForm.id_number" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>

                        <!-- Phase-21 DEFER-DPA-1: Kenya DPA Article 8 / Section 33 children's data. -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('tenants.show.edit_modal.dob') }}
                                <span class="text-gray-400 text-xs">{{ t('tenants.show.edit_modal.dob_hint') }}</span>
                            </label>
                            <input
                                v-model="editForm.dob"
                                type="date"
                                :max="new Date().toISOString().split('T')[0]"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="editForm.errors.dob" class="mt-1 text-sm text-red-600">{{ editForm.errors.dob }}</p>
                        </div>

                        <!-- Reactive minor-consent block — reveals when DOB resolves to under-18. -->
                        <div
                            v-if="isMinorTenant"
                            class="rounded-lg border-2 border-amber-300 bg-amber-50 p-4 space-y-3"
                        >
                            <div class="flex items-start gap-2">
                                <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-semibold text-amber-900">{{ t('tenants.show.edit_modal.minor_title') }}</p>
                                    <p class="text-xs text-amber-800 mt-0.5">
                                        {{ t('tenants.show.edit_modal.minor_body') }}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ t('tenants.show.edit_modal.consent_url') }}
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="editForm.parental_consent_artefact_url"
                                    type="url"
                                    :placeholder="t('tenants.show.edit_modal.consent_url_placeholder')"
                                    :required="isMinorTenant"
                                    class="w-full border-amber-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"
                                />
                                <p v-if="editForm.errors.parental_consent_artefact_url" class="mt-1 text-sm text-red-600">
                                    {{ editForm.errors.parental_consent_artefact_url }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.edit_modal.consent_at') }}</label>
                                <input
                                    v-model="editForm.parental_consent_provided_at"
                                    type="datetime-local"
                                    class="w-full border-amber-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"
                                />
                                <p v-if="editForm.errors.parental_consent_provided_at" class="mt-1 text-sm text-red-600">
                                    {{ editForm.errors.parental_consent_provided_at }}
                                </p>
                            </div>
                            <p v-if="!hasParentalConsent" class="text-xs text-amber-700">
                                {{ t('tenants.show.edit_modal.consent_required_note') }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">{{ t('tenants.show.edit_modal.cancel') }}</button>
                            <button
                                type="submit"
                                :disabled="editForm.processing || (isMinorTenant && !hasParentalConsent)"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ t('tenants.show.edit_modal.save') }}
                            </button>
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
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingNote ? t('tenants.show.note_modal.edit_title') : t('tenants.show.note_modal.add_title') }}</h3>
                        <button @click="showNoteModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitNote" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.note_modal.label') }}</label>
                            <textarea v-model="noteForm.content" rows="4" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" :placeholder="t('tenants.show.note_modal.placeholder')"></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="noteForm.is_pinned" type="checkbox" id="is_pinned" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="is_pinned" class="text-sm text-gray-700">{{ t('tenants.show.note_modal.pin') }}</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showNoteModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">{{ t('tenants.show.note_modal.cancel') }}</button>
                            <button type="submit" :disabled="noteForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingNote ? t('tenants.show.note_modal.save') : t('tenants.show.note_modal.add') }}
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
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingContact ? t('tenants.show.contact_modal.edit_title') : t('tenants.show.contact_modal.add_title') }}</h3>
                        <button @click="showContactModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitContact" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.contact_modal.name') }}</label>
                            <input v-model="contactForm.name" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" :placeholder="t('tenants.show.contact_modal.name_placeholder')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.contact_modal.relationship') }}</label>
                            <input v-model="contactForm.relationship" type="text" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" :placeholder="t('tenants.show.contact_modal.relationship_placeholder')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.contact_modal.phone') }}</label>
                            <input v-model="contactForm.phone" type="tel" required class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" :placeholder="t('tenants.show.contact_modal.phone_placeholder')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.contact_modal.email') }}</label>
                            <input v-model="contactForm.email" type="email" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" :placeholder="t('tenants.show.contact_modal.email_placeholder')" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="contactForm.is_primary" type="checkbox" id="is_primary" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <label for="is_primary" class="text-sm text-gray-700">{{ t('tenants.show.contact_modal.set_primary') }}</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showContactModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">{{ t('tenants.show.contact_modal.cancel') }}</button>
                            <button type="submit" :disabled="contactForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingContact ? t('tenants.show.contact_modal.save') : t('tenants.show.contact_modal.add') }}
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
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('tenants.show.wallet_modal.title') }}</h3>
                        <button @click="showWalletModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1">{{ t('tenants.show.wallet_modal.current_balance') }}</div>
                        <div :class="(activeLease?.wallet_balance || 0) > 0 ? 'text-emerald-600' : 'text-gray-500'" class="text-lg font-bold">
                            {{ formatCurrency(activeLease?.wallet_balance || 0) }}
                        </div>
                    </div>

                    <form @submit.prevent="submitWalletAdjustment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('tenants.show.wallet_modal.adjustment_type') }}</label>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="walletForm.type = 'credit'"
                                    :class="walletForm.type === 'credit' ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                    class="flex-1 px-4 py-2 text-sm font-medium border rounded-lg transition-colors"
                                >
                                    {{ t('tenants.show.wallet_modal.credit') }}
                                </button>
                                <button
                                    type="button"
                                    @click="walletForm.type = 'debit'"
                                    :class="walletForm.type === 'debit' ? 'bg-red-100 text-red-700 border-red-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                    class="flex-1 px-4 py-2 text-sm font-medium border rounded-lg transition-colors"
                                >
                                    {{ t('tenants.show.wallet_modal.debit') }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.wallet_modal.amount', { currency: currencyCode }) }}</label>
                            <input
                                v-model="walletForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                :placeholder="t('tenants.show.wallet_modal.amount_placeholder')"
                            />
                            <p v-if="walletForm.errors.amount" class="mt-1 text-sm text-red-600">{{ walletForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('tenants.show.wallet_modal.reason') }}</label>
                            <input
                                v-model="walletForm.reason"
                                type="text"
                                required
                                maxlength="255"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                :placeholder="t('tenants.show.wallet_modal.reason_placeholder')"
                            />
                            <p v-if="walletForm.errors.reason" class="mt-1 text-sm text-red-600">{{ walletForm.errors.reason }}</p>
                        </div>

                        <div v-if="walletForm.type === 'debit' && parseFloat(walletForm.amount) > (activeLease?.wallet_balance || 0)" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-700">
                                <strong>{{ t('tenants.show.wallet_modal.warning_label') }}</strong> {{ t('tenants.show.wallet_modal.warning_body') }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showWalletModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">{{ t('tenants.show.wallet_modal.cancel') }}</button>
                            <button
                                type="submit"
                                :disabled="walletForm.processing"
                                :class="walletForm.type === 'credit' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                                class="px-4 py-2 text-white rounded-lg disabled:opacity-50"
                            >
                                {{ walletForm.type === 'credit' ? t('tenants.show.wallet_modal.add_credit') : t('tenants.show.wallet_modal.remove_credit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Phase-82 NOTICE-GEN-2: generate a notice PDF for the active lease. -->
        <div v-if="showNoticeModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showNoticeModal = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $t('document.notice.generate') }}</h3>
                        <button @click="showNoticeModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitNotice" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.notice.type') }}</label>
                            <select
                                v-model="noticeForm.notice_type"
                                required
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="rent_increase">{{ $t('document.notice.rent_increase') }}</option>
                                <option value="arrears">{{ $t('document.notice.arrears') }}</option>
                                <option value="general">{{ $t('document.notice.general') }}</option>
                            </select>
                            <p v-if="noticeForm.errors.notice_type" class="mt-1 text-sm text-red-600">{{ noticeForm.errors.notice_type }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.notice.effective_date') }}</label>
                            <input
                                v-model="noticeForm.effective_date"
                                type="date"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="noticeForm.errors.effective_date" class="mt-1 text-sm text-red-600">{{ noticeForm.errors.effective_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('document.notice.reason') }}</label>
                            <textarea
                                v-model="noticeForm.reason"
                                rows="4"
                                maxlength="5000"
                                class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                            ></textarea>
                            <p v-if="noticeForm.errors.reason" class="mt-1 text-sm text-red-600">{{ noticeForm.errors.reason }}</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showNoticeModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">{{ $t('document.cancel') }}</button>
                            <button
                                type="submit"
                                :disabled="noticeForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ $t('document.notice.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
