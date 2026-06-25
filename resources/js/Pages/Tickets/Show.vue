<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TicketStatusBadge from '@/Components/TicketStatusBadge.vue';
import TicketPriorityBadge from '@/Components/TicketPriorityBadge.vue';
import PendingSyncBadge from '@/Components/Offline/PendingSyncBadge.vue';
import HoldCreateModal from '@/Components/LegalHold/HoldCreateModal.vue';
import TicketActivityTimeline from '@/Components/TicketActivityTimeline.vue';
import TicketFeedbackForm from '@/Components/TicketFeedbackForm.vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import { useI18n } from '@/composables/useI18n';
import type { TicketShowPageProps } from '@/types';
import {
    ArrowLeftIcon,
    WrenchScrewdriverIcon,
    ChatBubbleBottomCenterTextIcon,
    MapPinIcon,
    UserIcon,
    CalendarIcon,
    CheckCircleIcon,
    XMarkIcon,
    PaperAirplaneIcon,
    LockClosedIcon,
    StarIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';

// Phase-54 COST-UI-1/2: extend the inherited TicketShowPageProps type
// with the new optional cost props rather than fork the @/types file
// for two fields.
type CostBreakdown = { parts: number; vendor: number; labor: number; other: number; total: number };
const props = defineProps<TicketShowPageProps & {
    costs?: CostBreakdown | null;
    canManageCosts?: boolean;
    legalHoldActive?: boolean;
    isEscalated?: boolean;
    canAcknowledgeEscalation?: boolean;
    escalationReason?: string | null;
    escalatedByName?: string | null;
}>();

function acknowledgeEscalation(): void {
    router.post(route('tickets.escalation.acknowledge', props.ticket.id), {}, { preserveScroll: true });
}
const { can } = useAuth();
const { t } = useI18n();

const showResolveModal = ref(false);
const showAssignModal = ref(false);
const ticketLegalHoldModal = ref<InstanceType<typeof HoldCreateModal> | null>(null);
const showCostModal = ref(false);

const costs = computed(() => props.costs ?? null);
const canManageCosts = computed(() => Boolean(props.canManageCosts));

const costForm = useForm({
    category: 'vendor' as 'vendor' | 'labor' | 'other',
    amount_kes: 0,
    notes: '' as string,
});

function formatKes(cents: number): string {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

function costSegmentWidth(category: 'parts' | 'vendor' | 'labor' | 'other'): string {
    const breakdown = costs.value;
    if (!breakdown || breakdown.total === 0) return '0%';
    return `${(breakdown[category] / breakdown.total) * 100}%`;
}

function submitCost(): void {
    router.post(
        route('tickets.costs.store', props.ticket.id),
        {
            category: costForm.category,
            amount_cents: Math.round(costForm.amount_kes * 100),
            notes: costForm.notes || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCostModal.value = false;
                costForm.reset();
                costForm.category = 'vendor';
            },
        },
    );
}

const commentForm = useForm({
    comment: '',
    is_internal: false
});

const resolveForm = useForm({
    resolution_notes: ''
});

const assignForm = useForm({
    assigned_to: props.ticket.assigned_to || ''
});

const submitComment = () => {
    commentForm.post(route('tickets.comment', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
        }
    });
};

const resolveTicket = () => {
    resolveForm.post(route('tickets.resolve', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            showResolveModal.value = false;
            resolveForm.reset();
        }
    });
};

const closeTicket = () => {
    if (confirm(t('tickets.show.confirm_close'))) {
        router.post(route('tickets.close', props.ticket.id), {}, {
            preserveScroll: true
        });
    }
};

const assignTicket = () => {
    assignForm.post(route('tickets.assign', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
        }
    });
};

const cancelTicket = () => {
    if (confirm(t('tickets.show.confirm_cancel'))) {
        router.delete(route('tickets.destroy', props.ticket.id));
    }
};

const updateStatus = (newStatus) => {
    router.put(route('tickets.update', props.ticket.id), {
        status: newStatus
    }, {
        preserveScroll: true
    });
};

// Use composables
const { formatDateTime: formatDate } = useFormatters();

const getCategoryIcon = computed(() => {
    return props.ticket.category === 'issue' ? WrenchScrewdriverIcon : ChatBubbleBottomCenterTextIcon;
});

const getCategoryClass = computed(() => {
    return props.ticket.category === 'issue'
        ? 'bg-orange-50 text-orange-700 border-orange-200'
        : 'bg-indigo-50 text-indigo-700 border-indigo-200';
});

const isOpen = computed(() => {
    return ['open', 'acknowledged', 'in_progress'].includes(props.ticket.status);
});

const canEdit = computed(() => {
    return ['open', 'acknowledged'].includes(props.ticket.status);
});
</script>

<template>
    <Head :title="ticket.title" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Phase-80 ESCALATION-VIEW-3: open-escalation banner -->
                <div v-if="isEscalated" class="mb-4 rounded-lg border border-purple-200 bg-purple-50 p-4" data-testid="escalation-banner">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-purple-800">{{ t('maintenance.escalation.banner_title') }}<span v-if="escalatedByName"> · {{ escalatedByName }}</span></p>
                            <p v-if="escalationReason" class="mt-1 text-sm text-purple-700">{{ escalationReason }}</p>
                        </div>
                        <button
                            v-if="canAcknowledgeEscalation"
                            type="button"
                            class="rounded-md bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-700"
                            @click="acknowledgeEscalation"
                        >{{ t('maintenance.escalation.acknowledge') }}</button>
                    </div>
                </div>

                <!-- Header -->
                <div class="mb-6">
                    <Link
                        :href="route('tickets.index')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <ArrowLeftIcon class="h-4 w-4 me-1" />
                        {{ t('tickets.show.back_to_tickets') }}
                    </Link>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start space-x-4">
                            <div :class="[getCategoryClass, 'p-3 rounded-lg border']">
                                <component :is="getCategoryIcon" class="h-6 w-6" />
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ ticket.title }}</h1>
                                <div class="flex items-center space-x-3 mt-1">
                                    <TicketStatusBadge :status="ticket.status" />
                                    <TicketPriorityBadge :priority="ticket.priority" />
                                    <span class="text-sm text-gray-500">
                                        #{{ ticket.id }}
                                    </span>
                                    <PendingSyncBadge route-family="tickets" :resource-id="ticket.id" />
                                    <button
                                        type="button"
                                        @click="ticketLegalHoldModal?.open()"
                                        class="ms-2 inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded"
                                        data-testid="open-legal-hold"
                                    >
                                        <ScaleIcon class="h-3.5 w-3.5" />
                                        {{ t('tickets.show.legal_hold') }}
                                    </button>
                                    <Link
                                        :href="route('legal-holds.history', { subject_type: 'App\\Models\\Ticket', subject_id: ticket.id })"
                                        class="ms-1 inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900"
                                        data-testid="hold-history-link"
                                    >
                                        {{ t('tickets.show.hold_history') }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div v-if="canChangeStatus && isOpen" class="flex space-x-2">
                            <button
                                v-if="ticket.status === 'open'"
                                @click="updateStatus('acknowledged')"
                                class="px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200"
                            >
                                {{ t('tickets.show.acknowledge') }}
                            </button>
                            <button
                                v-if="ticket.status === 'acknowledged'"
                                @click="updateStatus('in_progress')"
                                class="px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 rounded-md hover:bg-purple-200"
                            >
                                {{ t('tickets.show.start_work') }}
                            </button>
                            <button
                                v-if="isOpen"
                                @click="showResolveModal = true"
                                class="px-3 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200"
                            >
                                <CheckCircleIcon class="h-4 w-4 inline me-1" />
                                {{ t('tickets.show.resolve') }}
                            </button>
                        </div>

                        <div v-if="canChangeStatus && ticket.status === 'resolved'" class="flex space-x-2">
                            <button
                                @click="closeTicket"
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                            >
                                <LockClosedIcon class="h-4 w-4 inline me-1" />
                                {{ t('tickets.show.close_ticket') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Description -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.description') }}</h3>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>

                            <div v-if="ticket.resolution_notes && ticket.status === 'resolved' || ticket.status === 'closed'" class="mt-6 pt-6 border-t">
                                <h4 class="text-sm font-medium text-green-800 mb-2">{{ t('tickets.show.resolution_notes') }}</h4>
                                <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.resolution_notes || t('tickets.show.no_notes_provided') }}</p>
                            </div>
                        </div>

                        <!-- Feedback Form (for tenants on closed tickets) -->
                        <div v-if="canSubmitFeedback">
                            <TicketFeedbackForm :ticket-id="ticket.id" />
                        </div>

                        <!-- Existing Feedback -->
                        <div v-if="ticket.feedback" class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <StarIcon class="h-5 w-5 text-yellow-500 me-2" />
                                {{ t('tickets.show.tenant_feedback') }}
                            </h3>
                            <div class="flex items-center space-x-1 mb-2">
                                <StarIcon
                                    v-for="star in 5"
                                    :key="star"
                                    :class="[star <= ticket.feedback.rating ? 'text-yellow-400' : 'text-gray-300', 'h-5 w-5']"
                                />
                                <span class="ms-2 text-sm text-gray-600">
                                    {{ t('tickets.show.rating_out_of', { rating: ticket.feedback.rating }) }}
                                </span>
                            </div>
                            <p v-if="ticket.feedback.comments" class="text-gray-700 mt-2">
                                "{{ ticket.feedback.comments }}"
                            </p>
                            <p class="text-xs text-gray-500 mt-2">
                                {{ t('tickets.show.submitted_by', { name: ticket.feedback.user?.name, date: formatDate(ticket.feedback.created_at) }) }}
                            </p>
                        </div>

                        <!-- Comments -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.comments') }}</h3>

                            <div v-if="ticket.comments?.length > 0" class="space-y-4 mb-6">
                                <div
                                    v-for="comment in ticket.comments"
                                    :key="comment.id"
                                    :class="[
                                        comment.is_internal ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200',
                                        'rounded-lg border p-4' /* i18n-ignore: tailwind classes */
                                    ]"
                                >
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-gray-900">{{ comment.author?.name }}</span>
                                            <span v-if="comment.is_internal" class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded">
                                                {{ t('tickets.show.internal_note') }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ formatDate(comment.created_at) }}</span>
                                    </div>
                                    <p class="text-gray-700 whitespace-pre-wrap">{{ comment.comment }}</p>
                                </div>
                            </div>

                            <div v-else class="text-center py-4 text-gray-500 mb-6">
                                {{ t('tickets.show.no_comments_yet') }}
                            </div>

                            <!-- Add Comment Form -->
                            <form @submit.prevent="submitComment" class="border-t pt-4">
                                <div class="mb-3">
                                    <label for="ticket-comment-textarea" class="sr-only">{{ t('tickets.show.add_comment_placeholder') }}</label>
                                    <textarea
                                        id="ticket-comment-textarea"
                                        v-model="commentForm.comment"
                                        rows="3"
                                        :placeholder="t('tickets.show.add_comment_placeholder')"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                                <div class="flex items-center justify-between">
                                    <label v-if="canAddInternalComment" class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="commentForm.is_internal"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="ms-2 text-sm text-gray-600">{{ t('tickets.show.internal_note_label') }}</span>
                                    </label>
                                    <div v-else></div>
                                    <button
                                        type="submit"
                                        :disabled="!commentForm.comment || commentForm.processing"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        <PaperAirplaneIcon class="h-4 w-4 me-2" />
                                        {{ t('tickets.show.send') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Activity Timeline -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.activity_history') }}</h3>
                            <TicketActivityTimeline :activities="ticket.activities || []" />
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Details Card -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.details') }}</h3>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.category') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 capitalize">{{ ticket.category }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.type') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 capitalize">{{ ticket.subcategory?.replace('_', ' ') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.building') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ ticket.building?.name }}</dd>
                                </div>
                                <div v-if="ticket.unit">
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.unit') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ ticket.unit.unit_number }}</dd>
                                </div>
                                <div v-if="ticket.location">
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.location') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <MapPinIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ ticket.location }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.reported_by') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <UserIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ ticket.reporter?.name }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.assigned_to') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center justify-between">
                                        <span class="flex items-center">
                                            <UserIcon class="h-4 w-4 me-1 text-gray-400" />
                                            {{ ticket.assignee?.name || t('tickets.show.unassigned') }}
                                        </span>
                                        <button
                                            v-if="canAssign"
                                            @click="showAssignModal = true"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm"
                                        >
                                            {{ t('tickets.show.change') }}
                                        </button>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.created') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <CalendarIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ formatDate(ticket.created_at) }}
                                    </dd>
                                </div>
                                <div v-if="ticket.resolved_at">
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.resolved') }}</dt>
                                    <dd class="mt-1 text-sm text-green-600 flex items-center">
                                        <CheckCircleIcon class="h-4 w-4 me-1" />
                                        {{ formatDate(ticket.resolved_at) }}
                                    </dd>
                                </div>
                                <div v-if="ticket.closed_at">
                                    <dt class="text-sm font-medium text-gray-500">{{ t('tickets.show.closed') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-600 flex items-center">
                                        <LockClosedIcon class="h-4 w-4 me-1" />
                                        {{ formatDate(ticket.closed_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Cancel Button (for reporter) -->
                        <div v-if="can('tenants:manage') && canEdit && $page.props.auth.user.id === ticket.reporter_id">
                            <button
                                @click="cancelTicket"
                                :disabled="legalHoldActive"
                                :title="legalHoldActive ? t('legal_holds.delete_blocked_hint') : undefined"
                                :data-testid="legalHoldActive ? 'delete-blocked-by-hold' : undefined"
                                class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50 text-sm disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                            >
                                <XMarkIcon class="h-4 w-4 inline me-1" />
                                {{ t('tickets.show.cancel_ticket') }}
                            </button>
                        </div>

                        <!-- Phase-54 COST-UI-1/2: maintenance cost card. Landlords + caretakers see the breakdown; only landlords see the Add button. -->
                        <div v-if="costs" class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-medium text-gray-900">{{ t('tickets.show.cost') }}</h3>
                                <button
                                    v-if="canManageCosts"
                                    type="button"
                                    class="text-xs text-indigo-600 hover:underline"
                                    @click="showCostModal = true"
                                >
                                    {{ t('tickets.show.add_cost') }}
                                </button>
                            </div>
                            <p class="text-2xl font-semibold text-gray-900">{{ formatKes(costs.total) }}</p>

                            <div v-if="costs.total > 0" class="mt-3 flex h-2 overflow-hidden rounded bg-gray-100">
                                <div :style="{ width: costSegmentWidth('parts') }" class="bg-indigo-400" :title="t('tickets.show.segment_title', { category: t('tickets.show.parts'), amount: formatKes(costs.parts) })"></div>
                                <div :style="{ width: costSegmentWidth('vendor') }" class="bg-emerald-400" :title="t('tickets.show.segment_title', { category: t('tickets.show.vendor'), amount: formatKes(costs.vendor) })"></div>
                                <div :style="{ width: costSegmentWidth('labor') }" class="bg-amber-400" :title="t('tickets.show.segment_title', { category: t('tickets.show.labor'), amount: formatKes(costs.labor) })"></div>
                                <div :style="{ width: costSegmentWidth('other') }" class="bg-rose-400" :title="t('tickets.show.segment_title', { category: t('tickets.show.other'), amount: formatKes(costs.other) })"></div>
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-indigo-400"></span>
                                    <span class="text-gray-600">{{ t('tickets.show.parts') }}</span>
                                    <span class="ms-auto font-medium text-gray-900">{{ formatKes(costs.parts) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                    <span class="text-gray-600">{{ t('tickets.show.vendor') }}</span>
                                    <span class="ms-auto font-medium text-gray-900">{{ formatKes(costs.vendor) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                    <span class="text-gray-600">{{ t('tickets.show.labor') }}</span>
                                    <span class="ms-auto font-medium text-gray-900">{{ formatKes(costs.labor) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                                    <span class="text-gray-600">{{ t('tickets.show.other') }}</span>
                                    <span class="ms-auto font-medium text-gray-900">{{ formatKes(costs.other) }}</span>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase-54 COST-UI-2: manual cost entry modal (landlord-only). -->
        <div v-if="showCostModal && canManageCosts" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ t('tickets.show.add_maintenance_cost') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ t('tickets.show.parts_auto_note') }}</p>
                <form class="mt-4 space-y-3" @submit.prevent="submitCost">
                    <div>
                        <label for="cost-category" class="block text-xs font-semibold text-gray-700">{{ t('tickets.show.category') }}</label>
                        <select id="cost-category" v-model="costForm.category" required class="mt-1 w-full rounded border-gray-300 text-sm">
                            <option value="vendor">{{ t('tickets.show.vendor') }}</option>
                            <option value="labor">{{ t('tickets.show.labor') }}</option>
                            <option value="other">{{ t('tickets.show.other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="cost-amount-kes" class="block text-xs font-semibold text-gray-700">{{ t('tickets.show.amount_kes') }}</label>
                        <input id="cost-amount-kes" v-model.number="costForm.amount_kes" type="number" min="0.01" step="0.01" required class="mt-1 w-full rounded border-gray-300 text-sm">
                    </div>
                    <div>
                        <label for="cost-notes" class="block text-xs font-semibold text-gray-700">{{ t('tickets.show.notes') }}</label>
                        <textarea id="cost-notes" v-model="costForm.notes" rows="2" maxlength="500" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm" @click="showCostModal = false">{{ t('tickets.show.cancel') }}</button>
                        <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ t('tickets.show.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resolve Modal -->
        <div v-if="showResolveModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showResolveModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.resolve_ticket') }}</h3>
                    <form @submit.prevent="resolveTicket">
                        <div class="mb-4">
                            <label for="resolve-resolution-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ t('tickets.show.resolution_notes') }}</label>
                            <textarea
                                id="resolve-resolution-notes"
                                v-model="resolveForm.resolution_notes"
                                rows="4"
                                :placeholder="t('tickets.show.resolution_notes_placeholder')"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showResolveModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('tickets.show.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="resolveForm.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50"
                            >
                                {{ t('tickets.show.mark_as_resolved') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Assign Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showAssignModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('tickets.show.assign_ticket') }}</h3>
                    <form @submit.prevent="assignTicket">
                        <div class="mb-4">
                            <label for="assign-caretaker" class="block text-sm font-medium text-gray-700 mb-1">{{ t('tickets.show.select_caretaker') }}</label>
                            <select
                                id="assign-caretaker"
                                v-model="assignForm.assigned_to"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">{{ t('tickets.show.unassigned') }}</option>
                                <option v-for="caretaker in caretakers" :key="caretaker.id" :value="caretaker.id">
                                    {{ caretaker.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showAssignModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('tickets.show.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="assignForm.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ t('tickets.show.assign') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <HoldCreateModal
            ref="ticketLegalHoldModal"
            subject-type="App\\Models\\Ticket"
            :subject-id="ticket.id"
            :subject-label="t('tickets.show.subject_label', { id: ticket.id, title: ticket.title })"
        />
    </AuthenticatedLayout>
</template>
