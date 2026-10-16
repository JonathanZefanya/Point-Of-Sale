<script setup>
// Label status kecil. Warna status hanya untuk status (CLAUDE.md §7.3), bukan dekorasi.
defineProps({
  tone: { type: String, default: 'neutral' }, // neutral | success | warning | danger
})

const tones = {
  neutral: 'border-border bg-surface-muted text-muted',
  success: 'border-success/20 bg-success-soft text-success',
  warning: 'border-warning/20 bg-warning-soft text-warning',
  danger: 'border-danger/20 bg-danger-soft text-danger',
}
</script>

<template>
  <span :class="['inline-flex items-center rounded-sm border px-1.5 py-px text-xs font-medium', tones[tone] ?? tones.neutral]">
    <slot />
  </span>
</template>
