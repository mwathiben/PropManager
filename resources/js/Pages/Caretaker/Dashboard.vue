<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import ActionItemCard from '@/Components/ActionItemCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import { useFormatters, useStatusColors } from '@/composables';
import {
    WrenchScrewdriverIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    TicketIcon,
    ClipboardDocumentListIcon,
    HomeModernIcon,
    UserGroupIcon,
    PhoneIcon,
    EnvelopeIcon,
    ChevronRightIcon,
    BuildingOffice2Icon,
    ArrowRightIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    property: Object,
    buildings: Array,
    actionItems: {
        type: Object,
        default: () => ({
            urgent_tickets: 0,
            open_tickets: 0,
            pending_readings: 0
        })
    },
    ticketStats: {
        type: Object,
        default: () => ({
            total: 0,
            open: 0,
            urgent: 0,
            resolved: 0
        })
    },
    todaysTasks: {
        type: Array,
        default: () => []
    },
    unitStats: {
        type: Object,
        default: () => ({
            total: 0,
            occupied: 0,
            vacant: 0,
            maintenance: 0
        })
    },
    hasWaterEnabled: {
        type: Boolean,
        default: false
    },
    landlord: {
        type: Object,
        default: null
    }
});

// Use composables
const { formatDate } = useFormatters();
const { ticketPriorityColor: getPriorityBadgeClass } = useStatusColors();

const getPriorityIcon = (priority) => {
    if (priority === 'urgent') return '🔴';
    if (priority === 'high') return '🟠';
    if (priority === 'normal') return '🔵';
    return '⚪';
};
</script>

<template>
    <Head title="Caretaker Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ property?.name || 'Property' }} Operations</h1>
                    <p class="text-sm text-gray-500">{{ buildings?.length || 0 }} Building(s) Assigned</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="hasWaterEnabled" :href="route('readings.index')"
                          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        <ClipboardDocumentListIcon class="w-4 h-4 mr-2" />
                        Record Readings
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 lg:p-8 space-y-6">
            <!-- === ACTION ITEMS (Task-Focused) === -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <ActionItemCard
                    v-if="actionItems.urgent_tickets > 0"
                    urgency="critical"
                    :icon="ExclamationTriangleIcon"
                    :count="actionItems.urgent_tickets"
                    title="Urgent Tickets"
                    description="Require immediate attention"
                    actionLabel="View"
                    :actionHref="route('tickets.index', { priority: 'urgent' })"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="CheckCircleIcon"
                    :count="0"
                    title="No Urgent Issues"
                    description="All urgent tickets resolved"
                />

                <ActionItemCard
                    v-if="actionItems.open_tickets > 0"
                    urgency="medium"
                    :icon="TicketIcon"
                    :count="actionItems.open_tickets"
                    title="Open Tickets"
                    description="Awaiting resolution"
                    actionLabel="View All"
                    :actionHref="route('tickets.index')"
                />
                <ActionItemCard
                    v-else
                    urgency="low"
                    :icon="TicketIcon"
                    :count="0"
                    title="No Open Tickets"
                    description="All tickets resolved"
                />

                <ActionItemCard
                    v-if="hasWaterEnabled && actionItems.pending_readings > 0"
                    urgency="medium"
                    :icon="ClipboardDocumentListIcon"
                    :count="actionItems.pending_readings"
                    title="Pending Readings"
                    description="Awaiting input"
                    actionLabel="Input"
                    :actionHref="route('readings.index')"
                />
                <MetricCard
                    v-else
                    title="Total Units"
                    :value="unitStats.total"
                    :subtitle="unitStats.occupied + ' occupied'"
                    :icon="HomeModernIcon"
                    iconBgColor="bg-indigo-100"
                    iconColor="text-indigo-600"
                />
            </div>

            <!-- === TODAY'S TASKS (Priority Sorted) === -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900">Today's Tasks</h3>
                        <p class="text-sm text-gray-500">Priority sorted tickets assigned to you</p>
                    </div>
                    <Link :href="route('tickets.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center">
                        View All <ChevronRightIcon class="w-4 h-4 ml-1" />
                    </Link>
                </div>

                <div v-if="todaysTasks.length === 0" class="p-8 text-center">
                    <CheckCircleIcon class="h-12 w-12 text-green-400 mx-auto mb-3" />
                    <p class="text-gray-600 font-medium">All caught up!</p>
                    <p class="text-sm text-gray-400">No tasks assigned to you</p>
                </div>

                <div v-else class="divide-y divide-gray-100">
                    <Link v-for="task in todaysTasks" :key="task.id"
                          :href="route('tickets.show', task.id)"
                          class="block px-6 py-4 hover:bg-gray-50 transition">
                        <div class="flex items-start gap-4">
                            <!-- Priority Indicator -->
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-lg"
                                 :class="task.priority === 'urgent' ? 'bg-red-100' :
                                         task.priority === 'high' ? 'bg-orange-100' :
                                         task.priority === 'normal' ? 'bg-blue-100' : 'bg-gray-100'">
                                {{ getPriorityIcon(task.priority) }}
                            </div>

                            <!-- Task Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ task.title }}</p>
                                        <p class="text-sm text-gray-500 mt-0.5">
                                            <span v-if="task.unit">Unit {{ task.unit.unit_number }} •</span>
                                            <span v-if="task.building">{{ task.building.name }}</span>
                                        </p>
                                    </div>
                                    <span class="text-xs px-2.5 py-1 rounded-full border font-medium uppercase"
                                          :class="getPriorityBadgeClass(task.priority)">
                                        {{ task.priority }}
                                    </span>
                                </div>
                                <p v-if="task.description" class="text-sm text-gray-500 mt-2 line-clamp-2">
                                    {{ task.description }}
                                </p>
                            </div>

                            <!-- Arrow -->
                            <ArrowRightIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
                        </div>
                    </Link>
                </div>
            </div>

            <!-- === QUICK ACTIONS + UNIT STATUS === -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <Link v-if="hasWaterEnabled" :href="route('readings.index')"
                              class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <ClipboardDocumentListIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Input Water Readings</p>
                                    <p class="text-sm text-gray-500">Record monthly meter readings</p>
                                </div>
                            </div>
                            <ChevronRightIcon class="w-5 h-5 text-gray-400" />
                        </Link>

                        <Link :href="route('tickets.index')"
                              class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-xl hover:bg-yellow-100 transition">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                    <TicketIcon class="w-5 h-5 text-yellow-600" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">View My Tickets</p>
                                    <p class="text-sm text-gray-500">
                                        {{ ticketStats.open }} open tickets
                                    </p>
                                </div>
                            </div>
                            <ChevronRightIcon class="w-5 h-5 text-gray-400" />
                        </Link>

                        <Link :href="route('tickets.create')"
                              class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <WrenchScrewdriverIcon class="w-5 h-5 text-green-600" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Report New Issue</p>
                                    <p class="text-sm text-gray-500">Create a maintenance ticket</p>
                                </div>
                            </div>
                            <ChevronRightIcon class="w-5 h-5 text-gray-400" />
                        </Link>
                    </div>
                </div>

                <!-- Unit Status Overview -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Unit Status Overview</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-green-50 border border-green-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <UserGroupIcon class="w-5 h-5 text-green-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-700">{{ unitStats.occupied }}</p>
                                    <p class="text-sm text-green-600">Occupied</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                    <HomeModernIcon class="w-5 h-5 text-gray-500" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-700">{{ unitStats.vacant }}</p>
                                    <p class="text-sm text-gray-500">Vacant</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                    <WrenchScrewdriverIcon class="w-5 h-5 text-orange-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-orange-700">{{ unitStats.maintenance }}</p>
                                    <p class="text-sm text-orange-600">Maintenance</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <BuildingOffice2Icon class="w-5 h-5 text-indigo-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-indigo-700">{{ unitStats.total }}</p>
                                    <p class="text-sm text-indigo-600">Total Units</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Stats -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">My Ticket Summary</h4>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Resolved</span>
                            <span class="font-semibold text-green-600">{{ ticketStats.resolved }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-2">
                            <span class="text-gray-500">Open</span>
                            <span class="font-semibold text-yellow-600">{{ ticketStats.open }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-2">
                            <span class="text-gray-500">Total Assigned</span>
                            <span class="font-semibold text-gray-900">{{ ticketStats.total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === LANDLORD CONTACT === -->
            <div v-if="landlord" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4">Landlord Contact</h3>
                <div class="flex items-start gap-4">
                    <div class="h-14 w-14 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-xl">
                        {{ landlord.name?.charAt(0) || '?' }}
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 text-lg">{{ landlord.name }}</p>
                        <div class="flex flex-wrap gap-4 mt-2">
                            <a v-if="landlord.mobile_number"
                               :href="'tel:' + landlord.mobile_number"
                               class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700 text-sm">
                                <PhoneIcon class="w-4 h-4" />
                                {{ landlord.mobile_number }}
                            </a>
                            <a v-if="landlord.email"
                               :href="'mailto:' + landlord.email"
                               class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700 text-sm">
                                <EnvelopeIcon class="w-4 h-4" />
                                {{ landlord.email }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
