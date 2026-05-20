<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue';
import DocumentIcon from '@heroicons/vue/24/outline/DocumentIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import { useI18n } from '@/composables/useI18n';

/**
 * Phase-64 INBOX-POLISH-2: thumbnail preview for files queued in the
 * inbox compose form. Lets users spot mis-uploads (wrong file picked
 * from photo gallery) BEFORE submit. Image MIMEs render a 64x64
 * thumbnail via URL.createObjectURL; non-image MIMEs render an icon
 * + filename chip.
 *
 * Memory: URL.revokeObjectURL is called on unmount + on per-row
 * remove so blob URLs don't leak in long-lived compose sessions.
 */
interface PreviewEntry {
    file: File;
    objectUrl: string | null;
    isImage: boolean;
}

const props = defineProps<{
    files: File[];
}>();

const emit = defineEmits<{
    (event: 'remove', index: number): void;
}>();

const { t } = useI18n();

const IMAGE_MIME_PREFIX = 'image/';

const entries = computed<PreviewEntry[]>(() =>
    props.files.map((file) => {
        const isImage = file.type.startsWith(IMAGE_MIME_PREFIX);

        return {
            file,
            objectUrl: isImage ? URL.createObjectURL(file) : null,
            isImage,
        };
    }),
);

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function remove(index: number, entry: PreviewEntry): void {
    if (entry.objectUrl !== null) {
        URL.revokeObjectURL(entry.objectUrl);
    }
    emit('remove', index);
}

onBeforeUnmount(() => {
    for (const entry of entries.value) {
        if (entry.objectUrl !== null) {
            URL.revokeObjectURL(entry.objectUrl);
        }
    }
});
</script>

<template>
    <div v-if="files.length > 0" class="space-y-2">
        <p class="text-xs text-gray-500" data-testid="attachment-scan-hint">
            {{ t('inbox.scan.hint') }}
        </p>
        <ul
            class="flex flex-wrap gap-2"
            data-testid="attachment-preview-list"
        >
            <li
                v-for="(entry, index) in entries"
                :key="`${entry.file.name}-${entry.file.size}-${index}`"
                class="relative inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 pr-3 shadow-sm"
            >
                <img
                    v-if="entry.isImage && entry.objectUrl"
                    :src="entry.objectUrl"
                    :alt="entry.file.name"
                    class="h-16 w-16 rounded object-cover"
                />
                <div
                    v-else
                    class="flex h-16 w-16 items-center justify-center rounded bg-gray-100"
                >
                    <DocumentIcon class="h-8 w-8 text-gray-500" aria-hidden="true" />
                </div>

                <div class="flex flex-col text-xs">
                    <span class="font-medium text-gray-900 truncate max-w-[10rem]">
                        {{ entry.file.name }}
                    </span>
                    <span class="text-gray-500">{{ formatSize(entry.file.size) }}</span>
                </div>

                <button
                    type="button"
                    @click="remove(index, entry)"
                    class="absolute top-1 right-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/90 text-gray-500 hover:text-rose-600 hover:bg-rose-50"
                    :aria-label="`Remove ${entry.file.name}`"
                >
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </li>
        </ul>
    </div>
</template>
