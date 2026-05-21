<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
import { useI18n } from '@/composables/useI18n';

interface Settings {
    stale_after_days: number | null;
    reminder_cooldown_days: number | null;
    matter_reference_format: string | null;
    reminder_recipients: string[];
    auto_hold_on_eviction: boolean;
}

const props = defineProps<{
    settings: Settings;
    defaults: { stale_after_days: number; reminder_cooldown_days: number };
}>();

const { t } = useI18n();

const recipientsText = ref(props.settings.reminder_recipients.join('\n'));

const form = useForm({
    stale_after_days: props.settings.stale_after_days,
    reminder_cooldown_days: props.settings.reminder_cooldown_days,
    matter_reference_format: props.settings.matter_reference_format ?? '',
    reminder_recipients: props.settings.reminder_recipients,
    auto_hold_on_eviction: props.settings.auto_hold_on_eviction,
});

function submit(): void {
    form.reminder_recipients = recipientsText.value
        .split(/[\n,]/)
        .map((e) => e.trim())
        .filter(Boolean);
    form.put(route('legal-holds.settings.update'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="t('legal_holds.settings.title')" />

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <header class="flex items-center gap-3">
                <Cog6ToothIcon class="h-6 w-6 text-gray-500" />
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('legal_holds.settings.title') }}</h1>
            </header>

            <form class="space-y-5 rounded-lg bg-white p-5 shadow" data-testid="hold-settings" @submit.prevent="submit">
                <div>
                    <label for="s-stale" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.settings.stale_label') }}</label>
                    <input
                        id="s-stale"
                        v-model.number="form.stale_after_days"
                        type="number"
                        min="30"
                        max="3650"
                        :placeholder="String(defaults.stale_after_days)"
                        class="mt-1 block w-40 rounded-md border-gray-300 text-sm shadow-sm"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('legal_holds.settings.stale_help', { default: defaults.stale_after_days }) }}</p>
                    <p v-if="form.errors.stale_after_days" class="mt-1 text-xs text-rose-600">{{ form.errors.stale_after_days }}</p>
                </div>

                <div>
                    <label for="s-cooldown" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.settings.cooldown_label') }}</label>
                    <input
                        id="s-cooldown"
                        v-model.number="form.reminder_cooldown_days"
                        type="number"
                        min="1"
                        max="365"
                        :placeholder="String(defaults.reminder_cooldown_days)"
                        class="mt-1 block w-40 rounded-md border-gray-300 text-sm shadow-sm"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('legal_holds.settings.cooldown_help', { default: defaults.reminder_cooldown_days }) }}</p>
                    <p v-if="form.errors.reminder_cooldown_days" class="mt-1 text-xs text-rose-600">{{ form.errors.reminder_cooldown_days }}</p>
                </div>

                <div>
                    <label for="s-format" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.settings.format_label') }}</label>
                    <input
                        id="s-format"
                        v-model="form.matter_reference_format"
                        type="text"
                        maxlength="100"
                        placeholder="CV/{year}/{seq}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('legal_holds.settings.format_help') }}</p>
                </div>

                <div>
                    <label for="s-recipients" class="block text-sm font-medium text-gray-700">{{ t('legal_holds.settings.recipients_label') }}</label>
                    <textarea
                        id="s-recipients"
                        v-model="recipientsText"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        :placeholder="t('legal_holds.settings.recipients_placeholder')"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ t('legal_holds.settings.recipients_help') }}</p>
                    <p v-if="form.errors['reminder_recipients.0'] || form.errors.reminder_recipients" class="mt-1 text-xs text-rose-600">
                        {{ form.errors['reminder_recipients.0'] || form.errors.reminder_recipients }}
                    </p>
                </div>

                <label class="flex items-start gap-2">
                    <input v-model="form.auto_hold_on_eviction" type="checkbox" class="mt-0.5 rounded border-gray-300" data-testid="auto-hold-toggle" />
                    <span class="text-sm">
                        <span class="font-medium text-gray-700">{{ t('legal_holds.settings.auto_hold_label') }}</span>
                        <span class="block text-xs text-gray-500">{{ t('legal_holds.settings.auto_hold_help') }}</span>
                    </span>
                </label>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <Link :href="route('legal-holds.index')" class="text-sm text-gray-600 hover:underline">
                        {{ t('legal_holds.matters.back') }}
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {{ t('legal_holds.settings.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
