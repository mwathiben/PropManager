<script setup lang="ts">
/**
 * Phase-24 I18N-FRONT-2: the language picker. A native <select> (so
 * it is keyboard-operable + screen-reader friendly out of the box —
 * Phase-23 a11y patterns) bound to the active locale; on change it
 * PATCHes the locale-switch endpoint (I18N-INFRA-4) and lets Inertia
 * reload so the SetLocale middleware re-renders the whole UI
 * translated.
 */
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import InputLabel from '@/Components/InputLabel.vue';

const { t, locale, availableLocales } = useI18n();

function onChange(event: Event): void {
    const next = (event.target as HTMLSelectElement).value;
    if (next === locale.value) {
        return;
    }
    router.patch(route('locale.update'), { locale: next }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <InputLabel for="locale-selector" :value="t('common.language')" />
        <!-- Phase-43 LOCALE-SWITCHER-3: native <select> is already
             keyboard-navigable + screen-reader friendly. Adding
             explicit aria-label + aria-live so an assistive-tech user
             hears the change announced and a label even if the
             InputLabel scrolls out of view. -->
        <select
            id="locale-selector"
            :value="locale"
            @change="onChange"
            :aria-label="t('common.language')"
            aria-live="polite"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
            <option
                v-for="(label, code) in availableLocales"
                :key="code"
                :value="code"
            >
                {{ label }}
            </option>
        </select>
    </div>
</template>
