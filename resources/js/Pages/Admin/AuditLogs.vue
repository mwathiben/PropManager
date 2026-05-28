<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CursorPagination from '@/Components/CursorPagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { AdminAuditLogsPageProps } from '@/types';

const props = defineProps<AdminAuditLogsPageProps>();
const { t } = useI18n();

const filters = ref({
    event_type: props.filters.event_type || '',
    model_type: props.filters.model_type || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get(route('audit-logs.index'), {
        ...filters.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filters.value = {
        event_type: '',
        model_type: '',
        date_from: '',
        date_to: '',
        search: '',
    };
    applyFilters();
};

const viewDetails = (logId) => {
    router.visit(route('audit-logs.show', logId));
};

const exportLogs = () => {
    window.location.href = route('audit-logs.export', filters.value);
};

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
</script>

<template>
    <Head :title="t('admin_audit_logs.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ t('admin_audit_logs.title') }}
                </h1>
                <button
                    @click="exportLogs"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm"
                >
                    {{ t('admin_audit_logs.export_csv') }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_audit_logs.filters.search') }}</label>
                            <input
                                v-model="filters.search"
                                type="text"
                                :placeholder="t('admin_audit_logs.filters.search_placeholder')"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                @keyup.enter="applyFilters"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_audit_logs.filters.event_type') }}</label>
                            <select
                                v-model="filters.event_type"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                @change="applyFilters"
                            >
                                <option value="">{{ t('admin_audit_logs.filters.all_events') }}</option>
                                <option v-for="type in eventTypes" :key="type" :value="type">
                                    {{ type }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_audit_logs.filters.model_type') }}</label>
                            <select
                                v-model="filters.model_type"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                @change="applyFilters"
                            >
                                <option value="">{{ t('admin_audit_logs.filters.all_models') }}</option>
                                <option v-for="type in modelTypes" :key="type" :value="type">
                                    {{ type }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_audit_logs.filters.from_date') }}</label>
                            <input
                                v-model="filters.date_from"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                @change="applyFilters"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_audit_logs.filters.to_date') }}</label>
                            <input
                                v-model="filters.date_to"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                @change="applyFilters"
                            >
                        </div>
                    </div>
                    <div class="flex justify-end mt-4 space-x-2">
                        <button
                            @click="clearFilters"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm"
                        >
                            {{ t('admin_audit_logs.filters.clear') }}
                        </button>
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm"
                        >
                            {{ t('admin_audit_logs.filters.apply') }}
                        </button>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.datetime') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.user') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.event') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.model') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.changes') }}
                                </th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.ip') }}
                                </th>
                                <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ t('admin_audit_logs.columns.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ log.created_at }}</div>
                                    <div class="text-xs text-gray-400">{{ log.created_at_human }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="log.user" class="text-sm">
                                        <div class="font-medium text-gray-900">{{ log.user.name }}</div>
                                        <div class="text-gray-500 text-xs">{{ log.user.email }}</div>
                                    </div>
                                    <span v-else class="text-sm text-gray-400">{{ t('admin_audit_logs.system_user') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="getEventBadgeClass(log.event_color)"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ log.event_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>{{ log.auditable_type }}</div>
                                    <div class="text-xs text-gray-500">#{{ log.auditable_id }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div v-if="log.changed_fields && log.changed_fields.length" class="max-w-xs truncate">
                                        {{ log.changed_fields.join(', ') }}
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ log.ip_address || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                                    <button
                                        @click="viewDetails(log.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ t('admin_audit_logs.view_details') }}
                                    </button>
                                </td>
                            </tr>
                            <!-- Phase-20 FRONT-UX-9: EmptyState component. -->
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="px-6">
                                    <EmptyState
                                        :icon="ClipboardDocumentListIcon"
                                        :title="t('admin_audit_logs.empty.title')"
                                        :description="t('admin_audit_logs.empty.body')"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Phase-20 FRONT-UX-1: cursor pagination (was offset). -->
                    <!-- CursorPagination has no from/to/total counters — see runbook. -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <CursorPagination :paginator="logs" color="indigo" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
