<script setup>
import { formatNumber, formatRupiah } from '@/lib/format'

// Ringkasan angka di atas tabel (F11.3). items: [{ label, value, money?, note? }]
defineProps({
  items: { type: Array, required: true },
})
</script>

<template>
  <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div v-for="item in items" :key="item.label" class="rounded border border-border bg-surface px-4 py-3">
      <dt class="text-muted">{{ item.label }}</dt>
      <dd class="mt-1 text-xl font-semibold tabular-nums">{{ item.money === false ? formatNumber(item.value) : formatRupiah(item.value) }}</dd>
      <dd v-if="item.note" class="mt-0.5 text-sm text-muted">{{ item.note }}</dd>
    </div>
  </dl>
</template>
