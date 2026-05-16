<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'

interface ResumeStatus {
  current_step: number
  total_steps: number
  current_step_name: string
  completion_pct: number
  last_touched_at: string | null
  started_at: string | null
  resume_url: string
}

const { t } = useI18n()
const status = ref<ResumeStatus | null>(null)
const dismissed = ref(false)

const STORAGE_KEY = 'pm-onboarding-resume-dismissed'

onMounted(async () => {
  if (sessionStorage.getItem(STORAGE_KEY) === '1') {
    dismissed.value = true
    return
  }
  try {
    const res = await fetch('/api/onboarding/status', { credentials: 'same-origin' })
    if (res.ok) {
      status.value = (await res.json()) as ResumeStatus | null
    }
  } catch {
    status.value = null
  }
})

const dismiss = () => {
  sessionStorage.setItem(STORAGE_KEY, '1')
  dismissed.value = true
}
</script>

<template>
  <div
    v-if="status && !dismissed"
    class="rounded-md border border-indigo-200 bg-indigo-50 p-4 flex items-center justify-between"
    role="status"
  >
    <div>
      <p class="text-sm font-medium text-indigo-900">
        {{ t('onboarding.resume_banner.title', { current: status.current_step, total: status.total_steps }) }}
      </p>
      <p class="text-sm text-indigo-700">
        {{ t('onboarding.resume_banner.subtitle', { pct: status.completion_pct }) }}
      </p>
    </div>
    <div class="flex gap-2">
      <a
        :href="status.resume_url"
        class="px-3 py-1.5 rounded bg-indigo-600 text-white text-sm"
      >{{ t('onboarding.resume_banner.continue') }}</a>
      <button
        @click="dismiss"
        class="px-3 py-1.5 rounded border border-indigo-300 text-indigo-700 text-sm"
      >{{ t('onboarding.resume_banner.dismiss') }}</button>
    </div>
  </div>
</template>
