<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Participant {
    id: number;
    name: string;
    role: string;
}

interface Thread {
    id: number;
    title: string | null;
    status: 'open' | 'archived' | 'locked';
    last_message_at: string | null;
    participants: Participant[];
}

interface Props {
    threads: {
        data: Thread[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

defineProps<Props>();

const form = useForm({
    title: '',
    body: '',
});

function submit() {
    form.post(route('tenant.inbox.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Inbox" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header>
                <h1 class="text-2xl font-semibold text-gray-900">Inbox</h1>
                <p class="text-sm text-gray-500">Messages with your landlord and the property team.</p>
            </header>

            <form @submit.prevent="submit" class="rounded-lg bg-white p-4 shadow" data-testid="tenant-inbox-compose">
                <input
                    v-model="form.title"
                    type="text"
                    maxlength="200"
                    placeholder="Subject (optional)"
                    class="mb-2 w-full rounded-md border-gray-300 shadow-sm text-sm"
                />
                <textarea
                    v-model="form.body"
                    rows="3"
                    maxlength="4000"
                    placeholder="Write your landlord a message…"
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                ></textarea>
                <button
                    type="submit"
                    :disabled="form.processing || form.body.length === 0"
                    class="mt-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    Send new message
                </button>
            </form>

            <ul class="divide-y divide-gray-200 bg-white shadow rounded-lg overflow-hidden">
                <li v-for="thread in threads.data" :key="thread.id" class="hover:bg-gray-50">
                    <Link :href="route('tenant.inbox.show', thread.id)" class="block px-4 py-4">
                        <p class="text-sm font-medium text-gray-900">
                            {{ thread.title || `Thread #${thread.id}` }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ thread.participants.map(p => p.name).join(', ') }}
                        </p>
                    </Link>
                </li>
                <li v-if="threads.data.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                    No messages yet. Send your first one above.
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
