<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import FormSubmitButton from '@/Components/FormSubmitButton.vue';
import { useFormatters } from '@/composables/useFormatters';
import { BoltIcon, TrashIcon, ClipboardDocumentIcon, ListBulletIcon, PauseCircleIcon, PlayCircleIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

interface Subscription {
    id: number;
    url: string;
    events: string[];
    active: boolean;
    last_delivery_at: string | null;
    created_at: string | null;
}

const props = defineProps<{
    subscriptions: Subscription[];
    availableEvents: Record<string, string>;
    plaintextSecret: string | null;
}>();

const { formatDate } = useFormatters();

const form = useForm<{
    url: string;
    events: string[];
}>({
    url: 'https://',
    events: [],
});

const showSecret = ref(props.plaintextSecret !== null);
const copied = ref(false);

const submit = () => {
    form.post(route('settings.webhooks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.url = 'https://';
        },
    });
};

const toggleActive = (sub: Subscription) => {
    router.patch(
        route('settings.webhooks.update', sub.id),
        { active: !sub.active },
        { preserveScroll: true },
    );
};

const revoke = (sub: Subscription) => {
    if (!confirm(`Delete the webhook for ${sub.url}? Pending deliveries will be cancelled.`)) {
        return;
    }
    router.delete(route('settings.webhooks.destroy', sub.id), { preserveScroll: true });
};

const test = (sub: Subscription) => {
    router.post(route('settings.webhooks.test', sub.id), {}, { preserveScroll: true });
};

const copySecret = async () => {
    if (!props.plaintextSecret) return;
    try {
        await navigator.clipboard.writeText(props.plaintextSecret);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // clipboard unavailable
    }
};
</script>

<template>
    <Head title="Webhooks" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Webhooks</h1>
        </template>

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <div
                v-if="showSecret && plaintextSecret"
                role="alert"
                class="bg-amber-50 border border-amber-300 rounded-xl p-6"
            >
                <h2 class="text-base font-semibold text-amber-900 mb-2">Save this signing secret — you will not see it again</h2>
                <p class="text-sm text-amber-800 mb-4">
                    Use this to verify the <code class="font-mono">X-PropManager-Signature</code>
                    header on incoming webhook deliveries (HMAC-SHA256 of the raw body).
                </p>
                <div class="flex items-center gap-2 bg-white rounded-md border border-amber-200 p-3 font-mono text-sm">
                    <span class="flex-1 break-all">{{ plaintextSecret }}</span>
                    <button
                        type="button"
                        @click="copySecret"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-600 text-white text-xs font-medium rounded-md hover:bg-amber-700"
                    >
                        <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <button
                    type="button"
                    @click="showSecret = false"
                    class="mt-4 text-sm text-amber-900 underline hover:text-amber-700"
                >
                    I've saved it — hide this banner
                </button>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Register a new webhook</h2>
                <p class="text-sm text-gray-500 mb-4">
                    PropManager will POST event payloads to this URL, signed with HMAC-SHA256.
                    Your endpoint must respond 2xx within 10 seconds; non-2xx triggers exponential-backoff retries (5 attempts max, then dead-letter).
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel required for="webhook-url" value="Endpoint URL (https only)" />
                        <TextInput
                            id="webhook-url"
                            v-model="form.url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://your-app.example.com/hooks/propmanager"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.url" />
                    </div>

                    <div>
                        <InputLabel value="Subscribe to events" />
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                v-for="(description, event) in availableEvents"
                                :key="event"
                                class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                            >
                                <input
                                    type="checkbox"
                                    :value="event"
                                    v-model="form.events"
                                    class="mt-0.5"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 font-mono truncate">{{ event }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ description }}</p>
                                </div>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.events" />
                    </div>

                    <div class="flex justify-end">
                        <FormSubmitButton :processing="form.processing">
                            Register webhook
                        </FormSubmitButton>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Active subscriptions</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Pause a subscription to stop deliveries without losing the configuration.
                    </p>
                </div>

                <div v-if="subscriptions.length === 0" class="p-12 text-center">
                    <BoltIcon class="h-12 w-12 mx-auto text-gray-300" aria-hidden="true" />
                    <p class="mt-3 text-sm text-gray-500">No webhook subscriptions yet.</p>
                </div>

                <ul v-else class="divide-y divide-gray-200">
                    <li v-for="sub in subscriptions" :key="sub.id" class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-mono text-gray-900 truncate">{{ sub.url }}</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="event in sub.events"
                                        :key="event"
                                        class="inline-block px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-mono rounded"
                                    >{{ event }}</span>
                                </div>
                                <dl class="mt-2 text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                                    <div>
                                        <dt class="inline">Status:</dt>
                                        <dd class="inline ml-1 font-medium" :class="sub.active ? 'text-emerald-700' : 'text-gray-700'">
                                            {{ sub.active ? 'Active' : 'Paused' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="inline">Last delivery:</dt>
                                        <dd class="inline ml-1">{{ sub.last_delivery_at ? formatDate(sub.last_delivery_at) : 'Never' }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button
                                    type="button"
                                    @click="test(sub)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-700 border border-indigo-200 rounded-md hover:bg-indigo-50"
                                    aria-label="Send test delivery"
                                >
                                    <PaperAirplaneIcon class="h-4 w-4" aria-hidden="true" />
                                    Test
                                </button>
                                <button
                                    type="button"
                                    @click="toggleActive(sub)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50"
                                >
                                    <PauseCircleIcon v-if="sub.active" class="h-4 w-4" aria-hidden="true" />
                                    <PlayCircleIcon v-else class="h-4 w-4" aria-hidden="true" />
                                    {{ sub.active ? 'Pause' : 'Resume' }}
                                </button>
                                <Link
                                    :href="route('settings.webhooks.show', sub.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50"
                                >
                                    <ListBulletIcon class="h-4 w-4" aria-hidden="true" />
                                    Log
                                </Link>
                                <button
                                    type="button"
                                    @click="revoke(sub)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-700 border border-red-200 rounded-md hover:bg-red-50"
                                >
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
