<script setup lang="ts">
/**
 * Phase-51 LEASE-COUNTER-UI-1: status badge for lease_renewals.status.
 *
 * Phase-45 [LEASE-COUNTER] shipped the backend (counter_proposed status
 * + counter_* columns + lease_renewal_counter_history audit table) but
 * no Vue surface ever consumed it. This badge slots into the landlord
 * and tenant lease pages so both parties can see the current state at
 * a glance.
 *
 * Status → color map:
 *   counter_proposed → indigo (waiting on the other party)
 *   accepted         → emerald (negotiation closed positively)
 *   declined         → rose (negotiation closed negatively)
 *   expired          → gray (14-day window lapsed)
 */
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label?: string;
}>();

const colorClass = computed(() => {
    switch (props.status) {
        case 'counter_proposed':
            return 'bg-indigo-50 text-indigo-700 ring-indigo-200';
        case 'accepted':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        case 'declined':
            return 'bg-rose-50 text-rose-700 ring-rose-200';
        case 'expired':
            return 'bg-gray-100 text-gray-600 ring-gray-200';
        default:
            return 'bg-gray-50 text-gray-700 ring-gray-200';
    }
});

const labelText = computed(() => props.label ?? props.status.replace('_', ' '));
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', colorClass]"
    >
        <span
            v-if="status === 'counter_proposed'"
            class="h-1.5 w-1.5 animate-pulse rounded-full bg-indigo-500"
        ></span>
        {{ labelText }}
    </span>
</template>
