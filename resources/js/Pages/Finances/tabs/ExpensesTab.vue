<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useTabFilters, useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useAuth } from '@/composables/useAuth';
import { Pagination, ExportDropdown } from '@/Components/Finances';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    BanknotesIcon,
    TagIcon,
    BuildingOfficeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    FunnelIcon,
    XMarkIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import type { PaginatedResponse, Expense, Building, Property } from '@/types/finances';

interface ExpenseCategory {
    id: number;
    name: string;
    description?: string;
    color: string;
    expense_count?: number;
    total_amount?: number;
}

interface Vendor {
    id: number;
    name: string;
    contact_person?: string;
    email?: string;
    phone?: string;
    address?: string;
    tax_id?: string;
    notes?: string;
    total_amount?: number;
}

interface ExpenseStats {
    total_expenses: number;
    monthly_trend: number;
    top_category?: string;
    pending_payments?: number;
}

interface Props {
    expenses?: PaginatedResponse<Expense>;
    filters?: Record<string, unknown>;
    categories?: ExpenseCategory[];
    vendors?: Vendor[];
    buildings?: Building[];
    properties?: Property[];
    stats?: ExpenseStats;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    categories: () => [],
    vendors: () => [],
    buildings: () => [],
    properties: () => [],
    stats: () => ({}),
});

const { formatMoney: formatCurrency, formatDate, todayAsISODate } = useFormatters();
const { currencySymbol } = useCurrency();
const { can } = useAuth();
const { t } = useI18n();

const activeTab = ref('expenses');
const showExpenseForm = ref(false);
const editingExpense = ref(null);
const showDeleteConfirm = ref(false);
const itemToDelete = ref(null);
const deleteType = ref(null);

const showCategoryForm = ref(false);
const editingCategory = ref(null);
const showVendorForm = ref(false);
const editingVendor = ref(null);

const { localFilters, applyFilters, clearFilters, hasActiveFilters, getExportParams } = useTabFilters({
    routeName: 'finances.expenses',
    propsFilters: props.filters,
    filterConfig: {
        search: { default: '' },
        categoryId: { urlKey: 'category_id', default: null },
        vendorId: { urlKey: 'vendor_id', default: null },
        buildingId: { urlKey: 'building_id', default: null },
        dateFrom: { urlKey: 'date_from', default: null },
        dateTo: { urlKey: 'date_to', default: null },
    },
});

const exportData = (format) => {
    const params = getExportParams(format);
    window.location.href = route('finances.expenses.export') + '?' + params.toString();
};

const expenseForm = useForm({
    category_id: null,
    vendor_id: null,
    property_id: null,
    building_id: null,
    unit_id: null,
    description: '',
    amount: '',
    expense_date: todayAsISODate(),
    payment_method: '',
    reference: '',
    notes: '',
    is_recurring: false,
    recurring_frequency: null,
});

const categoryForm = useForm({
    name: '',
    description: '',
    color: '#6B7280',
});

const vendorForm = useForm({
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    tax_id: '',
    notes: '',
    specialties: [] as string[],
});

// Phase-75 VENDOR-ROUTING-1: trade competencies (matches Ticket issue
// subcategories; server allow-list-gates on save).
const VENDOR_SPECIALTIES = computed(() => [
    { value: 'plumbing', label: t('finances_expenses.specialties.plumbing') },
    { value: 'electrical', label: t('finances_expenses.specialties.electrical') },
    { value: 'water_supply', label: t('finances_expenses.specialties.water_supply') },
    { value: 'structural', label: t('finances_expenses.specialties.structural') },
    { value: 'appliances', label: t('finances_expenses.specialties.appliances') },
    { value: 'painting', label: t('finances_expenses.specialties.painting') },
    { value: 'pest_control', label: t('finances_expenses.specialties.pest_control') },
    { value: 'other', label: t('finances_expenses.specialties.other') },
]);

const paymentMethods = computed(() => [
    { value: 'cash', label: t('finances_expenses.payment_methods.cash') },
    { value: 'bank_transfer', label: t('finances_expenses.payment_methods.bank_transfer') },
    { value: 'mobile_money', label: t('finances_expenses.payment_methods.mobile_money') },
    { value: 'mpesa', label: t('finances_expenses.payment_methods.mpesa') },
    { value: 'cheque', label: t('finances_expenses.payment_methods.cheque') },
    { value: 'card', label: t('finances_expenses.payment_methods.card') },
]);

const recurringOptions = computed(() => [
    { value: 'weekly', label: t('finances_expenses.recurring_options.weekly') },
    { value: 'monthly', label: t('finances_expenses.recurring_options.monthly') },
    { value: 'quarterly', label: t('finances_expenses.recurring_options.quarterly') },
    { value: 'yearly', label: t('finances_expenses.recurring_options.yearly') },
]);

const categoryColors = [
    '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6',
    '#EC4899', '#6B7280', '#14B8A6', '#F97316', '#6366F1',
];

const openExpenseForm = (expense = null) => {
    editingExpense.value = expense;
    if (expense) {
        expenseForm.category_id = expense.category_id;
        expenseForm.vendor_id = expense.vendor_id;
        expenseForm.property_id = expense.property_id;
        expenseForm.building_id = expense.building_id;
        expenseForm.unit_id = expense.unit_id;
        expenseForm.description = expense.description;
        expenseForm.amount = expense.amount;
        expenseForm.expense_date = expense.expense_date;
        expenseForm.payment_method = expense.payment_method || '';
        expenseForm.reference = expense.reference || '';
        expenseForm.notes = expense.notes || '';
        expenseForm.is_recurring = expense.is_recurring;
        expenseForm.recurring_frequency = expense.recurring_frequency;
    } else {
        expenseForm.reset();
        expenseForm.expense_date = todayAsISODate();
    }
    showExpenseForm.value = true;
};

const submitExpenseForm = () => {
    if (editingExpense.value) {
        expenseForm.put(route('finances.expenses.update', editingExpense.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showExpenseForm.value = false;
                editingExpense.value = null;
                expenseForm.reset();
            },
        });
    } else {
        expenseForm.post(route('finances.expenses.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showExpenseForm.value = false;
                expenseForm.reset();
            },
        });
    }
};

const openCategoryForm = (category = null) => {
    editingCategory.value = category;
    if (category) {
        categoryForm.name = category.name;
        categoryForm.description = category.description || '';
        categoryForm.color = category.color || '#6B7280';
    } else {
        categoryForm.reset();
        categoryForm.color = '#6B7280';
    }
    showCategoryForm.value = true;
};

const submitCategoryForm = () => {
    if (editingCategory.value) {
        categoryForm.put(route('finances.expense-categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showCategoryForm.value = false;
                editingCategory.value = null;
                categoryForm.reset();
            },
        });
    } else {
        categoryForm.post(route('finances.expense-categories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showCategoryForm.value = false;
                categoryForm.reset();
            },
        });
    }
};

const openVendorForm = (vendor = null) => {
    editingVendor.value = vendor;
    if (vendor) {
        vendorForm.name = vendor.name;
        vendorForm.contact_person = vendor.contact_person || '';
        vendorForm.email = vendor.email || '';
        vendorForm.phone = vendor.phone || '';
        vendorForm.address = vendor.address || '';
        vendorForm.tax_id = vendor.tax_id || '';
        vendorForm.notes = vendor.notes || '';
        vendorForm.specialties = (vendor.specialties || []).map((s) => (typeof s === 'string' ? s : s.category));
    } else {
        vendorForm.reset();
    }
    showVendorForm.value = true;
};

const submitVendorForm = () => {
    if (editingVendor.value) {
        vendorForm.put(route('finances.vendors.update', editingVendor.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showVendorForm.value = false;
                editingVendor.value = null;
                vendorForm.reset();
            },
        });
    } else {
        vendorForm.post(route('finances.vendors.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showVendorForm.value = false;
                vendorForm.reset();
            },
        });
    }
};

const deleteTypeLabel = computed(() =>
    deleteType.value ? t(`finances_expenses.delete_types.${deleteType.value}`) : '',
);

const confirmDelete = (item, type) => {
    itemToDelete.value = item;
    deleteType.value = type;
    showDeleteConfirm.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;

    const routes = {
        expense: 'finances.expenses.destroy',
        category: 'finances.expense-categories.destroy',
        vendor: 'finances.vendors.destroy',
    };

    router.delete(route(routes[deleteType.value], itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            itemToDelete.value = null;
            deleteType.value = null;
        },
    });
};

</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <BanknotesIcon class="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.stats.this_month') }}</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.this_month) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <BanknotesIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.stats.last_month') }}</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.last_month) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div :class="[
                        'p-2 rounded-lg',
                        stats.month_trend > 0 ? 'bg-red-100' : 'bg-emerald-100'
                    ]">
                        <component
                            :is="stats.month_trend > 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                            :class="['w-5 h-5', stats.month_trend > 0 ? 'text-red-600' : 'text-emerald-600']"
                        />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.stats.month_trend') }}</p>
                        <p :class="['text-lg font-semibold', stats.month_trend > 0 ? 'text-red-600' : 'text-emerald-600']">
                            {{ stats.month_trend > 0 ? '+' : '' }}{{ stats.month_trend }}%
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <BanknotesIcon class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.stats.this_year') }}</p>
                        <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(stats.this_year) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button
                        @click="activeTab = 'expenses'"
                        :class="['px-6 py-3 text-sm font-medium border-b-2 transition-colors', activeTab === 'expenses' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                    >
                        <BanknotesIcon class="w-4 h-4 inline me-2" />
                        {{ t('finances_expenses.tabs.expenses') }}
                    </button>
                    <button
                        @click="activeTab = 'categories'"
                        :class="['px-6 py-3 text-sm font-medium border-b-2 transition-colors', activeTab === 'categories' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                    >
                        <TagIcon class="w-4 h-4 inline me-2" />
                        {{ t('finances_expenses.tabs.categories') }}
                    </button>
                    <button
                        @click="activeTab = 'vendors'"
                        :class="['px-6 py-3 text-sm font-medium border-b-2 transition-colors', activeTab === 'vendors' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                    >
                        <UserGroupIcon class="w-4 h-4 inline me-2" />
                        {{ t('finances_expenses.tabs.vendors') }}
                    </button>
                </nav>
            </div>

            <div v-if="activeTab === 'expenses'" class="p-4 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-64">
                        <input
                            v-model="localFilters.search"
                            type="text"
                            :placeholder="t('finances_expenses.filters.search_placeholder')"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="localFilters.categoryId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">{{ t('finances_expenses.filters.all_categories') }}</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <select
                        v-model="localFilters.vendorId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">{{ t('finances_expenses.filters.all_vendors') }}</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                    <select
                        v-model="localFilters.buildingId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">{{ t('finances_expenses.filters.all_buildings') }}</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <input
                        v-model="localFilters.dateFrom"
                        type="date"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    />
                    <span class="text-gray-400">{{ t('finances_expenses.filters.to') }}</span>
                    <input
                        v-model="localFilters.dateTo"
                        type="date"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    />
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <XMarkIcon class="w-4 h-4 inline me-1" />
                        {{ t('finances_expenses.filters.clear') }}
                    </button>
                    <ExportDropdown @export="exportData" />
                    <button
                        @click="openExpenseForm()"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        {{ t('finances_expenses.add_expense') }}
                    </button>
                </div>

                <div v-if="showExpenseForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitExpenseForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.description') }}</label>
                                <input
                                    v-model="expenseForm.description"
                                    type="text"
                                    required
                                    :placeholder="t('finances_expenses.expense_form.description_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.amount') }}</label>
                                <div class="relative">
                                    <span class="absolute start-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model.number="expenseForm.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        class="w-full px-3 py-2 ps-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.date') }}</label>
                                <input
                                    v-model="expenseForm.expense_date"
                                    type="date"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.category') }}</label>
                                <select
                                    v-model="expenseForm.category_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">{{ t('finances_expenses.expense_form.select_category') }}</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.vendor') }}</label>
                                <select
                                    v-model="expenseForm.vendor_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">{{ t('finances_expenses.expense_form.select_vendor') }}</option>
                                    <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.payment_method') }}</label>
                                <select
                                    v-model="expenseForm.payment_method"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">{{ t('finances_expenses.expense_form.select_method') }}</option>
                                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.reference') }}</label>
                                <input
                                    v-model="expenseForm.reference"
                                    type="text"
                                    :placeholder="t('finances_expenses.expense_form.reference_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.building') }}</label>
                                <select
                                    v-model="expenseForm.building_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">{{ t('finances_expenses.expense_form.general_expense') }}</option>
                                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.expense_form.notes') }}</label>
                                <input
                                    v-model="expenseForm.notes"
                                    type="text"
                                    :placeholder="t('finances_expenses.expense_form.notes_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="expenseForm.is_recurring"
                                    type="checkbox"
                                    class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                />
                                <span class="text-sm text-gray-700">{{ t('finances_expenses.expense_form.recurring') }}</span>
                            </label>
                            <div v-if="expenseForm.is_recurring" class="flex items-center gap-2">
                                <label class="text-sm text-gray-700">{{ t('finances_expenses.expense_form.frequency') }}</label>
                                <select
                                    v-model="expenseForm.recurring_frequency"
                                    class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option v-for="opt in recurringOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showExpenseForm = false; editingExpense = null;"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                {{ t('finances_expenses.expense_form.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="expenseForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ expenseForm.processing ? t('finances_expenses.expense_form.saving') : (editingExpense ? t('finances_expenses.expense_form.update') : t('finances_expenses.expense_form.save')) }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!expenses?.data?.length" class="py-12 text-center">
                    <BanknotesIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('finances_expenses.empty_expenses.title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ t('finances_expenses.empty_expenses.body') }}</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">{{ t('finances_expenses.table.date') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.table.description') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.table.category') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.table.vendor') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.table.location') }}</th>
                                <th class="px-4 py-3 text-end">{{ t('finances_expenses.table.amount') }}</th>
                                <th class="px-4 py-3 text-end"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(expense.expense_date) }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ expense.description }}</div>
                                    <div v-if="expense.reference" class="text-xs text-gray-500">{{ t('finances_expenses.table.ref', { reference: expense.reference }) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="expense.category"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full"
                                        :style="{ backgroundColor: expense.category_color + '20', color: expense.category_color }"
                                    >
                                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: expense.category_color }"></span>
                                        {{ expense.category }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ expense.vendor || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ expense.location }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-end">
                                    {{ formatCurrency(expense.amount) }}
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openExpenseForm(expense)"
                                            class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                                            :title="t('finances_expenses.actions.edit')"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="can('finances:manage')"
                                            @click="confirmDelete(expense, 'expense')"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                            :title="t('finances_expenses.actions.delete')"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="expenses?.links" wrapper-class="pt-4" />
            </div>

            <div v-if="activeTab === 'categories'" class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_expenses.categories.title') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.categories.subtitle') }}</p>
                    </div>
                    <button
                        @click="openCategoryForm()"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        {{ t('finances_expenses.categories.add') }}
                    </button>
                </div>

                <div v-if="showCategoryForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitCategoryForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.categories.name') }}</label>
                                <input
                                    v-model="categoryForm.name"
                                    type="text"
                                    required
                                    :placeholder="t('finances_expenses.categories.name_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.categories.description') }}</label>
                                <input
                                    v-model="categoryForm.description"
                                    type="text"
                                    :placeholder="t('finances_expenses.categories.description_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">{{ t('finances_expenses.categories.color') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in categoryColors"
                                    :key="color"
                                    type="button"
                                    @click="categoryForm.color = color"
                                    :class="['w-8 h-8 rounded-full transition-all', categoryForm.color === color ? 'ring-2 ring-offset-2 ring-gray-400' : '']"
                                    :style="{ backgroundColor: color }"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showCategoryForm = false; editingCategory = null;"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                {{ t('finances_expenses.categories.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="categoryForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ categoryForm.processing ? t('finances_expenses.categories.saving') : (editingCategory ? t('finances_expenses.categories.update') : t('finances_expenses.categories.create')) }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!categories.length" class="py-12 text-center">
                    <TagIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('finances_expenses.categories.empty_title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ t('finances_expenses.categories.empty_body') }}</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-sm transition-shadow"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: category.color }"></div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ category.name }}</h4>
                                    <p v-if="category.description" class="text-xs text-gray-500">{{ category.description }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    @click="openCategoryForm(category)"
                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                                >
                                    <PencilSquareIcon class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="can('finances:manage')"
                                    @click="confirmDelete(category, 'category')"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ t('finances_expenses.categories.expense_count', { count: category.expense_count }) }}</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'vendors'" class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ t('finances_expenses.vendors.title') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('finances_expenses.vendors.subtitle') }}</p>
                    </div>
                    <button
                        @click="openVendorForm()"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        {{ t('finances_expenses.vendors.add') }}
                    </button>
                </div>

                <div v-if="showVendorForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitVendorForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.name') }}</label>
                                <input
                                    v-model="vendorForm.name"
                                    type="text"
                                    required
                                    :placeholder="t('finances_expenses.vendors.name_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.contact_person') }}</label>
                                <input
                                    v-model="vendorForm.contact_person"
                                    type="text"
                                    :placeholder="t('finances_expenses.vendors.contact_person_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.email') }}</label>
                                <input
                                    v-model="vendorForm.email"
                                    type="email"
                                    :placeholder="t('finances_expenses.vendors.email_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.phone') }}</label>
                                <input
                                    v-model="vendorForm.phone"
                                    type="text"
                                    :placeholder="t('finances_expenses.vendors.phone_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.tax_id') }}</label>
                                <input
                                    v-model="vendorForm.tax_id"
                                    type="text"
                                    :placeholder="t('finances_expenses.vendors.tax_id_placeholder')"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.address') }}</label>
                            <input
                                v-model="vendorForm.address"
                                type="text"
                                :placeholder="t('finances_expenses.vendors.address_placeholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.notes') }}</label>
                            <textarea
                                v-model="vendorForm.notes"
                                rows="2"
                                :placeholder="t('finances_expenses.vendors.notes_placeholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('finances_expenses.vendors.specialties') }}</label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" data-testid="vendor-specialties">
                                <label v-for="s in VENDOR_SPECIALTIES" :key="s.value" class="flex items-center gap-2 text-xs text-gray-700">
                                    <input v-model="vendorForm.specialties" :value="s.value" type="checkbox" class="rounded border-gray-300 text-emerald-600" />
                                    {{ s.label }}
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showVendorForm = false; editingVendor = null;"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                {{ t('finances_expenses.vendors.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="vendorForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ vendorForm.processing ? t('finances_expenses.vendors.saving') : (editingVendor ? t('finances_expenses.vendors.update') : t('finances_expenses.vendors.create')) }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!vendors.length" class="py-12 text-center">
                    <UserGroupIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('finances_expenses.vendors.empty_title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ t('finances_expenses.vendors.empty_body') }}</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">{{ t('finances_expenses.vendors.table_vendor') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.vendors.table_contact') }}</th>
                                <th class="px-4 py-3">{{ t('finances_expenses.vendors.table_phone') }}</th>
                                <th class="px-4 py-3 text-end">{{ t('finances_expenses.vendors.table_total') }}</th>
                                <th class="px-4 py-3 text-end"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="vendor in vendors" :key="vendor.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ vendor.name }}</div>
                                    <div v-if="vendor.email" class="text-xs text-gray-500">{{ vendor.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ vendor.contact_person || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ vendor.phone || '-' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-end">
                                    {{ formatCurrency(vendor.total_expenses) }}
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openVendorForm(vendor)"
                                            class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                                            :title="t('finances_expenses.actions.edit')"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="can('finances:manage')"
                                            @click="confirmDelete(vendor, 'vendor')"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                            :title="t('finances_expenses.actions.delete')"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4">
                <div class="fixed inset-0 bg-gray-900/50 z-40" @click="showDeleteConfirm = false"></div>
                <div class="relative z-50 bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900">{{ t('finances_expenses.delete_confirm.title', { type: deleteTypeLabel }) }}</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ t('finances_expenses.delete_confirm.body', { type: deleteTypeLabel }) }}
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            {{ t('finances_expenses.delete_confirm.cancel') }}
                        </button>
                        <button
                            @click="executeDelete"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        >
                            {{ t('finances_expenses.delete_confirm.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
