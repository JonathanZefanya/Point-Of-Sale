<script setup>
import { computed } from 'vue'
import { formatNumber, formatRupiah } from '@/lib/format'

/**
 * Angka utama + pembanding yang tertulis jelas ("naik 12% vs kemarin (Rp45.000)").
 * Tidak memakai panah/warna tanpa konteks (CLAUDE.md §7.2).
 */
const props = defineProps({
  label: { type: String, required: true },
  value: { type: Number, required: true },
  previous: { type: Number, required: true },
  money: { type: Boolean, default: true },
  compareLabel: { type: String, default: 'kemarin' },
})

const format = (n) => (props.money ? formatRupiah(n) : formatNumber(n))

const comparison = computed(() => {
  const { value, previous } = props
  if (previous === 0 && value === 0) return `Sama dengan ${props.compareLabel} (${format(0)})`
  if (previous === 0) return `${props.compareLabel[0].toUpperCase()}${props.compareLabel.slice(1)} ${format(0)}`
  const pct = Math.round(((value - previous) / previous) * 100)
  if (pct === 0) return `Sama dengan ${props.compareLabel} (${format(previous)})`
  return `${pct > 0 ? 'Naik' : 'Turun'} ${Math.abs(pct)}% vs ${props.compareLabel} (${format(previous)})`
})
</script>

<template>
  <div class="rounded border border-border bg-surface px-4 py-3">
    <p class="text-muted">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ format(value) }}</p>
    <p class="mt-1 text-sm text-muted">{{ comparison }}</p>
  </div>
</template>
