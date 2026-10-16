<script setup>
import { computed } from 'vue'
import Table from '@/components/ui/Table.vue'
import { formatDate, formatDateTime } from '@/lib/format'
import { formatCell } from '@/lib/reports'

/** Tabel laporan generik dari definisi kolom (lib/reports.js). Angka rata kanan. */
const props = defineProps({
  definition: { type: Object, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const numeric = ['money', 'number', 'percent']
const columns = computed(() => props.definition.columns.map((c) => ({ key: c.key, label: c.label, align: numeric.includes(c.format) ? 'right' : 'left' })))
const keyed = computed(() => props.rows.map((r) => ({ ...r, __key: typeof props.definition.rowKey === 'function' ? props.definition.rowKey(r) : r[props.definition.rowKey] })))

function display(row, col) {
  const value = row[col.key]
  if (col.format === 'date') return value ? formatDate(value) : '–'
  if (col.format === 'datetime') return value ? formatDateTime(value) : '–'
  return formatCell(value, col.format)
}
</script>

<template>
  <Table :columns="columns" :rows="keyed" row-key="__key" :loading="loading">
    <template v-for="col in definition.columns" :key="col.key" #[`cell-${col.key}`]="{ row }">
      <span :class="[col.mono && 'font-mono', col.tone?.(row)]">{{ display(row, col) }}</span>
      <span v-if="col.sub && row[col.sub]" class="block text-muted" :class="col.sub === 'sku' && 'font-mono text-sm'">{{ col.subPrefix ?? '' }}{{ row[col.sub] }}</span>
    </template>
    <template #empty><slot name="empty" /></template>
  </Table>
</template>
