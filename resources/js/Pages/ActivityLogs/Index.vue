<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CursorPagination from '@/Components/CursorPagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
const { formatDateTime } = useFormatters();
import type { ActivityLogsIndexPageProps } from '@/types';
import {
    ClipboardDocumentListIcon,
    MagnifyingGlassIcon,
    CalendarDaysIcon,
    ClockIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ActivityLogsIndexPageProps>();

// Filter state
const search = ref(props.filters.search || '');
const type = ref(props.filters.type || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

// Apply filters
const applyFilters = () => {
    router.get(route('activity-logs.index'), {
        search: search.value || undefined,
        type: type.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    type.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

</script>

<template>
    <Head title="Activity Logs" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Activity Logs</h1>
                    <p class="text-gray-600 mt-1">Track all tenant and property activities</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-indigo-100 rounded-full">
                                <ClipboardDocumentListIcon class="w-6 h-6 text-indigo-600" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Activities</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.total_activities }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-full">
                                <CalendarDaysIcon class="w-6 h-6 text-green-600" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Today</p>
                                <p class="text-2xl font-bold text-green-600">{{ stats.today }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-full">
                                <ClockIcon class="w-6 h-6 text-blue-600" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">This Week</p>
                                <p class="text-2xl font-bold text-blue-600">{{ stats.this_week }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-full">
                                <CalendarDaysIcon class="w-6 h-6 text-purple-600" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">This Month</p>
                                <p class="text-2xl font-bold text-purple-600">{{ stats.this_month }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-white shadow-sm rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search by description..."
                                    class="w-full pl-10 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Activity Type</label>
                            <select
                                v-model="type"
                                @change="applyFilters"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Types</option>
                                <option v-for="activityType in activityTypes" :key="activityType.value" :value="activityType.value">
                                    {{ activityType.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input
                                v-model="dateFrom"
                                @change="applyFilters"
                                type="date"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input
                                v-model="dateTo"
                                @change="applyFilters"
                                type="date"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>

                        <div class="flex items-end">
                            <button
                                @click="clearFilters"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Timeline/Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="flow-root">
                        <ul role="list" class="divide-y divide-gray-200">
                            <li v-for="activity in activities.data" :key="activity.id" class="px-6 py-4 hover:bg-gray-50">
                                <div class="flex items-start space-x-4">
                                    <!-- Activity Type Badge -->
                                    <div class="shrink-0">
                                        <span
                                            :class="activity.type_color"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        >
                                            {{ activity.type_label }}
                                        </span>
                                    </div>

                                    <!-- Activity Content -->
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-900">
                                            {{ activity.description }}
                                        </p>
                                        <div class="mt-1 flex items-center gap-4 text-xs text-gray-500">
                                            <!-- Tenant -->
                                            <span v-if="activity.tenant" class="flex items-center gap-1">
                                                <UserIcon class="w-3.5 h-3.5" />
                                                {{ activity.tenant.name }}
                                            </span>
                                            <!-- Performer -->
                                            <span v-if="activity.performer" class="flex items-center gap-1">
                                                By: {{ activity.performer.name }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Timestamp -->
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs text-gray-500">{{ formatDateTime(activity.created_at) }}</p>
                                        <p class="text-xs text-gray-400">{{ activity.created_at_human }}</p>
                                    </div>
                                </div>
                            </li>
                            <li v-if="activities.data.length === 0" class="px-6 py-12 text-center">
                                <ClipboardDocumentListIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                                <p class="text-lg font-medium text-gray-500">No activity logs found</p>
                                <p class="text-sm text-gray-400">Activities will appear here as they occur</p>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Phase-20 FRONT-UX-1: cursor pagination (was offset). -->
                <CursorPagination v-if="activities.data.length > 0" :paginator="activities" color="indigo" class="mt-6" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
