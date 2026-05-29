<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface Row {
    id: number;
    name: string;
    email: string;
    preference: 'paystack' | 'stripe' | 'auto';
    paystack_enabled: boolean;
    stripe_enabled: boolean;
}

defineProps<{ rows: Row[] }>();
const { t } = useI18n();

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
    <Head :title="t('admin_gateways_index.head_title')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">{{ t('admin_gateways_index.heading') }}</h1>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600">
                    {{ t('admin_gateways_index.description_prefix') }}<strong>{{ t('admin_gateways_index.auto_label') }}</strong>{{ t('admin_gateways_index.description_suffix') }}
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
                                <th class="px-4 py-2 text-start">{{ t('admin_gateways_index.table.landlord') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('admin_gateways_index.table.email') }}</th>
                                <th class="px-4 py-2 text-center">{{ t('admin_gateways_index.table.paystack') }}</th>
                                <th class="px-4 py-2 text-center">{{ t('admin_gateways_index.table.stripe') }}</th>
                                <th class="px-4 py-2 text-start">{{ t('admin_gateways_index.table.preference') }}</th>
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
                                        <option value="auto">{{ t('admin_gateways_index.options.auto') }}</option>
                                        <option value="paystack">{{ t('admin_gateways_index.options.paystack') }}</option>
                                        <option value="stripe">{{ t('admin_gateways_index.options.stripe') }}</option>
                                    </select>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ t('admin_gateways_index.empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
