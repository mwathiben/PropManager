<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

/**
 * Phase-64 INBOX-MOUNT-1: lightweight slide-over dialog for the
 * 'Message this tenant' CTA on Pages/Tenants/Show.vue. Inlined here
 * (not a route detour) so the landlord stays on the tenant detail
 * page until the thread exists, then the server's redirect-to
 * message-threads.show takes over.
 */
const props = defineProps<{
    tenantId: number;
    tenantName: string;
}>();

const isOpen = ref(false);

const form = useForm({
    participants: [props.tenantId],
    body: '',
    title: '' as string,
});

function open(): void {
    form.reset();
    form.body = '';
    form.title = '';
    form.participants = [props.tenantId];
    isOpen.value = true;
}

function close(): void {
    isOpen.value = false;
}

function submit(): void {
    form.post(route('message-threads.store'), {
        forceFormData: false,
        preserveScroll: false,
        onSuccess: () => {
            close();
        },
    });
}

defineExpose({ open });
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50"
            role="dialog"
            aria-modal="true"
            :aria-label="`Message ${tenantName}`"
            data-testid="initiate-thread-dialog"
            @click.self="close"
        >
            <div class="bg-white rounded-t-xl sm:rounded-xl shadow-xl w-full sm:max-w-lg p-6 space-y-4">
                <header class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Message {{ tenantName }}
                    </h2>
                    <button
                        type="button"
                        @click="close"
                        class="rounded-md p-1 text-gray-500 hover:bg-gray-100"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </header>

                <form @submit.prevent="submit" class="space-y-3">
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="200"
                        placeholder="Subject (optional)"
                        class="w-full rounded-md border-gray-300 text-sm"
                    />
                    <textarea
                        v-model="form.body"
                        rows="4"
                        maxlength="4000"
                        placeholder="Write your message…"
                        class="w-full rounded-md border-gray-300 text-sm"
                        required
                    />
                    <p
                        v-if="form.errors.body"
                        class="text-xs text-rose-700"
                    >{{ form.errors.body }}</p>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="close"
                            class="px-4 py-2 rounded-md text-sm text-gray-700 hover:bg-gray-100"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || form.body.length === 0"
                            class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
