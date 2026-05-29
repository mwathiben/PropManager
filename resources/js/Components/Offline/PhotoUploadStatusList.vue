<script setup lang="ts">
/**
 * Phase-62 OFFLINE-PHOTOS-2: per-ticket status list of pending /
 * uploading / failed offline photo captures.
 *
 * Mounted from Pages/Tickets/Show.vue (Phase-45 surface). Surfaces
 * the offline-photo retry handle so users know their work was saved
 * even if the upload didn't reach the server yet.
 */
import { ref, onMounted, computed } from 'vue';
import {
    listPendingForTicket,
    discardPhoto,
    type OfflinePhotoEntry,
} from '@/lib/offlinePhotoStore';
import { useI18n } from '@/composables/useI18n';

interface Props {
    ticketId: number;
}

const props = defineProps<Props>();
const { t } = useI18n();
const entries = ref<OfflinePhotoEntry[]>([]);
const loading = ref(true);

async function refresh(): Promise<void> {
    entries.value = await listPendingForTicket(props.ticketId);
    loading.value = false;
}

async function cancel(key: string): Promise<void> {
    await discardPhoto(key);
    await refresh();
}

onMounted(refresh);

const hasEntries = computed(() => entries.value.length > 0);
</script>

<template>
    <div v-if="hasEntries" class="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-2">
        <div class="text-sm font-semibold text-amber-900">
            {{ t('photo_upload_status_list.pending_sync', entries.length, { count: entries.length }) }}
        </div>
        <ul class="space-y-1">
            <li
                v-for="e in entries"
                :key="e.key"
                class="flex items-center justify-between gap-2 text-xs text-amber-800"
                :data-testid="`photo-status-${e.key}`"
            >
                <div class="flex items-center gap-2">
                    <span
                        :class="[
                            'inline-block h-2 w-2 rounded-full', /* i18n-ignore */
                            e.status === 'pending' && 'bg-amber-400',
                            e.status === 'uploading' && 'bg-blue-400 animate-pulse',
                            e.status === 'failed' && 'bg-rose-500',
                        ]"
                    ></span>
                    <span class="capitalize">{{ t(`photo_upload_status_list.status.${e.status}`, e.status ?? '') }}</span>
                    <span class="text-amber-700">{{ t('photo_upload_status_list.attempt', { n: e.attempts }) }}</span>
                </div>
                <button
                    type="button"
                    class="text-amber-700 hover:text-amber-900 underline"
                    @click="cancel(e.key)"
                >
                    {{ t('photo_upload_status_list.cancel') }}
                </button>
            </li>
        </ul>
    </div>
</template>
