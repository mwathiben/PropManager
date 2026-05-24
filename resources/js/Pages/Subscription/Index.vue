<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { SubscriptionIndexPageProps } from '@/types/settings';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    CreditCardIcon,
    ArrowPathIcon,
    XCircleIcon,
    ArrowTopRightOnSquareIcon,
    DocumentArrowDownIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<SubscriptionIndexPageProps>();

const { formatDate, formatMoney: formatCurrency } = useFormatters();
const { t } = useI18n();
const showCancelModal = ref(false);
const cancelImmediately = ref(false);

const statusColors = {
    active: 'bg-green-100 text-green-800',
    trialing: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-gray-100 text-gray-800',
    past_due: 'bg-red-100 text-red-800',
    paused: 'bg-yellow-100 text-yellow-800',
};

const statusIcons = {
    active: CheckCircleIcon,
    trialing: SparklesIcon,
    cancelled: XCircleIcon,
    past_due: ExclamationTriangleIcon,
    paused: ArrowPathIcon,
};

const cancelForm = useForm({
    immediately: false,
});

const handleCancel = () => {
    cancelForm.immediately = cancelImmediately.value;
    cancelForm.post(route('subscription.cancel'), {
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};

const handleResume = () => {
    router.post(route('subscription.resume'));
};

const usagePercentage = (current, limit) => {
    if (limit === 0 || limit >= 999) return 0;
    return Math.min(100, Math.round((current / limit) * 100));
};

const isNearLimit = (current, limit) => {
    if (limit >= 999) return false;
    return current >= limit * 0.8;
};

const isAtLimit = (current, limit) => {
    if (limit >= 999) return false;
    return current >= limit;
};
</script>

<template>
    <Head :title="t('subscription.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('subscription.title') }}</h1>
                        <p class="text-gray-600">{{ t('subscription.subtitle') }}</p>
                    </div>
                    <Link
                        :href="route('subscription.plans')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <SparklesIcon class="h-5 w-5" />
                        {{ t('subscription.view_plans') }}
                    </Link>
                </div>

                <!-- Payment Gateway Not Configured Warning -->
                <div v-if="!paystackConfigured" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-8">
                    <div class="flex items-start gap-4">
                        <div class="h-10 w-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                            <ExclamationTriangleIcon class="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-amber-800">{{ t('subscription.gateway_warning.title') }}</h3>
                            <p class="text-sm text-amber-700 mt-1">
                                {{ t('subscription.gateway_warning.body') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Current Plan Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">{{ t('subscription.your_plan') }}</h2>
                    </div>
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-xl font-bold text-gray-900">{{ t('subscription.plan_name', { name: currentPlan?.name || t('subscription.free') }) }}</h2>
                                    <span
                                        v-if="subscription"
                                        :class="statusColors[subscription.status]"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                    >
                                        <component :is="statusIcons[subscription.status]" class="h-3.5 w-3.5" />
                                        {{ subscription.status_label }}
                                    </span>
                                </div>
                                <p class="mt-1 text-gray-600">{{ currentPlan?.description }}</p>
                            </div>
                            <div class="text-end">
                                <p class="text-3xl font-bold text-gray-900">
                                    {{ currentPlan?.price_monthly > 0 ? formatCurrency(currentPlan.price_monthly) : t('subscription.free') }}
                                </p>
                                <p v-if="currentPlan?.price_monthly > 0" class="text-sm text-gray-500">
                                    {{ t('subscription.per_cycle', { cycle: subscription?.billing_cycle || t('subscription.cycle.month') }) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Details -->
                    <div v-if="subscription" class="p-6 bg-gray-50 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">{{ t('subscription.details.billing_cycle') }}</p>
                            <p class="font-medium text-gray-900 capitalize">{{ subscription.billing_cycle }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">
                                {{ subscription.cancelled_at ? t('subscription.details.ends_on') : t('subscription.details.next_billing') }}
                            </p>
                            <p class="font-medium text-gray-900">{{ formatDate(subscription.ends_at || subscription.current_period_end) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ t('subscription.details.trial_ends') }}</p>
                            <p class="font-medium text-gray-900">
                                {{ subscription.trial_ends_at ? formatDate(subscription.trial_ends_at) : t('subscription.details.na') }}
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="p-6 flex items-center justify-end gap-3 border-t border-gray-100">
                        <button
                            v-if="subscription?.cancelled_at && !subscription?.ended"
                            @click="handleResume"
                            class="px-4 py-2 text-indigo-600 hover:text-indigo-700 font-medium"
                        >
                            {{ t('subscription.actions.resume') }}
                        </button>
                        <button
                            v-else-if="subscription && !currentPlan?.is_free"
                            @click="showCancelModal = true"
                            class="px-4 py-2 text-red-600 hover:text-red-700 font-medium"
                        >
                            {{ t('subscription.actions.cancel') }}
                        </button>
                        <Link
                            :href="route('subscription.plans')"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            {{ currentPlan?.is_free ? t('subscription.actions.upgrade') : t('subscription.actions.change') }}
                        </Link>
                    </div>
                </div>

                <!-- Usage -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">{{ t('subscription.usage.heading') }}</h2>
                    </div>
                    <div class="p-6 border-b border-gray-100">
                        <p class="text-sm text-gray-600">{{ t('subscription.usage.subtitle') }}</p>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="(data, feature) in usage" :key="feature" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700 capitalize">{{ feature }}</span>
                                <span class="text-sm text-gray-500">
                                    {{ data.current }} / {{ data.limit >= 999 ? '∞' : data.limit }}
                                </span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all', /* i18n-ignore */
                                        isAtLimit(data.current, data.limit) ? 'bg-red-500' :
                                        isNearLimit(data.current, data.limit) ? 'bg-yellow-500' : 'bg-indigo-500'
                                    ]"
                                    :style="{ width: usagePercentage(data.current, data.limit) + '%' }"
                                />
                            </div>
                            <p v-if="isAtLimit(data.current, data.limit)" class="text-xs text-red-600">
                                {{ t('subscription.usage.at_limit') }}
                            </p>
                            <p v-else-if="isNearLimit(data.current, data.limit)" class="text-xs text-yellow-600">
                                {{ t('subscription.usage.near_limit') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">{{ t('subscription.payments.heading') }}</h2>
                    </div>
                    <div v-if="payments?.length" class="divide-y divide-gray-100">
                        <div
                            v-for="payment in payments"
                            :key="payment.id"
                            class="p-6 flex items-center justify-between"
                        >
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                    <CreditCardIcon class="h-5 w-5 text-gray-600" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{ t('subscription.payments.line', { plan: payment.subscription?.plan?.name || t('subscription.payments.default_plan') }) }}
                                    </p>
                                    <p class="text-sm text-gray-500">{{ formatDate(payment.paid_at) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-xs font-medium', /* i18n-ignore */
                                        payment.status === 'successful' ? 'bg-green-100 text-green-800' :
                                        payment.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ payment.status_label }}
                                </span>
                                <span class="font-semibold text-gray-900">
                                    {{ formatCurrency(payment.amount, payment.currency) }}
                                </span>
                                <a
                                    :href="route('subscription.invoice', payment.id)"
                                    class="p-2 text-gray-400 hover:text-gray-600"
                                    :title="t('subscription.payments.download')"
                                >
                                    <DocumentArrowDownIcon class="h-5 w-5" />
                                </a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center text-gray-500">
                        {{ t('subscription.payments.empty') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-gray-900/50" @click="showCancelModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('subscription.cancel_modal.title') }}</h3>
                <p class="text-gray-600 mb-4">
                    {{ t('subscription.cancel_modal.intro') }}
                </p>
                <div class="space-y-3 mb-6">
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" v-model="cancelImmediately" :value="false" class="mt-1" />
                        <div>
                            <p class="font-medium text-gray-900">{{ t('subscription.cancel_modal.at_period_end') }}</p>
                            <p class="text-sm text-gray-500">{{ t('subscription.cancel_modal.keep_until', { date: formatDate(subscription?.current_period_end) }) }}</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" v-model="cancelImmediately" :value="true" class="mt-1" />
                        <div>
                            <p class="font-medium text-gray-900">{{ t('subscription.cancel_modal.immediately') }}</p>
                            <p class="text-sm text-gray-500">{{ t('subscription.cancel_modal.immediately_note') }}</p>
                        </div>
                    </label>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                        {{ t('subscription.cancel_modal.keep') }}
                    </button>
                    <button
                        @click="handleCancel"
                        :disabled="cancelForm.processing"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ cancelForm.processing ? t('subscription.cancel_modal.cancelling') : t('subscription.cancel_modal.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
