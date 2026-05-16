<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps<{
  diagnostics: {
    invoice_types_unmapped: number
    expense_categories_unmapped: number
    missing_default_income: boolean
    missing_default_expense: boolean
  }
  accountCount: number
  formats: string[]
}>()

const { t } = useI18n()

const from = ref<string>(new Date(Date.now() - 30 * 86_400_000).toISOString().slice(0, 10))
const to = ref<string>(new Date().toISOString().slice(0, 10))
const format = ref<string>('iif')

const exportUrl = () => {
  const params = new URLSearchParams({ from: from.value, to: to.value, format: format.value })
  return `/finances/accounting/export?${params.toString()}`
}
</script>

<template>
  <Head :title="t('accounting.export.title')" />
  <AppLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-semibold">{{ t('accounting.export.title') }}</h1>

      <section class="rounded border p-4 bg-white">
        <h2 class="font-medium mb-2">{{ t('accounting.export.diagnostics_heading') }}</h2>
        <ul class="text-sm space-y-1">
          <li>{{ t('accounting.export.accounts_configured', { count: accountCount }) }}</li>
          <li v-if="diagnostics.invoice_types_unmapped > 0" class="text-amber-700">
            {{ t('accounting.export.invoice_types_unmapped', { count: diagnostics.invoice_types_unmapped }) }}
          </li>
          <li v-if="diagnostics.expense_categories_unmapped > 0" class="text-amber-700">
            {{ t('accounting.export.expense_categories_unmapped', { count: diagnostics.expense_categories_unmapped }) }}
          </li>
          <li v-if="diagnostics.missing_default_income" class="text-amber-700">
            {{ t('accounting.export.missing_default_income') }}
          </li>
          <li v-if="diagnostics.missing_default_expense" class="text-amber-700">
            {{ t('accounting.export.missing_default_expense') }}
          </li>
        </ul>
      </section>

      <section class="rounded border p-4 bg-white space-y-3">
        <h2 class="font-medium">{{ t('accounting.export.run_heading') }}</h2>
        <div class="grid grid-cols-3 gap-3">
          <label class="text-sm">
            <span class="block">{{ t('accounting.export.from') }}</span>
            <input type="date" v-model="from" class="mt-1 w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">
            <span class="block">{{ t('accounting.export.to') }}</span>
            <input type="date" v-model="to" class="mt-1 w-full border rounded px-2 py-1" />
          </label>
          <label class="text-sm">
            <span class="block">{{ t('accounting.export.format') }}</span>
            <select v-model="format" class="mt-1 w-full border rounded px-2 py-1">
              <option v-for="f in formats" :key="f" :value="f">{{ f.toUpperCase() }}</option>
            </select>
          </label>
        </div>
        <a
          :href="exportUrl()"
          class="inline-block px-4 py-2 rounded bg-indigo-600 text-white text-sm"
        >
          {{ t('accounting.export.download') }}
        </a>
      </section>
    </div>
  </AppLayout>
</template>
