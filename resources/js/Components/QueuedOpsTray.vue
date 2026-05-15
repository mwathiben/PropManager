<script setup lang="ts">
/**
 * Phase-26 PWA-NETWORK-3: bottom-right tray that surfaces ops queued
 * for background-sync replay. Visible only when count > 0 (same
 * silent-when-empty discipline as OnlineIndicator).
 *
 * Wired into AuthenticatedLayout so it spans every authenticated
 * page — a user can leave Invoices/Create after submitting offline
 * and the tray follows them.
 */
import { ref } from 'vue';
import { useQueuedOpsStore } from '@/stores/queuedOps';
import { useI18n } from '@/composables/useI18n';
import { CloudArrowUpIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const store = useQueuedOpsStore();
const { t } = useI18n();
const expanded = ref(false);

function toggle(): void {
    expanded.value = !expanded.value;
}

function ageSeconds(queuedAt: number): number {
    return Math.max(1, Math.floor((Date.now() - queuedAt) / 1000));
}
</script>

<template>
    <div
        v-if="store.hasPending"
        class="fixed bottom-4 right-4 z-50 max-w-xs"
        role="region"
        :aria-label="t('offline.queue.aria') as string"
    >
        <!-- Collapsed badge -->
        <button
            v-if="!expanded"
            type="button"
            class="flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 text-sm font-medium text-amber-900 shadow-md ring-1 ring-amber-200 transition hover:bg-amber-200"
            @click="toggle"
        >
            <CloudArrowUpIcon class="h-4 w-4" aria-hidden="true" />
            {{ t('offline.queue.badge', { count: store.count }) }}
        </button>

        <!-- Expanded tray -->
        <div
            v-else
            class="rounded-lg border border-amber-200 bg-white shadow-lg"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2">
                <h2 class="text-sm font-semibold text-gray-900">
                    {{ t('offline.queue.title') }}
                </h2>
                <button
                    type="button"
                    class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    :aria-label="t('offline.queue.collapse') as string"
                    @click="toggle"
                >
                    <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
            <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
                <li
                    v-for="op in store.items"
                    :key="op.id"
                    class="flex items-start justify-between gap-2 px-4 py-2 text-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-gray-900">{{ op.label }}</p>
                        <p class="text-xs text-gray-500">
                            {{ t('offline.queue.queued_secs_ago', { seconds: ageSeconds(op.queuedAt) }) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                        @click="store.cancel(op.id)"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </li>
            </ul>
            <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
                {{ t('offline.queue.footer') }}
            </p>
        </div>
    </div>
</template>
