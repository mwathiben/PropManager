<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHelpDrawer } from '@/composables/useHelpDrawer'

interface Article {
  id: number
  title: string
  slug: string
  help_key: string | null
  category: string
  excerpt: string
}

const { t } = useI18n()
const { isOpen, currentHelpKey, close, open } = useHelpDrawer()

const articles = ref<Article[]>([])
const searchQuery = ref('')
const loading = ref(false)
const closeButton = ref<HTMLButtonElement | null>(null)

let debounceTimer: number | null = null

const fetchContextual = async (key: string | null) => {
  loading.value = true
  try {
    const url = key
      ? `/api/help/contextual?key=${encodeURIComponent(key)}`
      : '/api/help/search?q=getting'
    const res = await fetch(url, { credentials: 'same-origin' })
    if (res.ok) {
      const data = (await res.json()) as { articles: Article[] }
      articles.value = data.articles
    }
  } finally {
    loading.value = false
  }
}

const debouncedSearch = () => {
  if (debounceTimer !== null) window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(async () => {
    const q = searchQuery.value.trim()
    if (q.length < 2) {
      await fetchContextual(currentHelpKey.value)
      return
    }
    loading.value = true
    try {
      const res = await fetch(`/api/help/search?q=${encodeURIComponent(q)}`, {
        credentials: 'same-origin',
      })
      if (res.ok) {
        const data = (await res.json()) as { articles: Article[] }
        articles.value = data.articles
      }
    } finally {
      loading.value = false
    }
  }, 250)
}

watch(isOpen, async (open) => {
  if (open) {
    await fetchContextual(currentHelpKey.value)
    await nextTick()
    closeButton.value?.focus()
  } else {
    searchQuery.value = ''
  }
})

const onKeydown = (e: KeyboardEvent) => {
  // ESC to close
  if (e.key === 'Escape' && isOpen.value) {
    e.preventDefault()
    close()
    return
  }
  // '?' opens drawer from anywhere except text inputs
  if (e.key === '?' && !isOpen.value) {
    const target = e.target as HTMLElement | null
    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
      return
    }
    e.preventDefault()
    const pageKey = (window as unknown as { __helpKey?: string }).__helpKey ?? null
    open(pageKey)
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex justify-end"
      role="dialog"
      :aria-label="t('onboarding.help.drawer_title')"
    >
      <div class="fixed inset-0 bg-black/40" role="button" tabindex="0" @click="close" @keydown.enter="close" @keydown.space.prevent="close" />
      <aside class="relative w-full max-w-md bg-white shadow-xl h-full flex flex-col">
        <header class="border-b px-4 py-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold">{{ t('onboarding.help.drawer_title') }}</h2>
          <button
            ref="closeButton"
            @click="close"
            class="text-gray-500 hover:text-gray-700"
            :aria-label="t('common.close', 'Close')"
          >×</button>
        </header>
        <div class="p-4 border-b">
          <input
            v-model="searchQuery"
            type="search"
            class="w-full border rounded px-3 py-2 text-sm"
            :placeholder="t('onboarding.help.search_placeholder')"
            :aria-label="t('onboarding.help.search_placeholder')"
            @input="debouncedSearch"
          />
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <p v-if="loading" class="text-sm text-gray-500">{{ t('common.loading', 'Loading…') }}</p>
          <p v-else-if="articles.length === 0" class="text-sm text-gray-500">
            {{ t('onboarding.help.no_results') }}
          </p>
          <a
            v-for="a in articles"
            :key="a.id"
            :href="`/help/${a.slug}`"
            class="block rounded border p-3 hover:bg-gray-50"
          >
            <p class="text-sm font-medium text-gray-900">{{ a.title }}</p>
            <p class="text-xs text-gray-600 mt-1">{{ a.excerpt }}</p>
          </a>
        </div>
      </aside>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
