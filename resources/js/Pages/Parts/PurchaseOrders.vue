<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface PartLine {
    id: number;
    part: {
        id: number;
        name: string;
        sku: string | null;
        qty_available: number;
        reorder_threshold: number;
    };
    qty_suggested: number;
    cost_per_unit_cents_snapshot: number;
}

interface Order {
    id: number;
    status: 'draft' | 'sent' | 'cancelled';
    sent_at: string | null;
    vendor: { id: number; name: string; email: string | null } | null;
    lines: PartLine[];
    total_cents: number;
}

const props = defineProps<{
    orders: Order[];
}>();

const formatKes = (cents: number): string =>
    new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        maximumFractionDigits: 0,
    }).format(cents / 100);

function confirm(order: Order): void {
    if (!window.confirm(`Mark order #${order.id} as sent to ${order.vendor?.name ?? 'vendor'}?`)) return;
    router.post(route('parts.purchase-orders.confirm', order.id), {}, { preserveScroll: true });
}

function cancel(order: Order): void {
    if (!window.confirm(`Cancel order #${order.id}?`)) return;
    router.post(route('parts.purchase-orders.cancel', order.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Parts purchase orders" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Draft purchase orders</h1>
        </template>

        <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 lg:px-8">
            <p class="text-sm text-gray-600">
                Parts whose stock has dropped to or below the reorder threshold are grouped into draft orders by suggested vendor. Confirm an order to mark it sent; cancel to dismiss.
            </p>

            <p v-if="!props.orders.length" class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
                No draft orders yet. Run <code>php artisan parts:reorder-suggest</code> after parts cross their reorder threshold.
            </p>

            <article
                v-for="order in props.orders"
                :key="order.id"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
            >
                <header class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">
                            Order #{{ order.id }} — {{ order.vendor?.name ?? 'No suggested vendor' }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            {{ order.lines.length }} part(s) · {{ formatKes(order.total_cents) }}
                        </p>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-medium capitalize"
                        :class="{
                            'bg-indigo-100 text-indigo-700': order.status === 'draft',
                            'bg-emerald-100 text-emerald-700': order.status === 'sent',
                            'bg-gray-100 text-gray-500': order.status === 'cancelled',
                        }"
                    >
                        {{ order.status }}
                    </span>
                </header>

                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Part</th>
                            <th class="px-2 py-2">SKU</th>
                            <th class="px-2 py-2">On hand</th>
                            <th class="px-2 py-2">Reorder at</th>
                            <th class="px-2 py-2">Suggested qty</th>
                            <th class="px-2 py-2 text-end">Unit cost</th>
                            <th class="px-2 py-2 text-end">Line total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="line in order.lines" :key="line.id">
                            <td class="px-2 py-2 text-gray-900">{{ line.part.name }}</td>
                            <td class="px-2 py-2 text-gray-600">{{ line.part.sku ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ line.part.qty_available }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ line.part.reorder_threshold }}</td>
                            <td class="px-2 py-2 text-gray-900">{{ line.qty_suggested }}</td>
                            <td class="px-2 py-2 text-end text-gray-900">{{ formatKes(line.cost_per_unit_cents_snapshot) }}</td>
                            <td class="px-2 py-2 text-end text-gray-900">{{ formatKes(line.qty_suggested * line.cost_per_unit_cents_snapshot) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="order.status === 'draft'" class="mt-3 flex justify-end gap-2">
                    <button class="rounded border border-gray-300 px-3 py-1.5 text-sm" @click="cancel(order)">Cancel</button>
                    <button class="rounded bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700" @click="confirm(order)">Mark as sent</button>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
