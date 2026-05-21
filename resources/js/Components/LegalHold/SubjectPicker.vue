<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';

interface SubjectItem {
    id: number;
    already_held: boolean;
}
interface Group {
    type: string;
    short: string;
    count: number;
    held: number;
    truncated: boolean;
    items: SubjectItem[];
}

const props = defineProps<{
    tenants: { id: number; name: string }[];
    modelValue: Record<string, number[]>;
    /** Short type names (e.g. 'Invoice') to auto-select-all after a tenant loads. */
    autoSelectTypes?: string[];
}>();

const emit = defineEmits<{ 'update:modelValue': [Record<string, number[]>] }>();

const { t } = useI18n();

const selectedTenant = ref<number | null>(null);
const groups = ref<Group[]>([]);
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);

async function loadTenant(): Promise<void> {
    emit('update:modelValue', {});
    groups.value = [];
    loaded.value = false;
    loadError.value = false;
    if (selectedTenant.value === null) {
        return;
    }
    loading.value = true;
    try {
        const res = await fetch(route('legal-holds.subjects.suggest', { tenant_id: selectedTenant.value }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error(`suggest failed: ${res.status}`);
        }
        const data = await res.json();
        groups.value = Array.isArray(data.groups) ? data.groups : [];
        loaded.value = true;
        applyAutoSelect();
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

// Situation presets pre-select their suggested types (excluding already-held).
function applyAutoSelect(): void {
    const auto = props.autoSelectTypes ?? [];
    if (!auto.length || !loaded.value) return;
    const next: Record<string, number[]> = {};
    for (const group of groups.value) {
        if (auto.includes(group.short)) {
            next[group.type] = group.items.filter((i) => !i.already_held).map((i) => i.id);
        }
    }
    emit('update:modelValue', next);
}

// Re-apply when the situation (and thus suggested types) changes after a tenant
// is already loaded — e.g. the user goes back and picks a different situation.
watch(() => props.autoSelectTypes, applyAutoSelect);

const isChecked = (type: string, id: number): boolean => (props.modelValue[type] ?? []).includes(id);

function toggle(type: string, id: number): void {
    const next = { ...props.modelValue };
    const set = new Set(next[type] ?? []);
    set.has(id) ? set.delete(id) : set.add(id);
    next[type] = [...set];
    emit('update:modelValue', next);
}

function selectAll(group: Group): void {
    const next = { ...props.modelValue };
    next[group.type] = group.items.filter((i) => !i.already_held).map((i) => i.id);
    emit('update:modelValue', next);
}

function clearGroup(group: Group): void {
    const next = { ...props.modelValue };
    next[group.type] = [];
    emit('update:modelValue', next);
}

const totalSelected = computed(() =>
    Object.values(props.modelValue).reduce((sum, ids) => sum + ids.length, 0),
);
const hasAnyRecords = computed(() => groups.value.some((g) => g.count > 0));
</script>

<template>
    <div class="space-y-4" data-testid="subject-picker">
        <div>
            <label for="subject-tenant" class="block text-sm font-medium text-gray-700">
                {{ t('legal_holds.wizard.pick_tenant') }}
            </label>
            <select
                id="subject-tenant"
                v-model="selectedTenant"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                @change="loadTenant"
            >
                <option :value="null">{{ t('legal_holds.wizard.pick_tenant_placeholder') }}</option>
                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
            </select>
        </div>

        <p v-if="loading" class="text-sm text-gray-500">{{ t('legal_holds.wizard.loading') }}</p>

        <p
            v-else-if="loadError"
            class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700"
            data-testid="subject-picker-error"
        >
            {{ t('legal_holds.wizard.load_error') }}
        </p>

        <template v-else-if="loaded">
            <p
                v-if="!hasAnyRecords"
                class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-500"
                data-testid="subject-picker-empty"
            >
                {{ t('legal_holds.wizard.no_records') }}
            </p>

            <template v-else>
                <div
                    v-for="group in groups"
                    :key="group.type"
                    class="rounded-lg border border-gray-200"
                    data-testid="subject-type-group"
                >
                    <header class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                        <span class="text-sm font-medium text-gray-700">
                            {{ group.short }}
                            <span class="text-gray-400">({{ group.count }})</span>
                            <span v-if="group.held" class="ms-1 text-xs text-amber-600">
                                {{ t('legal_holds.wizard.already_held_count', { count: group.held }) }}
                            </span>
                        </span>
                        <span v-if="group.count" class="flex gap-2 text-xs">
                            <button type="button" class="text-indigo-600 hover:underline" @click="selectAll(group)">
                                {{ t('legal_holds.wizard.select_all') }}
                            </button>
                            <button type="button" class="text-gray-500 hover:underline" @click="clearGroup(group)">
                                {{ t('legal_holds.wizard.clear') }}
                            </button>
                        </span>
                    </header>

                    <ul class="max-h-48 divide-y divide-gray-50 overflow-y-auto">
                        <li
                            v-for="item in group.items"
                            :key="item.id"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm"
                            data-testid="subject-row"
                        >
                            <input
                                :id="`subj-${group.short}-${item.id}`"
                                type="checkbox"
                                class="rounded border-gray-300"
                                :checked="isChecked(group.type, item.id)"
                                :disabled="item.already_held"
                                @change="toggle(group.type, item.id)"
                            />
                            <label :for="`subj-${group.short}-${item.id}`" class="flex-1" :class="item.already_held ? 'text-gray-400' : 'text-gray-700'">
                                {{ group.short }} #{{ item.id }}
                            </label>
                            <span v-if="item.already_held" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                {{ t('legal_holds.doc.on_hold') }}
                            </span>
                        </li>
                    </ul>

                    <p v-if="group.truncated" class="px-3 py-1.5 text-xs text-gray-400">
                        {{ t('legal_holds.wizard.truncated', { shown: group.items.length, total: group.count }) }}
                    </p>
                </div>

                <p class="text-sm font-medium text-gray-700">
                    {{ t('legal_holds.wizard.selected_count', { count: totalSelected }) }}
                </p>
            </template>
        </template>
    </div>
</template>
