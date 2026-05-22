<script setup lang="ts">
/**
 * Water hub Settings tab — renders the SAME canonical editor as the standalone
 * /water/settings page (Phase-83 follow-up WATER-SETTINGS-UNIFY). The hub feeds
 * `settings = { buildings, globalSettings }` from WaterSettingsData; previously
 * this tab edited an orphan WaterSetting model that the billing engine ignored.
 */
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
    settings: {
        buildings: BuildingRow[];
        globalSettings: { water_billing_type?: string; water_unit_rate?: number | string; flat_water_rate?: number | string };
    };
}>();
</script>

<template>
    <WaterSettingsForm
        :buildings="settings.buildings"
        :global-settings="settings.globalSettings"
    />
</template>
