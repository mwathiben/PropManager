<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDownIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const open = ref(false);
</script>

<template>
    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50" data-testid="legal-hold-help">
        <button
            type="button"
            class="flex w-full items-center justify-between px-4 py-2.5 text-start"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="flex items-center gap-2 text-sm font-medium text-indigo-900">
                <QuestionMarkCircleIcon class="h-5 w-5" />
                {{ t('legal_holds.help.title') }}
            </span>
            <ChevronDownIcon class="h-4 w-4 text-indigo-500 transition" :class="{ 'rotate-180': open }" />
        </button>

        <div v-if="open" class="space-y-3 px-4 pb-4 text-sm text-gray-700">
            <p>{{ t('legal_holds.help.what') }}</p>
            <p>{{ t('legal_holds.help.why') }}</p>
            <p class="rounded-md bg-white px-3 py-2 text-xs text-gray-600">{{ t('legal_holds.help.dpa') }}</p>
            <p>{{ t('legal_holds.help.when_release') }}</p>
        </div>
    </div>
</template>
