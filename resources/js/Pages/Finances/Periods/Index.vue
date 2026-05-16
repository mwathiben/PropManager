<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps<{
  periods: Array<{
    id: number
    period_start: string
    period_end: string
    status: 'open' | 'closed'
    closed_at: string | null
    close_notes: string | null
  }>
}>()

const { t } = useI18n()
const newMonth = ref<string>(new Date().toISOString().slice(0, 7))
const closeNotes = ref<string>('')

const submitClose = () => {
  router.post(route('finances.periods.close'), {
    month: newMonth.value,
    close_notes: closeNotes.value,
  })
}

const reopen = (periodId: number) => {
  if (!confirm(t('accounting.period.reopen_confirm'))) return
  router.post(route('finances.periods.reopen', { period: periodId }))
}
</script>

<template>
  <Head :title="t('accounting.period.title')" />
  <AuthenticatedLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-semibold">{{ t('accounting.period.title') }}</h1>

      <section class="rounded border bg-white p-4 space-y-3">
        <h2 class="font-medium">{{ t('accounting.period.close_heading') }}</h2>
        <div class="flex gap-3 items-end">
          <label class="text-sm">
            <span class="block">{{ t('accounting.period.month') }}</span>
            <input type="month" v-model="newMonth" class="mt-1 border rounded px-2 py-1" />
          </label>
          <label class="text-sm flex-1">
            <span class="block">{{ t('accounting.period.notes') }}</span>
            <input type="text" v-model="closeNotes" class="mt-1 w-full border rounded px-2 py-1" />
          </label>
          <button @click="submitClose" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm">
            {{ t('accounting.period.close_button') }}
          </button>
        </div>
      </section>

      <section class="rounded border bg-white">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-left">
            <tr>
              <th class="p-2">{{ t('accounting.period.period') }}</th>
              <th class="p-2">{{ t('accounting.period.status') }}</th>
              <th class="p-2">{{ t('accounting.period.closed_at') }}</th>
              <th class="p-2">{{ t('accounting.period.notes') }}</th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in periods" :key="p.id" class="border-t">
              <td class="p-2">{{ p.period_start }} → {{ p.period_end }}</td>
              <td class="p-2">
                <span
                  :class="p.status === 'closed' ? 'text-rose-700' : 'text-emerald-700'"
                >{{ p.status }}</span>
              </td>
              <td class="p-2">{{ p.closed_at ?? '—' }}</td>
              <td class="p-2">{{ p.close_notes ?? '—' }}</td>
              <td class="p-2 text-right">
                <button
                  v-if="p.status === 'closed'"
                  @click="reopen(p.id)"
                  class="text-xs text-rose-700 underline"
                >
                  {{ t('accounting.period.reopen') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
