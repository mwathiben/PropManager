<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    BuildingOfficeIcon,
    GlobeAltIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables/useFormatters';

interface Building {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    description: string | null;
    default_amount: number;
    always_apply: boolean;
    is_active: boolean;
    landlord_id: number | null;
    building_id: number | null;
    building?: Building | null;
}

interface PaginatedCategories {
    data: Category[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    categories: PaginatedCategories;
    buildings: Building[];
    canCreate: boolean;
}>();

const { formatCurrency } = useFormatters();

const showModal = ref(false);
const editingCategory = ref<Category | null>(null);

const form = useForm({
    name: '',
    description: '',
    default_amount: 0,
    building_id: null as number | null,
    always_apply: false,
    is_active: true,
});

const isEditing = computed(() => editingCategory.value !== null);
const modalTitle = computed(() => isEditing.value ? 'Edit Category' : 'Add Category');

const isGlobal = (category: Category) => category.landlord_id === null;
const canEdit = (category: Category) => !isGlobal(category) && props.canCreate;
const canDelete = (category: Category) => !isGlobal(category) && props.canCreate;

const openCreateModal = () => {
    editingCategory.value = null;
    form.reset();
    form.is_active = true;
    showModal.value = true;
};

const openEditModal = (category: Category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.description = category.description ?? '';
    form.default_amount = category.default_amount;
    form.building_id = category.building_id;
    form.always_apply = category.always_apply;
    form.is_active = category.is_active;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value && editingCategory.value) {
        form.put(route('move-out-categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('move-out-categories.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCategory = (category: Category) => {
    if (confirm(`Are you sure you want to delete "${category.name}"? This cannot be undone.`)) {
        router.delete(route('move-out-categories.destroy', category.id), {
            preserveScroll: true,
        });
    }
};

const toggleAlwaysApply = (category: Category) => {
    if (!canEdit(category)) return;

    router.put(route('move-out-categories.update', category.id), {
        always_apply: !category.always_apply,
    }, {
        preserveScroll: true,
    });
};

const toggleActive = (category: Category) => {
    if (!canEdit(category)) return;

    router.put(route('move-out-categories.update', category.id), {
        is_active: !category.is_active,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Move-Out Deduction Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Move-Out Deduction Categories
                </h2>
                <button
                    v-if="canCreate"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="openCreateModal"
                >
                    <PlusIcon class="h-5 w-5" />
                    Add Category
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="mb-4 text-sm text-gray-500">
                            Configure deduction categories for move-out inspections. Categories marked
                            "Always Apply" will be automatically added when an inspection starts.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Category
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Scope
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Default Amount
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Always Apply
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Active
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr
                                        v-for="category in categories.data"
                                        :key="category.id"
                                        class="hover:bg-gray-50"
                                        :class="{ 'opacity-50': !category.is_active }"
                                    >
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ category.name }}</div>
                                            <div v-if="category.description" class="text-sm text-gray-500">
                                                {{ category.description }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                v-if="isGlobal(category)"
                                                class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800"
                                            >
                                                <GlobeAltIcon class="h-3.5 w-3.5" />
                                                Platform Default
                                            </span>
                                            <span
                                                v-else-if="category.building"
                                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"
                                            >
                                                <BuildingOfficeIcon class="h-3.5 w-3.5" />
                                                {{ category.building.name }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800"
                                            >
                                                All Buildings
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-900">
                                            {{ formatCurrency(category.default_amount) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-center">
                                            <button
                                                type="button"
                                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                                                :class="[
                                                    category.always_apply ? 'bg-indigo-600' : 'bg-gray-200',
                                                    !canEdit(category) ? 'cursor-not-allowed opacity-50' : ''
                                                ]"
                                                :disabled="!canEdit(category)"
                                                @click="toggleAlwaysApply(category)"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="category.always_apply ? 'translate-x-5' : 'translate-x-0'"
                                                />
                                            </button>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-center">
                                            <button
                                                type="button"
                                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2"
                                                :class="[
                                                    category.is_active ? 'bg-green-600' : 'bg-gray-200',
                                                    !canEdit(category) ? 'cursor-not-allowed opacity-50' : ''
                                                ]"
                                                :disabled="!canEdit(category)"
                                                @click="toggleActive(category)"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="category.is_active ? 'translate-x-5' : 'translate-x-0'"
                                                />
                                            </button>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    v-if="canEdit(category)"
                                                    type="button"
                                                    class="text-indigo-600 hover:text-indigo-900"
                                                    @click="openEditModal(category)"
                                                >
                                                    <PencilSquareIcon class="h-5 w-5" />
                                                </button>
                                                <button
                                                    v-if="canDelete(category)"
                                                    type="button"
                                                    class="text-red-600 hover:text-red-900"
                                                    @click="deleteCategory(category)"
                                                >
                                                    <TrashIcon class="h-5 w-5" />
                                                </button>
                                                <span
                                                    v-if="isGlobal(category)"
                                                    class="text-xs text-gray-400"
                                                >
                                                    Read-only
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="categories.data.length === 0">
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                            No deduction categories found. Add your first category to get started.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination v-if="categories.last_page > 1" :links="categories.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal" />

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle">&#8203;</span>

                <div class="relative inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submitForm">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ modalTitle }}</h3>

                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">
                                        Category Name
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="e.g., Cleaning Fee"
                                        required
                                    />
                                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700">
                                        Description
                                    </label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Optional description"
                                    />
                                </div>

                                <div>
                                    <label for="default_amount" class="block text-sm font-medium text-gray-700">
                                        Default Amount (KES)
                                    </label>
                                    <input
                                        id="default_amount"
                                        v-model.number="form.default_amount"
                                        type="number"
                                        min="0"
                                        step="100"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        required
                                    />
                                    <p v-if="form.errors.default_amount" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.default_amount }}
                                    </p>
                                </div>

                                <div>
                                    <label for="building_id" class="block text-sm font-medium text-gray-700">
                                        Scope
                                    </label>
                                    <select
                                        id="building_id"
                                        v-model="form.building_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option :value="null">All Buildings</option>
                                        <option v-for="building in buildings" :key="building.id" :value="building.id">
                                            {{ building.name }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Select a specific building or leave as "All Buildings" for a global category.
                                    </p>
                                </div>

                                <div class="flex items-center gap-6">
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="form.always_apply"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="text-sm text-gray-700">Always Apply</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                        />
                                        <span class="text-sm text-gray-700">Active</span>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">
                                    "Always Apply" categories will be automatically added when a move-out inspection starts.
                                </p>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 sm:ml-3 sm:w-auto"
                            >
                                {{ form.processing ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                            </button>
                            <button
                                type="button"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
