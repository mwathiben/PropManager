<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'

interface MilestoneStatus {
  signed_up: boolean
  first_property: boolean
  first_unit: boolean
  first_tenant: boolean
  first_invoice: boolean
  first_payment: boolean
}

const { t } = useI18n()
const status = ref<MilestoneStatus | null>(null)
const dismissed = ref(false)

const STEPS = [
  { key: 'first_property', href: '/properties/create' },
  { key: 'first_unit', href: '/properties' },
  { key: 'first_tenant', href: '/tenants/create' },
  { key: 'first_invoice', href: '/finances/invoices' },
  { key: 'first_payment', href: '/finances/payments' },
] as const

const dashOk = computed(() => {
  if (!status.value) return false
  return STEPS.every((s) => status.value![s.key])
})

onMounted(async () => {
  try {
    const res = await fetch('/api/onboarding/milestones', { credentials: 'same-origin' })
    if (res.ok) {
      status.value = (await res.json()) as MilestoneStatus
    }
  } catch {
    status.value = null
  }
})

const dismiss = async () => {
  await fetch('/api/onboarding/checklist/dismiss', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement | null)?.content ?? '',
    },
  })
  dismissed.value = true
}
</script>

<template>
  <div
    v-if="status && !dismissed && !dashOk"
    class="rounded border bg-gray-50 p-4 text-start max-w-md mx-auto"
  >
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-sm font-semibold text-gray-900">{{ t('onboarding.checklist.heading') }}</h4>
      <button
        @click="dismiss"
        class="text-xs text-gray-500 hover:text-gray-700"
      >{{ t('onboarding.checklist.dismiss') }}</button>
    </div>
    <ul class="space-y-2 text-sm">
      <li
        v-for="step in STEPS"
        :key="step.key"
        class="flex items-center gap-2"
      >
        <span
          :class="status[step.key] ? 'text-emerald-600' : 'text-gray-400'"
          aria-hidden="true"
        >{{ status[step.key] ? '✓' : '○' }}</span>
        <a
          v-if="!status[step.key]"
          :href="step.href"
          class="text-indigo-600 hover:underline"
        >{{ t('onboarding.checklist.steps.' + step.key) }}</a>
        <span v-else class="text-gray-500 line-through">
          {{ t('onboarding.checklist.steps.' + step.key) }}
        </span>
      </li>
    </ul>
  </div>
</template>
