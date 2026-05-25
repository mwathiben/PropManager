<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { useZodForm } from '@/composables/forms/useZodForm';
import { moveOutSchema } from '@/composables/forms/schemas/moveOutSchema';
import type { MoveOutCreatePageProps } from '@/types/finances';
import {
    ArrowLeftIcon,
    ArrowRightOnRectangleIcon,
    HomeIcon,
    UserCircleIcon,
    CalendarDaysIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<MoveOutCreatePageProps>();
const { t } = useI18n();
const { formatMoney: formatCurrency, formatDate, todayAsISODate } = useFormatters();

const tenant = props.lease.tenant;
const unit = props.lease.unit;

const form = useForm({
    notice_date: todayAsISODate(),
    intended_move_out_date: '',
    reason: '',
});

const { validate } = useZodForm(form, moveOutSchema);

const submit = () => {
    if (!validate()) {
        return;
    }
    form.post(route('move-outs.store', props.lease.id));
};
</script>

<template>
    <Head :title="t('moveouts.create.head_title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex items-center gap-4">
                    <Link :href="route('tenants.show', tenant.id)" class="text-gray-400 hover:text-gray-600">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('moveouts.create.title') }}</h1>
                        <p class="text-sm text-gray-500">{{ t('moveouts.create.subtitle', { name: tenant.name }) }}</p>
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
                                <h3 class="text-sm font-medium text-gray-500">{{ t('moveouts.create.tenant_label') }}</h3>
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
                                <h3 class="text-sm font-medium text-gray-500">{{ t('moveouts.create.unit_label') }}</h3>
                                <p class="text-lg font-semibold text-gray-900">{{ t('moveouts.create.unit_prefix') }} {{ unit.unit_number }}</p>
                                <p class="text-sm text-gray-500">{{ unit.building?.name }} - {{ unit.building?.property?.name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">{{ t('moveouts.create.financial_summary') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">{{ t('moveouts.create.deposit_held') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(lease.deposit_amount) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">{{ t('moveouts.create.current_arrears') }}</p>
                                <p class="text-lg font-bold" :class="lease.arrears > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ formatCurrency(lease.arrears || 0) }}
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">{{ t('moveouts.create.monthly_rent') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatCurrency(lease.rent_amount) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-500">{{ t('moveouts.create.lease_started') }}</p>
                                <p class="text-lg font-bold text-gray-900">{{ formatDate(lease.start_date, 'short') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Move-Out Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <ArrowRightOnRectangleIcon class="w-5 h-5" />
                            {{ t('moveouts.create.details_heading') }}
                        </h3>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <CalendarDaysIcon class="w-4 h-4 inline me-1" />
                                    {{ t('moveouts.create.notice_date_label') }}
                                </label>
                                <input
                                    v-model="form.notice_date"
                                    type="date"
                                    required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('moveouts.create.notice_date_help') }}</p>
                                <p v-if="form.errors.notice_date" class="mt-1 text-sm text-red-600">{{ form.errors.notice_date }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <CalendarDaysIcon class="w-4 h-4 inline me-1" />
                                    {{ t('moveouts.create.intended_date_label') }}
                                </label>
                                <input
                                    v-model="form.intended_move_out_date"
                                    type="date"
                                    required
                                    :min="form.notice_date"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('moveouts.create.intended_date_help') }}</p>
                                <p v-if="form.errors.intended_move_out_date" class="mt-1 text-sm text-red-600">{{ form.errors.intended_move_out_date }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('moveouts.create.reason_label') }}</label>
                            <textarea
                                v-model="form.reason"
                                rows="3"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                :placeholder="t('moveouts.create.reason_placeholder')"
                            ></textarea>
                        </div>

                        <!-- What happens next -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">{{ t('moveouts.create.next_heading') }}</h4>
                            <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                                <li>{{ t('moveouts.create.next_step_1') }}</li>
                                <li>{{ t('moveouts.create.next_step_2') }}</li>
                                <li>{{ t('moveouts.create.next_step_3') }}</li>
                                <li>{{ t('moveouts.create.next_step_4') }}</li>
                                <li>{{ t('moveouts.create.next_step_5') }}</li>
                            </ol>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <Link
                                :href="route('tenants.show', tenant.id)"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                {{ t('moveouts.create.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <ArrowRightOnRectangleIcon class="w-5 h-5" />
                                {{ form.processing ? t('moveouts.create.submitting') : t('moveouts.create.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
