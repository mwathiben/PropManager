<script setup lang="ts">
/**
 * Phase-62 CONNECTIVITY-UX-2: per-resource "Pending sync" indicator.
 *
 * Renders an amber pill on a resource detail page when the queuedOps
 * store has at least one pending write targeting the given
 * (routeFamily, resourceId) pair. Used on Pages/Invoices/Show.vue,
 * Pages/Tickets/Show.vue, Pages/Leases/Show.vue, etc. to tell users
 * that a specific row's most recent edit hasn't reached the server
 * yet (in contrast to the global QueuedOpsTray which shows the
 * aggregate count).
 */
import { computed } from 'vue';
import { useQueuedOpsStore } from '@/stores/queuedOps';
import { useI18n } from '@/composables/useI18n';
import type { RouteFamily } from '@/composables/useBackgroundSync';

interface Props {
    routeFamily: RouteFamily;
    resourceId?: string | number;
}

const props = defineProps<Props>();
const store = useQueuedOpsStore();
const { t } = useI18n();

const pending = computed(() => store.hasPendingFor(props.routeFamily, props.resourceId));
</script>

<template>
    <span
        v-if="pending"
        role="status"
        aria-live="polite"
        data-testid="pending-sync-badge"
        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900"
    >
        <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
        {{ t('connectivity.pending_sync') }}
    </span>
</template>
