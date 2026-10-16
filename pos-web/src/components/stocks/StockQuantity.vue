<script setup>
import { formatNumber } from '@/lib/format'

// Angka stok + satuan dengan warna status: habis = merah, menipis = kuning.
defineProps({
  quantity: { type: Number, required: true },
  unit: { type: String, default: '' },
  status: { type: String, default: 'ok' }, // ok | low | out
})

const tones = { ok: 'text-text', low: 'font-medium text-warning', out: 'font-medium text-danger' }
const labels = { low: 'Menipis', out: 'Habis' }
</script>

<template>
  <span :class="['tabular-nums', tones[status]]">
    {{ formatNumber(quantity) }} <span class="font-normal text-muted">{{ unit }}</span>
    <span v-if="labels[status]" class="ml-1 text-xs">· {{ labels[status] }}</span>
  </span>
</template>
