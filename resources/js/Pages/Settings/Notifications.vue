<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useWebPush } from '@/composables/useWebPush';
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Props {
    preferences: Record<string, boolean>;
    transactional_locked: string[];
    toggleable_types: string[];
    channels: string[];
}

const props = defineProps<Props>();

const state = reactive<Record<string, boolean>>({ ...props.preferences });
const saving = ref<Record<string, boolean>>({});
const lastError = ref<string | null>(null);

const webPush = useWebPush();

const isLocked = (type: string): boolean => props.transactional_locked.includes(type);

async function toggleType(type: string): Promise<void> {
    if (isLocked(type)) return;
    const key = `${type}_enabled`;
    const enabled = !state[key];
    state[key] = enabled;
    await persist({ type, enabled }, key);
}

async function toggleChannel(channel: string): Promise<void> {
    const key = `${channel}_enabled`;
    const enabled = !state[key];
    state[key] = enabled;
    await persist({ type: channel === 'push' ? 'lifecycle' : 'lifecycle', channel, enabled }, key);
}

async function persist(payload: { type: string; channel?: string; enabled: boolean }, key: string): Promise<void> {
    saving.value[key] = true;
    lastError.value = null;
    try {
        const response = await fetch('/api/v1/notifications/preferences', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            const body = (await response.json().catch(() => ({}))) as { message?: string };
            lastError.value = body.message ?? `Save failed (${response.status})`;
            // revert
            state[key] = !payload.enabled;
        }
    } catch (err) {
        lastError.value = err instanceof Error ? err.message : 'Network error';
        state[key] = !payload.enabled;
    } finally {
        saving.value[key] = false;
    }
}

async function subscribePush(): Promise<void> {
    const ok = await webPush.subscribe();
    if (!ok) {
        lastError.value = webPush.error.value ?? 'Failed to subscribe';
    }
}

async function unsubscribePush(): Promise<void> {
    await webPush.unsubscribe();
}
</script>

<template>
    <Head title="Notification preferences" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Notification preferences</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" data-test="push-permission-card">
                    <h2 class="text-base font-semibold text-gray-900">Browser push notifications</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Receive in-browser alerts for invoices, lease events and system messages.
                    </p>

                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-green-100 text-green-800': webPush.permission.value === 'granted',
                                'bg-yellow-100 text-yellow-800': webPush.permission.value === 'default',
                                'bg-red-100 text-red-800': webPush.permission.value === 'denied',
                            }"
                        >{{ webPush.permission.value }}</span>

                        <button
                            v-if="!webPush.isSubscribed.value && webPush.permission.value !== 'denied'"
                            type="button"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="webPush.isLoading.value"
                            @click="subscribePush"
                        >Enable push</button>

                        <button
                            v-if="webPush.isSubscribed.value"
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="webPush.isLoading.value"
                            @click="unsubscribePush"
                        >Disable push</button>

                        <p v-if="webPush.permission.value === 'denied'" class="text-sm text-gray-600">
                            Push is blocked at the browser level — enable it from your browser's site settings to subscribe.
                        </p>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" data-test="channel-toggles">
                    <h2 class="text-base font-semibold text-gray-900">Channels</h2>
                    <p class="mt-1 text-sm text-gray-600">Globally enable or disable delivery channels.</p>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="channel in props.channels" :key="channel" class="flex items-center justify-between py-2">
                            <span class="text-sm font-medium text-gray-900">{{ channel }}</span>
                            <label class="inline-flex items-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                    :checked="state[`${channel}_enabled`]"
                                    :disabled="saving[`${channel}_enabled`]"
                                    @change="toggleChannel(channel)"
                                />
                            </label>
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" data-test="type-toggles">
                    <h2 class="text-base font-semibold text-gray-900">Notification types</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Transactional types (invoice, receipt) cannot be disabled.
                    </p>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="type in [...props.toggleable_types, ...props.transactional_locked]" :key="type" class="flex items-center justify-between py-2">
                            <span class="text-sm" :class="isLocked(type) ? 'text-gray-400' : 'text-gray-900 font-medium'">
                                {{ type }}
                                <span v-if="isLocked(type)" class="ms-2 text-xs text-gray-400">(locked)</span>
                            </span>
                            <label class="inline-flex items-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                    :checked="state[`${type}_enabled`]"
                                    :disabled="isLocked(type) || saving[`${type}_enabled`]"
                                    @change="toggleType(type)"
                                />
                            </label>
                        </li>
                    </ul>
                </section>

                <p v-if="lastError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ lastError }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
