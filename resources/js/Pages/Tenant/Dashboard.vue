<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import PushNotificationPrompt from '@/Components/PushNotificationPrompt.vue';
import DocumentExpiryBanner from '@/Components/Tenant/DocumentExpiryBanner.vue';
import { useFormatters, useStatusColors, useEcho } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { TenantDashboardPageProps } from '@/types';
import {
    HomeIcon,
    CreditCardIcon,
    TicketIcon,
    DocumentTextIcon,
    PhoneIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    ClockIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    ChatBubbleLeftRightIcon,
    BuildingOffice2Icon,
    EnvelopeOpenIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<TenantDashboardPageProps>();

// Use composables
const { t } = useI18n();
const { formatCurrency, formatDate, formatRelativeDate } = useFormatters();
const { notificationStatusColor: getStatusBadgeClass, ticketPriorityColor: getPriorityBadgeClass } = useStatusColors();

// Note: Ticket status has slightly different values - extend as needed
const getTicketStatusBadgeClass = (status) => {
    const classes = {
        open: 'bg-yellow-100 text-yellow-800',
        acknowledged: 'bg-blue-100 text-blue-800',
        in_progress: 'bg-purple-100 text-purple-800',
        resolved: 'bg-green-100 text-green-800',
        closed: 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

// Invitation handling
const processingInvitation = ref(null);

const acceptInvitation = (invitation) => {
    processingInvitation.value = invitation.id;
    router.post(route('tenant-invitations.accept-authenticated', invitation.id), {}, {
        onFinish: () => {
            processingInvitation.value = null;
        },
    });
};

const declineInvitation = (invitation) => {
    if (confirm(t('tenant_dashboard.confirm.decline'))) {
        processingInvitation.value = invitation.id;
        router.post(route('tenant-invitations.decline-authenticated', invitation.id), {}, {
            onFinish: () => {
                processingInvitation.value = null;
            },
        });
    }
};

// Local state for real-time updates
const localRecentTickets = ref([...(props.recentTickets || [])]);
const localActionItems = ref({ ...props.actionItems });

// Watch for navigation changes
watch(() => props.recentTickets, (newVal) => {
    if (newVal) localRecentTickets.value = [...newVal];
}, { deep: true });

watch(() => props.actionItems, (newVal) => {
    if (newVal) Object.assign(localActionItems.value, newVal);
}, { deep: true });

// Real-time updates
const { subscribePrivate, unsubscribe } = useEcho();
const tenantId = window.__auth?.user?.id;

onMounted(() => {
    if (tenantId) {
        subscribePrivate(`tenant.${tenantId}`, 'TicketStatusChanged', (data) => {
            // Update ticket in list
            const ticketIndex = localRecentTickets.value.findIndex(ticket => ticket.id === data.ticket_id);
            if (ticketIndex !== -1) {
                localRecentTickets.value[ticketIndex].status = data.new_status;
            }

            // Recalculate open tickets count
            const openCount = localRecentTickets.value.filter(ticket =>
                ['open', 'acknowledged', 'in_progress'].includes(ticket.status)
            ).length;
            localActionItems.value.open_tickets = openCount;
        });
    }
});

onUnmounted(() => {
    if (tenantId) {
        unsubscribe(`tenant.${tenantId}`);
    }
});
</script>

<template>
    <Head :title="t('tenant_dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900" v-if="hasLease">{{ t('tenant_dashboard.welcome', { building: building?.name }) }}</h1>
                    <p class="text-sm text-gray-500" v-if="hasLease">{{ t('tenant_dashboard.unit_floor', { unit: unit?.unit_number, floor: unit?.floor_number }) }}</p>
                </div>
                <div v-if="hasLease && pendingInvoices?.length > 0" class="flex items-center gap-2">
                    <Link :href="route('tenant.finances.index')"
                          class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium text-sm">
                        <CreditCardIcon class="w-4 h-4 me-2" />
                        {{ t('tenant_dashboard.pay_now') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 lg:p-8">
            <!-- Push Notification Prompt -->
            <PushNotificationPrompt class="mb-6" />

            <!-- Phase-28 TENANT-DOCS-3: 30-day document expiry banner. -->
            <DocumentExpiryBanner class="mb-6" />

            <!-- No Lease State -->
            <div v-if="!hasLease">
                <!-- Pending Invitations -->
                <div v-if="pendingInvitations?.length > 0" class="max-w-3xl mx-auto">
                    <div class="text-center mb-8">
                        <EnvelopeOpenIcon class="h-16 w-16 text-indigo-500 mx-auto mb-4" />
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ t('tenant_dashboard.no_lease.pending_title') }}</h2>
                        <p class="text-gray-600">{{ t('tenant_dashboard.no_lease.pending_subtitle') }}</p>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="invitation in pendingInvitations"
                            :key="invitation.id"
                            class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden"
                        >
                            <!-- Invitation Header -->
                            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-12 bg-white/20 rounded-xl flex items-center justify-center">
                                        <BuildingOffice2Icon class="h-6 w-6 text-white" />
                                    </div>
                                    <div class="text-white">
                                        <h3 class="font-bold text-lg">{{ invitation.property_name }}</h3>
                                        <p class="text-indigo-100">{{ t('tenant_dashboard.invitation.unit_label', { building: invitation.building_name, unit: invitation.unit_number }) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Invitation Details -->
                            <div class="p-6">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.invitation.monthly_rent') }}</p>
                                        <p class="font-bold text-gray-900 text-lg">{{ formatCurrency(invitation.rent_amount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.invitation.security_deposit') }}</p>
                                        <p class="font-bold text-gray-900 text-lg">{{ formatCurrency(invitation.deposit_amount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.invitation.start_date') }}</p>
                                        <p class="font-semibold text-gray-900">{{ invitation.start_date }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.invitation.floor') }}</p>
                                        <p class="font-semibold text-gray-900">{{ invitation.floor_number }}</p>
                                    </div>
                                </div>

                                <div v-if="invitation.service_charge > 0" class="mb-6 p-3 bg-gray-50 rounded-lg">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">{{ t('tenant_dashboard.invitation.service_charge') }}</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(invitation.service_charge) }}{{ t('tenant_dashboard.invitation.per_month') }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mb-6 p-4 bg-indigo-50 rounded-xl">
                                    <div>
                                        <p class="text-sm text-indigo-600 font-medium">{{ t('tenant_dashboard.invitation.total_move_in') }}</p>
                                        <p class="text-2xl font-bold text-indigo-700">{{ formatCurrency(invitation.total_move_in) }}</p>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-xs text-indigo-500">{{ t('tenant_dashboard.invitation.landlord') }}</p>
                                        <p class="text-sm font-medium text-indigo-700">{{ invitation.landlord_name }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-sm text-gray-500 mb-6">
                                    <span class="flex items-center gap-1">
                                        <ClockIcon class="h-4 w-4" />
                                        {{ t('tenant_dashboard.invitation.expires', { date: invitation.expires_at }) }}
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-3">
                                    <button
                                        @click="acceptInvitation(invitation)"
                                        :disabled="processingInvitation === invitation.id"
                                        class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <CheckCircleIcon class="w-5 h-5 me-2" />
                                        {{ processingInvitation === invitation.id ? t('tenant_dashboard.invitation.processing') : t('tenant_dashboard.invitation.accept') }}
                                    </button>
                                    <button
                                        @click="declineInvitation(invitation)"
                                        :disabled="processingInvitation === invitation.id"
                                        class="px-6 py-3 bg-white border border-red-300 text-red-600 rounded-xl hover:bg-red-50 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <XMarkIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Invitations Message -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center max-w-md mx-auto">
                    <ExclamationCircleIcon class="h-16 w-16 text-yellow-500 mx-auto mb-4" />
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ t('tenant_dashboard.no_lease.no_active_lease') }}</h2>
                    <p class="text-gray-600">{{ message }}</p>
                </div>
            </div>

            <template v-else>
                <!-- === BALANCE CARD (Prominent) === -->
                <div class="bg-white rounded-2xl shadow-sm border p-6 mb-6"
                     :class="balance >= 0 ? 'border-green-200' : 'border-red-200'">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="h-14 w-14 rounded-xl flex items-center justify-center"
                                 :class="balance >= 0 ? 'bg-green-100' : 'bg-red-100'">
                                <BanknotesIcon class="h-7 w-7" :class="balance >= 0 ? 'text-green-600' : 'text-red-600'" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">{{ t('tenant_dashboard.balance.current') }}</p>
                                <p class="text-3xl font-bold" :class="balance >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ formatCurrency(Math.abs(balance)) }}
                                </p>
                                <p class="text-sm mt-0.5" :class="balance >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ balance >= 0 ? t('tenant_dashboard.balance.credit') : t('tenant_dashboard.balance.arrears') }}
                                </p>
                            </div>
                        </div>
                        <div v-if="balance < 0" class="flex gap-3">
                            <Link :href="route('tenant.finances.index')"
                                  class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                                {{ t('tenant_dashboard.pay_now') }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- === ACTION ITEMS === -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <ActionItemCard
                        v-if="localActionItems?.overdue_invoices > 0"
                        urgency="critical"
                        :icon="ExclamationTriangleIcon"
                        :count="localActionItems.overdue_invoices"
                        :title="t('tenant_dashboard.action_items.overdue_invoices')"
                        :description="t('tenant_dashboard.action_items.days_late', { days: localActionItems.overdue_days })"
                        :actionLabel="t('tenant_dashboard.pay_now')"
                        :actionHref="route('tenant.finances.index')"
                    />
                    <ActionItemCard
                        v-else-if="localActionItems?.pending_invoices > 0"
                        urgency="medium"
                        :icon="DocumentTextIcon"
                        :count="localActionItems.pending_invoices"
                        :title="t('tenant_dashboard.action_items.pending_invoices')"
                        :description="t('tenant_dashboard.action_items.awaiting_payment')"
                        :actionLabel="t('tenant_dashboard.action_items.view')"
                        :actionHref="route('tenant.finances.index')"
                    />
                    <ActionItemCard
                        v-else
                        urgency="low"
                        :icon="CheckCircleIcon"
                        :count="0"
                        :title="t('tenant_dashboard.action_items.all_paid')"
                        :description="t('tenant_dashboard.action_items.no_pending_invoices')"
                    />

                    <ActionItemCard
                        v-if="localActionItems?.open_tickets > 0"
                        urgency="medium"
                        :icon="TicketIcon"
                        :count="localActionItems.open_tickets"
                        :title="t('tenant_dashboard.action_items.open_tickets')"
                        :description="t('tenant_dashboard.action_items.issues_being_resolved')"
                        :actionLabel="t('tenant_dashboard.action_items.view')"
                        :actionHref="route('tickets.index')"
                    />
                    <ActionItemCard
                        v-else
                        urgency="low"
                        :icon="TicketIcon"
                        :count="0"
                        :title="t('tenant_dashboard.action_items.no_issues')"
                        :description="t('tenant_dashboard.action_items.all_tickets_resolved')"
                    />

                    <MetricCard
                        :title="t('tenant_dashboard.action_items.monthly_rent')"
                        :value="lease?.rent_amount"
                        format="currency"
                        :subtitle="t('tenant_dashboard.action_items.due_monthly')"
                        :icon="CreditCardIcon"
                        color="indigo"
                    />
                </div>

                <!-- === NEXT PAYMENT === -->
                <div v-if="nextPayment" class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl p-6 text-white mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-indigo-100 text-sm font-medium mb-1">{{ t('tenant_dashboard.next_payment.title') }}</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(nextPayment.total_due - nextPayment.amount_paid) }}</p>
                            <p class="text-indigo-200 text-sm mt-1">
                                {{ nextPayment.invoice_number }} • {{ formatRelativeDate(nextPayment.due_date) }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Link :href="route('tenant.finances.index')"
                                  class="px-6 py-3 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-medium">
                                {{ t('tenant_dashboard.next_payment.pay_invoice') }}
                            </Link>
                            <Link :href="route('invoices.show', nextPayment.id)"
                                  class="px-6 py-3 bg-indigo-400 bg-opacity-30 text-white rounded-lg hover:bg-opacity-40 transition font-medium">
                                {{ t('tenant_dashboard.next_payment.view_details') }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- === MY TICKETS + PAYMENT HISTORY === -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- My Tickets -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900">{{ t('tenant_dashboard.tickets.title') }}</h3>
                            <Link :href="route('tickets.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                                {{ t('tenant_dashboard.tickets.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                            </Link>
                        </div>
                        <div v-if="localRecentTickets?.length === 0" class="p-8 text-center">
                            <CheckCircleIcon class="h-12 w-12 text-green-400 mx-auto mb-3" />
                            <p class="text-gray-500">{{ t('tenant_dashboard.tickets.none') }}</p>
                            <p class="text-sm text-gray-400">{{ t('tenant_dashboard.tickets.all_good') }}</p>
                        </div>
                        <div v-else class="divide-y divide-gray-100">
                            <Link v-for="ticket in localRecentTickets" :key="ticket.id"
                                  :href="route('tickets.show', ticket.id)"
                                  class="block px-6 py-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ ticket.title }}</p>
                                        <p class="text-sm text-gray-500 mt-0.5">{{ formatDate(ticket.created_at) }}</p>
                                    </div>
                                    <div class="flex gap-2 ms-4">
                                        <span class="text-xs px-2 py-1 rounded-full" :class="getPriorityBadgeClass(ticket.priority)">
                                            {{ ticket.priority }}
                                        </span>
                                        <span class="text-xs px-2 py-1 rounded-full" :class="getStatusBadgeClass(ticket.status)">
                                            {{ ticket.status?.replace('_', ' ') }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                            <Link :href="route('tickets.create')"
                                  class="w-full block text-center px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                                {{ t('tenant_dashboard.tickets.report_issue') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900">{{ t('tenant_dashboard.payments.title') }}</h3>
                            <Link :href="route('tenant.finances.history')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                                {{ t('tenant_dashboard.payments.view_all') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                            </Link>
                        </div>
                        <div v-if="recentPayments?.length === 0" class="p-8 text-center">
                            <BanknotesIcon class="h-12 w-12 text-gray-300 mx-auto mb-3" />
                            <p class="text-gray-500">{{ t('tenant_dashboard.payments.none') }}</p>
                        </div>
                        <div v-else class="divide-y divide-gray-100">
                            <div v-for="payment in recentPayments" :key="payment.id"
                                 class="px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <CheckCircleIcon class="w-5 h-5 text-green-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                        <p class="text-sm text-gray-500">{{ formatDate(payment.payment_date || payment.created_at) }}</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <p class="text-sm font-medium text-gray-700 capitalize">{{ payment.payment_method?.replace('_', ' ') || t('tenant_dashboard.payments.fallback_method') }}</p>
                                    <p class="text-xs text-gray-400">{{ payment.reference || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- === LEASE INFO + CARETAKER CONTACT === -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Lease Information -->
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-gray-900">{{ t('tenant_dashboard.lease.title') }}</h3>
                            <Link :href="route('tenant.lease')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                                {{ t('tenant_dashboard.lease.view_details') }} <ChevronRightIcon class="w-4 h-4 ms-1" />
                            </Link>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.lease.start_date') }}</p>
                                <p class="font-semibold text-gray-900">{{ formatDate(lease?.start_date) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.lease.end_date') }}</p>
                                <p class="font-semibold text-gray-900">{{ lease?.end_date ? formatDate(lease.end_date) : t('tenant_dashboard.lease.open_ended') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.lease.monthly_rent') }}</p>
                                <p class="font-semibold text-gray-900">{{ formatCurrency(lease?.rent_amount) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">{{ t('tenant_dashboard.lease.deposit_paid') }}</p>
                                <p class="font-semibold text-gray-900">{{ formatCurrency(lease?.deposit_amount) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Caretaker Contact -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="font-bold text-gray-900 mb-4">{{ t('tenant_dashboard.caretaker.title') }}</h3>
                        <div v-if="caretaker" class="flex items-start gap-4">
                            <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-lg">
                                {{ caretaker.name?.charAt(0) || '?' }}
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">{{ caretaker.name }}</p>
                                <a v-if="caretaker.mobile_number"
                                   :href="'tel:' + caretaker.mobile_number"
                                   class="flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-sm mt-1">
                                    <PhoneIcon class="w-4 h-4" />
                                    {{ caretaker.mobile_number }}
                                </a>
                                <a v-if="caretaker.mobile_number"
                                   :href="'https://wa.me/' + caretaker.mobile_number?.replace(/\D/g, '')"
                                   target="_blank"
                                   class="flex items-center gap-1 text-green-600 hover:text-green-700 text-sm mt-1">
                                    <ChatBubbleLeftRightIcon class="w-4 h-4" />
                                    {{ t('tenant_dashboard.caretaker.whatsapp') }}
                                </a>
                            </div>
                        </div>
                        <div v-else class="text-center py-4">
                            <PhoneIcon class="h-8 w-8 text-gray-300 mx-auto mb-2" />
                            <p class="text-sm text-gray-500">{{ t('tenant_dashboard.caretaker.none') }}</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
