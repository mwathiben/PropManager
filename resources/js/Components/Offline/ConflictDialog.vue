<script setup lang="ts">
/**
 * Phase-62 CONFLICT-RESOLUTION-3: last-write-wins-with-warning prompt.
 *
 * Surfaces when a queued POST replay receives a 409 carrying the
 * current row state + per-field diff. User picks one of:
 *   - overwrite: re-POST with the now-current version
 *   - discard: silently drop the queued op
 *   - merge:   field-level checkboxes select which side wins
 *
 * Mounted globally from AuthenticatedLayout via a teleport so any
 * page can trigger it via the conflictDialog store (or directly via
 * the writeConflict bus emitted from a fetch interceptor — wiring
 * comes in a follow-up).
 */
import { ref, computed } from 'vue';

interface ConflictPayload {
    current_version: number;
    current: Record<string, unknown>;
    incoming: Record<string, unknown>;
    diff: Record<string, { current: unknown; incoming: unknown }>;
}

interface Props {
    open: boolean;
    payload: ConflictPayload | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'resolve', resolution: 'overwrite' | 'discard' | 'merge', mergedFields?: Record<string, 'current' | 'incoming'>): void;
}>();

const mergeChoices = ref<Record<string, 'current' | 'incoming'>>({});

const fieldKeys = computed(() => (props.payload ? Object.keys(props.payload.diff) : []));
const hasDiff = computed(() => fieldKeys.value.length > 0);

function onMergeChoice(field: string, choice: 'current' | 'incoming'): void {
    mergeChoices.value[field] = choice;
}

function onOverwrite(): void {
    emit('resolve', 'overwrite');
}

function onDiscard(): void {
    emit('resolve', 'discard');
}

function onMerge(): void {
    emit('resolve', 'merge', { ...mergeChoices.value });
}
</script>

<template>
    <div
        v-if="open && payload"
        role="dialog"
        aria-modal="true"
        aria-labelledby="conflict-dialog-title"
        data-testid="conflict-dialog"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
    >
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 space-y-4">
            <h2 id="conflict-dialog-title" class="text-lg font-semibold text-gray-900">
                Conflict — this record changed while you were offline
            </h2>
            <p class="text-sm text-gray-600">
                Someone else updated this record. Your queued change conflicts with the latest version
                (server version {{ payload.current_version }}).
            </p>

            <div v-if="hasDiff" class="border border-gray-200 rounded-lg divide-y divide-gray-200">
                <div
                    v-for="field in fieldKeys"
                    :key="field"
                    class="p-3 grid grid-cols-3 gap-2 items-center text-sm"
                    :data-testid="`conflict-field-${field}`"
                >
                    <div class="font-medium text-gray-700">{{ field }}</div>
                    <label class="flex items-center gap-2">
                        <input
                            type="radio"
                            :name="`merge-${field}`"
                            value="current"
                            @change="onMergeChoice(field, 'current')"
                            class="text-indigo-600"
                        />
                        <span class="text-gray-600 truncate">
                            Server: {{ payload.diff[field].current }}
                        </span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            type="radio"
                            :name="`merge-${field}`"
                            value="incoming"
                            @change="onMergeChoice(field, 'incoming')"
                            class="text-indigo-600"
                        />
                        <span class="text-gray-600 truncate">
                            Your change: {{ payload.diff[field].incoming }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-gray-200">
                <button
                    type="button"
                    @click="onDiscard"
                    class="px-4 py-2 rounded text-sm bg-gray-100 text-gray-700"
                    data-testid="conflict-discard"
                >
                    Discard my change
                </button>
                <button
                    type="button"
                    @click="onMerge"
                    :disabled="!hasDiff"
                    class="px-4 py-2 rounded text-sm bg-amber-100 text-amber-900 disabled:opacity-50"
                    data-testid="conflict-merge"
                >
                    Merge selected fields
                </button>
                <button
                    type="button"
                    @click="onOverwrite"
                    class="px-4 py-2 rounded text-sm bg-rose-600 text-white"
                    data-testid="conflict-overwrite"
                >
                    Overwrite server version
                </button>
            </div>
        </div>
    </div>
</template>
