<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Row {
    id: number;
    name: string;
    email: string;
    preference: 'paystack' | 'stripe' | 'auto';
    paystack_enabled: boolean;
    stripe_enabled: boolean;
}

defineProps<{ rows: Row[] }>();

const page = usePage();
const updatingId = ref<number | null>(null);

function updatePreference(row: Row, preference: string): void {
    updatingId.value = row.id;
    router.post(
        route('admin.gateways.update', { user: row.id }),
        { preference },
        {
            preserveScroll: true,
            onFinish: () => (updatingId.value = null),
        },
    );
}
</script>

<template>
    <Head title="Gateway preferences" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Gateway preferences</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600">
                    Set each landlord's preferred payment gateway. <strong>auto</strong> means
                    KES routes to Paystack and USD/EUR/GBP routes to Stripe. Forced choices
                    override the currency rule for support cases.
                </p>

                <div v-if="(page.props.flash as any)?.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
                    {{ (page.props.flash as any).success }}
                </div>
                <div v-if="(page.props.flash as any)?.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ (page.props.flash as any).error }}
                </div>

                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">Landlord</th>
                                <th class="px-4 py-2 text-start">Email</th>
                                <th class="px-4 py-2 text-center">Paystack</th>
                                <th class="px-4 py-2 text-center">Stripe</th>
                                <th class="px-4 py-2 text-start">Preference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-900">{{ row.name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ row.email }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span :class="row.paystack_enabled ? 'text-green-600' : 'text-gray-400'">
                                        {{ row.paystack_enabled ? '●' : '○' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span :class="row.stripe_enabled ? 'text-green-600' : 'text-gray-400'">
                                        {{ row.stripe_enabled ? '●' : '○' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <select
                                        :value="row.preference"
                                        @change="updatePreference(row, ($event.target as HTMLSelectElement).value)"
                                        :disabled="updatingId === row.id"
                                        class="rounded-md border-gray-300 text-sm"
                                    >
                                        <option value="auto">auto (by currency)</option>
                                        <option value="paystack">Paystack</option>
                                        <option value="stripe">Stripe</option>
                                    </select>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No landlords found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
