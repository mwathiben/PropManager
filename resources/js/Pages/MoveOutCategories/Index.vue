<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    BuildingOfficeIcon,
    GlobeAltIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    TagIcon,
    CheckBadgeIcon,
    BoltIcon,
    UserIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { useFormatters } from '@/composables/useFormatters';
import { useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';

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
    stats: {
        total: number;
        active: number;
        always_apply: number;
        custom: number;
    };
    filters: {
        search: string | null;
    };
}>();

const { can } = useAuth();
const { t } = useI18n();

const { formatCurrency } = useFormatters();
const { currencyCode } = useCurrency();

// --- SEARCH ---
const search = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('move-out-categories.index'), {
            search: value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
});

const clearSearch = () => {
    search.value = '';
};

// --- SCOPE FILTER ---
const scopeFilter = ref('');

// --- GROUPED CATEGORIES ---
const groupedCategories = computed(() => {
    const data = props.categories.data;
    let filtered = data;

    if (scopeFilter.value === 'platform') {
        filtered = data.filter(c => c.landlord_id === null);
    } else if (scopeFilter.value === 'custom') {
        filtered = data.filter(c => c.landlord_id !== null && !c.building_id);
    } else if (scopeFilter.value === 'building') {
        filtered = data.filter(c => c.building_id !== null);
    }

    return {
        platform: filtered.filter(c => c.landlord_id === null),
        custom: filtered.filter(c => c.landlord_id !== null && !c.building_id),
        building: filtered.filter(c => c.building_id !== null),
    };
});

const hasResults = computed(() => {
    const g = groupedCategories.value;
    return g.platform.length > 0 || g.custom.length > 0 || g.building.length > 0;
});

// --- HELPERS ---
const isGlobal = (category: Category) => category.landlord_id === null;
const canEdit = (category: Category) => !isGlobal(category) && props.canCreate;
const canDelete = (category: Category) => !isGlobal(category) && props.canCreate;

// --- MODAL ---
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
const modalTitle = computed(() => isEditing.value ? t('move_out_categories_index.modal.title_edit') : t('move_out_categories_index.modal.title_new'));

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

// --- DELETE CONFIRMATION ---
const showDeleteModal = ref(false);
const categoryToDelete = ref<Category | null>(null);

const openDeleteModal = (category: Category) => {
    categoryToDelete.value = category;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!categoryToDelete.value) return;
    router.delete(route('move-out-categories.destroy', categoryToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            categoryToDelete.value = null;
        },
    });
};

// --- TOGGLES ---
const toggleAlwaysApply = (category: Category) => {
    if (!canEdit(category)) return;
    router.put(route('move-out-categories.update', category.id), {
        always_apply: !category.always_apply,
    }, { preserveScroll: true });
};

const toggleActive = (category: Category) => {
    if (!canEdit(category)) return;
    router.put(route('move-out-categories.update', category.id), {
        is_active: !category.is_active,
    }, { preserveScroll: true });
};

// --- BREADCRUMBS ---
const breadcrumbs = computed(() => [
    { label: t('move_out_categories_index.breadcrumbs.move_outs'), href: route('move-outs.index') },
    { label: t('move_out_categories_index.breadcrumbs.deduction_categories') },
]);
</script>

<template>
    <Head :title="t('move_out_categories_index.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Breadcrumb :items="breadcrumbs" />
                    <h1 class="text-lg font-semibold text-gray-900 mt-1">{{ t('move_out_categories_index.title') }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ t('move_out_categories_index.subtitle') }}
                    </p>
                </div>
                <button
                    v-if="canCreate"
                    type="button"
                    data-testid="add-category-button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
                    @click="openCreateModal"
                >
                    <PlusIcon class="h-4 w-4" />
                    {{ t('move_out_categories_index.add_category') }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-gray-100 rounded-lg">
                                <TagIcon class="w-5 h-5 text-gray-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div>
                                <div class="text-xs text-gray-500">{{ t('move_out_categories_index.stats.total') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <CheckBadgeIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.active }}</div>
                                <div class="text-xs text-gray-500">{{ t('move_out_categories_index.stats.active') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-100 rounded-lg">
                                <BoltIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.always_apply }}</div>
                                <div class="text-xs text-gray-500">{{ t('move_out_categories_index.stats.auto_applied') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <UserIcon class="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ stats.custom }}</div>
                                <div class="text-xs text-gray-500">{{ t('move_out_categories_index.stats.custom') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search + Filter Bar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <div class="relative flex-1 max-w-sm">
                        <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('move_out_categories_index.search.placeholder')"
                            class="w-full ps-9 pe-8 py-2 text-sm rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <button
                            v-if="search"
                            @click="clearSearch"
                            class="absolute end-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <XMarkIcon class="w-4 h-4" />
                        </button>
                    </div>
                    <select
                        v-model="scopeFilter"
                        class="text-sm rounded-lg border border-gray-300 py-2 pe-8 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">{{ t('move_out_categories_index.scope_filter.all') }}</option>
                        <option value="platform">{{ t('move_out_categories_index.scope_filter.platform') }}</option>
                        <option value="custom">{{ t('move_out_categories_index.scope_filter.custom') }}</option>
                        <option value="building">{{ t('move_out_categories_index.scope_filter.building') }}</option>
                    </select>
                </div>

                <!-- Empty State -->
                <div v-if="!hasResults" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <TagIcon class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                    <h3 class="text-sm font-medium text-gray-900">{{ t('move_out_categories_index.empty.title') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ search ? t('move_out_categories_index.empty.try_different_search') : t('move_out_categories_index.empty.add_first') }}
                    </p>
                    <button
                        v-if="canCreate && !search"
                        @click="openCreateModal"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        {{ t('move_out_categories_index.add_category') }}
                    </button>
                </div>

                <!-- Platform Defaults Section -->
                <div v-if="groupedCategories.platform.length > 0">
                    <div class="flex items-center gap-2 mb-3">
                        <GlobeAltIcon class="w-4 h-4 text-purple-600" />
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">{{ t('move_out_categories_index.sections.platform_defaults') }}</h2>
                        <span class="text-xs text-gray-400">({{ groupedCategories.platform.length }})</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="category in groupedCategories.platform"
                            :key="category.id"
                            data-testid="category-row"
                            class="bg-white rounded-xl border border-gray-200 p-4 transition-all"
                            :class="{ 'opacity-60 border-dashed': !category.is_active }"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                                    <GlobeAltIcon class="h-3 w-3" />
                                    {{ t('move_out_categories_index.badges.platform') }}
                                </span>
                                <span class="text-xs text-gray-400">{{ t('move_out_categories_index.badges.read_only') }}</span>
                            </div>
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-medium text-gray-900 truncate">{{ category.name }}</h3>
                                    <p v-if="category.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ category.description }}</p>
                                </div>
                                <div class="text-end ms-3 shrink-0">
                                    <div class="text-lg font-semibold text-gray-900">{{ formatCurrency(category.default_amount) }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-block w-2 h-2 rounded-full"
                                        :class="category.always_apply ? 'bg-indigo-500' : 'bg-gray-300'"
                                    />
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.auto_apply') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-block w-2 h-2 rounded-full"
                                        :class="category.is_active ? 'bg-green-500' : 'bg-gray-300'"
                                    />
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.active') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Your Categories Section -->
                <div v-if="groupedCategories.custom.length > 0 || (scopeFilter === '' || scopeFilter === 'custom')">
                    <div class="flex items-center gap-2 mb-3">
                        <UserIcon class="w-4 h-4 text-gray-600" />
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">{{ t('move_out_categories_index.sections.your_categories') }}</h2>
                        <span class="text-xs text-gray-400">({{ groupedCategories.custom.length }})</span>
                    </div>
                    <div v-if="groupedCategories.custom.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="category in groupedCategories.custom"
                            :key="category.id"
                            data-testid="category-row"
                            class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-all"
                            :class="{ 'opacity-60 border-dashed': !category.is_active }"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                    {{ t('move_out_categories_index.badges.all_buildings') }}
                                </span>
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="canEdit(category)"
                                        @click="openEditModal(category)"
                                        class="p-1 text-gray-400 hover:text-indigo-600 rounded transition-colors"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="can('settings:manage') && canDelete(category)"
                                        @click="openDeleteModal(category)"
                                        class="p-1 text-gray-400 hover:text-red-600 rounded transition-colors"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-medium text-gray-900 truncate">{{ category.name }}</h3>
                                    <p v-if="category.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ category.description }}</p>
                                </div>
                                <div class="text-end ms-3 shrink-0">
                                    <div class="text-lg font-semibold text-gray-900">{{ formatCurrency(category.default_amount) }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
                                <button
                                    @click="toggleAlwaysApply(category)"
                                    class="flex items-center gap-1.5 group"
                                    :class="canEdit(category) ? 'cursor-pointer' : 'cursor-default'"
                                >
                                    <span
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="category.always_apply ? 'bg-indigo-600' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                            :class="category.always_apply ? 'translate-x-4' : 'translate-x-0'"
                                        />
                                    </span>
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.auto_apply') }}</span>
                                </button>
                                <button
                                    @click="toggleActive(category)"
                                    class="flex items-center gap-1.5 group"
                                    :class="canEdit(category) ? 'cursor-pointer' : 'cursor-default'"
                                >
                                    <span
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="category.is_active ? 'bg-green-600' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                            :class="category.is_active ? 'translate-x-4' : 'translate-x-0'"
                                        />
                                    </span>
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.active') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bg-gray-50 rounded-xl border border-dashed border-gray-300 p-8 text-center">
                        <p class="text-sm text-gray-500">{{ t('move_out_categories_index.no_custom.message') }}</p>
                        <button
                            v-if="canCreate"
                            @click="openCreateModal"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                        >
                            <PlusIcon class="w-4 h-4" />
                            {{ t('move_out_categories_index.no_custom.create_first') }}
                        </button>
                    </div>
                </div>

                <!-- Building-Specific Section -->
                <div v-if="groupedCategories.building.length > 0">
                    <div class="flex items-center gap-2 mb-3">
                        <BuildingOfficeIcon class="w-4 h-4 text-blue-600" />
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">{{ t('move_out_categories_index.sections.building_specific') }}</h2>
                        <span class="text-xs text-gray-400">({{ groupedCategories.building.length }})</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="category in groupedCategories.building"
                            :key="category.id"
                            data-testid="category-row"
                            class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-all"
                            :class="{ 'opacity-60 border-dashed': !category.is_active }"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    <BuildingOfficeIcon class="h-3 w-3" />
                                    {{ category.building?.name }}
                                </span>
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="canEdit(category)"
                                        @click="openEditModal(category)"
                                        class="p-1 text-gray-400 hover:text-indigo-600 rounded transition-colors"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="can('settings:manage') && canDelete(category)"
                                        @click="openDeleteModal(category)"
                                        class="p-1 text-gray-400 hover:text-red-600 rounded transition-colors"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-medium text-gray-900 truncate">{{ category.name }}</h3>
                                    <p v-if="category.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ category.description }}</p>
                                </div>
                                <div class="text-end ms-3 shrink-0">
                                    <div class="text-lg font-semibold text-gray-900">{{ formatCurrency(category.default_amount) }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
                                <button
                                    @click="toggleAlwaysApply(category)"
                                    class="flex items-center gap-1.5 group"
                                    :class="canEdit(category) ? 'cursor-pointer' : 'cursor-default'"
                                >
                                    <span
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="category.always_apply ? 'bg-indigo-600' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                            :class="category.always_apply ? 'translate-x-4' : 'translate-x-0'"
                                        />
                                    </span>
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.auto_apply') }}</span>
                                </button>
                                <button
                                    @click="toggleActive(category)"
                                    class="flex items-center gap-1.5 group"
                                    :class="canEdit(category) ? 'cursor-pointer' : 'cursor-default'"
                                >
                                    <span
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="category.is_active ? 'bg-green-600' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                            :class="category.is_active ? 'translate-x-4' : 'translate-x-0'"
                                        />
                                    </span>
                                    <span class="text-xs text-gray-500">{{ t('move_out_categories_index.card.active') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <Pagination v-if="categories.last_page > 1" :links="categories.links" class="mt-6" />

            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeModal" />

                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                            <form @submit.prevent="submitForm">
                                <div class="p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5">{{ modalTitle }}</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ t('move_out_categories_index.modal.name_label') }}
                                            </label>
                                            <input
                                                id="name"
                                                v-model="form.name"
                                                type="text"
                                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                :placeholder="t('move_out_categories_index.modal.name_placeholder')"
                                                required
                                            />
                                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">
                                                {{ form.errors.name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ t('move_out_categories_index.modal.description_label') }}
                                            </label>
                                            <textarea
                                                id="description"
                                                v-model="form.description"
                                                rows="2"
                                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                :placeholder="t('move_out_categories_index.modal.description_placeholder')"
                                            />
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="default_amount" class="block text-sm font-medium text-gray-700 mb-1">
                                                    {{ t('move_out_categories_index.modal.default_amount_label', { currency: currencyCode }) }}
                                                </label>
                                                <input
                                                    id="default_amount"
                                                    v-model.number="form.default_amount"
                                                    type="number"
                                                    min="0"
                                                    step="100"
                                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                    required
                                                />
                                                <p v-if="form.errors.default_amount" class="mt-1 text-xs text-red-600">
                                                    {{ form.errors.default_amount }}
                                                </p>
                                            </div>
                                            <div>
                                                <label for="building_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                    {{ t('move_out_categories_index.modal.scope_label') }}
                                                </label>
                                                <select
                                                    id="building_id"
                                                    v-model="form.building_id"
                                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                >
                                                    <option :value="null">{{ t('move_out_categories_index.modal.all_buildings') }}</option>
                                                    <option v-for="building in buildings" :key="building.id" :value="building.id">
                                                        {{ building.name }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-6 pt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    v-model="form.always_apply"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">{{ t('move_out_categories_index.modal.always_apply_label') }}</span>
                                                    <p class="text-xs text-gray-500">{{ t('move_out_categories_index.modal.always_apply_help') }}</p>
                                                </div>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    v-model="form.is_active"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">{{ t('move_out_categories_index.modal.active_label') }}</span>
                                                    <p class="text-xs text-gray-500">{{ t('move_out_categories_index.modal.active_help') }}</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                        @click="closeModal"
                                    >
                                        {{ t('move_out_categories_index.modal.cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                                    >
                                        {{ form.processing ? t('move_out_categories_index.modal.saving') : (isEditing ? t('move_out_categories_index.modal.update') : t('move_out_categories_index.modal.create')) }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showDeleteModal = false" />

                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                            <div class="p-6 text-center">
                                <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                                    <ExclamationTriangleIcon class="w-7 h-7 text-red-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('move_out_categories_index.delete_modal.title') }}</h3>
                                <p class="text-sm text-gray-500">
                                    {{ t('move_out_categories_index.delete_modal.message_before') }}
                                    <span class="font-semibold">"{{ categoryToDelete?.name }}"</span>{{ t('move_out_categories_index.delete_modal.message_after') }}
                                </p>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 flex gap-3">
                                <button
                                    @click="showDeleteModal = false"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    {{ t('move_out_categories_index.delete_modal.cancel') }}
                                </button>
                                <button
                                    @click="confirmDelete"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                                >
                                    {{ t('move_out_categories_index.delete_modal.delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>
