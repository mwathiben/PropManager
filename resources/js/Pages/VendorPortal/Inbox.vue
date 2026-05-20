<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import VendorPortalLayout from '@/Layouts/VendorPortalLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useFormatters } from '@/composables/useFormatters';

interface VendorTicket {
    id: number;
    title: string;
    status: string;
    priority: string;
    location: string | null;
    vendor_status: string | null;
    resolution_due_at: string | null;
    created_at: string | null;
}

interface Props {
    vendor: { id: number; name: string };
    tickets: VendorTicket[];
}

const props = defineProps<Props>();
const { t } = useI18n();
const { formatDate } = useFormatters();

const pending = computed(() => props.tickets.filter((tk) => tk.vendor_status === 'pending'));
const active = computed(() => props.tickets.filter((tk) => tk.vendor_status === 'accepted'));

const decliningId = ref<number | null>(null);
const declineReason = ref('');

const accept = (tk: VendorTicket) => {
    router.post(`/v/portal/tickets/${tk.id}/accept`, {}, { preserveScroll: true });
};

const openDecline = (tk: VendorTicket) => {
    decliningId.value = tk.id;
    declineReason.value = '';
};

const submitDecline = (tk: VendorTicket) => {
    router.post(`/v/portal/tickets/${tk.id}/decline`, { reason: declineReason.value || null }, {
        preserveScroll: true,
        onFinish: () => { decliningId.value = null; },
    });
};
</script>

<template>
    <Head :title="t('vendor_portal.inbox.title')" />
    <VendorPortalLayout :vendor-name="vendor.name">
        <h1 class="text-xl font-semibold text-gray-900">{{ t('vendor_portal.inbox.title') }}</h1>

        <div v-if="tickets.length === 0" class="mt-6 rounded-2xl bg-white p-10 text-center text-gray-500 ring-1 ring-gray-100">
            {{ t('vendor_portal.inbox.empty') }}
        </div>

        <template v-else>
            <section v-if="pending.length" class="mt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-amber-700">
                    {{ t('vendor_portal.inbox.pending') }}
                </h2>
                <ul class="mt-3 space-y-3">
                    <li
                        v-for="tk in pending"
                        :key="tk.id"
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-amber-100"
                        data-testid="vendor-ticket-row"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ tk.title }}</p>
                                <p class="text-xs text-gray-500">{{ tk.location }} · {{ tk.priority }}</p>
                                <p v-if="tk.resolution_due_at" class="mt-1 text-xs text-rose-600">
                                    {{ t('vendor_portal.inbox.due') }}: {{ formatDate(tk.resolution_due_at) }}
                                </p>
                            </div>
                            <div class="flex flex-shrink-0 gap-2">
                                <button
                                    type="button"
                                    @click="accept(tk)"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                                    data-testid="vendor-accept"
                                >
                                    {{ t('vendor_portal.inbox.accept') }}
                                </button>
                                <button
                                    type="button"
                                    @click="openDecline(tk)"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                    data-testid="vendor-decline"
                                >
                                    {{ t('vendor_portal.inbox.decline') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="decliningId === tk.id" class="mt-3 border-t pt-3">
                            <textarea
                                v-model="declineReason"
                                rows="2"
                                maxlength="500"
                                :placeholder="t('vendor_portal.inbox.decline_reason')"
                                class="w-full rounded-lg border-gray-200 text-sm"
                            ></textarea>
                            <div class="mt-2 text-end">
                                <button
                                    type="button"
                                    @click="submitDecline(tk)"
                                    class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700"
                                >
                                    {{ t('vendor_portal.inbox.decline') }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="active.length" class="mt-8">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">
                    {{ t('vendor_portal.inbox.active') }}
                </h2>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="tk in active"
                        :key="tk.id"
                        class="flex items-center justify-between rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <a :href="`/v/portal/tickets/${tk.id}`" class="font-medium text-indigo-700 hover:underline">
                            {{ tk.title }}
                        </a>
                        <span class="text-xs uppercase text-gray-500">{{ tk.status }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </VendorPortalLayout>
</template>
