<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { BeakerIcon } from '@heroicons/vue/24/outline';

interface Reading {
    id: number;
    reading_date: string;
    consumption: number | string;
    cost: number | string;
    status: string;
}

defineProps<{ hasUnit: boolean; readings: Reading[] }>();

const { t } = useI18n();
const { formatCurrency, formatDate } = useFormatters();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('water.tenant.title')" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-100 rounded-lg">
                    <BeakerIcon class="w-6 h-6 text-cyan-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ t('water.tenant.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ t('water.tenant.subtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8" data-testid="tenant-water">
            <p v-if="!hasUnit || readings.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                {{ t('water.tenant.empty') }}
            </p>

            <div v-else class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('water.tenant.date') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('water.tenant.consumption') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('water.tenant.cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in readings" :key="r.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ formatDate(r.reading_date) }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ Number(r.consumption) }}</td>
                            <td class="px-4 py-3 text-end font-medium text-gray-900">{{ formatCurrency(Number(r.cost)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
