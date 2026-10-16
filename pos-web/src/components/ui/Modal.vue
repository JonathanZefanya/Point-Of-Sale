<script setup>
import { nextTick, ref, useId, watch } from 'vue'

/**
 * Memakai <dialog> native: fokus terkunci di dalam modal dan Esc menutup tanpa kode tambahan.
 * Modal konfirmasi hanya untuk aksi yang tidak bisa dibatalkan (CLAUDE.md §7.1 no. 8).
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  title: { type: String, required: true },
  size: { type: String, default: 'md' }, // sm | md | lg
  dismissible: { type: Boolean, default: true }, // false saat proses berjalan (cegah tutup)
})

const emit = defineEmits(['close'])

const dialog = ref(null)
const titleId = useId()
const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-3xl' }

watch(
  open,
  async (isOpen) => {
    await nextTick()
    if (!dialog.value) return
    if (isOpen && !dialog.value.open) dialog.value.showModal()
    if (!isOpen && dialog.value.open) dialog.value.close()
  },
  { immediate: true },
)

function requestClose() {
  if (!props.dismissible) return
  open.value = false
  emit('close')
}

function onCancel(event) {
  // Esc: tutup lewat state agar v-model tetap sinkron
  event.preventDefault()
  requestClose()
}

function onBackdropClick(event) {
  if (event.target === dialog.value) requestClose()
}
</script>

<template>
  <dialog
    ref="dialog"
    :class="[
      'm-auto w-[calc(100%-2rem)] rounded-lg border border-border bg-surface p-0 text-text shadow-float',
      'backdrop:bg-text/40',
      widths[size] ?? widths.md,
    ]"
    :aria-labelledby="titleId"
    @cancel="onCancel"
    @click="onBackdropClick"
  >
    <div v-if="open" class="flex max-h-[85vh] flex-col">
      <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-3.5">
        <h2 :id="titleId" class="text-base font-semibold">{{ title }}</h2>
        <button
          v-if="dismissible"
          type="button"
          class="-mr-2 inline-flex size-9 items-center justify-center rounded text-muted hover:bg-surface-muted hover:text-text"
          title="Tutup (Esc)"
          aria-label="Tutup"
          @click="requestClose"
        >
          <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
          </svg>
        </button>
      </header>

      <div class="overflow-y-auto px-5 py-4">
        <slot />
      </div>

      <footer v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-3">
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>
