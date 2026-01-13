<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowRightOnRectangleIcon,
    HomeIcon,
    UserCircleIcon,
    CalendarDaysIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    lease: Object,
});

const tenant = props.lease.tenant;
const unit = props.lease.unit;

const form = useForm({
    notice_date: new Date().toISOString().split('T')[0],
    intended_move_out_date: '',
    reason: '',
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
    }).format(amount || 0);
};

const submit = () => {
    form.post(route('move-outs.store', props.lease.id));
};
</script>

<template>
    <Head title="Initiate Move-Out" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex items-center gap-4">
                    <Link :href="route('tenants.show', tenant.id)" class="text-gray-400 hover:text-gray-600">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Initiate Move-Out</h1>
                        <p class="text-sm text-gray-500">Start the move-out process for {{ tenant.name }}</p>
                    </div>
                </div>

                <!-- Tenant & Unit Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tenant -->
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold">
                                {{ tenant.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Tenant</h3>
                                <p class="text-lg font-semibold text-gray-900">{{ tenant.name }}</p>
                                <p class="text-sm text-gray-500">{{ tenant.email }}</p>
                            </div>
                        </div>

                        <!-- Unit -->
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center">
                                <HomeIcon class="w-6 h-6 text-gray-600" />
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Unit</h3>
                                <p class="text-lg font-semibold text-gray-900">Unit {{ unit.unit_number }}</p>
                                <p class="text-sm text-gray-500">{{ unit.building?.name }} - {{ unit.building?.property?.name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">Financial Summary</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">Deposit Held</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(lease.deposit_amount) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">Current Arrears</p>
                                <p class="text-lg font-bold" :class="lease.arrears > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ formatCurrency(lease.arrears || 0) }}
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">Monthly Rent</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(lease.rent_amount) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">Lease Started</p>
                                <p class="text-lg font-bold text-gray-900">{{ new Date(lease.start_date).toLocaleDateString('en-KE', { month: 'short', year: 'numeric' }) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Move-Out Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <ArrowRightOnRectangleIcon class="w-5 h-5" />
                            Move-Out Details
                        </h3>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <CalendarDaysIcon class="w-4 h-4 inline mr-1" />
                                    Notice Date *
                                </label>
                                <input
                                    v-model="form.notice_date"
                                    type="date"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Date tenant gave notice to move out</p>
                                <p v-if="form.errors.notice_date" class="mt-1 text-sm text-red-600">{{ form.errors.notice_date }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <CalendarDaysIcon class="w-4 h-4 inline mr-1" />
                                    Intended Move-Out Date *
                                </label>
                                <input
                                    v-model="form.intended_move_out_date"
                                    type="date"
                                    required
                                    :min="form.notice_date"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Expected date tenant will vacate</p>
                                <p v-if="form.errors.intended_move_out_date" class="mt-1 text-sm text-red-600">{{ form.errors.intended_move_out_date }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Moving (Optional)</label>
                            <textarea
                                v-model="form.reason"
                                rows="3"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g., Relocation, end of lease term, etc."
                            ></textarea>
                        </div>

                        <!-- What happens next -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">What happens next?</h4>
                            <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                                <li>Move-out notice is recorded</li>
                                <li>When tenant vacates, you'll conduct a move-out inspection</li>
                                <li>Record any deductions (damages, unpaid rent, etc.)</li>
                                <li>Calculate and settle the deposit refund</li>
                                <li>Unit becomes vacant and available for new tenant</li>
                            </ol>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <Link
                                :href="route('tenants.show', tenant.id)"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <ArrowRightOnRectangleIcon class="w-5 h-5" />
                                {{ form.processing ? 'Processing...' : 'Start Move-Out Process' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
