<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

interface ExpiringDoc {
    id: number;
    title: string;
    document_type: string;
    expires_at: string;
    days_remaining: number;
}

const docs = computed<ExpiringDoc[]>(() => {
    const props = usePage().props as { tenantExpiringDocs?: ExpiringDoc[] };
    return props.tenantExpiringDocs ?? [];
});
</script>

<template>
    <div
        v-if="docs.length > 0"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-start gap-3"
        role="alert"
    >
        <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="flex-1">
            <p class="text-sm font-medium text-amber-900">
                {{ docs.length === 1 ? '1 document is expiring soon' : `${docs.length} documents are expiring soon` }}
            </p>
            <ul class="mt-2 space-y-1">
                <li
                    v-for="doc in docs"
                    :key="doc.id"
                    class="text-xs text-amber-800 flex items-center gap-2"
                >
                    <CalendarDaysIcon class="w-3 h-3" />
                    <span>{{ doc.title }} ({{ doc.document_type }}) —
                        <span v-if="doc.days_remaining < 0" class="font-semibold">expired {{ Math.abs(doc.days_remaining) }} days ago</span>
                        <span v-else-if="doc.days_remaining === 0" class="font-semibold">expires today</span>
                        <span v-else>expires in {{ doc.days_remaining }} days</span>
                    </span>
                </li>
            </ul>
            <Link
                :href="route('tenant.documents.index')"
                class="mt-2 inline-block text-xs font-medium text-amber-900 underline hover:text-amber-700"
            >
                Review documents
            </Link>
        </div>
    </div>
</template>
