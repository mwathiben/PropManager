<script setup lang="ts">
/**
 * Phase-51 LEASE-COUNTER-UI-3: timeline of lease_renewal_counter_history.
 *
 * Phase-45 captures every counter-proposal exchange (proposed_rent +
 * actor_role + action + timestamp). Without a Vue surface the audit
 * trail is invisible to both parties — this component renders the
 * timeline most-recent first with a role-keyed icon, action label,
 * KES-formatted rent, and relative timestamp.
 *
 * Layout: vertical timeline with a left rail (rounded marker per item).
 */
import { computed } from 'vue';

type HistoryEntry = {
    id: number;
    actor_role: 'landlord' | 'tenant' | 'caretaker' | string;
    action: string;
    proposed_rent: number | null;
    created_at: string;
    note?: string | null;
};

const props = defineProps<{
    history: HistoryEntry[];
}>();

const sorted = computed(() =>
    [...props.history].sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at)),
);

function actorColor(role: string): string {
    if (role === 'landlord') return 'bg-indigo-500';
    if (role === 'tenant') return 'bg-emerald-500';
    if (role === 'caretaker') return 'bg-amber-500';
    return 'bg-gray-400';
}

function formatRent(amount: number | null): string {
    if (amount === null || amount === undefined) return '—';
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(amount);
}

function relativeTime(iso: string): string {
    const then = Date.parse(iso);
    if (Number.isNaN(then)) return iso;
    const diffMs = Date.now() - then;
    const minutes = Math.round(diffMs / 60_000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.round(hours / 24);
    if (days < 30) return `${days}d ago`;
    return new Date(then).toLocaleDateString();
}
</script>

<template>
    <ol class="relative space-y-4 border-s border-gray-200 ps-4">
        <li v-for="entry in sorted" :key="entry.id" class="relative">
            <span
                :class="['absolute -start-[7px] mt-1 h-3 w-3 rounded-full ring-2 ring-white', actorColor(entry.actor_role)]"
                aria-hidden="true"
            ></span>
            <div class="rounded-md bg-gray-50 px-3 py-2 text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium text-gray-900">
                        <span class="capitalize">{{ entry.actor_role }}</span>
                        {{ entry.action.replace('_', ' ') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ relativeTime(entry.created_at) }}</p>
                </div>
                <p v-if="entry.proposed_rent !== null" class="mt-1 text-xs text-gray-700">
                    Proposed rent: <span class="font-medium">{{ formatRent(entry.proposed_rent) }}</span>
                </p>
                <p v-if="entry.note" class="mt-1 text-xs text-gray-600">{{ entry.note }}</p>
            </div>
        </li>
        <li v-if="sorted.length === 0" class="text-xs text-gray-500">No counter-offer history yet.</li>
    </ol>
</template>
