<script setup lang="ts">
import { ref } from 'vue';
import ArrowDownTrayIcon from '@heroicons/vue/24/outline/ArrowDownTrayIcon';
import { useI18n } from '@/composables/useI18n';
import type { ExportFormat } from '@/types/finances';

const { t } = useI18n();

interface FormatOption {
    value: ExportFormat | string;
    label: string;
}

interface Props {
    formats?: FormatOption[];
    buttonText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    formats: () => [
        { value: 'xlsx', label: 'Excel (.xlsx)' },
        { value: 'pdf', label: 'PDF' },
    ],
    buttonText: undefined,
});

const emit = defineEmits<{
    export: [format: string];
}>();

const showMenu = ref(false);

const handleExport = (format) => {
    emit('export', format);
    showMenu.value = false;
};
</script>

<template>
    <div class="relative">
        <button
            @click="showMenu = !showMenu"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
            <ArrowDownTrayIcon class="h-4 w-4" />
            {{ buttonText ?? t('finances_export_dropdown.button') }}
        </button>
        <!-- i18n-ignore -->
        <Transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
            <div
                v-if="showMenu"
                class="absolute end-0 z-10 mt-1 w-36 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
            >
                <button
                    v-for="format in formats"
                    :key="format.value"
                    @click="handleExport(format.value)"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                >
                    {{ format.label }}
                </button>
            </div>
        </Transition>
    </div>
</template>
