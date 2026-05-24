<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import {
    ArrowLeftIcon,
    BanknotesIcon,
    DocumentTextIcon,
    HomeModernIcon,
    UserIcon,
    XCircleIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

interface Tenant {
    id: number;
    name: string;
    email: string | null;
    mobile_number: string | null;
}

interface Unit {
    id: number;
    unit_number: string;
    building: { id: number; name: string } | null;
}

interface Lease {
    id: number;
    state: 'active' | 'ended' | 'soft_deleted' | 'unknown';
    rent_amount: number;
    tenant: Tenant | null;
    unit: Unit | null;
}

interface Invoice {
    id: number;
    invoice_number: string;
    total_due: number;
    amount_paid: number;
    status: string;
}

interface PaymentDetail {
    id: number;
    amount: number;
    currency: string;
    payment_method: string;
    reference: string | null;
    payment_date: string | null;
    created_at: string | null;
    is_voided: boolean;
    voided_at: string | null;
    void_reason: string | null;
}

const props = defineProps<{
    payment: PaymentDetail;
    invoice: Invoice | null;
    lease: Lease | null;
}>();

const kesFormatter = new Intl.NumberFormat('en-KE', {
    style: 'currency',
    currency: 'KES',
});

const leaseBadge = computed(() => {
    const map = {
        active: { label: 'Active lease', cls: 'bg-emerald-100 text-emerald-800' },
        ended: { label: 'Ended lease', cls: 'bg-gray-100 text-gray-800' },
        soft_deleted: { label: 'Archived lease', cls: 'bg-rose-100 text-rose-800' },
        unknown: { label: 'Unknown', cls: 'bg-gray-100 text-gray-600' },
    } as const;
    return map[props.lease?.state ?? 'unknown'];
});
</script>

<template>
    <Head :title="`Payment #${payment.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                    >
                        <ArrowLeftIcon class="mr-1 h-4 w-4" />
                        Back to dashboard
                    </Link>
                </div>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">
                    Payment #{{ payment.id }}
                </h1>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-lg bg-white shadow">
                    <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <BanknotesIcon class="h-6 w-6 text-emerald-600" />
                        <h3 class="text-base font-semibold text-gray-900">Transaction</h3>
                        <span
                            v-if="payment.is_voided"
                            class="ml-auto inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800"
                        >
                            <XCircleIcon class="mr-1 h-3.5 w-3.5" />
                            Voided
                        </span>
                    </header>
                    <dl class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Amount</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">
                                {{ kesFormatter.format(payment.amount) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Method</dt>
                            <dd class="mt-1 text-sm capitalize text-gray-900">{{ payment.payment_method }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Payment date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ payment.payment_date ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Reference</dt>
                            <dd class="mt-1 break-all text-sm text-gray-900">{{ payment.reference ?? '—' }}</dd>
                        </div>
                        <div v-if="payment.is_voided">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Voided at</dt>
                            <dd class="mt-1 text-sm text-rose-700">{{ payment.voided_at ?? '—' }}</dd>
                        </div>
                        <div v-if="payment.is_voided">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Void reason</dt>
                            <dd class="mt-1 text-sm text-rose-700">{{ payment.void_reason ?? '—' }}</dd>
                        </div>
                    </dl>
                    <footer class="border-t border-gray-100 bg-gray-50 px-6 py-3">
                        <Link
                            :href="route('payments.downloadReceipt', payment.id)"
                            class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 hover:text-emerald-900"
                        >
                            <ArrowDownTrayIcon class="h-4 w-4" />
                            Download receipt
                        </Link>
                    </footer>
                </section>

                <section v-if="invoice" class="overflow-hidden rounded-lg bg-white shadow">
                    <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <DocumentTextIcon class="h-6 w-6 text-blue-600" />
                        <h3 class="text-base font-semibold text-gray-900">Invoice</h3>
                    </header>
                    <dl class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <Link
                                    :href="route('invoices.show', invoice.id)"
                                    class="font-medium text-blue-700 hover:text-blue-900"
                                >
                                    {{ invoice.invoice_number }}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm capitalize text-gray-900">{{ invoice.status }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Total due</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ kesFormatter.format(invoice.total_due) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Amount paid</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ kesFormatter.format(invoice.amount_paid) }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="lease" class="overflow-hidden rounded-lg bg-white shadow">
                    <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <HomeModernIcon class="h-6 w-6 text-amber-600" />
                        <h3 class="text-base font-semibold text-gray-900">Lease &amp; tenant</h3>
                        <span
                            class="ml-auto inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="leaseBadge.cls"
                            :aria-label="leaseBadge.label"
                        >
                            {{ leaseBadge.label }}
                        </span>
                    </header>
                    <dl class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">
                        <div v-if="lease.tenant">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Tenant</dt>
                            <dd class="mt-1 flex items-center gap-1.5 text-sm text-gray-900">
                                <UserIcon class="h-4 w-4 text-gray-400" />
                                {{ lease.tenant.name }}
                            </dd>
                        </div>
                        <div v-if="lease.tenant?.email">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ lease.tenant.email }}</dd>
                        </div>
                        <div v-if="lease.tenant?.mobile_number">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Mobile</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ lease.tenant.mobile_number }}</dd>
                        </div>
                        <div v-if="lease.unit">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Unit</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ lease.unit.building?.name ?? '—' }} · {{ lease.unit.unit_number }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Rent</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ kesFormatter.format(lease.rent_amount) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
