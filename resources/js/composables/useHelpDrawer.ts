import { ref, computed, readonly } from 'vue'

const isOpen = ref(false)
const currentHelpKey = ref<string | null>(null)

export function useHelpDrawer() {
  const open = (helpKey?: string | null) => {
    currentHelpKey.value = helpKey ?? null
    isOpen.value = true
  }
  const close = () => {
    isOpen.value = false
  }
  const toggle = (helpKey?: string | null) => {
    if (isOpen.value) close()
    else open(helpKey)
  }

  return {
    isOpen: readonly(isOpen),
    currentHelpKey: readonly(currentHelpKey),
    open,
    close,
    toggle,
  }
}
