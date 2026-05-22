<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFormatters } from '@/composables';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import DocumentTextIcon from '@heroicons/vue/24/outline/DocumentTextIcon';
import TrashIcon from '@heroicons/vue/24/outline/TrashIcon';

interface PartyRow {
    id: number;
    name: string;
    email?: string | null;
    relationship?: string | null;
    is_responsible_for_rent?: boolean;
    liability_share?: number | null;
    guaranteed_amount?: number | null;
    status?: string;
}

interface EscalationRow {
    id: number;
    escalation_type: string;
    amount: number;
    effective_date: string;
    status: string;
    new_rent_amount?: number | null;
}

interface TimelineEvent { type: string; date: string; title: string; detail: string }

interface LeaseShow {
    id: number;
    rent_amount: number;
    deposit_amount: number;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    tenant?: { id: number; name: string; email?: string } | null;
    unit?: { id: number; unit_number: string; building?: { name: string } } | null;
    rent_escalations?: EscalationRow[];
    co_tenants?: PartyRow[];
    guarantors?: PartyRow[];
    documents?: { id: number; title: string; document_type: string; uploaded_at?: string }[];
}

const props = defineProps<{
    lease: LeaseShow;
    activeRenewal?: { id: number; status: string } | null;
    timeline: TimelineEvent[];
}>();

const { formatMoney: formatCurrency, formatDate } = useFormatters();

const escalationForm = useForm({ escalation_type: 'percentage', amount: '', effective_date: '', notes: '' });
const coTenantForm = useForm({ name: '', email: '', phone: '', relationship: '', is_responsible_for_rent: false, liability_share: '' });
const guarantorForm = useForm({ name: '', email: '', phone: '', relationship: '', guaranteed_amount: '' });

const submitEscalation = () => escalationForm.post(route('rent-escalations.store', props.lease.id), {
    preserveScroll: true, onSuccess: () => escalationForm.reset(),
});
const cancelEscalation = (id: number) => router.delete(route('rent-escalations.destroy', id), { preserveScroll: true });

const submitCoTenant = () => coTenantForm.post(route('lease-co-tenants.store', props.lease.id), {
    preserveScroll: true, onSuccess: () => coTenantForm.reset(),
});
const removeCoTenant = (id: number) => router.delete(route('lease-co-tenants.destroy', id), { preserveScroll: true });

const submitGuarantor = () => guarantorForm.post(route('lease-guarantors.store', props.lease.id), {
    preserveScroll: true, onSuccess: () => guarantorForm.reset(),
});
const releaseGuarantor = (id: number) => router.post(route('lease-guarantors.release', id), {}, { preserveScroll: true });

const generateAgreement = () => router.post(route('documents.generate-lease', props.lease.id), {}, { preserveScroll: true });
const generateRenewalOffer = () => {
    if (props.activeRenewal) {
        router.post(route('documents.generate-renewal-offer', props.activeRenewal.id), {}, { preserveScroll: true });
    }
};

const escalations = computed(() => props.lease.rent_escalations ?? []);
const coTenants = computed(() => props.lease.co_tenants ?? []);
const guarantors = computed(() => props.lease.guarantors ?? []);
const documents = computed(() => props.lease.documents ?? []);
</script>

<template>
    <Head :title="`Lease #${lease.id}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div>
                    <Link :href="route('leases.index')" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 mb-4">
                        <ArrowLeftIcon class="w-4 h-4" /> {{ $t('lease.lifecycle.back') }}
                    </Link>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">
                                {{ lease.tenant?.name ?? '—' }} · {{ lease.unit?.building?.name }} {{ lease.unit?.unit_number }}
                            </h1>
                            <p class="text-sm text-gray-500">
                                {{ formatCurrency(lease.rent_amount) }} ·
                                {{ lease.start_date ? formatDate(lease.start_date) : '—' }} → {{ lease.end_date ? formatDate(lease.end_date) : '—' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="generateAgreement" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                                <DocumentTextIcon class="w-4 h-4" /> {{ $t('lease_doc.agreement.generate') }}
                            </button>
                            <button v-if="activeRenewal" type="button" @click="generateRenewalOffer" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                                <DocumentTextIcon class="w-4 h-4" /> {{ $t('lease_doc.renewal.generate') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rent escalations -->
                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('lease.escalation.title') }}</h2>
                    <table v-if="escalations.length" class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead><tr class="text-start text-xs font-medium text-gray-500 uppercase">
                            <th class="py-2 text-start">{{ $t('lease.escalation.type') }}</th>
                            <th class="py-2 text-start">{{ $t('lease.escalation.amount') }}</th>
                            <th class="py-2 text-start">{{ $t('lease.escalation.effective_date') }}</th>
                            <th class="py-2 text-start">{{ $t('lease.escalation.status_scheduled') }}</th>
                            <th></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="e in escalations" :key="e.id">
                                <td class="py-2">{{ $t('lease.escalation.' + e.escalation_type) }}</td>
                                <td class="py-2">{{ e.escalation_type === 'percentage' ? e.amount + '%' : formatCurrency(e.amount) }}</td>
                                <td class="py-2">{{ formatDate(e.effective_date) }}</td>
                                <td class="py-2">{{ $t('lease.escalation.status_' + e.status) }}</td>
                                <td class="py-2 text-end">
                                    <button v-if="e.status === 'scheduled'" type="button" @click="cancelEscalation(e.id)" class="text-red-600 hover:text-red-800 text-xs">{{ $t('lease.escalation.cancel') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500 mb-4">{{ $t('lease.escalation.empty') }}</p>

                    <form @submit.prevent="submitEscalation" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('lease.escalation.type') }}</label>
                            <select v-model="escalationForm.escalation_type" class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="percentage">{{ $t('lease.escalation.percentage') }}</option>
                                <option value="fixed_amount">{{ $t('lease.escalation.fixed_amount') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('lease.escalation.amount') }}</label>
                            <input v-model="escalationForm.amount" type="number" step="0.01" min="0.01" required class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('lease.escalation.effective_date') }}</label>
                            <input v-model="escalationForm.effective_date" type="date" required class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <button type="submit" :disabled="escalationForm.processing" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 disabled:opacity-50 text-sm">
                            {{ $t('lease.escalation.add') }}
                        </button>
                    </form>
                </section>

                <!-- Co-tenants -->
                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('lease.co_tenant.title') }}</h2>
                    <ul v-if="coTenants.length" class="divide-y divide-gray-100 mb-4">
                        <li v-for="c in coTenants" :key="c.id" class="py-2 flex items-center justify-between text-sm">
                            <span>{{ c.name }}<span v-if="c.relationship" class="text-gray-500"> · {{ c.relationship }}</span></span>
                            <button type="button" @click="removeCoTenant(c.id)" class="text-red-600 hover:text-red-800"><TrashIcon class="w-4 h-4" /></button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 mb-4">{{ $t('lease.co_tenant.empty') }}</p>

                    <form @submit.prevent="submitCoTenant" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <input v-model="coTenantForm.name" type="text" required :placeholder="$t('lease.co_tenant.name')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <input v-model="coTenantForm.email" type="email" :placeholder="$t('lease.co_tenant.email')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <input v-model="coTenantForm.relationship" type="text" :placeholder="$t('lease.co_tenant.relationship')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <button type="submit" :disabled="coTenantForm.processing" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 disabled:opacity-50 text-sm">
                            {{ $t('lease.co_tenant.add') }}
                        </button>
                    </form>
                </section>

                <!-- Guarantors -->
                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('lease.guarantor.title') }}</h2>
                    <ul v-if="guarantors.length" class="divide-y divide-gray-100 mb-4">
                        <li v-for="g in guarantors" :key="g.id" class="py-2 flex items-center justify-between text-sm">
                            <span>{{ g.name }}<span class="text-gray-500"> · {{ $t('lease.guarantor.status_' + (g.status ?? 'active')) }}</span></span>
                            <button v-if="g.status === 'active'" type="button" @click="releaseGuarantor(g.id)" class="text-amber-600 hover:text-amber-800 text-xs">{{ $t('lease.guarantor.release') }}</button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 mb-4">{{ $t('lease.guarantor.empty') }}</p>

                    <form @submit.prevent="submitGuarantor" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <input v-model="guarantorForm.name" type="text" required :placeholder="$t('lease.guarantor.name')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <input v-model="guarantorForm.relationship" type="text" :placeholder="$t('lease.guarantor.relationship')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <input v-model="guarantorForm.guaranteed_amount" type="number" step="0.01" min="0" :placeholder="$t('lease.guarantor.guaranteed_amount')" class="w-full border-gray-300 rounded-lg text-sm" />
                        <button type="submit" :disabled="guarantorForm.processing" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 disabled:opacity-50 text-sm">
                            {{ $t('lease.guarantor.add') }}
                        </button>
                    </form>
                </section>

                <!-- Timeline -->
                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('lease.lifecycle.timeline') }}</h2>
                    <ol v-if="timeline.length" class="space-y-3">
                        <li v-for="(ev, i) in timeline" :key="i" class="flex gap-3 text-sm">
                            <span class="text-gray-400 w-24 shrink-0">{{ formatDate(ev.date) }}</span>
                            <span class="font-medium text-gray-800 w-40 shrink-0">{{ ev.title }}</span>
                            <span class="text-gray-500">{{ ev.detail }}</span>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-gray-500">{{ $t('lease.lifecycle.empty') }}</p>
                </section>

                <!-- Documents -->
                <section v-if="documents.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $t('lease.lifecycle.documents') }}</h2>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="d in documents" :key="d.id" class="py-2 flex items-center justify-between">
                            <span>{{ d.title }}</span>
                            <a :href="route('documents.download', d.id)" class="text-gray-600 hover:text-gray-900 text-xs">{{ $t('lease.lifecycle.download') }}</a>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
