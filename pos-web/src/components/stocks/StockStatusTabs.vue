<script setup>
import { formatNumber } from '@/lib/format'

// Tab Semua / Menipis / Habis dengan jumlahnya, agar stok bermasalah terlihat tanpa mencari.
const model = defineModel({ type: String, default: '' })

defineProps({
  summary: { type: Object, default: null }, // { low, out }
})
</script>

<template>
  <div class="inline-flex rounded border border-border-strong bg-surface p-0.5" role="tablist" aria-label="Filter status stok">
    <button
      v-for="tab in [
        { value: '', label: 'Semua' },
        { value: 'low', label: 'Menipis', count: summary?.low, tone: 'text-warning' },
        { value: 'out', label: 'Habis', count: summary?.out, tone: 'text-danger' },
      ]"
      :key="tab.value"
      type="button"
      role="tab"
      :aria-selected="model === tab.value"
      :class="['rounded-sm px-3 py-1.5', model === tab.value ? 'bg-primary-soft font-medium text-primary' : 'text-text hover:bg-surface-muted']"
      @click="model = tab.value"
    >
      {{ tab.label }}
      <span v-if="tab.count" :class="['ml-1 tabular-nums', model === tab.value ? '' : tab.tone]">{{ formatNumber(tab.count) }}</span>
    </button>
  </div>
</template>
