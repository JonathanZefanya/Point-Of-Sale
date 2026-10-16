<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'secondary' }, // primary | secondary | danger | ghost | danger-ghost
  size: { type: String, default: 'md' }, // md | lg (layar kasir, min 44px)
  type: { type: String, default: 'button' },
  loading: { type: Boolean, default: false },
  loadingText: { type: String, default: '' }, // mis. "Menyimpan..."
  disabled: { type: Boolean, default: false },
  shortcut: { type: String, default: '' }, // ditampilkan di tombol, mis. "F9"
  block: { type: Boolean, default: false },
})

const variants = {
  primary: 'bg-primary text-white border-primary hover:bg-primary-hover hover:border-primary-hover',
  secondary: 'bg-surface text-text border-border-strong hover:bg-surface-muted',
  danger: 'bg-danger text-white border-danger hover:opacity-90',
  ghost: 'bg-transparent text-text border-transparent hover:bg-surface-muted',
  // aksi berbahaya sekunder (mis. Hapus di form) — tidak bersaing dengan tombol utama
  'danger-ghost': 'bg-transparent text-danger border-transparent hover:bg-danger-soft',
}

const sizes = {
  md: 'h-9 px-3.5 text-sm gap-2',
  lg: 'min-h-touch px-5 text-base gap-2.5',
}

const classes = computed(() => [
  'inline-flex items-center justify-center rounded border font-medium whitespace-nowrap select-none',
  'transition-colors disabled:cursor-not-allowed disabled:opacity-50',
  variants[props.variant] ?? variants.secondary,
  sizes[props.size] ?? sizes.md,
  props.block && 'w-full',
])
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
    :aria-keyshortcuts="shortcut || undefined"
  >
    <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span v-if="loading && loadingText">{{ loadingText }}</span>
    <template v-else>
      <slot v-if="!loading" name="icon" />
      <span><slot /></span>
    </template>
    <kbd
      v-if="shortcut"
      class="rounded-sm border border-current/30 px-1.5 py-px font-mono text-xs font-normal opacity-80"
    >{{ shortcut }}</kbd>
  </button>
</template>
