<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();

interface Switcher {
    active_id: number | null;
    options: { id: number; name: string }[];
}

const switcher = computed<Switcher | null>(() => (page.props as { propertySwitcher?: Switcher }).propertySwitcher ?? null);

function onChange(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    if (!id) return;
    router.post(route('properties.switch', id), {}, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div v-if="switcher && switcher.options.length > 1" class="flex items-center gap-1" data-testid="property-switcher">
        <span class="sr-only">{{ t('property.switcher.label') }}</span>
        <select
            :value="switcher.active_id ?? ''"
            class="rounded-md border-gray-200 py-1 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
            :aria-label="t('property.switcher.label')"
            @change="onChange"
        >
            <option v-for="o in switcher.options" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
    </div>
</template>
