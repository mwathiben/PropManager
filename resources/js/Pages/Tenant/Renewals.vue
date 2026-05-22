<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import { ArrowPathIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

interface Renewal {
    id: number;
    status: string;
    proposed_rent: number;
    proposed_end_date: string | null;
    notes: string | null;
    counter_rent: number | null;
    counter_end_date: string | null;
    counter_message: string | null;
    can_respond: boolean;
}

const props = defineProps<{
    hasLease: boolean;
    lease: { rent_amount: number; end_date: string | null; unit?: string; building?: string } | null;
    renewal: Renewal | null;
    offerDocumentId: number | null;
}>();

const { formatMoney: formatCurrency, formatDate } = useFormatters();

const mode = ref<'none' | 'reject' | 'counter'>('none');

const acceptForm = useForm({});
const rejectForm = useForm({ rejection_reason: '' });
const counterForm = useForm({ counter_rent_amount_cents: '', counter_end_date: '', counter_message: '' });

const accept = () => {
    if (props.renewal) acceptForm.post(route('tenant.renewals.accept', props.renewal.id), { preserveScroll: true });
};
const reject = () => {
    if (props.renewal) rejectForm.post(route('tenant.renewals.reject', props.renewal.id), { preserveScroll: true });
};
const counter = () => {
    if (!props.renewal) return;
    counterForm
        .transform((d) => ({ ...d, counter_rent_amount_cents: Math.round(Number(d.counter_rent_amount_cents) * 100) }))
        .post(route('tenant.renewals.counter', props.renewal.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('tenant_renewal.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-indigo-100">
                        <ArrowPathIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $t('tenant_renewal.title') }}</h1>
                        <p class="text-gray-600">{{ $t('tenant_renewal.subtitle') }}</p>
                    </div>
                </div>

                <div v-if="!renewal" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
                    {{ $t('tenant_renewal.none') }}
                </div>

                <template v-else>
                    <!-- Offer: current vs proposed -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('tenant_renewal.offer') }}</h2>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-gray-500">
                                    <th class="text-start py-2"></th>
                                    <th class="text-start py-2">{{ $t('tenant_renewal.current') }}</th>
                                    <th class="text-start py-2">{{ $t('tenant_renewal.proposed') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="py-2 text-gray-600">{{ $t('tenant_renewal.rent') }}</td>
                                    <td class="py-2">{{ formatCurrency(lease?.rent_amount ?? 0) }}</td>
                                    <td class="py-2 font-medium">{{ formatCurrency(renewal.proposed_rent) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">{{ $t('tenant_renewal.end_date') }}</td>
                                    <td class="py-2">{{ lease?.end_date ? formatDate(lease.end_date) : '—' }}</td>
                                    <td class="py-2 font-medium">{{ renewal.proposed_end_date ? formatDate(renewal.proposed_end_date) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="renewal.notes" class="mt-4 text-sm text-gray-600">{{ renewal.notes }}</p>

                        <a
                            v-if="offerDocumentId"
                            :href="route('tenant.documents.download', offerDocumentId)"
                            class="mt-4 inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            <DocumentArrowDownIcon class="w-4 h-4" /> {{ $t('tenant_renewal.download_offer') }}
                        </a>

                        <p v-if="!renewal.can_respond" class="mt-4 text-sm text-amber-600">
                            {{ $t('tenant_renewal.status_' + renewal.status) }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div v-if="renewal.can_respond" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                        <div v-if="mode === 'none'" class="flex flex-wrap gap-3">
                            <button type="button" @click="accept" :disabled="acceptForm.processing" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                                {{ $t('tenant_renewal.accept') }}
                            </button>
                            <button type="button" @click="mode = 'counter'" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                {{ $t('tenant_renewal.counter') }}
                            </button>
                            <button type="button" @click="mode = 'reject'" class="px-4 py-2 bg-white border border-gray-300 text-red-700 rounded-lg hover:bg-red-50">
                                {{ $t('tenant_renewal.reject') }}
                            </button>
                        </div>

                        <form v-else-if="mode === 'reject'" @submit.prevent="reject" class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">{{ $t('tenant_renewal.reject_reason') }}</label>
                            <textarea v-model="rejectForm.rejection_reason" rows="3" maxlength="500" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="rejectForm.processing" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">{{ $t('tenant_renewal.reject') }}</button>
                                <button type="button" @click="mode = 'none'" class="px-4 py-2 text-gray-600">{{ $t('tenant_renewal.cancel') }}</button>
                            </div>
                        </form>

                        <form v-else-if="mode === 'counter'" @submit.prevent="counter" class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_renewal.counter_rent') }}</label>
                                <input v-model="counterForm.counter_rent_amount_cents" type="number" min="1" step="0.01" required class="w-48 border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="counterForm.errors.counter_rent_amount_cents" class="mt-1 text-sm text-red-600">{{ counterForm.errors.counter_rent_amount_cents }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_renewal.counter_end_date') }}</label>
                                <input v-model="counterForm.counter_end_date" type="date" required class="border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="counterForm.errors.counter_end_date" class="mt-1 text-sm text-red-600">{{ counterForm.errors.counter_end_date }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('tenant_renewal.counter_message') }}</label>
                                <textarea v-model="counterForm.counter_message" rows="2" maxlength="500" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="counterForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">{{ $t('tenant_renewal.send_counter') }}</button>
                                <button type="button" @click="mode = 'none'" class="px-4 py-2 text-gray-600">{{ $t('tenant_renewal.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
