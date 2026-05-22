<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import WaterSettingsForm from '@/Components/Water/WaterSettingsForm.vue';

interface BuildingRow {
    id: number;
    name: string;
    units_count?: number;
    water_billing_type?: string | null;
    water_unit_rate?: number | string | null;
    water_flat_rate?: number | string | null;
}

defineProps<{
    buildings: BuildingRow[];
    globalSettings: { water_billing_type?: string; water_unit_rate?: number | string; flat_water_rate?: number | string };
    highlightBuildingId?: number | null;
}>();
</script>

<template>
    <Head :title="$t('water.settings.title')" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $t('water.settings.title') }}</h1>
                    <p class="text-gray-600 mt-1">{{ $t('water.settings.subtitle') }}</p>
                </div>

                <WaterSettingsForm
                    :buildings="buildings"
                    :global-settings="globalSettings"
                    :highlight-building-id="highlightBuildingId"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
