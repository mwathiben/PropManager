<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import WizardSteps from '@/Components/Wizard/WizardSteps.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { PlusIcon, PencilSquareIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

interface Connection {
    id: number;
    identifier: string;
    client_name: string | null;
    has_account: boolean;
    billing_mode: string;
    client_rate: number | null;
    status: string;
    meter_id: number | null;
    meter: string | null;
    unit_id: number | null;
    connected_at: string | null;
    notes: string | null;
    outstanding: number;
    billing_issue: string | null;
}
interface MeterOption { id: number; label: string }
interface Clients {
    supplies_water_clients: boolean;
    water_client_rate: number | null;
    connections: Connection[];
    meters: MeterOption[];
    billing_modes: string[];
}

const props = withDefaults(defineProps<{ clients?: Clients }>(), {
    clients: () => ({ supplies_water_clients: false, water_client_rate: null, connections: [], meters: [], billing_modes: ['metered', 'flat_rate'] }),
});

const { formatMoney, formatDate } = useFormatters();
const { t } = useI18n();

const enabled = computed(() => props.clients.supplies_water_clients);
const connections = computed(() => props.clients.connections ?? []);

// --- Setup wizard (shown until the landlord opts in) ---
const step = ref(0);
const stepLabels = computed(() => [t('water.clients.wizard.step_declare'), t('water.clients.wizard.step_review')]);
const setupForm = useForm({ supplies_water_clients: true, water_client_rate: props.clients.water_client_rate ?? '' });
function enable(): void {
    setupForm.put(route('water.clients.setup'), { preserveScroll: true });
}

// --- Settings (when enabled): adjust default rate / disable ---
const settingsForm = useForm({ supplies_water_clients: true, water_client_rate: props.clients.water_client_rate ?? '' });
function saveSettings(): void {
    settingsForm.put(route('water.clients.setup'), { preserveScroll: true });
}
function disableClients(): void {
    router.put(route('water.clients.setup'), { supplies_water_clients: false, water_client_rate: props.clients.water_client_rate }, { preserveScroll: true });
}

// --- Connection (water line) create/edit ---
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ identifier: '', client_name: '', billing_mode: 'metered', client_rate: '', meter_id: '', status: 'active', connected_at: '', notes: '' });

function openCreate(): void {
    form.reset();
    editingId.value = null;
    modalOpen.value = true;
}
function openEdit(c: Connection): void {
    editingId.value = c.id;
    form.identifier = c.identifier;
    form.client_name = c.client_name ?? '';
    form.billing_mode = c.billing_mode;
    form.client_rate = c.client_rate !== null ? String(c.client_rate) : '';
    form.meter_id = c.meter_id !== null ? String(c.meter_id) : '';
    form.status = c.status;
    form.connected_at = c.connected_at ?? '';
    form.notes = c.notes ?? '';
    modalOpen.value = true;
}
function submit(): void {
    const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; form.reset(); } };
    if (editingId.value !== null) {
        form.put(route('water.connections.update', editingId.value), opts);
    } else {
        form.post(route('water.connections.store'), opts);
    }
}
function remove(c: Connection): void {
    router.delete(route('water.connections.destroy', c.id), { preserveScroll: true });
}

// --- Invite the client for a connection ---
const inviteOpen = ref(false);
const inviteConnection = ref<Connection | null>(null);
const inviteForm = useForm({ email: '' });
function openInvite(c: Connection): void {
    inviteForm.reset();
    inviteConnection.value = c;
    inviteOpen.value = true;
}
function submitInvite(): void {
    if (inviteConnection.value === null) return;
    inviteForm.post(route('water-client-invitations.store', inviteConnection.value.id), {
        preserveScroll: true,
        onSuccess: () => { inviteOpen.value = false; inviteForm.reset(); },
    });
}

// --- Record a water-client payment (landlord logs cash/M-Pesa received) ---
const payOpen = ref(false);
const payConnection = ref<Connection | null>(null);
const payForm = useForm({ amount: '' });
function openPay(c: Connection): void {
    payForm.reset();
    payConnection.value = c;
    payOpen.value = true;
}
function submitPay(): void {
    if (payConnection.value === null) return;
    payForm.post(route('water.connections.record-payment', payConnection.value.id), {
        preserveScroll: true,
        onSuccess: () => { payOpen.value = false; payForm.reset(); },
    });
}

const today = new Date().toISOString().slice(0, 10);
const modeLabel = (m: string) => t(`water.clients.mode_${m}`);
</script>

<template>
    <div data-testid="water-clients-tab">
        <!-- Setup wizard: shown until the landlord opts in -->
        <div v-if="!enabled" class="mx-auto max-w-2xl">
            <div class="mb-6 flex items-center gap-3">
                <div class="rounded-lg bg-cyan-100 p-2"><UserGroupIcon class="h-6 w-6 text-cyan-600" /></div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">{{ t('water.clients.intro_title') }}</h3>
                    <p class="text-sm text-gray-500">{{ t('water.clients.intro_body') }}</p>
                </div>
            </div>

            <WizardSteps :current-step="step + 1" :total-steps="2" :steps="stepLabels" />

            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
                <template v-if="step === 0">
                    <h4 class="text-sm font-semibold text-gray-900">{{ t('water.clients.declare_q') }}</h4>
                    <p class="mt-1 text-xs text-gray-500">{{ t('water.clients.declare_hint') }}</p>

                    <label class="mt-4 block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.default_rate') }}</span>
                        <input v-model="setupForm.water_client_rate" type="number" step="0.01" min="0" class="mt-1 w-48 rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        <span class="mt-1 block text-xs text-gray-400">{{ t('water.clients.default_rate_hint') }}</span>
                    </label>

                    <p class="mt-4 rounded-md bg-cyan-50 px-3 py-2 text-xs text-cyan-800">{{ t('water.clients.identifier_scheme_note') }}</p>

                    <div class="mt-6 flex justify-end">
                        <button type="button" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700" @click="step = 1">{{ t('water.clients.next') }}</button>
                    </div>
                </template>

                <template v-else>
                    <h4 class="text-sm font-semibold text-gray-900">{{ t('water.clients.review_title') }}</h4>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ t('water.clients.review_supply') }}</dt><dd class="font-medium text-gray-900">{{ t('water.clients.status_active') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ t('water.clients.default_rate') }}</dt><dd class="font-medium text-gray-900">{{ setupForm.water_client_rate === '' ? '—' : formatMoney(Number(setupForm.water_client_rate)) }}</dd></div>
                    </dl>
                    <div class="mt-6 flex justify-between">
                        <button type="button" class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="step = 0">{{ t('water.clients.back') }}</button>
                        <button type="button" :disabled="setupForm.processing" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50" @click="enable">{{ t('water.clients.enable') }}</button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Management: connections (water lines) -->
        <div v-else class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <label class="block">
                    <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.default_rate') }}</span>
                    <div class="mt-1 flex gap-2">
                        <input v-model="settingsForm.water_client_rate" type="number" step="0.01" min="0" class="w-40 rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        <button type="button" class="shrink-0 rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700" @click="saveSettings">{{ t('water.clients.save') }}</button>
                    </div>
                </label>
                <button type="button" class="text-xs font-medium text-gray-400 hover:text-red-600" @click="disableClients">{{ t('water.clients.disable') }}</button>
            </div>

            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">{{ t('water.clients.manage_title') }}</h3>
                <button type="button" class="inline-flex items-center gap-1 rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> {{ t('water.clients.add_line') }}
                </button>
            </div>

            <p v-if="connections.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">{{ t('water.clients.no_connections') }}</p>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('water.clients.col_identifier') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('water.clients.col_client') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('water.clients.col_meter') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('water.clients.col_rate') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('water.clients.outstanding') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('water.clients.col_status') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('water.clients.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in connections" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ c.identifier }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ c.client_name || t('water.clients.unassigned') }}
                                <span v-if="!c.has_account" class="ms-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">{{ t('water.clients.no_account') }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ c.meter || '—' }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ c.client_rate === null ? '—' : formatMoney(c.client_rate) }}</td>
                            <td class="px-4 py-3 text-end" :class="c.outstanding > 0 ? 'font-medium text-amber-700' : 'text-gray-400'">{{ c.outstanding > 0 ? formatMoney(c.outstanding) : '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', c.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600']">{{ t(`water.clients.status_${c.status}`) }}</span>
                                <span v-if="c.billing_issue" class="ms-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800" :title="t(`water.clients.issue_${c.billing_issue}`)">{{ t(`water.clients.issue_${c.billing_issue}`) }}</span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <button v-if="c.outstanding > 0" type="button" class="me-2 text-xs font-medium text-emerald-700 hover:text-emerald-900" @click="openPay(c)">{{ t('water.clients.record_payment') }}</button>
                                <button v-if="!c.has_account" type="button" class="me-2 text-xs font-medium text-cyan-700 hover:text-cyan-900" @click="openInvite(c)">{{ t('water.clients.invite') }}</button>
                                <button type="button" class="me-2 text-gray-400 hover:text-cyan-700" :title="t('water.clients.edit')" @click="openEdit(c)"><PencilSquareIcon class="inline h-4 w-4" /></button>
                                <button type="button" class="text-gray-300 hover:text-red-600" :title="t('water.clients.delete')" @click="remove(c)"><TrashIcon class="inline h-4 w-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Connection create/edit modal -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="modalOpen = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-6">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ editingId ? t('water.clients.form_title_edit') : t('water.clients.form_title_new') }}</h3>
                <form class="space-y-3" @submit.prevent="submit">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_identifier') }}</span>
                        <input v-model="form.identifier" type="text" maxlength="100" required class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_client_name') }}</span>
                        <input v-model="form.client_name" type="text" maxlength="255" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_billing_mode') }}</span>
                            <select v-model="form.billing_mode" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option v-for="m in clients.billing_modes" :key="m" :value="m">{{ modeLabel(m) }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_rate') }}</span>
                            <input v-model="form.client_rate" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_meter') }}</span>
                        <select v-model="form.meter_id" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                            <option value="">{{ t('water.clients.f_meter_none') }}</option>
                            <option v-for="m in clients.meters" :key="m.id" :value="m.id">{{ m.label }}</option>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_status') }}</span>
                            <select v-model="form.status" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option value="active">{{ t('water.clients.status_active') }}</option>
                                <option value="inactive">{{ t('water.clients.status_inactive') }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_connected_at') }}</span>
                            <input v-model="form.connected_at" type="date" :max="today" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.f_notes') }}</span>
                        <input v-model="form.notes" type="text" maxlength="1000" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <p v-if="form.errors.identifier || form.errors.client_rate || form.errors.meter_id" class="text-xs text-red-600">{{ form.errors.identifier || form.errors.client_rate || form.errors.meter_id }}</p>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-md bg-cyan-600 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{{ t('water.clients.save') }}</button>
                        <button type="button" class="flex-1 rounded-md bg-gray-100 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="modalOpen = false">{{ t('water.clients.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invite client modal -->
        <div v-if="inviteOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="inviteOpen = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-lg bg-white p-6">
                <h3 class="mb-1 text-lg font-semibold text-gray-900">{{ t('water.clients.invite_title') }}</h3>
                <p class="mb-4 text-sm text-gray-500">{{ inviteConnection?.identifier }}</p>
                <form class="space-y-3" @submit.prevent="submitInvite">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.invite_email') }}</span>
                        <input v-model="inviteForm.email" type="email" required class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        <span v-if="inviteForm.errors.email" class="mt-1 block text-xs text-red-600">{{ inviteForm.errors.email }}</span>
                    </label>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="inviteForm.processing" class="flex-1 rounded-md bg-cyan-600 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{{ t('water.clients.invite_send') }}</button>
                        <button type="button" class="flex-1 rounded-md bg-gray-100 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="inviteOpen = false">{{ t('water.clients.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Record payment modal -->
        <div v-if="payOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="payOpen = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-lg bg-white p-6">
                <h3 class="mb-1 text-lg font-semibold text-gray-900">{{ t('water.clients.record_payment_title') }}</h3>
                <p class="mb-4 text-sm text-gray-500">{{ payConnection?.identifier }} · {{ t('water.clients.outstanding') }}: {{ payConnection ? formatMoney(payConnection.outstanding) : '' }}</p>
                <form class="space-y-3" @submit.prevent="submitPay">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.clients.payment_amount') }}</span>
                        <input v-model="payForm.amount" type="number" step="0.01" min="0.01" required class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        <span v-if="payForm.errors.amount" class="mt-1 block text-xs text-red-600">{{ payForm.errors.amount }}</span>
                    </label>
                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="payForm.processing" class="flex-1 rounded-md bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{{ t('water.clients.record_payment') }}</button>
                        <button type="button" class="flex-1 rounded-md bg-gray-100 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="payOpen = false">{{ t('water.clients.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
