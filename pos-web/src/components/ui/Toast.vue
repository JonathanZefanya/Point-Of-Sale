<script setup>
import { useToastStore } from '@/stores/toast'

// Dipasang sekali di App.vue. Toast dengan aksi dipakai untuk undo: "Item dihapus · Batalkan".
const toast = useToastStore()

const tones = {
  info: 'border-l-text',
  success: 'border-l-success',
  error: 'border-l-danger',
}
</script>

<template>
  <div class="pointer-events-none fixed bottom-4 left-1/2 z-50 flex w-full max-w-md -translate-x-1/2 flex-col gap-2 px-4">
    <div
      v-for="item in toast.items"
      :key="item.id"
      :role="item.type === 'error' ? 'alert' : 'status'"
      :class="[
        'pointer-events-auto flex items-center gap-3 rounded border border-l-4 border-border bg-surface py-2 pl-4 pr-2 shadow-float',
        tones[item.type] ?? tones.info,
      ]"
    >
      <p class="flex-1 py-1.5">{{ item.message }}</p>
      <button
        v-if="item.action"
        type="button"
        class="min-h-touch rounded px-3 font-semibold text-primary hover:bg-primary-soft"
        @click="toast.runAction(item)"
      >
        {{ item.action.label }}
      </button>
      <button
        type="button"
        class="inline-flex size-9 shrink-0 items-center justify-center rounded text-muted hover:bg-surface-muted hover:text-text"
        title="Tutup"
        aria-label="Tutup notifikasi"
        @click="toast.dismiss(item.id)"
      >
        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
          <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
        </svg>
      </button>
    </div>
  </div>
</template>
