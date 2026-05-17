<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { useErrorHandler, useFormatters, useCurrency } from '@/composables';
import type { CreditNoteCreatePageProps } from '@/types/templates';
import {
    DocumentPlusIcon,
    MagnifyingGlassIcon,
    UserIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<CreditNoteCreatePageProps>();

const breadcrumbItems = [
    { label: 'Finance Hub', href: route('finances.index') },
    { label: 'Credit Notes', href: route('credit-notes.index') },
    { label: 'Issue Credit Note' },
];

const { logError } = useErrorHandler();
const { formatMoney } = useFormatters();
const { currencySymbol } = useCurrency();
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedTenant = ref(null);
const showSuccess = ref(false);
let searchTimeout = null;

const form = useForm({
    tenant_id: props.tenantId || null,
    invoice_id: null,
    amount: '',
    reason: '',
    notes: '',
});

const searchTenants = async (query) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`${route('tenants.search')}?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        searchResults.value = data.data || data;
    } catch (error) {
        logError(error, { component: 'CreditNotesCreate', action: 'searchTenants' });
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

watch(searchQuery, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchTenants(newValue), 300);
});

const selectTenant = (tenant) => {
    selectedTenant.value = tenant;
    form.tenant_id = tenant.id;
    searchQuery.value = '';
    searchResults.value = [];
};

const clearTenant = () => {
    selectedTenant.value = null;
    form.tenant_id = null;
};

const submit = () => {
    form.post(route('credit-notes.store'), {
        onSuccess: () => {
            showSuccess.value = true;
        },
    });
};

</script>

<template>
    <Head title="Issue Credit Note" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <DocumentPlusIcon class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Issue Credit Note</h1>
                    <p class="text-sm text-gray-500">Create a credit adjustment for a tenant account</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Breadcrumb :items="breadcrumbItems" />
                </div>

                <!-- Success State -->
                <div v-if="showSuccess" class="bg-white rounded-xl shadow-sm border border-green-200 p-8 text-center">
                    <CheckCircleIcon class="w-16 h-16 mx-auto text-green-500 mb-4" />
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Credit Note Created</h2>
                    <p class="text-gray-600 mb-6">The credit note has been created and is pending approval.</p>
                    <div class="flex gap-4 justify-center">
                        <Link
                            :href="route('credit-notes.index')"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                        >
                            View All Credit Notes
                        </Link>
                        <button
                            @click="showSuccess = false; form.reset()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition"
                        >
                            Create Another
                        </button>
                    </div>
                </div>

                <!-- Form -->
                <form v-else @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Credit Note Details</h2>
                        <p class="text-sm text-gray-500 mt-1">Credit notes reduce the tenant's outstanding balance.</p>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Tenant Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tenant <span class="text-red-500">*</span>
                            </label>

                            <div v-if="selectedTenant" class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="p-2 bg-purple-100 rounded-full">
                                    <UserIcon class="w-5 h-5 text-purple-600" />
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900">{{ selectedTenant.name }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ selectedTenant.unit_number }} / {{ selectedTenant.building_name }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="clearTenant"
                                    class="text-sm text-purple-600 hover:text-purple-800"
                                >
                                    Change
                                </button>
                            </div>

                            <div v-else class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute start-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search tenant by name, phone, or unit..."
                                    class="w-full ps-10 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                />

                                <div v-if="searchResults.length > 0" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                                    <button
                                        v-for="tenant in searchResults"
                                        :key="tenant.id"
                                        type="button"
                                        @click="selectTenant(tenant)"
                                        class="w-full px-4 py-3 text-start hover:bg-gray-50 border-b border-gray-100 last:border-0"
                                    >
                                        <p class="font-medium text-gray-900">{{ tenant.name }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ tenant.unit_number }} / {{ tenant.building_name }}
                                        </p>
                                    </button>
                                </div>

                                <p v-if="isSearching" class="text-sm text-gray-500 mt-1">Searching...</p>
                            </div>
                            <p v-if="form.errors.tenant_id" class="text-sm text-red-600 mt-1">{{ form.errors.tenant_id }}</p>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Credit Amount <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-500">{{ currencySymbol }}</span>
                                <input
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    class="w-full ps-14 pe-4 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                />
                            </div>
                            <p v-if="form.errors.amount" class="text-sm text-red-600 mt-1">{{ form.errors.amount }}</p>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="reason"
                                v-model="form.reason"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                            >
                                <option value="">Select a reason</option>
                                <option v-for="(label, value) in reasonOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.reason" class="text-sm text-red-600 mt-1">{{ form.errors.reason }}</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes <span class="text-gray-400">(optional)</span>
                            </label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                placeholder="Additional details about this credit..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                            ></textarea>
                            <p v-if="form.errors.notes" class="text-sm text-red-600 mt-1">{{ form.errors.notes }}</p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <Link
                            :href="route('credit-notes.index')"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.tenant_id || !form.amount || !form.reason"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Credit Note' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
