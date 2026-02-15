<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useTabFilters, useFormatters, useCurrency } from '@/composables';
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
});

const paymentMethods = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'mpesa', label: 'M-Pesa' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'card', label: 'Card' },
];

const recurringOptions = [
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

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
                        <p class="text-xs text-gray-500">This Month</p>
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
                        <p class="text-xs text-gray-500">Last Month</p>
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
                        <p class="text-xs text-gray-500">Month Trend</p>
                        <p :class="[
                            'text-lg font-semibold',
                            stats.month_trend > 0 ? 'text-red-600' : 'text-emerald-600'
                        ]">
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
                        <p class="text-xs text-gray-500">This Year</p>
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
                        :class="[
                            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                            activeTab === 'expenses'
                                ? 'border-emerald-500 text-emerald-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        <BanknotesIcon class="w-4 h-4 inline mr-2" />
                        Expenses
                    </button>
                    <button
                        @click="activeTab = 'categories'"
                        :class="[
                            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                            activeTab === 'categories'
                                ? 'border-emerald-500 text-emerald-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        <TagIcon class="w-4 h-4 inline mr-2" />
                        Categories
                    </button>
                    <button
                        @click="activeTab = 'vendors'"
                        :class="[
                            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                            activeTab === 'vendors'
                                ? 'border-emerald-500 text-emerald-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        <UserGroupIcon class="w-4 h-4 inline mr-2" />
                        Vendors
                    </button>
                </nav>
            </div>

            <div v-if="activeTab === 'expenses'" class="p-4 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-64">
                        <input
                            v-model="localFilters.search"
                            type="text"
                            placeholder="Search expenses..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="localFilters.categoryId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <select
                        v-model="localFilters.vendorId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">All Vendors</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                    <select
                        v-model="localFilters.buildingId"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    >
                        <option :value="null">All Buildings</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <input
                        v-model="localFilters.dateFrom"
                        type="date"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        @change="applyFilters"
                    />
                    <span class="text-gray-400">to</span>
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
                        <XMarkIcon class="w-4 h-4 inline mr-1" />
                        Clear
                    </button>
                    <ExportDropdown @export="exportData" />
                    <button
                        @click="openExpenseForm()"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Add Expense
                    </button>
                </div>

                <div v-if="showExpenseForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitExpenseForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description *</label>
                                <input
                                    v-model="expenseForm.description"
                                    type="text"
                                    required
                                    placeholder="e.g., Plumbing repair"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Amount *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-400">{{ currencySymbol }}</span>
                                    <input
                                        v-model.number="expenseForm.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        class="w-full px-3 py-2 pl-12 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Date *</label>
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
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                <select
                                    v-model="expenseForm.category_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">Select category</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Vendor</label>
                                <select
                                    v-model="expenseForm.vendor_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">Select vendor</option>
                                    <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                                <select
                                    v-model="expenseForm.payment_method"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option value="">Select method</option>
                                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Reference</label>
                                <input
                                    v-model="expenseForm.reference"
                                    type="text"
                                    placeholder="Receipt/Invoice #"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Building (Optional)</label>
                                <select
                                    v-model="expenseForm.building_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                >
                                    <option :value="null">General expense</option>
                                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                                <input
                                    v-model="expenseForm.notes"
                                    type="text"
                                    placeholder="Additional notes"
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
                                <span class="text-sm text-gray-700">Recurring expense</span>
                            </label>
                            <div v-if="expenseForm.is_recurring" class="flex items-center gap-2">
                                <label class="text-sm text-gray-700">Frequency:</label>
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
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="expenseForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ expenseForm.processing ? 'Saving...' : (editingExpense ? 'Update' : 'Save Expense') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!expenses?.data?.length" class="py-12 text-center">
                    <BanknotesIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No expenses</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by recording an expense.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(expense.expense_date) }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ expense.description }}</div>
                                    <div v-if="expense.reference" class="text-xs text-gray-500">Ref: {{ expense.reference }}</div>
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
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">
                                    {{ formatCurrency(expense.amount) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openExpenseForm(expense)"
                                            class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="confirmDelete(expense, 'expense')"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                            title="Delete"
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
                        <h3 class="text-sm font-semibold text-gray-900">Expense Categories</h3>
                        <p class="text-xs text-gray-500">Organize expenses by type</p>
                    </div>
                    <button
                        @click="openCategoryForm()"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Add Category
                    </button>
                </div>

                <div v-if="showCategoryForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitCategoryForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                                <input
                                    v-model="categoryForm.name"
                                    type="text"
                                    required
                                    placeholder="e.g., Maintenance"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                <input
                                    v-model="categoryForm.description"
                                    type="text"
                                    placeholder="Optional description"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-2">Color</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in categoryColors"
                                    :key="color"
                                    type="button"
                                    @click="categoryForm.color = color"
                                    :class="[
                                        'w-8 h-8 rounded-full transition-all',
                                        categoryForm.color === color ? 'ring-2 ring-offset-2 ring-gray-400' : ''
                                    ]"
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
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="categoryForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ categoryForm.processing ? 'Saving...' : (editingCategory ? 'Update' : 'Create') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!categories.length" class="py-12 text-center">
                    <TagIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No categories</h3>
                    <p class="mt-1 text-sm text-gray-500">Create categories to organize expenses.</p>
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
                                    @click="confirmDelete(category, 'category')"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ category.expense_count }} expense(s)</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'vendors'" class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Vendors</h3>
                        <p class="text-xs text-gray-500">Manage suppliers and service providers</p>
                    </div>
                    <button
                        @click="openVendorForm()"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Add Vendor
                    </button>
                </div>

                <div v-if="showVendorForm" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form @submit.prevent="submitVendorForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Vendor Name *</label>
                                <input
                                    v-model="vendorForm.name"
                                    type="text"
                                    required
                                    placeholder="Company or individual name"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Contact Person</label>
                                <input
                                    v-model="vendorForm.contact_person"
                                    type="text"
                                    placeholder="Primary contact"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    v-model="vendorForm.email"
                                    type="email"
                                    placeholder="vendor@example.com"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                                <input
                                    v-model="vendorForm.phone"
                                    type="text"
                                    placeholder="0712 345 678"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Tax ID / KRA PIN</label>
                                <input
                                    v-model="vendorForm.tax_id"
                                    type="text"
                                    placeholder="A123456789B"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                            <input
                                v-model="vendorForm.address"
                                type="text"
                                placeholder="Physical address"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                            <textarea
                                v-model="vendorForm.notes"
                                rows="2"
                                placeholder="Additional notes about this vendor"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showVendorForm = false; editingVendor = null;"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="vendorForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                            >
                                {{ vendorForm.processing ? 'Saving...' : (editingVendor ? 'Update' : 'Create') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="!vendors.length" class="py-12 text-center">
                    <UserGroupIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No vendors</h3>
                    <p class="mt-1 text-sm text-gray-500">Add vendors to track expenses by supplier.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Phone</th>
                                <th class="px-4 py-3 text-right">Total Expenses</th>
                                <th class="px-4 py-3 text-right"></th>
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
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">
                                    {{ formatCurrency(vendor.total_expenses) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="openVendorForm(vendor)"
                                            class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="confirmDelete(vendor, 'vendor')"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                                            title="Delete"
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
                    <h3 class="text-lg font-semibold text-gray-900">Delete {{ deleteType }}</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Are you sure you want to delete this {{ deleteType }}? This action cannot be undone.
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="executeDelete"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
