<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useErrorHandler, useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { SubscriptionPlansPageProps, SubscriptionPlan } from '@/types/settings';
import {
    CheckIcon,
    XMarkIcon,
    SparklesIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<SubscriptionPlansPageProps>();

const { logError } = useErrorHandler();
const { t } = useI18n();
const { formatMoney: formatCurrency } = useFormatters();
const selectedCycle = ref(props.billingCycle || 'monthly');
const isProcessing = ref(false);
const processingPlanId = ref(null);

const getPlanPrice = (plan) => {
    return selectedCycle.value === 'yearly' ? plan.price_yearly : plan.price_monthly;
};

const isCurrentPlan = (plan) => {
    return props.currentPlan?.id === plan.id;
};

const canUpgrade = (plan) => {
    if (!props.currentPlan) return true;
    // Find plan indexes to determine upgrade/downgrade
    const currentIndex = props.plans.findIndex(p => p.id === props.currentPlan.id);
    const targetIndex = props.plans.findIndex(p => p.id === plan.id);
    return targetIndex > currentIndex;
};

const handleSubscribe = async (plan) => {
    if (isCurrentPlan(plan)) return;

    isProcessing.value = true;
    processingPlanId.value = plan.id;

    try {
        // Phase-60 PLAN-CHANGE-3: if the user already has an active
        // subscription, swap-in-place via /subscription/change rather
        // than driving the user through Paystack/Stripe checkout fresh.
        // /subscribe is for first-time onboarding to a paid plan.
        if (props.currentPlan && props.currentPlan.id !== plan.id) {
            router.post(route('subscription.change'), {
                new_plan_id: plan.id,
            });
            return;
        }

        // For free plan, just submit directly
        if (plan.is_free) {
            router.post(route('subscription.subscribe'), {
                plan_id: plan.id,
                billing_cycle: 'monthly',
            });
            return;
        }

        // For paid plans, initialize Paystack payment
        const response = await fetch(route('subscription.subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                plan_id: plan.id,
                billing_cycle: selectedCycle.value,
            }),
        });

        const data = await response.json();

        if (data.authorization_url) {
            // Redirect to Paystack
            window.location.href = data.authorization_url;
        } else if (data.error) {
            alert(data.error);
        }
    } catch (error) {
        logError(error, { component: 'SubscriptionPlans', action: 'subscribe' });
        alert(t('subscription.plans.subscribe_failed'));
    } finally {
        isProcessing.value = false;
        processingPlanId.value = null;
    }
};

const popularPlan = computed(() => {
    return props.plans.find(p => p.slug === 'pro');
});
</script>

<template>
    <Head :title="t('subscription.plans.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Back Link -->
                <Link
                    :href="route('subscription.index')"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-600 mb-6"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    {{ t('subscription.plans.back') }}
                </Link>

                <!-- Header -->
                <div class="text-center mb-10">
                    <h1 class="text-3xl font-bold text-gray-900">{{ t('subscription.plans.title') }}</h1>
                    <p class="mt-2 text-gray-600">{{ t('subscription.plans.subtitle') }}</p>
                </div>

                <!-- Billing Toggle -->
                <div class="flex items-center justify-center gap-4 mb-10">
                    <span :class="selectedCycle === 'monthly' ? 'text-gray-900 font-medium' : 'text-gray-500'">
                        {{ t('subscription.plans.monthly') }}
                    </span>
                    <button
                        @click="selectedCycle = selectedCycle === 'monthly' ? 'yearly' : 'monthly'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                        :class="selectedCycle === 'yearly' ? 'bg-indigo-600' : 'bg-gray-200'"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                            :class="selectedCycle === 'yearly' ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                    <span :class="selectedCycle === 'yearly' ? 'text-gray-900 font-medium' : 'text-gray-500'">
                        {{ t('subscription.plans.yearly') }}
                        <span class="ms-1 text-green-600 text-sm font-medium">{{ t('subscription.plans.save_up_to') }}</span>
                    </span>
                </div>

                <!-- Plans Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="['relative bg-white rounded-2xl border-2 p-6 flex flex-col', isCurrentPlan(plan) ? 'border-indigo-500 ring-2 ring-indigo-500' : plan.slug === 'pro' ? 'border-indigo-200' : 'border-gray-200']"
                    >
                        <!-- Popular Badge -->
                        <div
                            v-if="plan.slug === 'pro'"
                            class="absolute -top-3 start-1/2 -translate-x-1/2 px-3 py-1 bg-indigo-600 text-white text-xs font-bold rounded-full"
                        >
                            {{ t('subscription.plans.popular_badge') }}
                        </div>

                        <!-- Current Badge -->
                        <div
                            v-if="isCurrentPlan(plan)"
                            class="absolute -top-3 start-1/2 -translate-x-1/2 px-3 py-1 bg-green-600 text-white text-xs font-bold rounded-full"
                        >
                            {{ t('subscription.plans.current_badge') }}
                        </div>

                        <div class="text-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900">{{ plan.name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ plan.description }}</p>
                        </div>

                        <div class="text-center mb-6">
                            <span class="text-4xl font-bold text-gray-900">
                                {{ plan.is_free ? t('subscription.free') : formatCurrency(getPlanPrice(plan)) }}
                            </span>
                            <span v-if="!plan.is_free" class="text-gray-500">
                                /{{ selectedCycle === 'yearly' ? t('subscription.plans.year') : t('subscription.cycle.month') }}
                            </span>
                            <div v-if="selectedCycle === 'yearly' && plan.yearly_savings > 0" class="mt-1">
                                <span class="text-sm text-green-600 font-medium">
                                    {{ t('subscription.plans.save_amount', { amount: formatCurrency(plan.yearly_savings) }) }}
                                </span>
                            </div>
                        </div>

                        <ul class="space-y-3 mb-6 flex-1">
                            <li
                                v-for="(feature, index) in plan.features"
                                :key="index"
                                class="flex items-start gap-2"
                            >
                                <CheckIcon class="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                <span class="text-sm text-gray-600">{{ feature }}</span>
                            </li>
                        </ul>

                        <button
                            @click="handleSubscribe(plan)"
                            :disabled="isCurrentPlan(plan) || (isProcessing && processingPlanId === plan.id)"
                            :class="['w-full py-3 px-4 rounded-lg font-medium transition-colors', isCurrentPlan(plan) ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : plan.slug === 'pro' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-900 text-white hover:bg-gray-800']"
                        >
                            <span v-if="isProcessing && processingPlanId === plan.id">
                                {{ t('subscription.plans.processing') }}
                            </span>
                            <span v-else-if="isCurrentPlan(plan)">
                                {{ t('subscription.plans.current_plan') }}
                            </span>
                            <span v-else-if="canUpgrade(plan)">
                                {{ plan.is_free ? t('subscription.plans.downgrade') : t('subscription.plans.upgrade') }}
                            </span>
                            <span v-else>
                                {{ t('subscription.plans.downgrade') }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- FAQ -->
                <div class="mt-16 max-w-3xl mx-auto">
                    <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">{{ t('subscription.plans.faq.heading') }}</h2>
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900">{{ t('subscription.plans.faq.change_q') }}</h3>
                            <p class="mt-2 text-gray-600">
                                {{ t('subscription.plans.faq.change_a') }}
                            </p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900">{{ t('subscription.plans.faq.limits_q') }}</h3>
                            <p class="mt-2 text-gray-600">
                                {{ t('subscription.plans.faq.limits_a') }}
                            </p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900">{{ t('subscription.plans.faq.trial_q') }}</h3>
                            <p class="mt-2 text-gray-600">
                                {{ t('subscription.plans.faq.trial_a') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
