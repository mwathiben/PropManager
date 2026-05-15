<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useFormatters } from '@/composables/useFormatters';
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

interface Delivery {
    id: number;
    event_type: string;
    attempt: number;
    http_status: number | null;
    error: string | null;
    dispatched_at: string | null;
    completed_at: string | null;
    dead_lettered: boolean;
    can_retry: boolean;
}

interface Subscription {
    id: number;
    url: string;
    events: string[];
    active: boolean;
    last_delivery_at: string | null;
    created_at: string | null;
}

defineProps<{
    subscription: Subscription;
    deliveries: Delivery[];
}>();

const { formatDate } = useFormatters();

const statusClass = (d: Delivery): string => {
    if (d.dead_lettered) return 'text-red-700 bg-red-50';
    if (d.http_status !== null && d.http_status >= 200 && d.http_status < 300) return 'text-emerald-700 bg-emerald-50';
    if (d.http_status !== null) return 'text-amber-700 bg-amber-50';
    return 'text-gray-700 bg-gray-100';
};

const statusLabel = (d: Delivery): string => {
    if (d.dead_lettered) return 'Dead-lettered';
    if (d.http_status !== null) return `HTTP ${d.http_status}`;
    return 'Pending';
};

const retry = (d: Delivery) => {
    router.post(route('settings.webhooks.deliveries.retry', d.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Webhook deliveries" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('settings.webhooks.index')" class="text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="h-5 w-5" aria-hidden="true" />
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Delivery log</h1>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <p class="text-sm text-gray-500">Endpoint</p>
                <p class="text-sm font-mono text-gray-900 break-all mt-1">{{ subscription.url }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    Status: <span :class="subscription.active ? 'text-emerald-700' : 'text-gray-700'">{{ subscription.active ? 'Active' : 'Paused' }}</span>
                    · Subscribes to {{ subscription.events.length }} event types
                </p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Recent deliveries</h2>
                    <p class="text-sm text-gray-500 mt-1">Most recent 100 attempts.</p>
                </div>

                <div v-if="deliveries.length === 0" class="p-12 text-center text-sm text-gray-500">
                    No deliveries yet — send a test event from the subscriptions list to verify the endpoint.
                </div>

                <ul v-else class="divide-y divide-gray-200">
                    <li v-for="d in deliveries" :key="d.id" class="p-4 flex items-center gap-4">
                        <span :class="['inline-flex px-2 py-1 rounded text-xs font-medium font-mono', statusClass(d)]">
                            {{ statusLabel(d) }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-mono text-gray-900">{{ d.event_type }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Attempt #{{ d.attempt }}
                                · Dispatched {{ d.dispatched_at ? formatDate(d.dispatched_at) : '—' }}
                            </p>
                            <p v-if="d.error" class="text-xs text-red-700 mt-1 font-mono truncate">
                                {{ d.error }}
                            </p>
                        </div>
                        <button
                            v-if="d.can_retry"
                            type="button"
                            @click="retry(d)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-700 border border-indigo-200 rounded-md hover:bg-indigo-50"
                        >
                            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
                            Retry
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
