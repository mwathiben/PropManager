<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TicketStatusBadge from '@/Components/TicketStatusBadge.vue';
import TicketPriorityBadge from '@/Components/TicketPriorityBadge.vue';
import TicketActivityTimeline from '@/Components/TicketActivityTimeline.vue';
import TicketFeedbackForm from '@/Components/TicketFeedbackForm.vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
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
    StarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<TicketShowPageProps>();
const { can } = useAuth();

const showResolveModal = ref(false);
const showAssignModal = ref(false);

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
    if (confirm('Are you sure you want to close this ticket?')) {
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
    if (confirm('Are you sure you want to cancel this ticket?')) {
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
                <!-- Header -->
                <div class="mb-6">
                    <Link
                        :href="route('tickets.index')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4"
                    >
                        <ArrowLeftIcon class="h-4 w-4 me-1" />
                        Back to Tickets
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
                                Acknowledge
                            </button>
                            <button
                                v-if="ticket.status === 'acknowledged'"
                                @click="updateStatus('in_progress')"
                                class="px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 rounded-md hover:bg-purple-200"
                            >
                                Start Work
                            </button>
                            <button
                                v-if="isOpen"
                                @click="showResolveModal = true"
                                class="px-3 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200"
                            >
                                <CheckCircleIcon class="h-4 w-4 inline me-1" />
                                Resolve
                            </button>
                        </div>

                        <div v-if="canChangeStatus && ticket.status === 'resolved'" class="flex space-x-2">
                            <button
                                @click="closeTicket"
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                            >
                                <LockClosedIcon class="h-4 w-4 inline me-1" />
                                Close Ticket
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Description -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Description</h3>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>

                            <div v-if="ticket.resolution_notes && ticket.status === 'resolved' || ticket.status === 'closed'" class="mt-6 pt-6 border-t">
                                <h4 class="text-sm font-medium text-green-800 mb-2">Resolution Notes</h4>
                                <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.resolution_notes || 'No notes provided.' }}</p>
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
                                Tenant Feedback
                            </h3>
                            <div class="flex items-center space-x-1 mb-2">
                                <StarIcon
                                    v-for="star in 5"
                                    :key="star"
                                    :class="[star <= ticket.feedback.rating ? 'text-yellow-400' : 'text-gray-300', 'h-5 w-5']"
                                />
                                <span class="ms-2 text-sm text-gray-600">
                                    {{ ticket.feedback.rating }}/5
                                </span>
                            </div>
                            <p v-if="ticket.feedback.comments" class="text-gray-700 mt-2">
                                "{{ ticket.feedback.comments }}"
                            </p>
                            <p class="text-xs text-gray-500 mt-2">
                                Submitted by {{ ticket.feedback.user?.name }} on {{ formatDate(ticket.feedback.created_at) }}
                            </p>
                        </div>

                        <!-- Comments -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Comments</h3>

                            <div v-if="ticket.comments?.length > 0" class="space-y-4 mb-6">
                                <div
                                    v-for="comment in ticket.comments"
                                    :key="comment.id"
                                    :class="[
                                        comment.is_internal ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200',
                                        'rounded-lg border p-4'
                                    ]"
                                >
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-gray-900">{{ comment.author?.name }}</span>
                                            <span v-if="comment.is_internal" class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded">
                                                Internal Note
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ formatDate(comment.created_at) }}</span>
                                    </div>
                                    <p class="text-gray-700 whitespace-pre-wrap">{{ comment.comment }}</p>
                                </div>
                            </div>

                            <div v-else class="text-center py-4 text-gray-500 mb-6">
                                No comments yet.
                            </div>

                            <!-- Add Comment Form -->
                            <form @submit.prevent="submitComment" class="border-t pt-4">
                                <div class="mb-3">
                                    <textarea
                                        v-model="commentForm.comment"
                                        rows="3"
                                        placeholder="Add a comment..."
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
                                        <span class="ms-2 text-sm text-gray-600">Internal note (not visible to tenant)</span>
                                    </label>
                                    <div v-else></div>
                                    <button
                                        type="submit"
                                        :disabled="!commentForm.comment || commentForm.processing"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        <PaperAirplaneIcon class="h-4 w-4 me-2" />
                                        Send
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Activity Timeline -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Activity History</h3>
                            <TicketActivityTimeline :activities="ticket.activities || []" />
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Details Card -->
                        <div class="bg-white shadow-sm rounded-lg border p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Details</h3>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Category</dt>
                                    <dd class="mt-1 text-sm text-gray-900 capitalize">{{ ticket.category }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900 capitalize">{{ ticket.subcategory?.replace('_', ' ') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Building</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ ticket.building?.name }}</dd>
                                </div>
                                <div v-if="ticket.unit">
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ ticket.unit.unit_number }}</dd>
                                </div>
                                <div v-if="ticket.location">
                                    <dt class="text-sm font-medium text-gray-500">Location</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <MapPinIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ ticket.location }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reported By</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <UserIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ ticket.reporter?.name }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center justify-between">
                                        <span class="flex items-center">
                                            <UserIcon class="h-4 w-4 me-1 text-gray-400" />
                                            {{ ticket.assignee?.name || 'Unassigned' }}
                                        </span>
                                        <button
                                            v-if="canAssign"
                                            @click="showAssignModal = true"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm"
                                        >
                                            Change
                                        </button>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <CalendarIcon class="h-4 w-4 me-1 text-gray-400" />
                                        {{ formatDate(ticket.created_at) }}
                                    </dd>
                                </div>
                                <div v-if="ticket.resolved_at">
                                    <dt class="text-sm font-medium text-gray-500">Resolved</dt>
                                    <dd class="mt-1 text-sm text-green-600 flex items-center">
                                        <CheckCircleIcon class="h-4 w-4 me-1" />
                                        {{ formatDate(ticket.resolved_at) }}
                                    </dd>
                                </div>
                                <div v-if="ticket.closed_at">
                                    <dt class="text-sm font-medium text-gray-500">Closed</dt>
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
                                class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50 text-sm"
                            >
                                <XMarkIcon class="h-4 w-4 inline me-1" />
                                Cancel Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolve Modal -->
        <div v-if="showResolveModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showResolveModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resolve Ticket</h3>
                    <form @submit.prevent="resolveTicket">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes</label>
                            <textarea
                                v-model="resolveForm.resolution_notes"
                                rows="4"
                                placeholder="Describe how the issue was resolved..."
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showResolveModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="resolveForm.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50"
                            >
                                Mark as Resolved
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
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Ticket</h3>
                    <form @submit.prevent="assignTicket">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Caretaker</label>
                            <select
                                v-model="assignForm.assigned_to"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Unassigned</option>
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
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="assignForm.processing"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Assign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
