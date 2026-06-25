<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useAuth } from '@/composables/useAuth';
import type { AdminLandlordsPageProps } from '@/types';
import {
    MagnifyingGlassIcon,
    PlusIcon,
    EyeIcon,
    ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<AdminLandlordsPageProps>();
const { can } = useAuth();
const { t } = useI18n();

const searchQuery = ref(props.filters?.search || '');
const showCreateModal = ref(false);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    mobile_number: '',
});

const search = () => {
    router.get(route('admin.landlords'), { search: searchQuery.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const createLandlord = () => {
    createForm.post(route('admin.landlords.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const impersonate = (landlordId) => {
    if (confirm(t('admin_landlords.confirm.impersonate'))) {
        router.post(route('admin.impersonate', landlordId));
    }
};

// Use composables
const { formatCurrency, formatDate } = useFormatters();

const getOccupancyRate = (occupied, total) => {
    if (!total) return 0;
    return Math.round((occupied / total) * 100);
};
</script>

<template>
    <Head :title="t('admin_landlords.title')" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">{{ t('admin_landlords.heading') }}</h1>
                    <button @click="showCreateModal = true"
                            class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <PlusIcon class="h-5 w-5 me-2" />
                        {{ t('admin_landlords.add') }}
                    </button>
                </div>

                <!-- Search -->
                <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
                    <form @submit.prevent="search" class="flex gap-4">
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                            <input type="text"
                                   v-model="searchQuery"
                                   :placeholder="t('admin_landlords.search_placeholder')"
                                   class="w-full ps-10 pe-4 py-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <button type="submit"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            {{ t('admin_landlords.search') }}
                        </button>
                    </form>
                </div>

                <!-- Landlords Table -->
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div v-if="landlords.data.length === 0" class="p-8 text-center text-gray-500">
                        {{ t('admin_landlords.empty') }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.landlord') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.properties') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.units') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.occupancy') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.revenue') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.joined') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ t('admin_landlords.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="landlord in landlords.data" :key="landlord.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ landlord.name }}</div>
                                            <div class="text-sm text-gray-500">{{ landlord.email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ landlord.properties_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ landlord.occupied_units }} / {{ landlord.units_count }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center">
                                            <div class="w-20 bg-gray-200 rounded-full h-2 me-2">
                                                <div class="bg-green-500 h-2 rounded-full"
                                                     :style="{ width: getOccupancyRate(landlord.occupied_units, landlord.units_count) + '%' }">
                                                </div>
                                            </div>
                                            <span class="text-xs text-gray-500">
                                                {{ getOccupancyRate(landlord.occupied_units, landlord.units_count) }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatCurrency(landlord.total_revenue) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(landlord.created_at) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <Link :href="route('admin.landlords.show', landlord.id)"
                                                  class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                                <EyeIcon class="h-4 w-4 me-1" />
                                                {{ t('admin_landlords.view') }}
                                            </Link>
                                            <button v-if="can('access-admin')" @click="impersonate(landlord.id)"
                                                    class="text-gray-600 hover:text-gray-900 flex items-center">
                                                <ArrowRightOnRectangleIcon class="h-4 w-4 me-1" />
                                                {{ t('admin_landlords.login_as') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="landlords.links && landlords.links.length > 3" class="px-4 py-3 border-t bg-gray-50">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">
                                {{ t('admin_landlords.pagination.showing', { from: landlords.from, to: landlords.to, total: landlords.total }) }}
                            </span>
                            <div class="flex gap-2">
                                <Link v-if="landlords.prev_page_url"
                                      :href="landlords.prev_page_url"
                                      class="px-3 py-1 border rounded text-sm hover:bg-gray-100">
                                    {{ t('admin_landlords.pagination.previous') }}
                                </Link>
                                <Link v-if="landlords.next_page_url"
                                      :href="landlords.next_page_url"
                                      class="px-3 py-1 border rounded text-sm hover:bg-gray-100">
                                    {{ t('admin_landlords.pagination.next') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showCreateModal = false"></div>
                <div class="relative z-50 bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('admin_landlords.modal.title') }}</h3>
                    <form @submit.prevent="createLandlord" class="space-y-4">
                        <div>
                            <label for="create-landlord-name" class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_landlords.modal.name') }}</label>
                            <input type="text"
                                   id="create-landlord-name"
                                   v-model="createForm.name"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   required />
                            <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="create-landlord-email" class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_landlords.modal.email') }}</label>
                            <input type="email"
                                   id="create-landlord-email"
                                   v-model="createForm.email"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   required />
                            <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="create-landlord-password" class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_landlords.modal.password') }}</label>
                            <input type="password"
                                   id="create-landlord-password"
                                   v-model="createForm.password"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   required />
                            <p v-if="createForm.errors.password" class="mt-1 text-sm text-red-600">{{ createForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="create-landlord-mobile" class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin_landlords.modal.mobile') }}</label>
                            <input type="text"
                                   id="create-landlord-mobile"
                                   v-model="createForm.mobile_number"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button"
                                    @click="showCreateModal = false"
                                    class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">
                                {{ t('admin_landlords.modal.cancel') }}
                            </button>
                            <button type="submit"
                                    :disabled="createForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ createForm.processing ? t('admin_landlords.modal.creating') : t('admin_landlords.modal.create') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
