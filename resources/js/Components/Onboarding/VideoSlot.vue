<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  url: string
}
const props = defineProps<Props>()
const { t } = useI18n()

// Convert plain YouTube URL to nocookie embed; pass through other URLs.
const embedUrl = computed(() => {
  const m = props.url.match(/(?:youtu\.be\/|v=)([\w-]{11})/)
  return m ? `https://www.youtube-nocookie.com/embed/${m[1]}` : props.url
})
</script>

<template>
  <div class="rounded border overflow-hidden max-w-md mx-auto" :aria-label="t('onboarding.video.label')">
    <div class="aspect-video">
      <iframe
        :src="embedUrl"
        class="w-full h-full"
        loading="lazy"
        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        :title="t('onboarding.video.label')"
      />
    </div>
  </div>
</template>
