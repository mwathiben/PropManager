<script setup lang="ts">
/**
 * Phase-20 FRONT-UX-1 (closes Phase-19 INDEX-6): cursor-paginator UI.
 *
 * Unlike the offset-based Pagination.vue, cursor pagination has no
 * from/to/total counters — the trade-off for constant-time seek on
 * unbounded tables (audit_logs, tenant_activities). See
 * docs/runbooks/policy-and-index.md section 3.3 + the upcoming
 * docs/runbooks/frontend-authz-and-ux.md for when to choose cursor
 * over offset.
 *
 * Expected props shape from Laravel ->cursorPaginate():
 *   {
 *     data: [...],
 *     next_page_url: string | null,
 *     prev_page_url: string | null,
 *     per_page: number,
 *     path: string,
 *   }
 */
import { router } from '@inertiajs/vue3';

interface CursorPaginator {
    next_page_url: string | null;
    prev_page_url: string | null;
    per_page?: number;
}

interface Props {
    paginator: CursorPaginator;
    wrapperClass?: string;
    color?: 'emerald' | 'indigo';
}

const props = withDefaults(defineProps<Props>(), {
    wrapperClass: '',
    color: 'indigo',
});

const colorClasses = {
    emerald: {
        active: 'bg-emerald-600 text-white hover:bg-emerald-700',
        disabled: 'bg-gray-100 text-gray-400 cursor-not-allowed',
    },
    indigo: {
        active: 'bg-indigo-600 text-white hover:bg-indigo-700',
        disabled: 'bg-gray-100 text-gray-400 cursor-not-allowed',
    },
};

function navigate(url: string | null) {
    if (!url) {
        return;
    }
    router.visit(url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div
        v-if="paginator.next_page_url || paginator.prev_page_url"
        :class="['flex justify-center gap-2', wrapperClass]"
    >
        <button
            type="button"
            :aria-label="'Previous page'"
            :disabled="!paginator.prev_page_url"
            :class="[
                'px-4 py-1.5 text-sm rounded-lg transition-colors',
                paginator.prev_page_url ? colorClasses[color].active : colorClasses[color].disabled,
            ]"
            @click="navigate(paginator.prev_page_url)"
        >
            &laquo; Previous
        </button>
        <button
            type="button"
            :aria-label="'Next page'"
            :disabled="!paginator.next_page_url"
            :class="[
                'px-4 py-1.5 text-sm rounded-lg transition-colors',
                paginator.next_page_url ? colorClasses[color].active : colorClasses[color].disabled,
            ]"
            @click="navigate(paginator.next_page_url)"
        >
            Next &raquo;
        </button>
    </div>
</template>
