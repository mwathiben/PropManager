<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import { ShieldCheckIcon, DocumentArrowUpIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

interface DocInfo { id: number; title: string; expires_at: string | null; expiry_status: 'expired' | 'expiring_soon' | 'valid' | 'none' }
interface Abstraction {
    limit: number | null;
    used: number;
    basis: 'meter' | 'units' | null;
    estimate: boolean;
    has_data: boolean;
    utilization_pct: number | null;
    projected_annual: number | null;
    status: 'no_limit' | 'unknown' | 'ok' | 'warning' | 'exceeded';
}
interface BuildingCompliance {
    building_id: number;
    name: string;
    overall_status: 'action' | 'warning' | 'ok';
    abstraction: Abstraction;
    permit: DocInfo | null;
    quality_cert: DocInfo | null;
}
interface Compliance { buildings: BuildingCompliance[]; summary: Record<string, number> }

const props = withDefaults(defineProps<{ compliance?: Compliance }>(), {
    compliance: () => ({ buildings: [], summary: {} }),
});

const { formatNumber, formatDate } = useFormatters();
const { t } = useI18n();

const buildings = computed(() => props.compliance?.buildings ?? []);
const summary = computed(() => props.compliance?.summary ?? {});

const overallClass: Record<string, string> = {
    action: 'bg-red-100 text-red-800',
    warning: 'bg-amber-100 text-amber-800',
    ok: 'bg-emerald-100 text-emerald-800',
};
const expiryClass: Record<string, string> = {
    expired: 'bg-red-100 text-red-800',
    expiring_soon: 'bg-amber-100 text-amber-800',
    valid: 'bg-emerald-100 text-emerald-800',
    none: 'bg-gray-100 text-gray-600',
};
const abstractionClass: Record<string, string> = {
    exceeded: 'text-red-600',
    warning: 'text-amber-600',
    ok: 'text-emerald-600',
    no_limit: 'text-amber-600',
    unknown: 'text-gray-400',
};

function barWidth(a: Abstraction): number {
    return Math.max(0, Math.min(100, a.utilization_pct ?? 0));
}
function barColor(status: string): string {
    if (status === 'exceeded') return 'bg-red-500';
    if (status === 'warning') return 'bg-amber-500';
    return 'bg-emerald-500';
}

// --- Abstraction limit inline editing ---
const limits = reactive<Record<number, string>>({});
buildings.value.forEach((b) => { limits[b.building_id] = b.abstraction.limit !== null ? String(b.abstraction.limit) : ''; });

function saveLimit(buildingId: number): void {
    router.put(route('water.compliance.limit', buildingId), {
        water_abstraction_limit: limits[buildingId] === '' ? null : limits[buildingId],
    }, { preserveScroll: true });
}

// --- Upload / renew document modal ---
const modalOpen = ref(false);
const modalMode = ref<'upload' | 'renew'>('upload');
const modalBuilding = ref<BuildingCompliance | null>(null);
const modalType = ref<'wra_abstraction_permit' | 'water_quality_certificate'>('wra_abstraction_permit');
const modalDocId = ref<number | null>(null);

const form = useForm<{
    file: File | null;
    title: string;
    document_type: string;
    documentable_type: string;
    documentable_id: number | null;
    issue_date: string;
    expires_at: string;
    is_renewable: boolean;
    reminder_days: number | null;
}>({
    file: null,
    title: '',
    document_type: '',
    documentable_type: 'Building',
    documentable_id: null,
    issue_date: '',
    expires_at: '',
    is_renewable: true,
    reminder_days: 30,
});

function openUpload(b: BuildingCompliance, type: 'wra_abstraction_permit' | 'water_quality_certificate'): void {
    form.reset();
    modalMode.value = 'upload';
    modalBuilding.value = b;
    modalType.value = type;
    modalDocId.value = null;
    form.document_type = type;
    form.documentable_id = b.building_id;
    modalOpen.value = true;
}

function openRenew(b: BuildingCompliance, type: 'wra_abstraction_permit' | 'water_quality_certificate', doc: DocInfo): void {
    form.reset();
    modalMode.value = 'renew';
    modalBuilding.value = b;
    modalType.value = type;
    modalDocId.value = doc.id;
    modalOpen.value = true;
}

function onFile(e: Event): void {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit(): void {
    const opts = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { modalOpen.value = false; form.reset(); },
    };
    if (modalMode.value === 'renew' && modalDocId.value !== null) {
        form.post(route('documents.renew', modalDocId.value), opts);
    } else {
        form.post(route('documents.store'), opts);
    }
}

const today = new Date().toISOString().slice(0, 10);
const typeLabel = (type: string) => t(`document.types.${type}`);
</script>

<template>
    <div class="space-y-8" data-testid="water-compliance-tab">
        <!-- Empty state: no borehole buildings -->
        <div v-if="buildings.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center">
            <ShieldCheckIcon class="mx-auto h-8 w-8 text-gray-300" />
            <p class="mt-2 text-sm font-medium text-gray-700">{{ t('water.compliance.empty_title') }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ t('water.compliance.empty_hint') }}</p>
        </div>

        <template v-else>
            <!-- Summary -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm text-gray-500">{{ t('water.compliance.summary_boreholes') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ summary.borehole_buildings ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm text-gray-500">{{ t('water.compliance.summary_attention') }}</div>
                    <div class="mt-1 text-2xl font-semibold" :class="(summary.attention ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'">{{ summary.attention ?? 0 }}</div>
                    <div v-if="(summary.watch ?? 0) > 0" class="mt-0.5 text-xs text-amber-600">+{{ summary.watch }} {{ t('water.compliance.to_watch') }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm text-gray-500">{{ t('water.compliance.summary_docs_expiring') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-600">{{ (summary.permits_expiring ?? 0) + (summary.certs_expiring ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm text-gray-500">{{ t('water.compliance.summary_limits_exceeded') }}</div>
                    <div class="mt-1 text-2xl font-semibold" :class="(summary.limits_exceeded ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'">{{ summary.limits_exceeded ?? 0 }}</div>
                </div>
            </div>

            <!-- Per borehole building -->
            <div v-for="b in buildings" :key="b.building_id" class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ b.name }}</h3>
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', overallClass[b.overall_status]]">
                        {{ t(`water.compliance.overall.${b.overall_status}`) }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Abstraction limit vs used -->
                    <div class="lg:col-span-1">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ t('water.compliance.abstraction') }}</div>
                        <div class="mt-2 flex items-end justify-between text-sm">
                            <span class="text-gray-500">{{ t('water.compliance.used_this_year') }}</span>
                            <span class="font-semibold text-gray-900">{{ formatNumber(b.abstraction.used) }}</span>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full transition-all" :class="barColor(b.abstraction.status)" :style="{ width: `${barWidth(b.abstraction)}%` }" />
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs">
                            <span :class="abstractionClass[b.abstraction.status]">{{ t(`water.compliance.status.${b.abstraction.status}`) }}</span>
                            <span class="text-gray-400">
                                {{ b.abstraction.utilization_pct === null ? '—' : `${b.abstraction.utilization_pct}%` }}
                                <template v-if="b.abstraction.projected_annual !== null"> · {{ t('water.compliance.projected') }} {{ formatNumber(b.abstraction.projected_annual) }}</template>
                            </span>
                        </div>
                        <p v-if="b.abstraction.basis" class="mt-1 text-[11px]" :class="b.abstraction.estimate ? 'text-amber-600' : 'text-gray-400'">{{ t(`water.compliance.basis_${b.abstraction.basis}`) }}</p>

                        <label class="mt-3 block">
                            <span class="block text-xs font-medium text-gray-500">{{ t('water.compliance.annual_limit') }}</span>
                            <div class="mt-1 flex gap-2">
                                <input v-model="limits[b.building_id]" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" :placeholder="t('water.compliance.limit_placeholder')" />
                                <button type="button" class="shrink-0 rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700" @click="saveLimit(b.building_id)">{{ t('water.compliance.save') }}</button>
                            </div>
                        </label>
                    </div>

                    <!-- Abstraction permit -->
                    <div class="lg:col-span-1">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ t('water.compliance.permit') }}</div>
                        <template v-if="b.permit">
                            <div class="mt-2 flex items-center gap-2">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', expiryClass[b.permit.expiry_status]]">{{ t(`document.expiry.${b.permit.expiry_status}`) }}</span>
                                <span v-if="b.permit.expires_at" class="text-xs text-gray-500">{{ formatDate(b.permit.expires_at) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-gray-700">{{ b.permit.title }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-cyan-700 hover:text-cyan-900" @click="openRenew(b, 'wra_abstraction_permit', b.permit)">
                                <ArrowPathIcon class="h-3.5 w-3.5" /> {{ t('document.renewal.renew') }}
                            </button>
                        </template>
                        <template v-else>
                            <p class="mt-2 text-sm text-gray-400">{{ t('water.compliance.no_permit') }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-cyan-700 hover:text-cyan-900" @click="openUpload(b, 'wra_abstraction_permit')">
                                <DocumentArrowUpIcon class="h-3.5 w-3.5" /> {{ t('water.compliance.upload_permit') }}
                            </button>
                        </template>
                    </div>

                    <!-- Quality certificate -->
                    <div class="lg:col-span-1">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ t('water.compliance.quality_cert') }}</div>
                        <template v-if="b.quality_cert">
                            <div class="mt-2 flex items-center gap-2">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', expiryClass[b.quality_cert.expiry_status]]">{{ t(`document.expiry.${b.quality_cert.expiry_status}`) }}</span>
                                <span v-if="b.quality_cert.expires_at" class="text-xs text-gray-500">{{ formatDate(b.quality_cert.expires_at) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-gray-700">{{ b.quality_cert.title }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-cyan-700 hover:text-cyan-900" @click="openRenew(b, 'water_quality_certificate', b.quality_cert)">
                                <ArrowPathIcon class="h-3.5 w-3.5" /> {{ t('document.renewal.renew') }}
                            </button>
                        </template>
                        <template v-else>
                            <p class="mt-2 text-sm text-gray-400">{{ t('water.compliance.no_cert') }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-cyan-700 hover:text-cyan-900" @click="openUpload(b, 'water_quality_certificate')">
                                <DocumentArrowUpIcon class="h-3.5 w-3.5" /> {{ t('water.compliance.upload_cert') }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- Upload / renew modal -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="modalOpen = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-6">
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    {{ modalMode === 'renew' ? t('document.renewal.title') : t('water.compliance.upload_title') }}
                </h3>
                <p class="mb-4 text-sm text-gray-500">{{ modalBuilding?.name }} · {{ typeLabel(modalType) }}</p>

                <form class="space-y-3" @submit.prevent="submit">
                    <label v-if="modalMode === 'upload'" class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.compliance.doc_title') }}</span>
                        <input v-model="form.title" type="text" maxlength="255" required class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('water.compliance.file') }}</span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required class="mt-1 w-full text-sm" @change="onFile" />
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('document.fields.issue_date') }}</span>
                        <input v-model="form.issue_date" type="date" :max="today" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ modalMode === 'renew' ? t('document.renewal.new_expiry') : t('document.fields.expires_at') }}</span>
                        <input v-model="form.expires_at" type="date" :required="modalMode === 'renew'" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" />
                    </label>
                    <label v-if="modalMode === 'upload'" class="block">
                        <span class="block text-xs font-medium text-gray-500">{{ t('document.fields.reminder_days') }}</span>
                        <input v-model="form.reminder_days" type="number" min="1" max="365" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" :placeholder="t('document.fields.reminder_days_hint')" />
                    </label>

                    <p v-if="form.errors.file || form.errors.expires_at || form.errors.title" class="text-xs text-red-600">
                        {{ form.errors.file || form.errors.expires_at || form.errors.title }}
                    </p>

                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-md bg-cyan-600 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50">
                            {{ modalMode === 'renew' ? t('document.renewal.submit') : t('water.compliance.upload') }}
                        </button>
                        <button type="button" class="flex-1 rounded-md bg-gray-100 py-2 text-sm text-gray-700 hover:bg-gray-200" @click="modalOpen = false">{{ t('document.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
