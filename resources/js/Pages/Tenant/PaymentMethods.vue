<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CreditCardIcon, TrashIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline';

interface Method {
    id: number;
    type: 'mpesa' | 'bank' | 'card';
    is_default: boolean;
    summary: string;
}

const props = defineProps<{ methods: Method[] }>();

const form = useForm({
    type: 'mpesa' as 'mpesa' | 'bank' | 'card',
    is_default: false,
    phone: '',
    bank_name: '',
    account_name: '',
    account_number: '',
    brand: '',
    last4: '',
});

const submit = () => form.post(route('tenant.payment-methods.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const setDefault = (id: number) => router.patch(route('tenant.payment-methods.default', id), {}, { preserveScroll: true });
const remove = (id: number) => router.delete(route('tenant.payment-methods.destroy', id), { preserveScroll: true });

const typeLabel = (type: string) => ({
    mpesa: 'M-Pesa',
    bank: 'Bank transfer',
    card: 'Card',
}[type] ?? type);

const hasMethods = computed(() => props.methods.length > 0);
</script>

<template>
    <Head :title="$t('tenant_payment_method.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $t('tenant_payment_method.title') }}</h1>
                    <p class="text-gray-600 mt-1">{{ $t('tenant_payment_method.subtitle') }}</p>
                </div>

                <!-- Saved methods -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('tenant_payment_method.saved') }}</h2>
                    <ul v-if="hasMethods" class="divide-y divide-gray-100">
                        <li v-for="m in methods" :key="m.id" class="py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <CreditCardIcon class="w-5 h-5 text-gray-400 shrink-0" />
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ typeLabel(m.type) }}</span>
                                        <span v-if="m.is_default" class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                            <CheckBadgeIcon class="w-3 h-3" /> {{ $t('tenant_payment_method.default') }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500 truncate">{{ m.summary }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <button v-if="!m.is_default" type="button" @click="setDefault(m.id)" class="text-xs text-gray-600 hover:text-gray-900">
                                    {{ $t('tenant_payment_method.make_default') }}
                                </button>
                                <button type="button" @click="remove(m.id)" class="text-red-600 hover:text-red-800" :title="$t('tenant_payment_method.remove')">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">{{ $t('tenant_payment_method.empty') }}</p>
                </div>

                <!-- Add method -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('tenant_payment_method.add') }}</h2>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="pm-type" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.type') }}</label>
                            <select id="pm-type" v-model="form.type" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">{{ $t('tenant_payment_method.bank') }}</option>
                                <option value="card">{{ $t('tenant_payment_method.card') }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'mpesa'">
                            <label for="pm-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.phone') }}</label>
                            <input id="pm-phone" v-model="form.phone" type="tel" placeholder="+2547..." class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>

                        <template v-else-if="form.type === 'bank'">
                            <div>
                                <label for="pm-bank-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.bank_name') }}</label>
                                <input id="pm-bank-name" v-model="form.bank_name" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.bank_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_name }}</p>
                            </div>
                            <div>
                                <label for="pm-account-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.account_name') }}</label>
                                <input id="pm-account-name" v-model="form.account_name" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.account_name" class="mt-1 text-sm text-red-600">{{ form.errors.account_name }}</p>
                            </div>
                            <div>
                                <label for="pm-account-number" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.account_number') }}</label>
                                <input id="pm-account-number" v-model="form.account_number" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.account_number" class="mt-1 text-sm text-red-600">{{ form.errors.account_number }}</p>
                            </div>
                        </template>

                        <template v-else-if="form.type === 'card'">
                            <div>
                                <label for="pm-brand" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.brand') }}</label>
                                <input id="pm-brand" v-model="form.brand" type="text" placeholder="Visa" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.brand" class="mt-1 text-sm text-red-600">{{ form.errors.brand }}</p>
                            </div>
                            <div>
                                <label for="pm-last4" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_payment_method.last4') }}</label>
                                <input id="pm-last4" v-model="form.last4" type="text" inputmode="numeric" maxlength="4" placeholder="4242" class="w-40 border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.last4" class="mt-1 text-sm text-red-600">{{ form.errors.last4 }}</p>
                            </div>
                        </template>

                        <label class="flex items-center gap-2">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">{{ $t('tenant_payment_method.set_default') }}</span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ $t('tenant_payment_method.add') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
