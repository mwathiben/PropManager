<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginatorLink from '@/Components/PaginatorLink.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TicketStatusBadge from '@/Components/TicketStatusBadge.vue';
import TicketPriorityBadge from '@/Components/TicketPriorityBadge.vue';
import { useFormatters } from '@/composables';
import type { CaretakerTicketsPageProps, Ticket } from '@/types';
import {
    WrenchScrewdriverIcon,
    ChatBubbleBottomCenterTextIcon,
    ClockIcon,
    CheckCircleIcon,
    PlayIcon,
    EyeIcon,
    FunnelIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps<CaretakerTicketsPageProps>();

const status = ref(props.filters.status || 'active');
const priority = ref(props.filters.priority || '');

const applyFilters = () => {
    router.get(route('tickets.index'), {
        status: status.value || undefined,
        priority: priority.value || undefined
    }, {
        preserveState: true,
        replace: true
    });
};

// Quick action forms
const acknowledgeForm = useForm({});
const startWorkForm = useForm({});
const resolveForm = useForm({
    resolution_notes: ''
});

const selectedTicket = ref(null);
const showResolveModal = ref(false);

const acknowledgeTicket = (ticket) => {
    acknowledgeForm.put(route('tickets.update', ticket.id), {
        data: { status: 'acknowledged' },
        preserveScroll: true
    });
};

const startWork = (ticket) => {
    startWorkForm.put(route('tickets.update', ticket.id), {
        data: { status: 'in_progress' },
        preserveScroll: true
    });
};

const openResolveModal = (ticket) => {
    selectedTicket.value = ticket;
    resolveForm.resolution_notes = '';
    showResolveModal.value = true;
};

const resolveTicket = () => {
    resolveForm.post(route('tickets.resolve', selectedTicket.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showResolveModal.value = false;
            selectedTicket.value = null;
        }
    });
};

// Use composables
const { formatDateTime: formatDate } = useFormatters();

const getCategoryIcon = (cat) => {
    return cat === 'issue' ? WrenchScrewdriverIcon : ChatBubbleBottomCenterTextIcon;
};

const getTimeAgo = (dateString) => {
    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now - date;
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffHours / 24);

    if (diffDays > 0) return `${diffDays}d ago`;
    if (diffHours > 0) return `${diffHours}h ago`;
    return 'Just now';
};
</script>

<template>
    <Head title="My Tickets" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">My Work Queue</h1>
                    <p class="text-gray-600 mt-1">Manage tickets assigned to you</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="flex items-center">
                            <ExclamationTriangleIcon class="h-8 w-8 text-red-500 mr-3" />
                            <div>
                                <div class="text-2xl font-bold text-red-600">{{ stats.urgent }}</div>
                                <div class="text-sm text-gray-500">Urgent</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="flex items-center">
                            <ClockIcon class="h-8 w-8 text-yellow-500 mr-3" />
                            <div>
                                <div class="text-2xl font-bold text-yellow-600">{{ stats.open }}</div>
                                <div class="text-sm text-gray-500">Open</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="flex items-center">
                            <PlayIcon class="h-8 w-8 text-purple-500 mr-3" />
                            <div>
                                <div class="text-2xl font-bold text-purple-600">{{ stats.in_progress || 0 }}</div>
                                <div class="text-sm text-gray-500">In Progress</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border">
                        <div class="flex items-center">
                            <CheckCircleIcon class="h-8 w-8 text-green-500 mr-3" />
                            <div>
                                <div class="text-2xl font-bold text-green-600">{{ stats.resolved }}</div>
                                <div class="text-sm text-gray-500">Resolved</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4 mb-6 border">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center">
                            <FunnelIcon class="h-5 w-5 text-gray-400 mr-2" />
                            <span class="text-sm font-medium text-gray-700">Filter:</span>
                        </div>
                        <div>
                            <select
                                v-model="status"
                                @change="applyFilters"
                                class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active (Open/In Progress)</option>
                                <option v-for="(label, value) in statuses" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <select
                                v-model="priority"
                                @change="applyFilters"
                                class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">All Priorities</option>
                                <option v-for="(label, value) in priorities" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tickets List -->
                <div class="space-y-4">
                    <div
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        class="bg-white shadow-sm rounded-lg overflow-hidden border hover:shadow-md transition"
                        :class="{
                            'border-l-4 border-l-red-500': ticket.priority === 'urgent',
                            'border-l-4 border-l-orange-500': ticket.priority === 'high'
                        }"
                    >
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-3">
                                    <component
                                        :is="getCategoryIcon(ticket.category)"
                                        :class="['h-6 w-6 mt-0.5', ticket.category === 'issue' ? 'text-orange-500' : 'text-indigo-500']"
                                    />
                                    <div>
                                        <Link
                                            :href="route('tickets.show', ticket.id)"
                                            class="text-lg font-medium text-gray-900 hover:text-indigo-600"
                                        >
                                            {{ ticket.title }}
                                        </Link>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                            <span>{{ ticket.building?.name }}</span>
                                            <span v-if="ticket.unit">- Unit {{ ticket.unit.unit_number }}</span>
                                            <span class="text-gray-300">|</span>
                                            <span>{{ ticket.subcategory }}</span>
                                            <span class="text-gray-300">|</span>
                                            <span>{{ getTimeAgo(ticket.created_at) }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-600 line-clamp-2">
                                            {{ ticket.description }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <TicketPriorityBadge :priority="ticket.priority" />
                                        <TicketStatusBadge :status="ticket.status" />
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="mt-4 pt-4 border-t flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    Reported by {{ ticket.reporter?.name || 'Unknown' }}
                                </div>
                                <div class="flex items-center space-x-2">
                                    <Link
                                        :href="route('tickets.show', ticket.id)"
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        <EyeIcon class="h-4 w-4 mr-1" />
                                        View
                                    </Link>

                                    <!-- Acknowledge Button (for open tickets) -->
                                    <button
                                        v-if="ticket.status === 'open'"
                                        @click="acknowledgeTicket(ticket)"
                                        :disabled="acknowledgeForm.processing"
                                        class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50"
                                    >
                                        <CheckCircleIcon class="h-4 w-4 mr-1" />
                                        Acknowledge
                                    </button>

                                    <!-- Start Work Button (for acknowledged tickets) -->
                                    <button
                                        v-if="ticket.status === 'acknowledged'"
                                        @click="startWork(ticket)"
                                        :disabled="startWorkForm.processing"
                                        class="inline-flex items-center px-3 py-1.5 border border-purple-300 text-xs font-medium rounded text-purple-700 bg-purple-50 hover:bg-purple-100 disabled:opacity-50"
                                    >
                                        <PlayIcon class="h-4 w-4 mr-1" />
                                        Start Work
                                    </button>

                                    <!-- Resolve Button (for in_progress tickets) -->
                                    <button
                                        v-if="ticket.status === 'in_progress'"
                                        @click="openResolveModal(ticket)"
                                        class="inline-flex items-center px-3 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100"
                                    >
                                        <CheckCircleIcon class="h-4 w-4 mr-1" />
                                        Resolve
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="tickets.data.length === 0" class="bg-white rounded-lg shadow-sm border">
                        <EmptyState
                            :icon="CheckCircleIcon"
                            title="All caught up!"
                            description="No tickets in your queue matching the current filters."
                        />
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="tickets.data.length > 0" class="mt-6 bg-white rounded-lg shadow-sm border px-4 py-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ tickets.from }} to {{ tickets.to }} of {{ tickets.total }} tickets
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-for="link in tickets.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="[
                                    link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                    'px-3 py-1 text-sm border rounded-md'
                                ]"
                                :disabled="!link.url"
                            >
                                <PaginatorLink :label="link.label" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolve Modal -->
        <Teleport to="body">
            <div v-if="showResolveModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="showResolveModal = false"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    <div class="relative z-50 inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                        <div>
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <CheckCircleIcon class="h-6 w-6 text-green-600" />
                            </div>
                            <div class="mt-3 text-center sm:mt-5">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Resolve Ticket
                                </h3>
                                <p class="mt-2 text-sm text-gray-500">
                                    {{ selectedTicket?.title }}
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="resolveTicket" class="mt-5">
                            <div>
                                <label for="resolution_notes" class="block text-sm font-medium text-gray-700">
                                    Resolution Notes <span class="text-red-500">*</span>
                                </label>
                                <textarea
                                    id="resolution_notes"
                                    v-model="resolveForm.resolution_notes"
                                    rows="4"
                                    required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    placeholder="Describe what was done to resolve this issue..."
                                ></textarea>
                                <p v-if="resolveForm.errors.resolution_notes" class="mt-1 text-sm text-red-600">
                                    {{ resolveForm.errors.resolution_notes }}
                                </p>
                            </div>

                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button
                                    type="submit"
                                    :disabled="resolveForm.processing"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
                                >
                                    <span v-if="resolveForm.processing">Resolving...</span>
                                    <span v-else>Mark as Resolved</span>
                                </button>
                                <button
                                    type="button"
                                    @click="showResolveModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
