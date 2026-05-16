<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
}

defineProps<{ users: UserRow[] }>();

const form = useForm({
    user_id: '' as string | number,
    title: 'Test push from /ops/push',
    body: 'This is a manual end-to-end push test.',
    click_url: '/dashboard',
});

const page = usePage();

function submit(): void {
    form.post(route('ops.push.send'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Push tester" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Push tester</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600">
                    Send a manual web push to any user with an active subscription. Useful for VAPID + click_url debugging.
                </p>

                <div v-if="(page.props.flash as any)?.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
                    {{ (page.props.flash as any).success }}
                </div>
                <div v-if="(page.props.flash as any)?.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ (page.props.flash as any).error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Recipient</label>
                        <select v-model="form.user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">— pick a user —</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.role }}, {{ u.email }})
                            </option>
                        </select>
                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600">{{ form.errors.user_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title</label>
                        <input v-model="form.title" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" maxlength="120" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Body</label>
                        <textarea v-model="form.body" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm" maxlength="500"></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Click URL</label>
                        <input v-model="form.click_url" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                        <p class="mt-1 text-xs text-gray-500">Where tapping the notification lands the user. Defaults to /dashboard.</p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="form.processing || !form.user_id"
                        >Send push</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
