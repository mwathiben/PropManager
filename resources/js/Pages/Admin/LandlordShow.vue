<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import {
    BuildingOffice2Icon,
    ArrowLeftIcon,
    UserGroupIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

interface Unit { id: number; status?: string }
interface Building { id: number; name: string; units?: Unit[] }
interface Property { id: number; name: string; address?: string; buildings?: Building[] }
interface Landlord { id: number; name: string; email: string; mobile_number?: string | null; created_at?: string }

interface Props {
    landlord: Landlord;
    properties?: Property[];
    stats?: {
        properties_count?: number;
        buildings_count?: number;
        units_count?: number;
        occupied_units?: number;
        total_revenue?: number;
        total_invoiced?: number;
    };
    caretakers?: { id: number; name: string; email: string }[];
}

const props = defineProps<Props>();
const { formatCurrency, formatDate } = useFormatters();

const impersonate = () => {
    router.post(route('admin.impersonate', props.landlord.id));
};
</script>

<template>
    <Head :title="landlord.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.landlords')" class="text-gray-400 hover:text-gray-600"><ArrowLeftIcon class="w-5 h-5" /></Link>
                <h1 class="text-lg font-semibold text-gray-900">{{ landlord.name }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 rounded-xl bg-blue-100 flex items-center justify-center">
                            <BuildingOffice2Icon class="w-7 h-7 text-blue-600" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ landlord.name }}</h2>
                            <p class="text-sm text-gray-500">{{ landlord.email }}</p>
                            <p v-if="landlord.mobile_number" class="text-sm text-gray-500">{{ landlord.mobile_number }}</p>
                            <p v-if="landlord.created_at" class="text-xs text-gray-400 mt-1">Joined {{ formatDate(landlord.created_at) }}</p>
                        </div>
                    </div>
                    <button
                        @click="impersonate"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-600"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        View as landlord
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats?.properties_count ?? 0 }}</p><p class="text-sm text-gray-500">Properties</p></div>
                <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats?.buildings_count ?? 0 }}</p><p class="text-sm text-gray-500">Buildings</p></div>
                <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ stats?.occupied_units ?? 0 }} / {{ stats?.units_count ?? 0 }}</p><p class="text-sm text-gray-500">Occupied units</p></div>
                <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(stats?.total_revenue ?? 0) }}</p><p class="text-sm text-gray-500">Total collected</p></div>
                <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(stats?.total_invoiced ?? 0) }}</p><p class="text-sm text-gray-500">Total invoiced</p></div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Properties</h3></div>
                <ul v-if="properties?.length" class="divide-y divide-gray-100">
                    <li v-for="property in properties" :key="property.id" class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ property.name }}</p>
                        <p v-if="property.address" class="text-xs text-gray-500">{{ property.address }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ property.buildings?.length ?? 0 }} building(s)</p>
                    </li>
                </ul>
                <p v-else class="px-6 py-6 text-sm text-gray-500">No properties yet.</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                    <UserGroupIcon class="w-5 h-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900">Caretakers</h3>
                </div>
                <ul v-if="caretakers?.length" class="divide-y divide-gray-100">
                    <li v-for="caretaker in caretakers" :key="caretaker.id" class="px-6 py-3">
                        <p class="text-sm font-medium text-gray-900">{{ caretaker.name }}</p>
                        <p class="text-xs text-gray-500">{{ caretaker.email }}</p>
                    </li>
                </ul>
                <p v-else class="px-6 py-6 text-sm text-gray-500">No caretakers.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
