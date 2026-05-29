<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useErrorHandler, useFormatters, useCurrency } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { AdminBillingSettingsPageProps } from '@/types';
import {
    CurrencyDollarIcon,
    Cog6ToothIcon,
    ChartBarIcon,
    ClockIcon,
    CheckCircleIcon,
    CalculatorIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<AdminBillingSettingsPageProps>();

const { logError } = useErrorHandler();
const { formatMoney: formatCurrency } = useFormatters();
const { currencyCode } = useCurrency();
const { t } = useI18n();
const activeTab = ref('settings');

const modelForm = useForm({
    billing_model: props.settings.active_billing_model,
    reason: '',
});

const feeForm = useForm({
    transaction_fee_percentage: props.settings.transaction_fee_percentage,
    minimum_fee: props.settings.minimum_fee,
    maximum_fee: props.settings.maximum_fee || '',
    fee_bearer: props.settings.fee_bearer,
    hybrid_subscription_discount: props.settings.hybrid_subscription_discount,
    reason: '',
});

const previewAmount = ref(10000);
const feePreview = ref(null);
const calculatingPreview = ref(false);

const submitModelChange = () => {
    modelForm.post(route('admin.billing.model'), {
        preserveScroll: true,
        onSuccess: () => {
            modelForm.reason = '';
        },
    });
};

const submitFeeChange = () => {
    feeForm.post(route('admin.billing.fees'), {
        preserveScroll: true,
        onSuccess: () => {
            feeForm.reason = '';
        },
    });
};

const calculatePreview = async () => {
    calculatingPreview.value = true;
    try {
        const response = await fetch(route('admin.billing.preview-fee'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ amount: previewAmount.value }),
        });
        const data = await response.json();
        feePreview.value = data.preview;
    } catch (error) {
        logError(error, { component: 'BillingSettings', action: 'previewCalculation' });
    } finally {
        calculatingPreview.value = false;
    }
};

</script>

<template>
    <Head :title="t('admin_billing_settings.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ t('admin_billing_settings.title') }}
            </h1>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <CurrencyDollarIcon class="w-8 h-8 text-green-600" />
                            <div class="ms-4">
                                <p class="text-sm text-gray-500">{{ t('admin_billing_settings.stats.monthly_revenue') }}</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(monthlyAnalytics.totals.platform_fees) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <ChartBarIcon class="w-8 h-8 text-blue-600" />
                            <div class="ms-4">
                                <p class="text-sm text-gray-500">{{ t('admin_billing_settings.stats.transactions') }}</p>
                                <p class="text-xl font-bold text-gray-900">{{ monthlyAnalytics.totals.transaction_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <CalculatorIcon class="w-8 h-8 text-purple-600" />
                            <div class="ms-4">
                                <p class="text-sm text-gray-500">{{ t('admin_billing_settings.stats.avg_fee_percent') }}</p>
                                <p class="text-xl font-bold text-gray-900">{{ monthlyAnalytics.totals.average_fee_percentage }}%</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <CurrencyDollarIcon class="w-8 h-8 text-gray-600" />
                            <div class="ms-4">
                                <p class="text-sm text-gray-500">{{ t('admin_billing_settings.stats.total_processed') }}</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(monthlyAnalytics.totals.gross_amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white shadow rounded-lg">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                @click="activeTab = 'settings'"
                                :class="[
                                    'px-6 py-4 text-sm font-medium border-b-2',
                                    activeTab === 'settings'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <Cog6ToothIcon class="w-5 h-5 inline me-2" />
                                {{ t('admin_billing_settings.tabs.settings') }}
                            </button>
                            <button
                                @click="activeTab = 'history'"
                                :class="[
                                    'px-6 py-4 text-sm font-medium border-b-2',
                                    activeTab === 'history'
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <ClockIcon class="w-5 h-5 inline me-2" />
                                {{ t('admin_billing_settings.tabs.history') }}
                            </button>
                        </nav>
                    </div>

                    <!-- Settings Tab -->
                    <div v-show="activeTab === 'settings'" class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Billing Model Section -->
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('admin_billing_settings.billing_model.heading') }}</h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-sm text-gray-600 mb-4">
                                            {{ t('admin_billing_settings.billing_model.current_label') }} <span class="font-semibold">{{ settings.billing_model_label }}</span>
                                        </p>

                                        <form @submit.prevent="submitModelChange" class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.billing_model.select_label') }}</label>
                                                <select
                                                    v-model="modelForm.billing_model"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                    <option v-for="(label, value) in billingModels" :key="value" :value="value">
                                                        {{ label }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.billing_model.reason_label') }}</label>
                                                <textarea
                                                    v-model="modelForm.reason"
                                                    rows="2"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    :placeholder="t('admin_billing_settings.billing_model.reason_placeholder')"
                                                ></textarea>
                                            </div>

                                            <button
                                                type="submit"
                                                :disabled="modelForm.processing || modelForm.billing_model === settings.active_billing_model"
                                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                                            >
                                                {{ modelForm.processing ? t('admin_billing_settings.billing_model.submitting') : t('admin_billing_settings.billing_model.submit') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Fee Preview Calculator -->
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('admin_billing_settings.calculator.heading') }}</h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex space-x-2">
                                            <input
                                                v-model="previewAmount"
                                                type="number"
                                                min="100"
                                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                :placeholder="t('admin_billing_settings.calculator.amount_placeholder')"
                                            />
                                            <button
                                                @click="calculatePreview"
                                                :disabled="calculatingPreview"
                                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:opacity-50"
                                            >
                                                {{ t('admin_billing_settings.calculator.calculate') }}
                                            </button>
                                        </div>

                                        <div v-if="feePreview" class="mt-4 space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">{{ t('admin_billing_settings.calculator.gross_amount') }}</span>
                                                <span class="font-medium">{{ formatCurrency(feePreview.gross_amount) }}</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">{{ t('admin_billing_settings.calculator.platform_fee', { percent: feePreview.fee_percentage }) }}</span>
                                                <span class="font-medium text-red-600">-{{ formatCurrency(feePreview.fee_amount) }}</span>
                                            </div>
                                            <div class="flex justify-between text-sm border-t pt-2">
                                                <span class="text-gray-900 font-medium">{{ t('admin_billing_settings.calculator.landlord_receives') }}</span>
                                                <span class="font-bold text-green-600">{{ formatCurrency(feePreview.net_amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fee Settings Section -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('admin_billing_settings.fees.heading') }}</h3>
                                <form @submit.prevent="submitFeeChange" class="bg-gray-50 rounded-lg p-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.transaction_fee_percent') }}</label>
                                        <input
                                            v-model="feeForm.transaction_fee_percentage"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">{{ t('admin_billing_settings.fees.transaction_fee_hint') }}</p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.minimum_fee', { currency: currencyCode }) }}</label>
                                            <input
                                                v-model="feeForm.minimum_fee"
                                                type="number"
                                                step="1"
                                                min="0"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.maximum_fee', { currency: currencyCode }) }}</label>
                                            <input
                                                v-model="feeForm.maximum_fee"
                                                type="number"
                                                step="1"
                                                min="0"
                                                :placeholder="t('admin_billing_settings.fees.maximum_fee_placeholder')"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.fee_bearer') }}</label>
                                        <select
                                            v-model="feeForm.fee_bearer"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            <option v-for="(label, value) in feeBearers" :key="value" :value="value">
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.hybrid_discount') }}</label>
                                        <input
                                            v-model="feeForm.hybrid_subscription_discount"
                                            type="number"
                                            step="1"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">{{ t('admin_billing_settings.fees.hybrid_discount_hint') }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ t('admin_billing_settings.fees.reason_label') }}</label>
                                        <textarea
                                            v-model="feeForm.reason"
                                            rows="2"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            :placeholder="t('admin_billing_settings.fees.reason_placeholder')"
                                        ></textarea>
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="feeForm.processing"
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {{ feeForm.processing ? t('admin_billing_settings.fees.submitting') : t('admin_billing_settings.fees.submit') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div v-show="activeTab === 'history'" class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('admin_billing_settings.history.heading') }}</h3>

                        <div v-if="recentChanges.length > 0" class="space-y-4">
                            <div
                                v-for="change in recentChanges"
                                :key="change.id"
                                class="bg-gray-50 rounded-lg p-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ change.description }}</p>
                                        <p v-if="change.reason" class="text-sm text-gray-600 mt-1">
                                            {{ t('admin_billing_settings.history.reason_prefix', { reason: change.reason }) }}
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-sm text-gray-500">{{ change.changed_by }}</p>
                                        <p class="text-xs text-gray-400">{{ change.effective_date }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8 text-gray-500">
                            <ClockIcon class="mx-auto h-12 w-12 text-gray-400" />
                            <p class="mt-2">{{ t('admin_billing_settings.history.empty') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
