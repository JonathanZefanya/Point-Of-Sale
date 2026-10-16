<script setup>
import Table from '@/components/ui/Table.vue'
import { formatDateTime, formatNumber } from '@/lib/format'

defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const columns = [
  { key: 'created_at', label: 'Waktu', width: '170px' },
  { key: 'type_label', label: 'Jenis', width: '170px' },
  { key: 'quantity_change', label: 'Masuk / Keluar', align: 'right', width: '130px' },
  { key: 'quantity_after', label: 'Saldo', align: 'right', width: '100px' },
  { key: 'user', label: 'Oleh', width: '150px' },
  { key: 'note', label: 'Keterangan' },
]

function signed(value) {
  return value > 0 ? `+${formatNumber(value)}` : `−${formatNumber(Math.abs(value))}`
}
</script>

<template>
  <Table :columns="columns" :rows="rows" :loading="loading">
    <template #cell-created_at="{ value }">{{ formatDateTime(value) }}</template>
    <template #cell-quantity_change="{ value }">
      <span :class="value > 0 ? 'text-success' : 'text-danger'">{{ signed(value) }}</span>
    </template>
    <template #cell-quantity_after="{ value }">{{ formatNumber(value) }}</template>
    <template #cell-user="{ row }">{{ row.user?.name ?? 'Sistem' }}</template>
    <template #cell-note="{ row }">
      <span v-if="row.note">{{ row.note }}</span>
      <span v-else class="text-muted">-</span>
    </template>
    <template #empty>
      <slot name="empty" />
    </template>
  </Table>
</template>
