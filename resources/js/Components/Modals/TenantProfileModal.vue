<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useErrorHandler } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import Modal from '@/Components/Modal.vue';
import KycBadge from '@/Components/KycBadge.vue';
import OverviewTab from '@/Components/TenantProfile/OverviewTab.vue';
import LeaseFinancesTab from '@/Components/TenantProfile/LeaseFinancesTab.vue';
import DocumentsTab from '@/Components/TenantProfile/DocumentsTab.vue';
import HistoryTab from '@/Components/TenantProfile/HistoryTab.vue';
import NotesContactsTab from '@/Components/TenantProfile/NotesContactsTab.vue';
import type { TenantProfileModalData } from '@/types';

const { t } = useI18n();

const props = withDefaults(defineProps<{
    show?: boolean;
    tenantId?: number | null;
    initialData?: TenantProfileModalData | null;
}>(), {
    show: false,
    tenantId: null,
    initialData: null,
});

const emit = defineEmits(['close']);
const { logError, logWarning } = useErrorHandler();

const loading = ref(false);
const error = ref(null);
const activeTab = ref('overview');
const data = ref(null);

const tabs = computed(() => [
    { id: 'overview', label: t('tenant_profile_modal.tabs.overview') },
    { id: 'lease', label: t('tenant_profile_modal.tabs.lease') },
    { id: 'documents', label: t('tenant_profile_modal.tabs.documents') },
    { id: 'history', label: t('tenant_profile_modal.tabs.history') },
    { id: 'notes', label: t('tenant_profile_modal.tabs.notes') },
]);

const fetchTenantData = async () => {
    if (!props.tenantId) {
        logWarning('No tenantId provided', { component: 'TenantProfileModal' });
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/tenants/${props.tenantId}/modal-data`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(t('tenant_profile_modal.failed_to_load'));
        }

        data.value = await response.json();
    } catch (e) {
        error.value = e.message;
        logError(e, { component: 'TenantProfileModal', action: 'fetchTenantData' });
    } finally {
        loading.value = false;
    }
};

watch(() => props.show, (newShow) => {
    if (newShow && props.tenantId) {
        activeTab.value = 'overview';
        if (props.initialData) {
            data.value = props.initialData;
        }
        fetchTenantData();
    }
});

watch(() => props.tenantId, () => {
    if (props.show && props.tenantId) {
        fetchTenantData();
    }
});

const tenant = computed(() => data.value?.tenant);
const activeLease = computed(() => data.value?.activeLease);
const pastLeases = computed(() => data.value?.pastLeases || []);
const financialSummary = computed(() => data.value?.financialSummary || {});
const documents = computed(() => data.value?.documents || []);
const payments = computed(() => data.value?.payments || []);
const invoices = computed(() => data.value?.invoices || []);
const emergencyContacts = computed(() => data.value?.emergencyContacts || []);
const tenantNotes = computed(() => data.value?.tenantNotes || []);
const verificationStatus = computed(() => data.value?.verificationStatus || {});
const activities = computed(() => data.value?.activities || []);

const close = () => {
    emit('close');
};

const getProfilePhoto = () => {
    if (tenant.value?.profile_photo_path) {
        return `/storage/${tenant.value.profile_photo_path}`;
    }
    return null;
};

const getInitials = () => {
    if (!tenant.value?.name) return '?';
    return tenant.value.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="close">
        <div class="flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-gray-50">
                <div v-if="loading && !data" class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-gray-200 animate-pulse"></div>
                    <div class="space-y-2">
                        <div class="h-4 w-32 bg-gray-200 rounded animate-pulse"></div>
                        <div class="h-3 w-24 bg-gray-200 rounded animate-pulse"></div>
                    </div>
                </div>
                <div v-else-if="tenant" class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="getProfilePhoto()"
                            :src="getProfilePhoto()"
                            :alt="tenant.name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else class="text-lg font-medium text-gray-600">{{ getInitials() }}</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ tenant.name }}</h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span v-if="activeLease" class="text-sm text-gray-500">
                                {{ t('tenant_profile_modal.unit_label', { number: activeLease.unit?.unit_number }) }}
                            </span>
                            <KycBadge
                                :completed="verificationStatus.kyc_completed"
                                :completed-at="verificationStatus.kyc_completed_at"
                            />
                        </div>
                    </div>
                </div>
                <button
                    @click="close"
                    :aria-label="t('tenant_profile_modal.close_aria')"
                    class="text-gray-400 hover:text-gray-500 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="border-b">
                <nav class="flex -mb-px px-6 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[ activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm' ]"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tab.id === 'documents' && documents.length"
                            class="ms-1.5 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600"
                        >
                            {{ documents.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div v-if="loading && !data" class="space-y-4">
                    <div class="h-24 bg-gray-100 rounded-lg animate-pulse"></div>
                    <div class="h-32 bg-gray-100 rounded-lg animate-pulse"></div>
                    <div class="h-20 bg-gray-100 rounded-lg animate-pulse"></div>
                </div>

                <div v-else-if="error" class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="mt-2 text-sm text-red-600">{{ error }}</p>
                    <button
                        @click="fetchTenantData"
                        class="mt-4 text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        {{ t('tenant_profile_modal.try_again') }}
                    </button>
                </div>

                <template v-else-if="data">
                    <OverviewTab
                        v-if="activeTab === 'overview'"
                        :tenant="tenant"
                        :active-lease="activeLease"
                        :financial-summary="financialSummary"
                        :verification-status="verificationStatus"
                        :emergency-contacts="emergencyContacts"
                        :activities="activities"
                    />

                    <LeaseFinancesTab
                        v-else-if="activeTab === 'lease'"
                        :active-lease="activeLease"
                        :past-leases="pastLeases"
                        :financial-summary="financialSummary"
                    />

                    <DocumentsTab
                        v-else-if="activeTab === 'documents'"
                        :documents="documents"
                    />

                    <HistoryTab
                        v-else-if="activeTab === 'history'"
                        :payments="payments"
                        :invoices="invoices"
                        :past-leases="pastLeases"
                    />

                    <NotesContactsTab
                        v-else-if="activeTab === 'notes'"
                        :tenant-notes="tenantNotes"
                        :emergency-contacts="emergencyContacts"
                    />
                </template>
            </div>

            <div class="border-t px-6 py-4 bg-gray-50 flex items-center justify-between">
                <a
                    v-if="tenant?.id"
                    :href="`/tenants/${tenant.id}`"
                    class="text-sm text-indigo-600 hover:text-indigo-500"
                >
                    {{ t('tenant_profile_modal.view_full_profile') }}
                </a>
                <div v-else></div>
                <button
                    @click="close"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    {{ t('tenant_profile_modal.close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
