<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    log: Object,
});

const getEventBadgeClass = (color) => {
    const classes = {
        green: 'bg-green-100 text-green-800',
        blue: 'bg-blue-100 text-blue-800',
        red: 'bg-red-100 text-red-800',
        purple: 'bg-purple-100 text-purple-800',
        yellow: 'bg-yellow-100 text-yellow-800',
        orange: 'bg-orange-100 text-orange-800',
        gray: 'bg-gray-100 text-gray-800',
        indigo: 'bg-indigo-100 text-indigo-800',
    };
    return classes[color] || classes.gray;
};

const formatJson = (data) => {
    if (!data) return null;
    return JSON.stringify(data, null, 2);
};
</script>

<template>
    <Head title="Audit Log Detail" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <Link
                    :href="route('audit-logs.index')"
                    class="text-gray-500 hover:text-gray-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Audit Log #{{ log.id }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Summary Card -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <span
                                :class="getEventBadgeClass(log.event_color)"
                                class="px-3 py-1 text-sm font-medium rounded-full"
                            >
                                {{ log.event_type }}
                            </span>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">
                                {{ log.description }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ log.created_at }} ({{ log.created_at_human }})
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- User Info -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">User</h4>
                        <div v-if="log.user">
                            <p class="text-lg font-medium text-gray-900">{{ log.user.name }}</p>
                            <p class="text-sm text-gray-500">{{ log.user.email }}</p>
                        </div>
                        <p v-else class="text-gray-400">System Action</p>
                    </div>

                    <!-- Model Info -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Model</h4>
                        <p class="text-lg font-medium text-gray-900">{{ log.auditable_type }}</p>
                        <p class="text-sm text-gray-500">ID: {{ log.auditable_id }}</p>
                    </div>

                    <!-- Request Info -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Request Info</h4>
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm text-gray-500">IP Address</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ log.ip_address || '-' }}</dd>
                            </div>
                            <div v-if="log.url">
                                <dt class="text-sm text-gray-500">URL</dt>
                                <dd class="text-sm font-medium text-gray-900 break-all">{{ log.url }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Changed Fields -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Changed Fields</h4>
                        <div v-if="log.changed_fields && log.changed_fields.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="field in log.changed_fields"
                                :key="field"
                                class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm"
                            >
                                {{ field }}
                            </span>
                        </div>
                        <p v-else class="text-gray-400 text-sm">No fields tracked</p>
                    </div>
                </div>

                <!-- Values Comparison -->
                <div v-if="log.old_values || log.new_values" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Old Values -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 bg-red-400 rounded-full mr-2"></span>
                                Old Values
                            </span>
                        </h4>
                        <pre v-if="log.old_values" class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">{{ formatJson(log.old_values) }}</pre>
                        <p v-else class="text-gray-400 text-sm">No previous values</p>
                    </div>

                    <!-- New Values -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                                New Values
                            </span>
                        </h4>
                        <pre v-if="log.new_values" class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">{{ formatJson(log.new_values) }}</pre>
                        <p v-else class="text-gray-400 text-sm">No new values</p>
                    </div>
                </div>

                <!-- Metadata -->
                <div v-if="log.metadata" class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Additional Metadata</h4>
                    <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">{{ formatJson(log.metadata) }}</pre>
                </div>

                <!-- User Agent -->
                <div v-if="log.user_agent" class="bg-white rounded-lg shadow-sm p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">User Agent</h4>
                    <p class="text-sm text-gray-600 break-all">{{ log.user_agent }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
