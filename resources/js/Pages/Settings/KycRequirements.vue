<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    BuildingOfficeIcon,
    GlobeAltIcon,
    CheckCircleIcon,
    XCircleIcon,
    ArrowLeftIcon,
    DocumentCheckIcon,
} from '@heroicons/vue/24/outline';

interface Building {
    id: number;
    name: string;
}

interface KycRequirement {
    id: number;
    requirement_type: string;
    label: string;
    description: string | null;
    is_required: boolean;
    is_active: boolean;
    landlord_id: number | null;
    building_id: number | null;
    building?: Building | null;
    sort_order: number;
    is_platform_default: boolean;
}

interface PaginatedRequirements {
    data: KycRequirement[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    requirements: PaginatedRequirements;
    buildings: Building[];
    canCreate: boolean;
}>();

const { can } = useAuth();

const showModal = ref(false);
const editingRequirement = ref<KycRequirement | null>(null);

const form = useForm({
    requirement_type: '',
    label: '',
    description: '',
    building_id: null as number | null,
    is_required: true,
    is_active: true,
    sort_order: 0,
});

const isEditing = computed(() => editingRequirement.value !== null);
const modalTitle = computed(() => isEditing.value ? 'Edit Requirement' : 'Add Requirement');

const isPlatformDefault = (req: KycRequirement) => req.is_platform_default;
const canEdit = (req: KycRequirement) => !isPlatformDefault(req) && props.canCreate;
const canDelete = (req: KycRequirement) => !isPlatformDefault(req) && props.canCreate;

const getScopeLabel = (req: KycRequirement): string => {
    if (isPlatformDefault(req)) return 'Platform Default';
    if (req.building) return `Building: ${req.building.name}`;
    return 'All Buildings';
};

const getScopeIcon = (req: KycRequirement) => {
    if (isPlatformDefault(req)) return GlobeAltIcon;
    if (req.building) return BuildingOfficeIcon;
    return GlobeAltIcon;
};

const openCreateModal = () => {
    editingRequirement.value = null;
    form.reset();
    form.is_required = true;
    form.is_active = true;
    showModal.value = true;
};

const openEditModal = (req: KycRequirement) => {
    editingRequirement.value = req;
    form.requirement_type = req.requirement_type;
    form.label = req.label;
    form.description = req.description ?? '';
    form.building_id = req.building_id;
    form.is_required = req.is_required;
    form.is_active = req.is_active;
    form.sort_order = req.sort_order;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRequirement.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value && editingRequirement.value) {
        form.put(route('kyc-requirements.update', editingRequirement.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('kyc-requirements.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const deleteRequirement = (req: KycRequirement) => {
    if (confirm(`Are you sure you want to delete "${req.label}"? This cannot be undone.`)) {
        router.delete(route('kyc-requirements.destroy', req.id), {
            preserveScroll: true,
        });
    }
};

const toggleRequired = (req: KycRequirement) => {
    if (!canEdit(req)) return;

    router.put(route('kyc-requirements.update', req.id), {
        is_required: !req.is_required,
    }, {
        preserveScroll: true,
    });
};

const toggleActive = (req: KycRequirement) => {
    if (!canEdit(req)) return;

    router.put(route('kyc-requirements.update', req.id), {
        is_active: !req.is_active,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="KYC Requirements" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('settings.index')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                    >
                        <ArrowLeftIcon class="w-4 h-4 me-1" />
                        Back to Settings
                    </Link>
                    <h1 class="text-xl font-semibold leading-tight text-gray-800">
                        KYC Requirements
                    </h1>
                </div>
                <button
                    v-if="canCreate"
                    type="button"
                    data-testid="add-requirement-button"
                    @click="openCreateModal"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <PlusIcon class="w-5 h-5 me-2" />
                    Add Requirement
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <p class="mb-6 text-sm text-gray-600">
                            Configure KYC document requirements for your tenants. Platform defaults are read-only. You can add custom requirements for all buildings or specific buildings.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-start text-gray-500 uppercase">
                                            Label
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-start text-gray-500 uppercase">
                                            Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-start text-gray-500 uppercase">
                                            Scope
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                            Required
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                            Active
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-end text-gray-500 uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="req in requirements.data"
                                        :key="req.id"
                                        :data-testid="`requirement-row-${req.id}`"
                                        :class="{ 'bg-gray-50': isPlatformDefault(req) }"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <DocumentCheckIcon class="w-5 h-5 me-3 text-gray-400" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ req.label }}
                                                    </div>
                                                    <div v-if="req.description" class="text-xs text-gray-500">
                                                        {{ req.description }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded">
                                                {{ req.requirement_type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <component :is="getScopeIcon(req)" class="w-4 h-4 me-2" />
                                                <span :class="{ 'font-medium text-blue-600': isPlatformDefault(req) }">
                                                    {{ getScopeLabel(req) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <button
                                                type="button"
                                                :data-testid="`toggle-required-${req.id}`"
                                                :disabled="!canEdit(req)"
                                                @click="toggleRequired(req)"
                                                :aria-label="req.is_required ? 'Mark as not required' : 'Mark as required'"
                                                :aria-pressed="req.is_required"
                                                class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                                            >
                                                <CheckCircleIcon
                                                    v-if="req.is_required"
                                                    class="w-6 h-6 text-green-500"
                                                    :class="{ 'opacity-50': !canEdit(req) }"
                                                />
                                                <XCircleIcon
                                                    v-else
                                                    class="w-6 h-6 text-gray-300"
                                                    :class="{ 'opacity-50': !canEdit(req) }"
                                                />
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <button
                                                type="button"
                                                :data-testid="`toggle-active-${req.id}`"
                                                :disabled="!canEdit(req)"
                                                @click="toggleActive(req)"
                                                :aria-label="req.is_active ? 'Deactivate requirement' : 'Activate requirement'"
                                                :aria-pressed="req.is_active"
                                                class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                                            >
                                                <CheckCircleIcon
                                                    v-if="req.is_active"
                                                    class="w-6 h-6 text-green-500"
                                                    :class="{ 'opacity-50': !canEdit(req) }"
                                                />
                                                <XCircleIcon
                                                    v-else
                                                    class="w-6 h-6 text-gray-300"
                                                    :class="{ 'opacity-50': !canEdit(req) }"
                                                />
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-end whitespace-nowrap">
                                            <div v-if="canEdit(req)" class="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    :data-testid="`edit-requirement-${req.id}`"
                                                    @click="openEditModal(req)"
                                                    class="p-1 text-gray-400 hover:text-blue-600"
                                                    title="Edit"
                                                >
                                                    <PencilSquareIcon class="w-5 h-5" />
                                                </button>
                                                <button
                                                    v-if="can('templates:manage')"
                                                    type="button"
                                                    :data-testid="`delete-requirement-${req.id}`"
                                                    @click="deleteRequirement(req)"
                                                    class="p-1 text-gray-400 hover:text-red-600"
                                                    title="Delete"
                                                >
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">
                                                Read-only
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="requirements.data.length === 0" class="py-12 text-center">
                            <DocumentCheckIcon class="w-12 h-12 mx-auto text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No requirements</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by adding a KYC requirement.</p>
                            <div v-if="canCreate" class="mt-6">
                                <button
                                    type="button"
                                    @click="openCreateModal"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700"
                                >
                                    <PlusIcon class="w-5 h-5 me-2" />
                                    Add Requirement
                                </button>
                            </div>
                        </div>

                        <Pagination v-if="requirements.last_page > 1" :links="requirements.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto"
            >
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeModal"></div>
                <div class="relative w-full max-w-md p-6 mx-4 bg-white rounded-lg shadow-xl">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">
                        {{ modalTitle }}
                    </h3>

                    <form @submit.prevent="submitForm" data-testid="requirement-form">
                        <div class="space-y-4">
                            <!-- Requirement Type -->
                            <div v-if="!isEditing">
                                <label for="requirement_type" class="block text-sm font-medium text-gray-700">
                                    Requirement Type *
                                </label>
                                <input
                                    id="requirement_type"
                                    data-testid="input-requirement-type"
                                    v-model="form.requirement_type"
                                    type="text"
                                    placeholder="e.g., proof_of_income"
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.requirement_type }"
                                />
                                <p v-if="form.errors.requirement_type" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.requirement_type }}
                                </p>
                            </div>

                            <!-- Label -->
                            <div>
                                <label for="label" class="block text-sm font-medium text-gray-700">
                                    Label *
                                </label>
                                <input
                                    id="label"
                                    data-testid="input-label"
                                    v-model="form.label"
                                    type="text"
                                    placeholder="e.g., Proof of Income"
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.label }"
                                />
                                <p v-if="form.errors.label" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.label }}
                                </p>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    data-testid="input-description"
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Instructions for the tenant..."
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                ></textarea>
                            </div>

                            <!-- Building -->
                            <div v-if="!isEditing">
                                <label for="building_id" class="block text-sm font-medium text-gray-700">
                                    Building (Optional)
                                </label>
                                <select
                                    id="building_id"
                                    data-testid="select-building"
                                    v-model="form.building_id"
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                >
                                    <option :value="null">All Buildings</option>
                                    <option v-for="building in buildings" :key="building.id" :value="building.id">
                                        {{ building.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Leave empty to apply to all buildings
                                </p>
                            </div>

                            <!-- Checkboxes -->
                            <div class="flex gap-6">
                                <label class="flex items-center">
                                    <input
                                        data-testid="checkbox-required"
                                        v-model="form.is_required"
                                        type="checkbox"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ms-2 text-sm text-gray-700">Required</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        data-testid="checkbox-active"
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ms-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                data-testid="submit-button"
                                :disabled="form.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
